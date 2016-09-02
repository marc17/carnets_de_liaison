<?php

//************************
// Copyleft Marc Leygnac
//************************

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

function acces_dossier_documents()
	{
	if (!$test=@fopen("documents/test.txt","w")) return false;
		else
		{
		if (!@fwrite($test,"test")) return false;
		if (!@fclose($test)) return false;
		if (!@unlink("documents/test.txt")) return false;
		}
	return true;
	}

function del_tree($chemin) 
	{
	// supprime le dossier ou le fichier $chemin
	$erreurs="";
    if ($chemin[strlen($chemin)-1] != "/") $chemin.= "/";
    if (is_dir($chemin))
		{
		$dossier = opendir($chemin);
		while ($fichier = readdir($dossier))
			{
			if ($fichier != "." && $fichier != "..")
				{
				$chemin_fichier = $chemin . $fichier;
				if (is_dir($chemin_fichier)) del_tree($chemin_fichier);
					else if (!@unlink($chemin_fichier)) $erreurs.="Impossible de supprimer le fichier ".$chemin_fichier.".<br/>";
				}
			}
		closedir($dossier);
		if (!@rmdir($chemin)) $erreurs.="Impossible de supprimer le dossier ".$chemin.".<br/>";
		}
	else if (!@unlink($chemin)) $erreurs.="Impossible de supprimer le fichier".$chemin.".<br/>";
	return $erreurs;
	}

define( 'PCLZIP_TEMPORARY_DIR', 'documents/' );
require_once('../../lib/pclzip.lib.php');
function sauvegarde_documents()
	{
	// a priori tout se passera bien
	$bilan_sauvegarde="";
	// dossier destination de la sauvegarde
	$backup_directory=getSettingValue("backup_directory");
	// dossier des documents
	$dossier_documents="documents/";
	
	// si multisite
	$_COOKIE['RNE']="";
	if (isset($GLOBALS['multisite']) AND $GLOBALS['multisite']=='y')
		if (!isset($_COOKIE['RNE'])) $bilan_sauvegarde="Multisite : impossible de récupérer le cookie RNE.<br />";
		else $dossier_documents.=$_COOKIE['RNE']."/";
	//$_COOKIE['RNE']="rne"; $dossier_documents.=$_COOKIE['RNE']."/"; // pour test multisite
	// si aucun pb jusque là alors on continue
	if ($bilan_sauvegarde=="")
		{
		// tableau des fichiers à sauvegarder, pour éviter de sauvegarder les sous-dossiers
		$t_fichiers_a_sauvegarder=array();
		$d=opendir($dossier_documents);
		while ($fichier=readdir($d))
			{
			$pathinfo=pathinfo($fichier);
			if ($fichier != "." && $fichier != ".." && !isset($pathinfo['extension']) && !is_dir($dossier_documents.$fichier))
				$t_fichiers_a_sauvegarder[]=$dossier_documents.$fichier;
			}
		// fichier de sauvegarde
		$sauvegarde_documents="../../backup/".$backup_directory."/_carnets_docs";
		if ($_COOKIE['RNE']!="") $sauvegarde_documents.="_".$_COOKIE['RNE'];
		$sauvegarde_documents.="_le_".date("d_m_Y_\a_H\hi").".zip";
		// on compresse
		$o_zip=new PclZip($sauvegarde_documents);
		$v_list=$o_zip->create($t_fichiers_a_sauvegarder);
		if ($v_list==0) $bilan_sauvegarde="Echec à la création de la sauvegarde : ".$o_zip->errorInfo(true);
		}
	return $bilan_sauvegarde;
	}

function restauration_documents()
	{
	// a priori tout se passera bien
	$bilan_restauration="";
	$bilan_restauration_multisite="";

	// dossier des documents
	$dossier_documents="documents/";

	// si multisite
	$_COOKIE['RNE']="";
	if (isset($GLOBALS['multisite']) AND $GLOBALS['multisite']=='y')
		if (!isset($_COOKIE['RNE'])) $bilan_restauration_multisite="Multisite : impossible de récupérer le cookie RNE.<br />";
		else $dossier_documents.=$_COOKIE['RNE']."/";
	//$_COOKIE['RNE']="rne"; $dossier_documents.=$_COOKIE['RNE']."/"; // pour test multisite
	// Le téléchargement s'est-il bien passé ?
	if (isset($_FILES["fichier_sauvegarde"]))
		{
		$fichier_sauvegarde=$_FILES["fichier_sauvegarde"];
		// c'est dans $dir_temp que le travail se fera
		$dir_temp ="temp";
		// si multisite
		if ($_COOKIE['RNE']!="") 
			{
			$dir_temp.="_".$_COOKIE['RNE'];
			// si le dossier documents/rne n'existe pas on le crée
			if (!file_exists($dossier_documents))
				if (!@mkdir($dossier_documents,0700,true)) $bilan_restauration.="Impossible de créer ".$dossier_documents."<br/>";
			}
		// préparation du dossier temporaire
		if (is_file($dir_temp) && !@unlink($dir_temp)) $bilan_restauration.="Impossible de supprimer ".$dir_temp."<br/>";
			else if (!file_exists($dir_temp)) 
				if (!@mkdir($dir_temp,0700,true)) $bilan_restauration.="Impossible de créer ".$dir_temp."<br/>";
		// si aucun pb jusque là alors on continue
		if ($bilan_restauration=="")
			{
			// transfert du fichier ZIP dans $dir_temp
			$retour=telecharge_fichier($fichier_sauvegarde,$dir_temp,"zip",'application/zip application/octet-stream application/x-zip-compressed');
			if ($retour!="ok") $bilan_restauration.=$retour;
			else 
				{
				// décompression du fichier ZIP
				$o_zip=new PclZip($dir_temp."/".$fichier_sauvegarde['name']);
				$v_list=$o_zip->extract(PCLZIP_OPT_PATH,$dir_temp,PCLZIP_OPT_SET_CHMOD,0700);
				if ($v_list==0)
					$bilan_restauration.="Une erreur a été rencontrée lors de l'extraction du fichier zip : ".$o_zip->errorInfo(true)."<br />";
				else
					{
					// suppression du fichier .zip
					if (!@unlink ($dir_temp."/".$_FILES["fichier_sauvegarde"]['name']))
						$bilan_restauration.="Erreur lors de la suppression de ".$dir_temp."/".$_FILES["fichier_sauvegarde"]."<br/>";
					$dossier_origine=$dir_temp."/".$dossier_documents;
					if(!file_exists($dossier_origine))
						$bilan_restauration.="Le fichier de sauvegarde ne contient pas une arborescence correcte.<br />";
					else
						{
						$d=opendir($dossier_origine);
						while ($fichier=readdir($d))
							{
							$pathinfo=pathinfo($fichier);
							if ($fichier != "." && $fichier != ".." && !isset($pathinfo['extension']) && !is_dir($dossier_origine.$fichier))
								@copy($dossier_origine.$fichier,$dossier_documents.$fichier);
							}
						// si multisite
						if ($_COOKIE['RNE']!="")
							{
							@copy("documents/index.html",$dossier_documents."index.html");
							@copy("documents/index.php",$dossier_documents."index.php");
							}
						closedir($d);
						}
					}
				}
			}
			
		// quoiqu'il se soit passé on supprime le dossier ../temp
		$bilan_restauration.=del_tree($dir_temp);
		}
		else $bilan_restauration="Impossible de télécharger la sauvegarde sur le serveur.<br />";
	return $bilan_restauration;
	}

