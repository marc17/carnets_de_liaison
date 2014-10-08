<?php

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

// l'envoi de mail est-il activé ?
$carnets_de_liaison_mail=getSettingValue("carnets_de_liaison_mail");

// les rédacteurs peuvent-ils envoyer un mail de notification ?
$carnets_de_liaison_notification_mail_aux_responsables=getSettingValue("carnets_de_liaison_notification_mail_aux_responsables");

// les rédacteurs peuvent-ils joindre un fichier aux mots ?
$carnets_de_liaison_documents=getSettingValue("carnets_de_liaison_documents");

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

// si on est dans saisie_par_aid.php ou dans saisie_par_groupe.php
// et pas en mode modification d'un mot
// il faut convertir $_POST['ids_destinataires'] en tableau
if ((basename($_SERVER ['PHP_SELF'])=="saisie_par_aid.php" || basename($_SERVER ['PHP_SELF'])=="saisie_par_groupe.php") && !isset($_POST['modifier']))
	$_POST['ids_destinataires']=(isset($_POST['ids_destinataires']))?array($_POST['ids_destinataires']):array(0);
	
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
		$_POST['ids_destinataires']=explode(",",$le_mot['ids_destinataires']);
		array_pop($_POST['ids_destinataires']);
		$ids_destinataires_initial=$le_mot['ids_destinataires'];
		$document=unserialize($le_mot['document']);
		$id_modification=$_POST['modifier'];
		}
	}

// on stoke les distinataires pour l'envoi de notification
$liste_destinataires=isset($_POST['ids_destinataires'])?implode(",",$_POST['ids_destinataires']):"";

// a-t-on tous les champs renseignés ?
if (isset($_POST['intitule'])) if ($_POST['intitule']=="") $message_d_erreur.="Vous devez saisir un intitulé.<br />";
if (isset($_POST['texte'])) if ($_POST['texte']=="") $message_d_erreur.="Vous devez saisir un texte.<br />";
if ((!isset($_POST['ids_destinataires']) || $_POST['ids_destinataires'][0]==-1) && isset($_POST['saisie_ok'])) $message_d_erreur.=$message_erreur_saisie_destinataires;

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


if (isset($_POST['saisie_ok']) && ($message_d_erreur==""))
	{
	// ajout ou modification dans la table carnets_de_liaison_mots
	$ids_destinataires="";
	foreach ($_POST['ids_destinataires'] as $id_destinataire) $ids_destinataires.=$id_destinataire.",";
	$ids_destinataires.="-1";

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
		// modifications du mot
		$r_sql="UPDATE `carnets_de_liaison_mots` SET `mail`='".$_POST['mail']."',`reponse_destinataire`='".$_POST['reponse_destinataire']."',`intitule`='".$_POST['intitule']."', `texte`='".$_POST['texte']."', `ids_destinataires`='".$ids_destinataires."',`ensemble_destinataire`='".$ensemble_destinataire."' WHERE (`login_redacteur`='".$_SESSION['login']."' AND `id_mot`='".$id_modification."') LIMIT 1";
		$R_mot=mysqli_query($mysqli, $r_sql);
		if (!$R_mot) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		$id_mot=$id_modification;
		}
		else
		{
		// enregistrement du mot
		$r_sql="INSERT INTO `carnets_de_liaison_mots` VALUES('','".$_SESSION['login']."','".$_POST['mail']."','".$type_mot."','".$_POST['reponse_destinataire']."','".$ids_destinataires."','".$ensemble_destinataire."','1',CURRENT_DATE,'".$_POST['intitule']."','".$_POST['texte']."','')";
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
		// on cherche les destinataires supprimées
		$r_sql="SELECT * FROM ".$table_carnet." WHERE (FIND_IN_SET(".$champ_id_table_carnet.",'".$ids_destinataires_initial."') AND NOT FIND_IN_SET(".$champ_id_table_carnet.",'".implode(",",$_POST['ids_destinataires'])."'))";
		$R_destinataires=mysqli_query($mysqli, $r_sql);
		if (!$R_destinataires) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		while($un_destinataire=mysqli_fetch_assoc($R_destinataires))
			{
			// on supprime la référence au mot modifié
			$r_sql="UPDATE ".$table_carnet." SET `ids_mots`='".supp_element_liste($id_modification,$un_destinataire['ids_mots'])."' WHERE ".$champ_id_table_carnet."='".$un_destinataire[$champ_id_table_carnet]."'";
			if (!mysqli_query($mysqli, $r_sql)) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
			}
		// on limite la liste des  destinataires à ceux qui ont été nouvellement ajoutés
		$t_ids_destinataires_initial=explode(",",$ids_destinataires_initial);
		foreach($t_ids_destinataires_initial as $id_destinataire) 
			if (array_search($id_destinataire,$_POST['ids_destinataires'])!==false) unset($_POST['ids_destinataires'][array_search($id_destinataire,$_POST['ids_destinataires'])]);
		}


	foreach ($_POST['ids_destinataires'] as $id_destinataire)
		{
		// ajout dans les carnets de liaison des destinataires
		$r_sql="SELECT * FROM ".$table_carnet." WHERE ".$champ_id_table_carnet."='".$id_destinataire."'";
		$R_carnet=mysqli_query($mysqli, $r_sql);
		if (mysqli_num_rows($R_carnet)==0)
			{
			// si nécessaire on crée le carnet de liaison du destinataire
			$r_sql="INSERT INTO ".$table_carnet." VALUES('".$id_destinataire."','-1')";
			if (!mysqli_query($mysqli, $r_sql))	$message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
			$ids_mots="-1";
			}
			else 
			{
			$carnet=mysqli_fetch_assoc($R_carnet);
			$ids_mots=$carnet['ids_mots'];
			}
		// on ajoute à la liste des mots
		$ids_mots=$id_mot.",".$ids_mots;
		$r_sql="UPDATE ".$table_carnet." SET `ids_mots`='".$ids_mots."' WHERE ".$champ_id_table_carnet."='".$id_destinataire."'";
		if (!mysqli_query($mysqli, $r_sql))	
			$message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}

		// si tout s'est bien passé on envoie une notification
		if ($message_d_erreur=="")
			{
			$type_notification=$type_mot;
			include("envoi_notification.inc.php");
			}
	}

?>

