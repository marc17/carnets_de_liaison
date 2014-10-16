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


// tableaux des noms de jours et mois en français
include("jours_et_mois.inc");

function date_en_clair($d)
	{
	global $noms_mois;
	$t_d=explode("-",$d);
	//il y a une astuce :-)
	return ($t_d[2]+0)." ".$noms_mois[$t_d[1]-1]." ".$t_d[0]; 
	}

function date_du_jour()
	{
	$date=getdate();
	return date_en_clair($date['year']."-".$date['mon']."-".$date['mday']);
	}


function slashe_n2nl($ch)
	{
	$remplace=array("\\n"=>"\n","\\r"=>"");
	return strtr($ch,$remplace);
	}


function supp_guillements($ch)
	{
	$remplace=array("\""=>"'");
	return strtr($ch,$remplace);
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

// si l'appel se fait avec passage de paramètre alors test du token
if ((function_exists("check_token")) && ((count($_POST)<>0) || (count($_GET)<>0))) check_token();

// a priori pas d'erreur
$message_d_erreur="";

// a priori pas de document joint
$document=array('nom'=>'','type'=>'','prefixe'=>'');
$document_joint_nom="";
$document_joint_type="";
$document_joint_temp="";

// URL GEPI
$carnets_de_liaison_url_gepi=getSettingValue("carnets_de_liaison_url_gepi");

// l'envoi de mail est-il activé ?
$carnets_de_liaison_mail=getSettingValue("carnets_de_liaison_mail");

// les rédacteurs peuvent-ils joindre un fichier aux mots ?
$carnets_de_liaison_documents=getSettingValue("carnets_de_liaison_documents");

// les responsables peuvent-ils rédiger des mots ?
$carnets_de_liaison_saisie_responsable=getSettingValue("carnets_de_liaison_saisie_responsable");

// les responsables peuvent-ils répondre aux mots ?
$carnets_de_liaison_reponses_responsables=getSettingValue("carnets_de_liaison_reponses_responsables");

// les photos des élèves sont-elles affichées ?
$carnets_de_liaison_affiche_trombines_eleves=getSettingValue("carnets_de_liaison_affiche_trombines_eleves");

// les photos des profs sont-elles affichées ?
$carnets_de_liaison_affiche_trombines_profs=getSettingValue("carnets_de_liaison_affiche_trombines_profs");

// paramètres trombinoscope
$width_trombine=getSettingValue("l_max_aff_trombinoscopes");
$height_trombine=getSettingValue("h_max_aff_trombinoscopes");

// mail et civilité du responsable
$r_sql="SELECT `email`,`show_email`,`civilite` FROM `utilisateurs` WHERE `login`='".$_SESSION['login']."' LIMIT 1";
$R_coords=mysqli_query($mysqli,$r_sql);
$t_coords=mysqli_fetch_assoc($R_coords);
$mail_utilisateur=$t_coords['email'];
$show_email_utilisateur=$t_coords['show_email'];
$civilite_utilisateur=$t_coords['civilite'];

// on détermine les élèves dont l'utilisateur est responsable
$t_eleves=array();
$r_sql="SELECT DISTINCT `responsables2`.`ele_id`,`responsables2`.`resp_legal`,`eleves`.`login`,`eleves`.`id_eleve`,`eleves`.`elenoet`,`eleves`.`nom`,`eleves`.`prenom`,`classes`.`id`,`classes`.`classe`,`classes`.`nom_complet`,`j_eleves_classes`.`periode` FROM `resp_pers`,`responsables2`,`eleves`,`j_eleves_classes`,`classes` WHERE (`resp_pers`.`login`='".$_SESSION['login']."' AND `resp_pers`.`pers_id`=`responsables2`.`pers_id` AND (`responsables2`.`resp_legal`=1 OR `responsables2`.`resp_legal`=2) AND `responsables2`.`ele_id`=`eleves`.`ele_id` AND `eleves`.`login`=`j_eleves_classes`.`login` AND `j_eleves_classes`.`id_classe`=`classes`.`id`) ORDER BY `j_eleves_classes`.`periode` ASC";
$R_eleves=mysqli_query($mysqli, $r_sql);
while ($un_eleve=mysqli_fetch_assoc($R_eleves)) $t_eleves[$un_eleve['id_eleve']]=$un_eleve;

// initialisations
// $ele_id indice de l'élève dans la table 'eleves'
// $id_eleve dans le tableau $t_eleves
$id_classe=0;
$ele_id=0;
if (isset($_POST['id_eleve']) && ($_POST['id_eleve']>=0))
	{
	$id_eleve=$_POST['id_eleve'];
	$id_classe=$t_eleves[$_POST['id_eleve']]['id'];
	$ele_login=$t_eleves[$_POST['id_eleve']]['login'];
	$ele_id=$t_eleves[$_POST['id_eleve']]['ele_id'];
	$elenoet=$t_eleves[$_POST['id_eleve']]['elenoet'];
	}

// traitement du mot rédigé par le responsable
if (isset($_POST['saisie_ok']))
	{
	// a-t-on tous les champs renseignés ?
	if (isset($_POST['intitule'])) if ($_POST['intitule']=="") $message_d_erreur.="Vous devez saisir un intitulé.<br />";
	if (isset($_POST['texte'])) if ($_POST['texte']=="") $message_d_erreur.="Vous devez saisir un texte.<br />";
	if (isset($_POST['login_destinataire'])) if ($_POST['login_destinataire']=="")  $message_d_erreur.="Vous devez sélectionner un professeur.<br />";

	// traitement de l'upload si les autres champs sont renseignés
	if (isset($_FILES['document_joint']) && ($_FILES['document_joint']['name']!="") && ($message_d_erreur==""))
		{
		if ($_FILES['document_joint']['error']==0)
			if (is_uploaded_file($_FILES['document_joint']['tmp_name']))
				{
				if (move_uploaded_file($_FILES['document_joint']['tmp_name'], "documents/".$document_joint_temp=basename($_FILES['document_joint']['tmp_name'])))
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


	if ($message_d_erreur=="")
		{
		// quel est le destinataire ?
		$r_sql="SELECT `civilite`,`prenom`,`nom`,`email` FROM `utilisateurs` WHERE `login`='".$_POST['login_destinataire']."' LIMIT 1";
		$t_coords=mysqli_fetch_assoc(mysqli_query($mysqli,$r_sql));
		$destinataire=$t_coords['civilite']." ".$t_coords['prenom']." ".$t_coords['nom']; 
		$mail_destinataire=$t_coords['email'];
		// enregistrement du mot
		$r_sql="INSERT INTO `carnets_de_liaison_mots` VALUES('','".$_SESSION['login']."','".$mail_utilisateur."','prof','oui','".$_POST['login_destinataire'].",".$_POST['ele_id'].",-1','','1',CURRENT_DATE,'".$_POST['intitule']."','(pour ".$destinataire.")\n".$_POST['texte']."','')";
		$R_mot=mysqli_query($mysqli, $r_sql);
		if (!$R_mot) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		$id_mot=((is_null($___mysqli_res = mysqli_insert_id($mysqli))) ? false : $___mysqli_res);
		// traitement du document joint
		if (($document_joint_nom!="") && ($message_d_erreur==""))
			{
			$prefixe=prefixe();
			$fichier_document=$prefixe."_".$id_mot;
			if (!rename("documents/".$document_joint_temp,"documents/".$fichier_document))
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

		if ($message_d_erreur=="")
			{
			// ajout dans le carnet de liaison de l'élève
			$r_sql="SELECT * FROM `carnets_de_liaison_eleve` WHERE `ele_id`='".$_POST['ele_id']."'";
			$R_carnet=mysqli_query($mysqli, $r_sql);
			if (mysqli_num_rows($R_carnet)==0)
				{
				// si nécessaire on crée le carnet de liaison de l'élève
				$r_sql="INSERT INTO `carnets_de_liaison_eleve` VALUES('".$_POST['ele_id']."','-1')";
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
			$r_sql="UPDATE `carnets_de_liaison_eleve` SET `ids_mots`='".$ids_mots."' WHERE `ele_id`='".$_POST['ele_id']."'";
			if (!mysqli_query($mysqli, $r_sql))
				$message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
			}


		if (($message_d_erreur)=="")
			{
			// envoi du mot par mail
			if (($carnets_de_liaison_mail=="oui") && ($mail_destinataire!=""))
				{
				$to=$mail_destinataire;
				$subject="[GEPI- carnets de liaison] ".stripslashes($_POST['intitule']);
				$subject = "=?UTF-8?B?".base64_encode($subject)."?=";
				$message="(mot de ".$civilite_utilisateur." ".$_SESSION['prenom']." ".$_SESSION['nom']." le ".date_en_clair(date("Y-m-d"));
				$message.=" dans le carnet de ".$_POST['identite_eleve'].")\n\n";
				$message.=stripslashes(slashe_n2nl($_POST['texte']));
				$message.="\n\n".$carnets_de_liaison_url_gepi;
				$headers="From: \"".$_SESSION['prenom']." ".$_SESSION['nom']."\" <".$mail_utilisateur.">".PHP_EOL;
				$headers.="Content-type: text/plain; charset=utf-8".PHP_EOL;
				$headers.="MIME-Version: 1.0".PHP_EOL;
				if (!mail($to, $subject, $message, $headers))
					{
					$script_bilan_envoi_mail="
						<script type=\"text/javascript\">
						<!--
						alert(\"Echec lors de l'envoi du mot par courriel.\");
						-->
						</script>
						";
					}
					else
					{
					$script_bilan_envoi_mail="
						<script type=\"text/javascript\">
						<!--
						alert(\"Le mot a été envoyé par courriel.\");
						-->
						</script>
						";
					}
				}

			// message émis sur la page d'accueil du destinataire
			if (function_exists("message_accueil_utilisateur"))
				{
				$message="<!-- carnets de liaison -->";
				$message.="<span style=\"font-weight:bold\">Carnets de liaison : </span>";
				$message.="<form method=\"post\" name=\"consultation_carnet\" action=\"mod_plugins/carnets_de_liaison/consultation.php#ancre_retour".$id_mot."\">";
				if (function_exists("add_token_field")) $message.=add_token_field(false,false);
				$message.="<input type=\"hidden\" name=\"id_classe\" value=\"".$id_classe."\">";
				$message.="<input type=\"hidden\" name=\"ele_id\" value=\"".$ele_id."\">";
				$message.="mot intitulé \"".stripslashes($_POST['intitule'])."\" de ".$civilite_utilisateur." ".$_SESSION['prenom']." ".$_SESSION['nom']."  rédigé le ".date_du_jour();
				if (isset($_POST['identite_eleve'])) 
						$message.=" dans le carnet de ".$_POST['identite_eleve'];
				$message.=".&nbsp;<button type=\"submit\" title=\" Voir ce mot \" style=\"border: none; background: none; float: right;\"><img style=\"width:16px; height:16px; vertical-align: bottom;\" src=\"mod_plugins/carnets_de_liaison/bouton_voir_carnet.png\"></button></form>";
				message_accueil_utilisateur($_POST['login_destinataire'],$message,time(),time()+3600*24*7,0,true);
				}

			// on oublie tout
			$_POST['destinataire']="";
			$_POST['intitule']="";
			$_POST['texte']="";
			}
		}
	}
// fin du traitement du mot rédigé par le responsable


// envoi d'une réponse par le responsable de l'élève
include("envoi_reponse.inc.php");



//**************** EN-TETE *****************
$style_specifique="mod_plugins/carnets_de_liaison/styles";
$titre_page = "Carnets de liaison : consultation";
unset($_SESSION['ariane']);
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<?php
// on positionne la photo de l'éléve
if (getSettingAOui('active_module_trombinoscopes') && $carnets_de_liaison_affiche_trombines_eleves=="oui")
	{
?>
	<div id="trombine" style="float: right;">
	<img name="photo_eleve" style="width: <?php echo $width_trombine; ?>px; height: <?php echo $height_trombine; ?>px; vertical-align: top;" src="photo_vide.png">
	</div>
<?php
	}
?>

<p class=bold><a href="../../accueil.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/>Retour à l'accueil</a>
</p>
<div id="conteneur" style="margin: auto; width: 800px;">

<?php
if ($message_d_erreur!="")
	{
?>
	<p style="color: red; margin-left: 40px;"><?php echo $message_d_erreur; ?></p>
<?php
	}

if (count($t_eleves)>1)
	{
?>
	<form method="post" name="consultation" action="index.php">
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	<h2>Carnet de liaison de 
		<select name="id_eleve" onChange="document.forms['consultation'].submit();">
			<option value="-1"></option>
		<?php
		foreach($t_eleves as $id =>$eleve)
			{
		?>
			<option value="<?php echo $id; ?>" <?php if (isset($_POST['id_eleve']) && ($_POST['id_eleve']==$id)) echo "selected=\"selected\"" ?>><?php echo $eleve['prenom']." ".$eleve['nom']." (".$eleve['classe'].")"; ?></option>
		<?php
			}
		?>
		</select>
	</h2>
	</form>
<?php
	}
else 
	{
	$t_cles=array_keys($t_eleves);
	$id_eleve=$t_cles[0];
	$id_classe=$t_eleves[$id_eleve]['id'];
	$ele_id=$t_eleves[$id_eleve]['ele_id'];
	$ele_login=$t_eleves[$id_eleve]['login'];
	$elenoet=$t_eleves[$id_eleve]['elenoet'];
?>
<h2>Carnet de liaison de <?php echo $t_eleves[$id_eleve]['prenom']." ".$t_eleves[$id_eleve]['nom']." (".$t_eleves[$id_eleve]['nom_complet'].")"; ?></h2>
<?php
	}

if ($ele_id!==0 && getSettingAOui('active_module_trombinoscopes') && ($carnets_de_liaison_affiche_trombines_eleves=="oui"))
	{
	// l'élève étant déterminé on affiche sa photo
	$ele_photo=nom_photo($elenoet,$repertoire="eleves",$arbo=2);
	if ($ele_photo!=NULL)
		{
?>
		<script  type="text/javascript">
		<!--
		 document.photo_eleve.src="<?php echo $ele_photo;; ?>";
		//-->
		</script>
<?php
		}
	}

// dans tous les cas (responsable d'un seul élève ou plusieurs)
if (isset($id_eleve)) $identite_eleve=$t_eleves[$id_eleve]['prenom']." ".$t_eleves[$id_eleve]['nom']." ".$t_eleves[$id_eleve]['nom_complet'];
	else $identite_eleve="[identité élève non déterminée]";
?>

<?php
if ($ele_id!==0)
	{
	if ($carnets_de_liaison_saisie_responsable=="oui")
		{
		// on constitue la liste de l'équipe pédagogique (adaptation de groupes/visu_profs_eleve.php)
		// CPE
		$r_sql="SELECT DISTINCT `utilisateurs`.`civilite`,`utilisateurs`.`email`,`utilisateurs`.`nom`,`utilisateurs`.`prenom`,`j_eleves_cpe`.`cpe_login` FROM `utilisateurs`,`j_eleves_cpe`  " .
			"WHERE `j_eleves_cpe`.`e_login`='".$ele_login."' AND `utilisateurs`.`login`=`j_eleves_cpe`.`cpe_login` ORDER BY `j_eleves_cpe`.`cpe_login`";
		$R_cpe=mysqli_query($mysqli, $r_sql);
		$cpe=mysqli_fetch_assoc($R_cpe);
		$t_equipe_pedagogique[]=array('login'=>$cpe['cpe_login'],'civilite'=>'','mail'=>$cpe['email'],'nom'=>$cpe['nom'],'prenom'=>$cpe['prenom'],'pp'=>false);
		// Professeurs
		$r_sql="SELECT `j_eleves_groupes`.`id_groupe`,`j_groupes_professeurs`.`id_groupe`,`j_groupes_professeurs`.`login`,`utilisateurs`.`civilite`,`utilisateurs`.`email`,`utilisateurs`.`nom`,`utilisateurs`.`prenom` FROM `j_eleves_groupes`,`j_groupes_professeurs`,`utilisateurs` WHERE (`j_eleves_groupes`.`login`='".$ele_login."' AND`j_groupes_professeurs`.`id_groupe`=`j_eleves_groupes`.`id_groupe` AND `utilisateurs`.`login`=`j_groupes_professeurs`.`login`) GROUP BY `j_groupes_professeurs`.`login`";
		$R_profs=mysqli_query($mysqli, $r_sql);
		while($prof=mysqli_fetch_assoc($R_profs))
			{
			// Professeur principal ?
			$sql="SELECT * FROM j_eleves_professeurs WHERE login = '".$ele_login."' AND professeur='".$prof['login']."'";
			$R_pp=mysqli_query($mysqli, $sql);
			$pp=(mysqli_num_rows($R_pp)>0)?true:false;

			$t_equipe_pedagogique[]=array('login'=>$prof['login'],'civilite'=>$prof['civilite'],'mail'=>$prof['email'],'nom'=>$prof['nom'],'prenom'=>$prof['prenom'],'pp'=>$pp);
			}
		// gestion du bouton "Rédiger un mot"
		$afficher_bouton=(!isset($_POST['saisie_ok']) || ($message_d_erreur==""))?"inline":"none";
		$afficher_formulaire=($afficher_bouton=="inline")?"none":"block";
?>
		<div style="margin-left: 40px; display: <?php echo $afficher_bouton; ?>;" id="bouton_rediger">
		<button type="submit" name="rediger_mot" value=" Rédiger un mot " onClick="document.getElementById('bouton_rediger').style.display='none';document.getElementById('rediger').style.display='block';"> Rédiger un mot dans ce carnet </button>
		</div>
		<div class="saisie" style="margin-left: 40px; display: <?php echo $afficher_formulaire; ?>; width: 530px;" id="rediger">
		<h4 style="margin-left:10px;">
		<b>Rédiger un mot dans ce carnet</b>
		<br /><br />
		<form method="post" action="index.php" name="form_rediger_mot" enctype="multipart/form-data">
		<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		<input type="hidden" name="ele_id" value="<?php echo $ele_id; ?>">
		<input type="hidden" name="id_eleve" value="<?php echo $id_eleve; ?>">
		<input type="hidden" name="identite_eleve" value="<?php echo $identite_eleve; ?>">
		Destinataire&nbsp;:&nbsp;
		  <select size="1" name="login_destinataire">
			  <option></option>
		<?php
		foreach($t_equipe_pedagogique as $professeur)
			{
			$destinataire=$professeur['civilite']." ".$professeur['prenom']." ".$professeur['nom'];
			if ($professeur['pp']) $destinataire.= " (".getSettingValue('gepi_prof_suivi').")";
		?>
			<option value="<?php echo $professeur['login']; ?>" <?php if (isset($_POST['login_destinataire']) && ($_POST['login_destinataire']==$professeur['login'])) echo "selected=\"selected\"" ; ?>><?php echo $destinataire; ?></option>
		<?php
			}
		?>
			</select>
		 <br /><br />
		Intitulé&nbsp;:&nbsp;<input name="intitule"  class="intitule" value="<?php if (isset($_POST['intitule'])) echo stripslashes($_POST['intitule']); ?>" ><br />
		<br />Texte&nbsp;:&nbsp;
		<div style="margin-left:0px;"><textarea name="texte" class="texte" height:200px;"><?php if (isset($_POST['texte'])) echo stripslashes(slashe_n2nl($_POST['texte'])); ?></textarea></div>
		<?php
		if ($carnets_de_liaison_documents=="oui")
			{
		?>
			<br />
			<?php
			if ($document['nom']!="")
				echo "Document joint : ".$document['nom']."<br />";
			else
				echo "Joindre un document : "
			?>
			<br />
			 <!-- input type="hidden" name="MAX_FILE_SIZE" value="2048" -->
			 <input style="border: 1px solid black; width: 500px;" size="43" type="file" name="document_joint">
			 <!-- button type="submit" name="joindre_document" value="Joindre">&nbsp;&nbsp;Joindre&nbsp;&nbsp;</button-->
			<br />
<?php
			}
?>
		<br />
		<div style="margin-left:200px"><button name="saisie_ok" value="ok" type="submit">&nbsp;&nbsp;&nbsp;&nbsp;Valider la saisie&nbsp;&nbsp;&nbsp;&nbsp;</button></div>
		</form>
		</h4>
		</div>
<?php
		}
	include("affiche_carnet.inc.php");
	}
?>
<br /><br />
</div>
<?php
include("../../lib/footer.inc.php");

// retour d'envoi de réponse
if (isset($script_bilan_envoi_mail)) echo $script_bilan_envoi_mail;
?>