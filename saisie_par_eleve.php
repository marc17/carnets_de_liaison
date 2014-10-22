<?php
$niveau_arbo = "2";
// Initialisations files (Attention au chemin des fichiers en fonction de l'arborescence)
//include("../../lib/initialisationsPropel.inc.php");
include("../../lib/initialisations.inc.php");
include("../plugins.class.php");

// Resume session
$resultat_session = $session_gepi->security_check();
if ($resultat_session == 'c') {
    header("Location: ../../utilisateurs/mon_compte.php?change_mdp=yes");
    die();
} else if ($resultat_session == '0') {
    header("Location: ../../logout.php?auto=1");
    die();
}

// Il faut adapter cette ligne au statut des utilisateurs qui auront accès à cette page, par défaut des utilisateurs professionnels
//$utilisateur = UtilisateurProfessionnelPeer::retrieveByPk($_SESSION["login"]);
//$user_auth = new gepiPlugIn("change_compte");
//$user_auth->verifDroits();


//******************************************
// Copyleft Marc Leygnac (pour ce qui suit)
//******************************************

// l'utilisateur est-il autorisé à exécuter ce script ?
include("verification_autorisations.inc.php");

function slashe_n2nl($ch)
	{
	$remplace=array("\\n"=>"\n","\\r"=>"");
	return strtr($ch,$remplace);
	}

function supp_element_liste($element,$liste)
	{
	$t_liste=explode(",",$liste);
	if (in_array($element,$t_liste)) unset($t_liste[array_search($element,$t_liste)]);
	return implode(",",$t_liste);
	}

function t_liste_eleves($liste)
	{
	global $mysqli;
	$liste_eleves=array();
	$r_sql="SELECT DISTINCT `ele_id`,`nom`,`prenom`,`classe`,`nom_complet` FROM `eleves`,`j_eleves_classes`,`classes` WHERE (FIND_IN_SET(ele_id,'".$liste."') AND `eleves`.`login`=`j_eleves_classes`.`login` AND `j_eleves_classes`.`id_classe`=`classes`.`id`) ORDER BY `j_eleves_classes`.`periode` DESC,`classe`,`nom`,`prenom`";
	$R_eleves=mysqli_query($mysqli, $r_sql);
	while($un_eleve=mysqli_fetch_assoc($R_eleves))
		// si un élève a changé de classe on doit éliminer le doublon correspondant
		if (!array_key_exists($un_eleve['ele_id'],$liste_eleves)) $liste_eleves[$un_eleve['ele_id']]=array('ele_id' => $un_eleve['ele_id'], 'nom' => $un_eleve['prenom']." ".$un_eleve['nom']." (".$un_eleve['nom_complet'].")");
	return $liste_eleves;
	}

function prefixe()
	{
	return rand(10000,99999);
	}

function formate_nom_fichier(&$nom)
	{
	$a_remplacer=array("'"," ");
	$nom=str_replace($a_remplacer, "_",$nom);
	}

function derniere_periode_active($id_classe)
	// renvoie l'indice de la première période non vérouillée si elle existe, de la dernière période sinon
	{
	global $mysqli;
	$R_periode=mysqli_query($mysqli,"SELECT `num_periode`,`verouiller` FROM `periodes` WHERE `id_classe`='".$id_classe."' ORDER BY `num_periode`");
	$retour=0; $p_courante=0;
	while ($periode=mysqli_fetch_assoc($R_periode))
		{
		$p_courante=$periode['num_periode'];
		if ($periode['verouiller']=='N' && $retour==0) $retour=$p_courante;
		}
	if ($retour==0) $retour=$p_courante;
	return $retour;
	}
	
// si l'appel se fait avec passage de paramètre alors test du token
if ((function_exists("check_token")) && ((count($_POST)<>0) || (count($_GET)<>0))) check_token();

// l'envoi de mail est-il activé ?
$carnets_de_liaison_mail=getSettingValue("carnets_de_liaison_mail");

// les rédacteurs peuvent-ils envoyer un mail de notification ?
$carnets_de_liaison_notification_mail_aux_responsables=getSettingValue("carnets_de_liaison_notification_mail_aux_responsables");

// les rédacteurs peuvent-ils joindre un fichier aux mots ?
$carnets_de_liaison_documents=getSettingValue("carnets_de_liaison_documents");