function existe_table($table)
{
global $mysqli;
$r_sql="SHOW TABLES LIKE '".$table."'";
return (mysqli_num_rows(mysqli_query($mysqli, $r_sql))!=0);
}

// si l'appel se fait avec passage de paramètre alors test du token
if ((function_exists("check_token")) && ((count($_POST)<>0) || (count($_GET)<>0))) check_token();

// mettre à jour les tables pour passer de la version 1.2 à la version 1.3
$message_maj_tables="";
if (!existe_table("carnets_de_liaison_reponses"))
	{
	$r_sql="ALTER TABLE `carnets_de_liaison_mots` ADD `reponse_destinataire` VARCHAR( 3 ) NOT NULL DEFAULT 'non' AFTER `type`";
	if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible d'ajouter le champ `reponse_destinataire` à la table `carnets_de_liaison_mots` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="Le champ `reponse_destinataire` a été ajouté à la table `carnets_de_liaison_mots`.<br />";
	$r_sql="UPDATE `carnets_de_liaison_mots` SET `reponse_destinataire`='oui' WHERE `mail`<>''";
	if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible d'initialiser le champ `reponse_destinataire` de la table `carnets_de_liaison_mots` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="Le champ `reponse_destinataire` de la table `carnets_de_liaison_mots` a été initialisé.<br />";
	if (!saveSetting('carnets_de_liaison_saisie_responsable', 'oui')) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_saisie_responsable' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_saisie_responsable' a été ajoutée à la table `setting`.<br />";
	if (!saveSetting('carnets_de_liaison_reponses_responsables', 'oui')) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_reponses_responsables' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_reponses_responsables' a été ajoutée à la table `setting`.<br />";
	$r_sql="CREATE TABLE `carnets_de_liaison_reponses` (
		`id_mot` INT(11) NOT NULL DEFAULT '0',
		`login_redacteur` VARCHAR(50) NOT NULL DEFAULT '',
		`date` DATE NOT NULL DEFAULT '0000-00-00',
		`texte` TEXT NOT NULL DEFAULT '',
		INDEX (`id_mot`)
		)";
	if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible de créer la table `carnets_de_liaison_reponses` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="La table `carnets_de_liaison_reponses` a été créée.<br />";
	}

// mettre à jour les tables pour passer de la version 1.3 à la version 1.4
if (!existe_table("carnets_de_liaison_aid"))
	{
	$r_sql="ALTER TABLE `carnets_de_liaison_mots` ADD `ensemble_destinataire` VARCHAR( 256 ) NOT NULL DEFAULT '' AFTER `ids_destinataires`";
	if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible d'ajouter le champ `ensemble_destinataire` à la table `carnets_de_liaison_mots` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="Le champ `ensemble_destinataire` a été ajouté à la table `carnets_de_liaison_mots`.<br />";
	$r_sql="CREATE TABLE `carnets_de_liaison_aid` (
			  `id_aid` smallint(6) NOT NULL,
			  `ids_mots` varchar(512) NOT NULL DEFAULT '-1',
			  UNIQUE KEY `id_aid` (`id_aid`)
			)";
	if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible de créer la table `carnets_de_liaison_aid` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="La table `carnets_de_liaison_aid` a été créée.<br />";
	$r_sql="CREATE TABLE `carnets_de_liaison_groupe` (
			  `id_groupe` smallint(6) NOT NULL,
			  `ids_mots` varchar(512) NOT NULL DEFAULT '-1',
			  UNIQUE KEY `id_groupe` (`id_groupe`)
			)";
	if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible de créer la table `carnets_de_liaison_groupe` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="La table `carnets_de_liaison_groupe` a été créée.<br />";
	$r_sql="UPDATE `carnets_de_liaison_classe` SET `ids_mots`=CONCAT(SUBSTRING(`ids_mots`,1,LENGTH(`ids_mots`)-1),'-1')";
	if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible de remplacer la fin de liste '0' par '-1' dans la table `carnets_de_liaison_classe` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="La fin de liste '0' a été remplacée par '-1' dans la table `carnets_de_liaison_classe`.<br />";
	$r_sql="UPDATE `carnets_de_liaison_eleve` SET `ids_mots`=CONCAT(SUBSTRING(`ids_mots`,1,LENGTH(`ids_mots`)-1),'-1')";
	if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible de remplacer la fin de liste '0' par '-1' dans la table `carnets_de_liaison_eleve` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="La fin de liste '0' a été remplacée par '-1' dans la table `carnets_de_liaison_eleve`.<br />";
	$r_sql="UPDATE `carnets_de_liaison_mots` SET `ids_destinataires`=CONCAT(SUBSTRING(`ids_destinataires`,1,LENGTH(`ids_destinataires`)-1),'-1')";
	if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible de remplacer la fin de liste '0' par '-1' dans la table `carnets_de_liaison_mots` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="La fin de liste '0' a été remplacée par '-1' dans la table `carnets_de_liaison_mots`.<br />";
	}

// mettre à jour les tables pour passer de la version 1.4 à la version 1.5
if(!isset($gepiSettings['carnets_de_liaison_affiche_trombines_eleves']))
	{
	if (!saveSetting('carnets_de_liaison_affiche_trombines_eleves', 'oui')) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_affiche_trombines_eleves' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_affiche_trombines_eleves' a été ajoutée à la table `setting`.<br />";
	if (!saveSetting('carnets_de_liaison_affiche_trombines_profs', 'non')) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_affiche_trombines_profs' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_affiche_trombines_profs' a été ajoutée à la table `setting`.<br />";
	}