// les rédacteurs peuvent-ils envoyer un sms de notification ?
$carnets_de_liaison_notification_sms_aux_responsables=getSettingValue("carnets_de_liaison_notification_sms_aux_responsables");

// a priori pas d'erreur
$message_d_erreur="";

// a priori pas de document joint
$document=array('nom'=>'','type'=>'','prefixe'=>'');
$document_joint_nom="";
$document_joint_type="";
$document_joint_temp="";

// a priori ce n'est pas une modification d'un mot déjà saisi
$id_modification=isset($_POST['id_modification'])?$_POST['id_modification']:0;

// initialisations
$ids_destinataires_initial=isset($_POST['ids_destinataires_initial'])?$_POST['ids_destinataires_initial']:"-1";

if (isset($_POST['modifier']))
	{
	$r_sql="SELECT * FROM `carnets_de_liaison_mots` WHERE (`login_redacteur`='".$_SESSION['login']."' AND `id_mot`='".$_POST['modifier']."') LIMIT 1";
	$R_mots=mysqli_query($mysqli, $r_sql);
	if (!$R_mots) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		else
		{
		$le_mot=mysqli_fetch_assoc($R_mots);
		$_POST['mail']=$le_mot['mail'];
		$_POST['reponse_destinataire']=$le_mot['reponse_destinataire'];
		$_POST['intitule']=$le_mot['intitule'];
		$_POST['texte']=$le_mot['texte'];
		$_POST['ele_ids']=$le_mot['ids_destinataires'];
		$ids_destinataires_initial=$le_mot['ids_destinataires'];
		$id_modification=$_POST['modifier'];
		}
	}


// dossier des documents
$dossier_documents="documents/";
if (isset($GLOBALS['multisite']) AND $GLOBALS['multisite']=='y')
	{
	if (!isset($_COOKIE['RNE'])) $message_d_erreur.="Multisite : impossible de récupérer le cookie RNE.<br />";
	else
		{
		if (!file_exists($dossier_documents.$_COOKIE['RNE']))
			// si le dossier n'exsite pas on le crée
			if (!mkdir($dossier_documents.$_COOKIE['RNE'],0700)) $message_d_erreur.="Multisite : impossible de créer le dossier ".$dossier_documents.$_COOKIE['RNE'].".<br />";
			else
				{
				copy($dossier_documents."index.html",$dossier_documents.$_COOKIE['RNE']."/index.html");
				copy($dossier_documents."index.php",$dossier_documents.$_COOKIE['RNE']."/index.php");
				}
		$dossier_documents.=$_COOKIE['RNE']."/";
		}
	}

	
// initialisation de variables
$id_classe=isset($_POST['id_classe'])?$_POST['id_classe']:0;
// si l'on vient de consultation.php (isset($_POST['ele_id'])) un élève doit être automatiquement sélectionné
$ele_ids=isset($_POST['ele_ids'])?$_POST['ele_ids']:"-1";
$etape=isset($_POST['etape'])?$_POST['etape']:1;
$mail=isset($_POST['mail'])?stripslashes($_POST['mail']):"";
$reponse_destinataire=isset($_POST['reponse_destinataire'])?stripslashes($_POST['reponse_destinataire']):"non";
$intitule=isset($_POST['intitule'])?stripslashes($_POST['intitule']):"";
$texte=isset($_POST['texte'])?stripslashes(slashe_n2nl($_POST['texte'])):"";

// on stoke les distinataires pour l'envoi de notification
$liste_destinataires=$ele_ids;

// on ajoute un élève à la liste des destinataires
if (isset($_POST['ele_id']) && ($_POST['ele_id']!="-1"))
	$ele_ids=$_POST['ele_id'].",".$ele_ids;

// on retire un élève de la liste des destinataires
if (isset($_POST['retirer_eleve']))
	{
	$t_ele_ids=explode(",",$ele_ids);
	unset($t_ele_ids[array_search($_POST['retirer_eleve'], $t_ele_ids)]);
	$ele_ids=implode(",",$t_ele_ids);
	}

// la saisie des noms d'élèves est terminée
if (isset($_POST['clore_ajout_eleve'])) 
	if ($ele_ids!="-1") $etape=2;
		else $message_d_erreur.="Vous devez sélectionner un ou plusieurs élèves.<br />";

// la saisie du mot est (peut-être) terminée
if (isset($_POST['saisie_ok']))
	{
	// a-t-on tous les champs renseignés ?
	if ($intitule=="") $message_d_erreur.="Vous devez saisir un intitulé.<br />";
	if ($texte=="") $message_d_erreur.="Vous devez saisir un texte.<br />";

	// traitement de l'upload si les autres champs sont renseignés
	if (isset($_FILES['document_joint']) && ($_FILES['document_joint']['name']!="") && ($message_d_erreur==""))
		{
		if ($_FILES['document_joint']['error']==0)
			if (is_uploaded_file($_FILES['document_joint']['tmp_name']))
				{
				if (move_uploaded_file($_FILES['document_joint']['tmp_name'], $dossier_documents.$document_joint_temp=basename($_FILES['document_joint']['tmp_name'])))
					{
					$document_joint_nom=$_FILES['document_joint']['name'];
					$document_joint_type=$_FILES['document_joint']['type'];
					$document_joint_temp=basename($_FILES['document_joint']['tmp_name']);
					}
				else $message_d_erreur.="Impossible d'enregistrer le document sur le seveur.<br />";
				}
			else $message_d_erreur.="Tentative de piratage de fichier uploadé.<br />";
		else
			{
			$message_d_erreur.="Impossible de joindre ce document : ";
			switch ($_FILES['document_joint']['error'])
				{
			   case 1: // UPLOAD_ERR_INI_SIZE
			   $message_d_erreur.="la taille du fichier dépasse la limite autorisée par le serveur.<br />";
			   break;
			   case 2: // UPLOAD_ERR_FORM_SIZE
			   $message_d_erreur.="la taille du fichier dépasse la limite autorisée dans le formulaire.<br />";
			   break;
			   case 3: // UPLOAD_ERR_PARTIAL
			   $message_d_erreur.="l'envoi du fichier a été interrompu pendant le transfert<br />";
			   break;
			   case 4: // UPLOAD_ERR_NO_FILE
			   $message_d_erreur.="pas de fichier transmis.<br />";
			   break;
			   case 6: // UPLOAD_ERR_NO_TMP_DIR
			   $message_d_erreur.="pas de dossier temporaire sur le serveur<br />";
			   break;
			   case 7: // UPLOAD_ERR_CANT_WRITE
			   $message_d_erreur.="impossible d'enregistrer le fichier sur le servaur.<br />";
			   break;
			   case 8: // UPLOAD_ERR_EXTENSION
			   $message_d_erreur.="une extension PHP a provoqué une erreur.<br />";
			   break;
				}
			}
		}
	}

	if (isset($_POST['saisie_ok']) && $message_d_erreur=="")
		{
		// ajout ou modification dans la table carnets_de_liaison_mots
		if ($id_modification!=0)
			{
			// suppression du document joint
			if (isset($_POST['supprimer_document']) && ($document_joint_nom==""))
				{
				$fichier_document=$_POST['supprimer_document']."_".$id_modification;
				if (is_file($dossier_documents.$fichier_document))
					if (!unlink($dossier_documents.$fichier_document)) 
						$message_d_erreur.="Impossible de supprimer l'ancien document joint.<br />";
				if ($message_d_erreur=="")
					{
					$r_sql="UPDATE `carnets_de_liaison_mots` SET `document`='' WHERE (`login_redacteur`='".$_SESSION['login']."' AND `id_mot`='".$id_modification."') LIMIT 1";
					$R_mot=mysqli_query($mysqli, $r_sql);
					if (!$R_mot) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
					}
				}
			// traitement du document joint
			if ($document_joint_nom!="")
				{
				// suppression éventuelle de l'ancien document joint
				$r_sql="SELECT `document` FROM `carnets_de_liaison_mots` WHERE `id_mot`='".$id_modification."' LIMIT 1";
				$R_document=mysqli_query($mysqli, $r_sql);
				$le_mot=mysqli_fetch_assoc($R_document);
				$document=unserialize($le_mot['document']);
				if (is_file($dossier_documents.$document['prefixe']."_".$id_modification))
					if (!unlink($dossier_documents.$document['prefixe']."_".$id_modification)) 
						$message_d_erreur.="Impossible de supprimer l'ancien document joint.<br />";
				// enregistrement du document joint
				$prefixe=prefixe();
				$fichier_document=$prefixe."_".$id_modification;
				if (!rename($dossier_documents.$document_joint_temp,$dossier_documents.$fichier_document))
					$message_d_erreur.="Impossible de renommer le document joint.<br />";
				if ($message_d_erreur=="")
					{
					formate_nom_fichier($document_joint_nom);
					$document=serialize(array('nom'=>$document_joint_nom,'type'=>$document_joint_type,'prefixe'=>$prefixe));
					$r_sql="UPDATE `carnets_de_liaison_mots` SET `document`='".$document."' WHERE (`login_redacteur`='".$_SESSION['login']."' AND `id_mot`='".$id_modification."') LIMIT 1";
					$R_mot=mysqli_query($mysqli, $r_sql);
					if (!$R_mot) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
					}
				}
			// modification du mot
			$r_sql="UPDATE `carnets_de_liaison_mots` SET `mail`='".$_POST['mail']."',`reponse_destinataire`='".$_POST['reponse_destinataire']."',`intitule`='".$_POST['intitule']."',`texte`='".$_POST['texte']."',`ids_destinataires`='".$ele_ids."' WHERE (`login_redacteur`='".$_SESSION['login']."' AND `id_mot`='".$id_modification."') LIMIT 1";
			$R_mot=mysqli_query($mysqli, $r_sql);
			if (!$R_mot) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
			$id_mot=$id_modification;
			}
			else
			{
			// enregistrement du mot
			$r_sql="INSERT INTO `carnets_de_liaison_mots` VALUES('','".$_SESSION['login']."','".$_POST['mail']."','eleve','".$_POST['reponse_destinataire']."','".$ele_ids."','','1',CURRENT_DATE,'".$_POST['intitule']."','".$_POST['texte']."','')";
			$R_mot=mysqli_query($mysqli, $r_sql);
			if (!$R_mot) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
			$id_mot=((is_null($___mysqli_res = mysqli_insert_id($mysqli))) ? false : $___mysqli_res);
			// traitement du document joint
			if ($document_joint_nom!="")
				{
				$prefixe=prefixe();
				$fichier_document=$prefixe."_".$id_mot;
				if (!rename($dossier_documents.$document_joint_temp,$dossier_documents.$fichier_document))
					$message_d_erreur.="Impossible de renommer le document joint.<br />";
				if ($message_d_erreur=="")
					{
					formate_nom_fichier($document_joint_nom);
					$document=serialize(array('nom'=>$document_joint_nom,'type'=>$document_joint_type,'prefixe'=>$prefixe));
					$r_sql="UPDATE `carnets_de_liaison_mots` SET `document`='".$document."' WHERE (`login_redacteur`='".$_SESSION['login']."' AND `id_mot`='".$id_mot."') LIMIT 1";
					$R_mot=mysqli_query($mysqli, $r_sql);
					if (!$R_mot) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
					}
				}
			}


		if ($id_modification!=0)
			{
			// on cherche les élèves destinataires supprimés
			$r_sql="SELECT * FROM `carnets_de_liaison_eleve` WHERE (FIND_IN_SET(`ele_id`,'".$ids_destinataires_initial."') AND NOT FIND_IN_SET(`ele_id`,'".$ele_ids."'))";
			$R_destinataires=mysqli_query($mysqli, $r_sql);
			if (!$R_destinataires) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
			while($un_destinataire=mysqli_fetch_assoc($R_destinataires))
				{
				// on supprime la référence au mot modifié
				$r_sql="UPDATE `carnets_de_liaison_eleve` SET `ids_mots`='".supp_element_liste($id_modification,$un_destinataire['ids_mots'])."' WHERE `ele_id`='".$un_destinataire['ele_id']."'";
				if (!mysqli_query($mysqli, $r_sql)) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
				}
			// on limite la liste des élèves destinataires à ceux qui ont été nouvellement ajoutés
			$t_ids_destinataires_initial=explode(",",$ids_destinataires_initial);
			array_pop($t_ids_destinataires_initial);
			foreach($t_ids_destinataires_initial as $id_destinataire)
				$ele_ids=supp_element_liste($id_destinataire,$ele_ids);
			}

		$t_ele_ids=explode(",",$ele_ids);
		array_pop($t_ele_ids);

		foreach($t_ele_ids as $ele_id)
			{
			// ajout dans les carnets de liaison des élèves destinataires
			$r_sql="SELECT * FROM `carnets_de_liaison_eleve` WHERE `ele_id`='".$ele_id."'";
			$R_carnet=mysqli_query($mysqli, $r_sql);
			if (mysqli_num_rows($R_carnet)==0)
				{
				// si nécessaire on crée le carnet de liaison de l'élève
				$r_sql="INSERT INTO `carnets_de_liaison_eleve` VALUES('".$ele_id."','-1')";
				if (!mysqli_query($mysqli, $r_sql))
					$message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
				$ids_mots="-1";
				}
				else 
				{
				$carnet=mysqli_fetch_assoc($R_carnet);
				$ids_mots=$carnet['ids_mots'];
				}
			// on ajoute à la liste des mots
			$ids_mots=$id_mot.",".$ids_mots;
			$r_sql="UPDATE `carnets_de_liaison_eleve` SET `ids_mots`='".$ids_mots."' WHERE `ele_id`='".$ele_id."'";
			if (!mysqli_query($mysqli, $r_sql)) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
			}

		// si tout s'est bien passé on envoie une notification
		if ($message_d_erreur=="") 
			{
			$type_notification="eleve";
			include("envoi_notification.inc.php");
			}
	}