// mettre à jour les tables pour passer de la version 1.5 à la version 1.6
if (!existe_table("carnets_de_liaison_droits"))
	{
	// nouvelle table : autorisations de compte 'autre' à rédiger des mots
	$r_sql="CREATE TABLE `carnets_de_liaison_droits` (
	`id_carnets_de_liaison_droits` int(11) NOT NULL AUTO_INCREMENT,
	`login` varchar(50) NOT NULL,
	`nom` varchar(100) NOT NULL,
	PRIMARY KEY (`id_carnets_de_liaison_droits`) )";
	if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible de créer la table `carnets_de_liaison_droits` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="La table `carnets_de_liaison_droits` a été créée.<br />";

	// mise à jour des menus
	$R_plugin=mysqli_query($mysqli, "SELECT `id` FROM `plugins` WHERE `nom`='carnets_de_liaison'");
	if ($R_plugin)
		{
		$t_id=mysqli_fetch_assoc($R_plugin);
		$id_plugin=$t_id['id'];
		$R_autorisation=mysqli_query($mysqli, "SELECT * FROM `plugins_autorisations` WHERE (`plugin_id`='".$id_plugin."' AND `user_statut`='autre' AND `fichier`='mod_plugins/carnets_de_liaison/saisie.php')"); 
		if (mysqli_num_rows($R_autorisation)==0)
			{
			// il faut mettre à jour vers la version 1.6
			$r_sql="INSERT INTO `plugins_menus` (`id`, `plugin_id`, `user_statut`, `titre_item`, `lien_item`, `description_item`) VALUES
				('', ".$id_plugin.", 'autre', 'Saisie', 'mod_plugins/carnets_de_liaison/saisie.php', 'Saisie de mots dans un carnet de liaison.'),
				('', ".$id_plugin.", 'autre', 'Consultation', 'mod_plugins/carnets_de_liaison/consultation.php', 'Consultation de carnets de liaison.')";
			if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables.="Impossible de mettre à jour la table 'plugins_menus' ".mysqli_error($mysqli)."<br />";
				else $message_maj_tables.="La table 'plugins_menus' a été mise à jour.<br />";

			$r_sql="INSERT INTO `plugins_autorisations` (`id`, `plugin_id`, `fichier`, `user_statut`, `auth`) VALUES
				('', ".$id_plugin.", 'mod_plugins/carnets_de_liaison/saisie.php', 'autre', 'V'),
				('', ".$id_plugin.", 'mod_plugins/carnets_de_liaison/consultation.php', 'autre', 'V')";
			if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables.="Impossible de mettre à jour la table 'plugins_autorisations' ".mysqli_error($mysqli)."<br />";
				else $message_maj_tables.="La table 'plugins_autorisations' a été mise à jour.<br />";
			}
		}
		else $message_maj_tables.="Echec de la mise à jour vers la version 1.6 : ".mysqli_error($mysqli)."<br />";
	}
	
	if(!isset($gepiSettings['carnets_de_liaison_notification_mail_aux_responsables']))
		{
		if (!saveSetting('carnets_de_liaison_notification_mail_aux_responsables', 'non')) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_notification_au_responsables' à la table `setting` : ".mysqli_error($mysqli)."<br />";
			else $message_maj_tables.="L'entrée 'carnets_de_liaison_notification_mail_aux_responsables' a été ajoutée à la table `setting`.<br />";
		}
	if(!isset($gepiSettings['carnets_de_liaison_email_notification']))
		{
		if (!saveSetting('carnets_de_liaison_email_notification', "nobody@".$_SERVER['SERVER_NAME'])) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_email_notification' à la table `setting` : ".mysqli_error($mysqli)."<br />";
			else $message_maj_tables.="L'entrée 'carnets_de_liaison_email_notification' a été ajoutée à la table `setting`.<br />";
		}
	if(!isset($gepiSettings['carnets_de_liaison_url_gepi']))
		{
		$t_url=parse_url($_SERVER['HTTP_REFERER']);
		$url=$t_url['scheme']."://".$t_url['host'].$gepiPath;
		if (!saveSetting('carnets_de_liaison_url_gepi',$url)) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_url_gepi' à la table `setting` : ".mysqli_error($mysqli)."<br />";
			else $message_maj_tables.="L'entrée 'carnets_de_liaison_url_gepi' a été ajoutée à la table `setting`.<br />";
		}

// mettre à jour les tables pour passer de la version 1.6 à la version 1.6.1
	if(!isset($gepiSettings['carnets_de_liaison_max_mails_notification']))
		{
		if (!saveSetting('carnets_de_liaison_max_mails_notification',60)) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_max_mails_notification' à la table `setting` : ".mysqli_error($mysqli)."<br />";
			else $message_maj_tables.="L'entrée 'carnets_de_liaison_max_mails_notification' a été ajoutée à la table `setting`.<br />";
		}

// mettre à jour les tables pour passer de la version 1.6.2 à la version 1.7.0
	$R_plugin=mysqli_query($mysqli, "SELECT `id` FROM `plugins` WHERE `nom`='carnets_de_liaison'");
	if ($R_plugin)
		{
		$t_id=mysqli_fetch_assoc($R_plugin);
		$id_plugin=$t_id['id'];
		$R_autorisation=mysqli_query($mysqli, "SELECT * FROM `plugins_autorisations` WHERE (`plugin_id`='".$id_plugin."' AND `user_statut`='eleve' AND `fichier`='mod_plugins/carnets_de_liaison/index_eleve.php')"); 
		if (mysqli_num_rows($R_autorisation)==0)
			{
			// il faut mettre à jour vers la version 1.7.0
			$r_sql="INSERT INTO `plugins_menus` (`id`, `plugin_id`, `user_statut`, `titre_item`, `lien_item`, `description_item`) VALUES
				('', ".$id_plugin.", 'eleve', 'Consultation', 'mod_plugins/carnets_de_liaison/index_eleve.php', 'Consultation du carnet de liaison.')";
			if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables.="Impossible de mettre à jour la table 'plugins_menus' ".mysqli_error($mysqli)."<br />";
				else $message_maj_tables.="La table 'plugins_menus' a été mise à jour.<br />";

			$r_sql="INSERT INTO `plugins_autorisations` (`id`, `plugin_id`, `fichier`, `user_statut`, `auth`) VALUES
				('', ".$id_plugin.", 'mod_plugins/carnets_de_liaison/index_eleve.php', 'eleve', 'V')";
			if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables.="Impossible de mettre à jour la table 'plugins_autorisations' ".mysqli_error($mysqli)."<br />";
				else $message_maj_tables.="La table 'plugins_autorisations' a été mise à jour.<br />";
			if (!saveSetting('carnets_de_liaison_consultation_eleve', 'non')) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_consultation_eleve' à la table `setting` : ".mysqli_error($mysqli)."<br />";
				else $message_maj_tables.="L'entrée 'carnets_de_liaison_consultation_eleve' a été ajoutée à la table `setting`.<br />";
			}
		}
		else $message_maj_tables.="Echec de la mise à jour vers la version 1.7.0 : ".mysqli_error($mysqli)."<br />";