//**************** EN-TETE *****************
$style_specifique="mod_plugins/carnets_de_liaison/styles";
$titre_page = "Carnets de liaison : saisie";
unset($_SESSION['ariane']);
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<p class=bold><a href="saisie.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/> Retour</a></p>
<div id="conteneur" style="margin: auto; width: 800px;">

<h2>Saisie d'un mot dans un (des) carnet(s) de liaison d'élève</h2>

<?php
if ($message_d_erreur!="")
	{
?>
	<p style="color: red; margin-left: 40px;"><?php echo $message_d_erreur; ?></p>
<?php
	}
?>
<?php
if ($etape==1)
	{
?>
	<div <div class="saisie" style="width: 600px;">
		<form method="post" name="saisie_1" action="saisie_par_eleve.php">
		<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		<input type="hidden" name="id_modification" value="<?php echo $id_modification; ?>">
	<?php
	if ($id_modification!=0)
		{
	?>
		<input type="hidden" name="mail" value="<?php echo str_replace("\"","'",$mail) ; ?>">
		<input type="hidden" name="reponse_destinataire" value="<?php echo $reponse_destinataire; ?>">
		<input type="hidden" name="intitule" value="<?php echo str_replace("\"","'",$intitule) ; ?>">
		<input type="hidden" name="texte" value="<?php echo str_replace("\"","'",$texte) ; ?>">
		<input type="hidden" name="ids_destinataires_initial" value="<?php echo $ids_destinataires_initial; ?>">
	<?php
		}
	?>
		<input type="hidden" name="ele_ids" value="<?php echo $ele_ids; ?>">
		<input type="hidden" name="etape" value="<?php echo $etape; ?>">
		<h4 style="margin-left:20px;">
		1. Sélectionner un ou plusieurs élèves<br /><br />
		Classe&nbsp;:&nbsp;
		<select name="id_classe" onChange="document.forms['saisie_1'].submit();" style="max-width: 140px;">
			<option value="0"></option>
	<?php
		$r_sql="SELECT *FROM `classes` ORDER BY `classe`";
		$R_classes=mysqli_query($mysqli, $r_sql);
		while($une_classe=mysqli_fetch_assoc($R_classes)) 
			{
	?>
			<option value="<?php echo $une_classe['id']; ?>" <?php if ($une_classe['id']==$id_classe) echo "selected=\"selected\"" ?>><?php echo $une_classe['nom_complet'].($une_classe['nom_complet']!=$une_classe['classe']?" (".$une_classe['classe'].")":""); ?></option>
	<?php
			}
	?>
		</select>
		&nbsp;Élève&nbsp;:&nbsp;
		<select name="ele_id" onChange="document.forms['saisie_1'].submit();" style="min-width: 100px; max-width: 260px;">
			<option value="-1"></option>
	<?php
		$r_sql="SELECT DISTINCT `ele_id`,`nom`,`prenom` FROM `eleves`,`j_eleves_classes` WHERE (`eleves`.`login`=`j_eleves_classes`.`login` AND `j_eleves_classes`.`id_classe`=".$id_classe." AND `periode`='".derniere_periode_active($id_classe)."' AND NOT FIND_IN_SET(`ele_id`,'".$ele_ids."')) ORDER BY `nom`,`prenom`";
		echo $r_sql;
		$R_eleves=mysqli_query($mysqli, $r_sql);
		while($un_eleve=mysqli_fetch_assoc($R_eleves))
			{
	?>
			<option value="<?php echo $un_eleve['ele_id'] ?>"><?php echo $un_eleve['prenom']." ".$un_eleve['nom']; ?></option>
	<?php
		}
	?>
		</select>
		<!--
		&nbsp;<button name="ajout_eleve" value="ajout_eleve" type="submit"> Ajouter cet èlève à la liste </button>
		-->
		</h4>
		<h4 style="margin-left:160px;">
		<button name="clore_ajout_eleve" value="clore" type="submit"> Terminer la sélection </button>
		 </h4>
		</form>
	</div>