// mettre à jour les tables pour passer de la version 1.7.0 à la version 1.8.0
if(!isset($gepiSettings['carnets_de_liaison_notification_sms_aux_responsables']))
	{
	// remplacer dans la table Settings "carnets_de_liaison_notification_aux_responsables" par "carnets_de_liaison_notification_mail_aux_responsables"
	if (!saveSetting('carnets_de_liaison_notification_mail_aux_responsables',getSettingValue("carnets_de_liaison_notification_aux_responsables"))) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_notification_mail_aux_responsables' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_notification_mail_aux_responsables' a été ajoutée à la table `setting`.<br />";
	if (!deleteSetting("carnets_de_liaison_notification_aux_responsables")) $message_maj_tables="Impossible de supprimer l'entrée 'carnets_de_liaison_notification_aux_responsables' de la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_notification_aux_responsables' a été supprimée de la table `setting`.<br />";

	// nouvelles entrées dans la table Settings
	if (!saveSetting('carnets_de_liaison_notification_sms_aux_responsables','non')) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_notification_sms_aux_responsables' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_notification_sms_aux_responsables' a été ajoutée à la table `setting`.<br />";

	if (!saveSetting('carnets_de_liaison_prestataire_sms','')) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_prestataire_sms' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_prestataire_sms' a été ajoutée à la table `setting`.<br />";

	if (!saveSetting('carnets_de_liaison_login_sms','')) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_login_sms' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_login_sms' a été ajoutée à la table `setting`.<br />";

	if (!saveSetting('carnets_de_liaison_password_sms','')) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_password_sms' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_password_sms' a été ajoutée à la table `setting`.<br />";

	if (!saveSetting('carnets_de_liaison_identite_sms',getSettingValue('gepiSchoolName'))) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_identite_sms' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_identite_sms' a été ajoutée à la table `setting`.<br />";

	if (!saveSetting('carnets_de_liaison_max_sms_notification',60)) $message_maj_tables="Impossible d'ajouter l'entrée 'carnets_de_liaison_max_sms_notification' à la table `setting` : ".mysqli_error($mysqli)."<br />";
		else $message_maj_tables.="L'entrée 'carnets_de_liaison_max_sms_notification' a été ajoutée à la table `setting`.<br />";
	}

// mettre à jour les tables pour passer de la version 1.8.0 à la version 1.8.1
if(isset($gepiSettings['carnets_de_liaison_prestataire_sms']))
	{
	$OK=TRUE;
	$tab_transfert_noms_prestataires=array('pluriware.fr' => 'PLURIWARE','tm4b.com' => 'TM4B','123-SMS.net' => '123-SMS');
	switch (getSettingValue('carnets_de_liaison_prestataire_sms')) {
		case 'pluriware.fr' :
			$OK=saveSetting('sms_prestataire','PLURIWARE');
			break;
		case 'tm4b.com' :
			$OK=saveSetting('sms_prestataire','TM4B');
			break;
		case '123-SMS.net' :
			$OK=saveSetting('sms_prestataire','123-SMS');
			break;
		default :
			$OK=saveSetting('sms_prestataire','');
	}
	$OK=$OK && saveSetting('sms_username',getSettingValue('carnets_de_liaison_login_sms')) && saveSetting('sms_password',getSettingValue('carnets_de_liaison_password_sms')) && saveSetting('sms_identite',getSettingValue('carnets_de_liaison_identite_sms'));
	// on supprime les entrées devenues inutiles
	deleteSetting('carnets_de_liaison_prestataire_sms'); deleteSetting('carnets_de_liaison_login_sms'); deleteSetting('carnets_de_liaison_password_sms'); deleteSetting('carnets_de_liaison_identite_sms');
	if (!$OK) $message_maj_tables="Version 1.8.0 vers version 1.8.1 : les données prestattaires SMS n'ont pas été correctement enregistrées.<br />";
		else $message_maj_tables.="Version 1.8.0 vers version 1.8.1 : les données prestattaires SMS ont été correctement enregistrées.<br />";
	}

// l'utilisateur est-il autorisé à exécuter ce script ?
include("verification_autorisations.inc.php");

// l'envoi de mail est-il activé ?
$carnets_de_liaison_mail=getSettingValue("carnets_de_liaison_mail");

// nom de domaine fictif pour l'envoi de mail anonyme
$carnets_de_liaison_email_notification=getSettingValue("carnets_de_liaison_email_notification");

// URL GEPI
$carnets_de_liaison_url_gepi=getSettingValue("carnets_de_liaison_url_gepi");

// les rédacteurs peuvent-ils envoyer un mail de notification ?
$carnets_de_liaison_notification_mail_aux_responsables=getSettingValue("carnets_de_liaison_notification_mail_aux_responsables");

// max de mails de notification qu'il est possible d'envoyer
$carnets_de_liaison_max_mails_notification=intval(getSettingValue("carnets_de_liaison_max_mails_notification"));

// les rédacteurs peuvent-ils envoyer un sms de notification ?
$carnets_de_liaison_notification_sms_aux_responsables=getSettingValue("carnets_de_liaison_notification_sms_aux_responsables");

// max de sms de notification qu'il est possible d'envoyer
$carnets_de_liaison_max_sms_notification=intval(getSettingValue("carnets_de_liaison_max_sms_notification"));

// les rédacteurs peuvent-ils joindre un fichier aux mots ?
$carnets_de_liaison_documents=getSettingValue("carnets_de_liaison_documents");

// les responsables peuvent-ils rédiger des mots ?
$carnets_de_liaison_saisie_responsable=getSettingValue("carnets_de_liaison_saisie_responsable");

// les responsables peuvent-ils répondre aux mots ?
$carnets_de_liaison_reponses_responsables=getSettingValue("carnets_de_liaison_reponses_responsables");

// les éléves peuvent-ils consulter leur carnet ?
$carnets_de_liaison_consultation_eleve=getSettingValue("carnets_de_liaison_consultation_eleve");

// les photos des élèves sont-elles affichées ?
$carnets_de_liaison_affiche_trombines_eleves=getSettingValue("carnets_de_liaison_affiche_trombines_eleves");

// les photos des profs sont-elles affichées ?
$carnets_de_liaison_affiche_trombines_profs=getSettingValue("carnets_de_liaison_affiche_trombines_profs");


// a priori pas d'erreur
$message_d_erreur="";
$message_bilan="";