<?php
	}
?>
<?php
if ($etape==2)
	{
?>
	<div <div class="saisie" style="width: 560px;">
		<form method="post" name="saisie_2" action="saisie_par_eleve.php" enctype="multipart/form-data">
		<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		<input type="hidden" name="id_modification" value="<?php echo $id_modification; ?>">
		<input type="hidden" name="ele_ids" value="<?php echo $ele_ids; ?>">
		<input type="hidden" name="etape" value="<?php echo $etape; ?>">
	<?php
	if ($id_modification!=0)
		{
	?>
		<input type="hidden" name="ids_destinataires_initial" value="<?php echo $ids_destinataires_initial; ?>">
	<?php
		$r_sql="SELECT `document` FROM `carnets_de_liaison_mots` WHERE (`login_redacteur`='".$_SESSION['login']."' AND `id_mot`='".$id_modification."') LIMIT 1";
		$R_mots=mysqli_query($mysqli, $r_sql);
		if (!$R_mots) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
			else
			{
			$le_mot=mysqli_fetch_assoc($R_mots);
			$document=unserialize($le_mot['document']);
			}
		}
	?>
		<h4 style="margin-left:20px;">2. Saisir le mot<br />
		<br />Intitulé&nbsp;:&nbsp;<input type="text" class="intitule" name="intitule" value="<?php if (isset($_POST['intitule'])) echo stripslashes($_POST['intitule']); ?>">
		<br />Texte&nbsp;:&nbsp;
		<div style="margin-left:0px;"><textarea name="texte" class="texte" height:200px;"><?php if (isset($_POST['texte'])) echo stripslashes(slashe_n2nl($_POST['texte'])); ?></textarea></div>
		<br />Autoriser les destinataires à répondre : 
			OUI <input <?php if (isset($_POST['reponse_destinataire']) && ($_POST['reponse_destinataire']=="oui")) echo "checked=\"checked\"" ?>name="reponse_destinataire" value="oui" type="radio">
			NON <input <?php if ((!isset($_POST['reponse_destinataire'])) || (isset($_POST['reponse_destinataire']) && ($_POST['reponse_destinataire']=="non"))) echo "checked=\"checked\"" ?> name="reponse_destinataire" value="non" type="radio">
		<br />
<?php
	$r_sql="SELECT `email` FROM `utilisateurs` WHERE `login`='".$_SESSION['login']."'";
	$R_mail=mysqli_query($mysqli,$r_sql);
	$t_email=mysqli_fetch_assoc($R_mail);
	$email=$t_email['email'];
	if ($carnets_de_liaison_mail=="oui")
		{
?>
		<br />Courriel&nbsp;:&nbsp;<input type="text" style="width:420px;" name="mail" value="<?php if (isset($_POST['mail'])) echo stripslashes($_POST['mail']); else echo $email; ?>">
		<br /><span style="font-size: smaller; font-style: italic;">(si ce champ est vide les réponses ne seront pas transmises par courriel)</span><br />
<?php
	if ($carnets_de_liaison_notification_mail_aux_responsables=="oui")
			{
?>
			<br />Courriel de notification aux destinataires : 
				OUI <input <?php if (isset($_POST['envoi_mail_notification']) && ($_POST['envoi_mail_notification']=="oui")) echo "checked=\"checked\"" ?>name="envoi_mail_notification" value="oui" type="radio">
				NON <input <?php if ((!isset($_POST['envoi_mail_notification'])) || (isset($_POST['envoi_mail_notification']) && ($_POST['envoi_mail_notification']=="non"))) echo "checked=\"checked\"" ?> name="envoi_mail_notification" value="non" type="radio">
			<br />
<?php
			}
		}
	else
		{
?>
		<input type="hidden" name="mail" value="<?php if (isset($_POST['mail'])) echo stripslashes($_POST['mail']); else echo $email; ?>">
		<input type="hidden" name="envoi_mail_notification" value="<?php if (isset($_POST['envoi_mail_notification'])) echo $_POST['envoi_mail_notification']; else echo "non" ?>">
<?php
		}
?>

<?php
	if ($carnets_de_liaison_notification_sms_aux_responsables=="oui")
		{
?>
		<br />SMS de notification aux destinataires : 
			OUI <input <?php if (isset($_POST['envoi_sms_notification']) && ($_POST['envoi_sms_notification']=="oui")) echo "checked=\"checked\"" ?>name="envoi_sms_notification" value="oui" type="radio">
			NON <input <?php if ((!isset($_POST['envoi_sms_notification'])) || (isset($_POST['envoi_sms_notification']) && ($_POST['envoi_sms_notification']=="non"))) echo "checked=\"checked\"" ?> name="envoi_sms_notification" value="non" type="radio">
		<br />
<?php
		}
	else
		{
?>
		<input type="hidden" name="envoi_sms_notification" value="<?php if (isset($_POST['envoi_sms_notification'])) echo $_POST['envoi_sms_notification']; else echo "non" ?>">
<?php
		}
?>

<?php
	if ($carnets_de_liaison_documents=="oui")
		{
?>
		<br />
<?php
		if ($document['nom']!="")
		{
		echo "Document joint : ".$document['nom']."<br />";
?>
		Supprimer le document <input type="checkbox" name="supprimer_document" value="<?php echo $document['prefixe']; ?>"> ou le remplacer par :
<?php
		}
			else
			echo "Joindre un document : "
?>
		<br />
		 <!-- input type="hidden" name="MAX_FILE_SIZE" value="2048" -->
		 <input style="border: 1px solid black; width: 500px;" size="40" type="file" name="document_joint">
		 <!-- button type="submit" name="joindre_document" value="Joindre">&nbsp;&nbsp;Joindre&nbsp;&nbsp;</button-->
<?php
		}
?>

		</h4>
		<div style="margin-left:200px;"><button name="saisie_ok" value="saisie_ok" type="submit"> Valider la saisie </button></div>
		</form>
	<br />
	</div>
<?php
	}
?>
<?php
if ($ele_ids!="-1")
	{
?>
		<form method="post" name="liste_eleve" action="saisie_par_eleve.php">
		<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		<input type="hidden" name="id_modification" value="<?php echo $id_modification; ?>">
		<input type="hidden" name="ele_ids" value="<?php echo $ele_ids; ?>">
		<input type="hidden" name="id_classe" value="<?php echo $id_classe; ?>">
		<input type="hidden" name="etape" value="<?php echo $etape; ?>">
	<?php
	if ($id_modification!=0)
		{
	?>
		<input type="hidden" name="mail" value="<?php echo str_replace("\"","'",$mail) ; ?>">
		<input type="hidden" name="intitule" value="<?php echo str_replace("\"","'",$intitule) ; ?>">
		<input type="hidden" name="texte" value="<?php echo str_replace("\"","'",$texte) ; ?>">
		<input type="hidden" name="ids_destinataires_initial" value="<?php echo $ids_destinataires_initial; ?>">
	<?php
		}
	?>
	<h4 style="margin-left:20px; margin-bottom: 0px">Élève(s)&nbsp;:&nbsp;</h4>
	<?php
	if ($etape==1)
		{
	?>
	<div style="margin-left:20px; margin-bottom: 20px">( <button title=" Retirer cet élève " style="border: none; background: none; vertical-align: middle;"><img style="width: 16px; height: 16px;" src="bouton_not_OK.png"></button> : retirer l'élève de la liste)</div>
	<?php
		}
	?>
		<div style="margin-left:40px;">
		<?php
		if ($ele_ids!="-1")
			{
			foreach(t_liste_eleves($ele_ids) as $un_eleve)
				{
		?>
				&nbsp;&nbsp;&nbsp;
				<?php 
				if ($etape==1)
					{ 
				?>
				<button name="retirer_eleve" value="<?php echo $un_eleve['ele_id']; ?>" type="submit" title=" Retirer cet élève " style="border: none; background: none; vertical-align: middle;"><img style="width: 16px; height: 16px;" src="bouton_not_OK.png"></button>
				<?php
					};
				echo $un_eleve['nom'];
				?>
				<br />
		<?php
				}
			}
		?>
		</div>
	<?php
		}
	?>
		</form>
<br /><br />
</div>
<?php
include("../../lib/footer.inc.php");
?>