// modification de la valeur de "carnets_de_liaison_mail"
if (isset($_POST['valider_mail']))
	{
	if (isset($_POST['activer_mail']) && ($_POST['activer_mail']=="oui")) 
		$carnets_de_liaison_mail="oui";
		else
		$carnets_de_liaison_mail="non";
	if (!saveSetting('carnets_de_liaison_mail',$carnets_de_liaison_mail)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}


// modification de la valeur de "carnets_de_liaison_email_notification"
if (isset($_POST['valider_email_notification']))
	{
	$envoi_email_notification=$_POST['envoi_email_notification'];
	if (!saveSetting('carnets_de_liaison_email_notification',$envoi_email_notification)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}

// modification de la valeur de "carnets_de_liaison_url_gepi"
if (isset($_POST['valider_url_gepi']))
	{
	$carnets_de_liaison_url_gepi=$_POST['carnets_de_liaison_url_gepi'];
	if (!saveSetting('carnets_de_liaison_url_gepi',$carnets_de_liaison_url_gepi)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}

// modification de la valeur de "carnets_de_liaison_notification_mail_aux_responsables"
if (isset($_POST['valider_notification_mail']))
	{
	if (isset($_POST['activer_notification_mail']) && ($_POST['activer_notification_mail']=="oui")) 
		$carnets_de_liaison_notification_mail_aux_responsables="oui";
		else
		$carnets_de_liaison_notification_mail_aux_responsables="non";
	if (!saveSetting('carnets_de_liaison_notification_mail_aux_responsables',$carnets_de_liaison_notification_mail_aux_responsables)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}

// modification de la valeur de "carnets_de_liaison_max_mails_notification"
if (isset($_POST['valider_max_mails_notification']))
	{
	$carnets_de_liaison_max_mails_notification=intval($_POST['carnets_de_liaison_max_mails_notification']);
	if (!saveSetting('carnets_de_liaison_max_mails_notification',$carnets_de_liaison_max_mails_notification)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}

// modification de la valeur de "carnets_de_liaison_notification_sms_aux_responsables"
if (isset($_POST['valider_notification_sms']))
	{
	if (isset($_POST['activer_notification_sms']) && ($_POST['activer_notification_sms']=="oui")) 
		$carnets_de_liaison_notification_sms_aux_responsables="oui";
		else
		$carnets_de_liaison_notification_sms_aux_responsables="non";
	if (!saveSetting('carnets_de_liaison_notification_sms_aux_responsables',$carnets_de_liaison_notification_sms_aux_responsables)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}

// modification de la valeur de "carnets_de_liaison_max_sms_notification"
if (isset($_POST['valider_max_sms_notification']))
	{
	$carnets_de_liaison_max_sms_notification=intval($_POST['max_sms_notification']);
	if (!saveSetting('carnets_de_liaison_max_sms_notification',$carnets_de_liaison_max_sms_notification)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}

// modification de la valeur de "carnets_de_liaison_documents"
if (isset($_POST['valider_documents']))
	{
	if (isset($_POST['activer_documents']) && ($_POST['activer_documents']=="oui")) 
		$carnets_de_liaison_documents="oui";
		else
		$carnets_de_liaison_documents="non";

	if ((!acces_dossier_documents()) && ($carnets_de_liaison_documents=="oui"))
		{
		$message_d_erreur.="Le dossier mod_plugins/carnets_de_liaison/documents n'est pas accessible en écriture.";
		$carnets_de_liaison_documents="non";
		}

	if (!saveSetting('carnets_de_liaison_documents',$carnets_de_liaison_documents)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}

// ajout d'un compte 'autre' autorisé à rédiger des mots
if (isset($_POST['ajout_compte_autre']) && ($_POST['login']!=""))
	{
	$r_sql="SELECT * FROM `carnets_de_liaison_droits` WHERE login='".$_POST['login']."'";
	$R_utilisateurs=mysqli_query($mysqli, $r_sql);
	if (mysqli_num_rows($R_utilisateurs)>0)
		{
		//$r_sql="SELECT login,nom,prenom FROM `utilisateurs` WHERE login='".$_POST['login']."'";
		$utilisateur=mysqli_fetch_assoc($R_utilisateurs);
		$message_d_erreur.="<br />Le compte <b>".$utilisateur['login']."</b> (".$utilisateur['nom'].")</b> est déjà autorisé à rédiger des mots.";
		}
		else
		{
		$r_sql="SELECT * FROM `utilisateurs` WHERE login='".$_POST['login']."' LIMIT 1";
		$R_utilisateurs=mysqli_query($mysqli, $r_sql);
		if (mysqli_num_rows($R_utilisateurs)<=0)
			{
			$message_d_erreur.="<br />Le login <b>".$_POST['login']."</b> n'existe pas.";
			}
			else
			{
			$utilisateur=mysqli_fetch_assoc($R_utilisateurs);
			$r_sql="INSERT INTO `carnets_de_liaison_droits` VALUES ('','".$_POST['login']."','".$utilisateur['prenom']." ".$utilisateur['nom']."')";
			if (!mysqli_query($mysqli, $r_sql)) $message_d_erreur.="<br />La commande a échoué à ajouter l'autorisation de rédiger des mots pour le login <b>".$_POST['login']."</b> : ".mysqli_error($mysqli)."<br />";
			}
		}
	}

// suppression d'un compte 'autre' autorisé à rédiger des mots
if (isset($_GET['id_compte_autre_a_supprimer']))
	{
	$r_sql="SELECT * FROM `carnets_de_liaison_droits` WHERE id_carnets_de_liaison_droits='".$_GET['id_compte_autre_a_supprimer']."' LIMIT 1";
	if ($utilisateur=mysqli_fetch_assoc(mysqli_query($mysqli, $r_sql)))
		{
		$r_sql="DELETE FROM `carnets_de_liaison_droits` WHERE id_carnets_de_liaison_droits='".$_GET['id_compte_autre_a_supprimer']."'";
		if (!mysqli_query($mysqli, $r_sql)) $message_d_erreur.="<br />La commande a échoué à retirer l'autorisation de rédiger des mots pour le login <b>".$utilisateur['login']."</b> : ".mysqli_error($mysqli)."<br />";
		}
	}

// suppression de tous les comptes 'autre' autorisés à saisir
if (isset($_POST['supprimer_tous_comptes_autre_autorises']))
	{
	$r_sql="DELETE FROM `carnets_de_liaison_droits`";
	if (!mysqli_query($mysqli, $r_sql)) $message_d_erreur.="<br />La commande a échoué à retirer toutes les autorisations de rédiger des mots pour les comptes 'autre' : ".mysqli_error($mysqli)."<br />";
	}

// modification de la valeur de "carnets_de_liaison_saisie_responsable"
if (isset($_POST['valider_saisie_responsable']))
	{
	if (isset($_POST['activer_saisie_responsable']) && ($_POST['activer_saisie_responsable']=="oui")) 
		$carnets_de_liaison_saisie_responsable="oui";
		else
		$carnets_de_liaison_saisie_responsable="non";
	if (!saveSetting('carnets_de_liaison_saisie_responsable',$carnets_de_liaison_saisie_responsable)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}

// modification de la valeur de "carnets_de_liaison_reponses_responsables"
if (isset($_POST['valider_reponses_responsables']))
	{
	if (isset($_POST['activer_reponses_responsables']) && ($_POST['activer_reponses_responsables']=="oui")) 
		$carnets_de_liaison_reponses_responsables="oui";
		else
		$carnets_de_liaison_reponses_responsables="non";
	if (!saveSetting('carnets_de_liaison_reponses_responsables',$carnets_de_liaison_reponses_responsables)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}

// modification de la valeur de "carnets_de_liaison_consultation_eleve"
if (isset($_POST['valider_consulation_eleve']))
	{
	if (isset($_POST['activer_consulation_eleve']) && ($_POST['activer_consulation_eleve']=="oui")) 
		$carnets_de_liaison_consultation_eleve="oui";
		else
		$carnets_de_liaison_consultation_eleve="non";
	if (!saveSetting('carnets_de_liaison_consultation_eleve',$carnets_de_liaison_consultation_eleve)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}


// modification de la valeur de "carnets_de_liaison_affiche_trombines_eleves"
if (isset($_POST['valider_affiche_trombines_eleves']))
	{
	if (isset($_POST['activer_affiche_trombines_eleves']) && ($_POST['activer_affiche_trombines_eleves']=="oui")) 
		$carnets_de_liaison_affiche_trombines_eleves="oui";
		else
		$carnets_de_liaison_affiche_trombines_eleves="non";
	if (!saveSetting('carnets_de_liaison_affiche_trombines_eleves',$carnets_de_liaison_affiche_trombines_eleves)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}

// modification de la valeur de "carnets_de_liaison_affiche_trombines_profs"
if (isset($_POST['valider_affiche_trombines_profs']))
	{
	if (isset($_POST['activer_affiche_trombines_profs']) && ($_POST['activer_affiche_trombines_profs']=="oui")) 
		$carnets_de_liaison_affiche_trombines_profs="oui";
		else
		$carnets_de_liaison_affiche_trombines_profs="non";
	if (!saveSetting('carnets_de_liaison_affiche_trombines_profs',$carnets_de_liaison_affiche_trombines_profs)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	}



// sauvegarde des documents joints
if (isset($_POST['valider_sauvegarde']))
	{
	$retour_sauvegarde=sauvegarde_documents();
	if ($retour_sauvegarde!="") $message_d_erreur.="Echec de la sauvegarde des documents joints : ".$retour_sauvegarde;
	else $message_bilan="La sauvegarde a été correctement effectuée.";
	}

// restauration d'une sauvegarde
if (isset($_POST['restaurer_sauvegarde']))  
	{
	$retour_restauration=restauration_documents();
	if ($retour_restauration!="") $message_d_erreur.="Echec de la restauration des documents joints : ".$retour_restauration;
	else $message_bilan="La restauration a été correctement effectuée.";
	}

// suppressions des carnets
if (isset($_POST['valider_suppression']))
	{
	if (!acces_dossier_documents()) 
		{
		$message_d_erreur.="Le dossier mod_plugins/carnets_de_liaison/documents n'est pas accessible en écriture, la suppression des carnets a echoué.<br />";
		}
		else
		{
		// lecture des requêtes de la section "instalation" de plugin.xml
		$plugin_xml = simplexml_load_file('plugin.xml');
		foreach ($plugin_xml->installation->requetes->requete as $une_requete)
			{
			$une_requete=trim($une_requete);
			if (stripos($une_requete,"CREATE")!== false)
			if (strtoupper(substr($une_requete,0,6))=="CREATE")
				{
				// on suppose que les requêtes sont toutes de la forme "CREATE TABLE `table`...
				$ind_debut=strpos($une_requete,"`");
				$ind_fin=strpos($une_requete,"`",$ind_debut+1);
				$table=substr($une_requete,$ind_debut,$ind_fin-$ind_debut+1);
				$r_sql="DROP TABLE ".$table;
				// on supprime la table (sans TRUNCATE)
				if (!mysqli_query($mysqli, $r_sql)) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli).",  la suppression de la table ".$table." a echouée.<br />";
				// et on la recrée
				if (!mysqli_query($mysqli, $une_requete)) $message_d_erreur.="Erreur MySQL : <br />".$une_requete." => ".mysqli_error($mysqli).",  la création de la table ".$table." a echoué.<br />";
				}
			}
		// suppression des documents joints
		$documents=opendir("documents/");
		while ($fichier = readdir($documents))
			{
			if(is_file("documents/".$fichier) && ($fichier!="index.html") && ($fichier!="index.php"))
				if (!unlink("documents/".$fichier))
					$message_d_erreur.="Impossible de supprimer le fichier mod_plugins/carnets_de_liaison/documents/".$fichier."<br />";
			}
		// suppression des messages
		$sql="DELETE FROM `messages`WHERE `texte` REGEXP '<!-- carnets de liaison -->'";
		if (!mysqli_query($mysqli, $sql)) $message_d_erreur.="Impossible de supprimer les messages générés par le plugin : ".mysqli_error($mysqli)."<br />";
		}
	}

//**************** EN-TETE *****************
$style_specifique="styles";
$titre_page = "Carnets de liaison : administration";
unset($_SESSION['ariane']);
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<p class=bold><a href="../../accueil.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/>Retour à l'accueil</a>
 | <a href="saisie.php">Saisie</a>
 | <a href="consultation.php">Consultation</a>

</p>


<?php
if ($message_d_erreur!="")
	{
?>
	<script type="text/javascript">
	<!--
	alert("Erreur (voir message au début de la page)");
	-->
	</script>
	<p style="color: red; margin-left: 20px;"><?php echo $message_d_erreur; ?></p>
<?php
	}

if ($message_bilan!="")
	{
?>
	<script type="text/javascript">
	<!--
	alert("<?php echo $message_bilan; ?>");
	-->
	</script>
	<p style="margin-left: 20px;"><?php echo $message_bilan; ?></p>
<?php
	}
?>

<h2>Administration du plugin 'carnets_de_liaison'</h2>
<br />

<div id="conteneur" style="margin: auto; width: 800px;">

<?php
if ($message_maj_tables!="")
	{
?>
	<h3 style="margin-left: 20px;">Mise à jour du plugin</h3>
	<p style="margin-left: 40px;"><?php echo $message_maj_tables; ?></p>
<?php
	}
?>

<h3 style="margin-left: 20px;">

Documentation du plugin : <a href="documentation.pdf" target="_blank"><button>Consulter la documentation</button></a>

<hr />


<form action="admin.php#mail" name="mail" method="post"><a name="mail"></a>
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Activer l'envoi de courriels&nbsp;:&nbsp;
<input name="activer_mail" value="oui" type="checkbox" <?php if ($carnets_de_liaison_mail=="oui") echo "checked=\"checked\""; ?> >
&nbsp;<button type="submit" value="ok" name="valider_mail">Valider</button>
<?php $mess=($carnets_de_liaison_mail=="oui")?"activé":"désactivé"; ?>
<p style="margin-left: 20px; font-style:italic;">Si cette option est désactivée le plugin ne génère aucun courriel.<br />
(état courant : l'envoi de courriels est <?php echo $mess; ?>)</p>
</form>
<br />

<?php
if(getSettingValue('carnets_de_liaison_mail')=="oui")
	{
?>
	<form action="admin.php#email_notification" name="email_notification" method="post"><a name="email_notification"></a>
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	Adresse expéditeur fictive pour l'envoi des courriels :&nbsp;
	<input type="text" style="width: 200px" name="envoi_email_notification" value="<?php echo $carnets_de_liaison_email_notification; ?>">
	&nbsp;<button type="submit" value="ok" name="valider_email_notification">Valider</button>
	<p style="margin-left: 20px; font-style:italic;">Cette adresse est celle du champ "De :" (ou "From :") des courriels ; elle sera utilisée pour l'envoi des notifications et des courriels adressés aux responsables lorsque le rédacteur ne souhaite pas afficher son adresse (Gérer mon compte) ; elle doit être syntaxiquement valide ; certains serveurs SMTP refusent de transférer les courriels si le nom de domaine de cette adresse est fictif.</p>
	</form>
	<br />
	<form action="admin.php#url_gepi" name="affiche_notifications" method="post"><a name="url_gepi"></a>
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	Adresse internet de GEPI&nbsp;:&nbsp;
	<input type="text" style="width: 300px" name="carnets_de_liaison_url_gepi" value="<?php echo $carnets_de_liaison_url_gepi; ?>">
	&nbsp;<button type="submit" value="ok" name="valider_url_gepi">Valider</button>
	<?php $mess=($carnets_de_liaison_notification_mail_aux_responsables=="oui")?"est":"n'est pas"; ?>
	<p style="margin-left: 20px; font-style:italic;">Cette adresse se trouvera au bas de chaque mail afin que le destinataire puisse accéder rapidement à GEPI.</p>
	</form>
	<br />
	<form action="admin.php#notifications" name="affiche_notifications" method="post"><a name="notifications"></a>
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	Autoriser l'envoi de courriels notifiant la rédaction d'un mot&nbsp;:&nbsp;
	<input name="activer_notification_mail" value="oui" type="checkbox" <?php if ($carnets_de_liaison_notification_mail_aux_responsables=="oui") echo "checked=\"checked\""; ?> >
	&nbsp;<button type="submit" value="ok" name="valider_notification_mail">Valider</button>
	<?php $mess=($carnets_de_liaison_notification_mail_aux_responsables=="oui")?"est":"n'est pas"; ?>
	<p style="margin-left: 20px; font-style:italic;">(état courant : l'envoi de courriels de notification aux destinataires <?php echo $mess; ?> autorisé)</p>
	</form>
	<br />
	<?php
	if (getSettingValue('carnets_de_liaison_notification_mail_aux_responsables')=="oui")
		{
	?>
		<form action="admin.php#max_mails_notifications" name="max_mails_notification" method="post"><a name="max_mails_notifications"></a>
		<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		Nombre maximun de courriels de notification pouvant être envoyés &nbsp;:&nbsp;
		<input name="carnets_de_liaison_max_mails_notification" value="<?php echo $carnets_de_liaison_max_mails_notification; ?>" type="text" size="4">
		&nbsp;<button type="submit" value="ok" name="valider_max_mails_notification">Valider</button>
		<p style="margin-left: 20px; font-style:italic;">Si le nombre de courriels de notification à envoyer est supérieur à ce nombre maximum l'envoi est alors annulé pour éviter d'être assimilé à du SPAM.</p>
		</form>
		<br />
	<?php
		}
	?>
<?php
	}
?>


<?php
if (getSettingAOui('autorise_envoi_sms'))
	{
?>
<hr />
	<form action="admin.php#envoi_sms" name="envoi_sms" method="post"><a name="envoi_sms"></a>
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	Autoriser l'envoi de SMS notifiant la rédaction d'un mot&nbsp;:&nbsp;
	<input name="activer_notification_sms" value="oui" type="checkbox" <?php if ($carnets_de_liaison_notification_sms_aux_responsables=="oui") echo "checked=\"checked\""; ?> >
	&nbsp;<button type="submit" value="ok" name="valider_notification_sms">Valider</button>
	<?php $mess=($carnets_de_liaison_notification_sms_aux_responsables=="oui")?"est":"n'est pas"; ?>
	<p style="margin-left: 20px; font-style:italic;">(état courant : l'envoi de SMS de notification aux destinataires <?php echo $mess; ?> autorisé)</p>
	</form>
	<?php
	if (getSettingValue('carnets_de_liaison_notification_sms_aux_responsables')=="oui")
		{
		include("../../lib/envoi_SMS.inc.php");
	?>
		<form action="admin.php#max_sms_notifications" name="max_sms_notification" method="post"><a name="max_sms_notifications"></a>
		<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		Nombre maximun de SMS de notification pouvant être envoyés &nbsp;:&nbsp;
		<input name="max_sms_notification" value="<?php echo $carnets_de_liaison_max_sms_notification; ?>" type="text" size="4">
		&nbsp;<button type="submit" value="ok" name="valider_max_sms_notification">Valider</button>
		<p style="margin-left: 20px; font-style:italic;">Si le nombre de SMS de notification à envoyer est supérieur à ce nombre l'envoi est alors annulé.</p>
		</form>
		<br /><p style="font-style:italic;">Vérifier les identifiants de connexion au prestataire dans <a href="../../gestion/param_gen.php#config_envoi_sms">Paramètres/Configuration générale</a></p>
<?php
		}
	}
?>

	
<hr />

<form action="admin.php#documents" name="documents" method="post"><a name="documents"></a>
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Autoriser les rédacteurs à joindre un fichier aux mots&nbsp;:&nbsp;
<input name="activer_documents" value="oui" type="checkbox" <?php if ($carnets_de_liaison_documents=="oui") echo "checked=\"checked\""; ?> >
&nbsp;<button type="submit" value="ok" name="valider_documents">Valider</button>
<?php $mess=($carnets_de_liaison_documents=="oui")?"sont":"ne sont pas"; ?>
<p style="margin-left: 20px; font-style:italic;">(état courant : les rédacteurs <?php echo $mess; ?> autorisés à joindre un fichier)</p>
</form>
<br />

Autoriser un compte 'autre' à rédiger des mots<a name="autorisations_comptes_autre"></a>
<form style="margin-left: 20px; font-size:medium;" method="post" action="admin.php#autorisations_comptes_autre" name="choix_utilisateur">
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	Sélectionner un login utilisateur :&nbsp;
	<select style="width: 200px;" name="login">
		<optgroup>
		<option></option>
	<?php
	$r_sql="SELECT login,nom,prenom FROM `utilisateurs` WHERE `statut`='autre' ORDER BY login";
	$R_utilisateurs=mysqli_query($mysqli, $r_sql);
	$initiale_courante=0;
	while($utilisateur=mysqli_fetch_assoc($R_utilisateurs))
		{
		$nom=strtoupper($utilisateur['nom'])." ".$utilisateur['prenom'];
		$initiale=ord(strtoupper($utilisateur['login']));
		if ($initiale!=$initiale_courante)
			{
			$initiale_courante=$initiale;
			echo "\t</optgroup><optgroup label=\"".chr($initiale)."\">";
			}
		?>
		<option value="<?php echo $utilisateur['login']; ?>"><?php echo $utilisateur['login']." (".$nom.")"; ?></option>
		<?php
		}
	?>
		</optgroup>
	</select>
	<input name="ajout_compte_autre" value="Ajouter cet utilisateur" type="submit">
</form>

<?php
$r_sql="SELECT * FROM `carnets_de_liaison_droits` ORDER BY login";
$R_utilisateurs=mysqli_query($mysqli, $r_sql);
if (mysqli_num_rows($R_utilisateurs)>0)
	{
?>
<div style="margin-left: 20px; font-size:medium;">Liste des comptes 'autre' autorisés à  rédiger des mots :</div>
<table class="table" style="padding-left: 60px;">
<tbody style="font-size:medium;">
	<tr>
		<td style="text-align: center; padding: 0px 20px; border-style:solid; border-width: 1px; border-color: #999999;">Login</td>
		<td style="text-align: center; padding: 0px 20px; border-style:solid; border-width: 1px; border-color: #999999;">Nom</td>
		<td style="text-align: center; padding: 0px 20px;"></td>
	</tr>
<?php
while($utilisateur=mysqli_fetch_assoc($R_utilisateurs))
	{
?>
	<tr>
		<td><?php echo $utilisateur['login']; ?></td>
		<td><?php echo $utilisateur['nom']; ?></td>
		<td style="text-align: center;"><a href="admin.php?id_compte_autre_a_supprimer=<?php echo $utilisateur['id_carnets_de_liaison_droits']; echo function_exists("add_token_in_url")?add_token_in_url(false):""; ?>#autorisations_comptes_autre">RETIRER L'AUTORISATION</a></td>
	</tr>
<?php
	}
?>
</tbody>
</table>
<form style="margin-left: 20px; font-size:medium;" method="post" action="admin.php#autorisations_comptes_autre" name="retirer_autorisations">
	Retirer toutes les autorisations
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	<input name="supprimer_tous_comptes_autre_autorises" value="Retirer toutes les autorisations" type="submit">
	<br />
</form>
<?php
	}
?>
<br />

<form action="admin.php#saisie_responsable" name="saisie_responsable" method="post"><a name="saisie_responsable"></a>
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Autoriser les responsables à rédiger des mots&nbsp;:&nbsp;
<input name="activer_saisie_responsable" value="oui" type="checkbox" <?php if ($carnets_de_liaison_saisie_responsable=="oui") echo "checked=\"checked\""; ?> >
&nbsp;<button type="submit" value="ok" name="valider_saisie_responsable">Valider</button>
<?php $mess=($carnets_de_liaison_saisie_responsable=="oui")?"sont":"ne sont pas"; ?>
<p style="margin-left: 20px; font-style:italic;">(état courant : les responsables <?php echo $mess; ?> autorisés à rédiger des mots)</p>
</form>
<br/>

<form action="admin.php#reponses_responsables" name="reponses_responsables" method="post"><a name="reponses_responsables"></a>
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Autoriser les responsables à répondre aux mots&nbsp;:&nbsp;
<input name="activer_reponses_responsables" value="oui" type="checkbox" <?php if ($carnets_de_liaison_reponses_responsables=="oui") echo "checked=\"checked\""; ?> >
&nbsp;<button type="submit" value="ok" name="valider_reponses_responsables">Valider</button>
<?php $mess=($carnets_de_liaison_reponses_responsables=="oui")?"sont":"ne sont pas"; ?>
<p style="margin-left: 20px; font-style:italic;">(état courant : les responsables <?php echo $mess; ?> autorisés à répondre aux mots)</p>
</form>
<br/>

<form action="admin.php#reponses_responsables" name="consulation_eleve" method="post"><a name="reponses_responsables"></a>
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Autoriser les élèves à consulter leur carnet de liaison&nbsp;:&nbsp;
<input name="activer_consulation_eleve" value="oui" type="checkbox" <?php if ($carnets_de_liaison_consultation_eleve=="oui") echo "checked=\"checked\""; ?> >
&nbsp;<button type="submit" value="ok" name="valider_consulation_eleve">Valider</button>
<?php $mess=($carnets_de_liaison_consultation_eleve=="oui")?"sont":"ne sont pas"; ?>
<p style="margin-left: 20px; font-style:italic;">(état courant : les élèves <?php echo $mess; ?> autorisés à consulter leur carnet)</p>
</form>
<br/>

<?php
if(getSettingAOui('active_module_trombinoscopes'))
	{
?>
	<form action="admin.php#affiche_trombines_eleves" name="affiche_trombines_eleves" method="post"><a name="affiche_trombines_eleves"></a>
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	Afficher les photos des élèves&nbsp;:&nbsp;
	<input name="activer_affiche_trombines_eleves" value="oui" type="checkbox" <?php if ($carnets_de_liaison_affiche_trombines_eleves=="oui") echo "checked=\"checked\""; ?> >
	&nbsp;<button type="submit" value="ok" name="valider_affiche_trombines_eleves">Valider</button>
	<?php $mess=($carnets_de_liaison_affiche_trombines_eleves=="oui")?"sont":"ne sont pas"; ?>
	<p style="margin-left: 20px; font-style:italic;">(état courant : les photos des élèves <?php echo $mess; ?> affichées)</p>
	</form>
	<br />
<?php
	}
?>

<?php
if(getSettingAOui('active_module_trombino_pers'))
	{
?>
	<form action="admin.php#affiche_trombines_profs" name="affiche_trombines_profs" method="post"><a name="affiche_trombines_profs"></a>
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	Afficher les photos des professeurs&nbsp;:&nbsp;
	<input name="activer_affiche_trombines_profs" value="oui" type="checkbox" <?php if ($carnets_de_liaison_affiche_trombines_profs=="oui") echo "checked=\"checked\""; ?> >
	&nbsp;<button type="submit" value="ok" name="valider_affiche_trombines_profs">Valider</button>
	<?php $mess=($carnets_de_liaison_affiche_trombines_profs=="oui")?"sont":"ne sont pas"; ?>
	<p style="margin-left: 20px; font-style:italic;">(état courant : les photos des professeurs <?php echo $mess; ?> affichées)</p>
	</form>
	<br />
<?php
	}
?>

<hr />

<form action="test_incoherences.php">
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Cohérence des données&nbsp;:&nbsp;<button type="submit" value="ok"> Lancer la vérification </button>
<p style="margin-left: 20px; font-style:italic;">(pour que la consultation de carnets soit rapide les données sont redondantes, et donc un incident, très improbable, peut éventuellement corrompre la cohérence entre les tables)</p>
</form>
<br />

<form action="admin.php#sauvegarde" name="sauvegarde" method="post"><a name="sauvegarde"></a>
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Sauvegarde des documents joints aux mots&nbsp;:&nbsp;
<button type="submit" value="ok" name="valider_sauvegarde">Sauvegarder</button>
<p style="margin-left: 20px; font-style:italic;">Après sauvegarde le fichier ZIP contenant les documents joints est à télécharger<br />dans <a href="../../gestion/accueil_sauve.php">"Fichiers de restauration"</a> du module "Sauvegardes".</p>
</form>
<br />

<form method="post" action="admin.php#restauration" name="restauration" enctype="multipart/form-data"><a name="restauration"></a>
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Restaurer les documents joints aux mots à partir d'une sauvegarde :
<p style="margin-left: 20px;">
Nom du fichier de sauvegarde : <input type="file" name="fichier_sauvegarde" title="Nom du fichier de sauvegarde">
<button type="submit" value="ok" name="restaurer_sauvegarde">Restaurer</button>
</p>
<p style="margin-left: 20px; font-style:italic;">La taille maximale d'un fichier pouvant être téléchargé vers le serveur est de <b><?php echo ini_get('upload_max_filesize');?></b>,<br />mais il est possible de procéder à la restauration en fragmentant la sauvegarde en plusieurs <br />fichiers de structure identique (documents/* ou documents/rne/*).</p>
</form>
<br />

<form action="admin.php#suppression" name="suppression" method="post" onSubmit="return confirm('Lancer la suppression des carnets ?');"><a name="suppression"></a>
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Suppression des carnets&nbsp;:&nbsp;
<button type="submit" value="ok" name="valider_suppression">Supprimer</button>
<p style="margin-left: 20px; font-style:italic;"><b>Attention !</b> cela supprimera définitivement tous les carnets (mots et documents joints),<br />à utiliser uniquement pour initialiser les données pour une nouvelle année scolaire.</p>
</form>

<hr />
</h3>
</div>
<?php
include("../../lib/footer.inc.php");
?>