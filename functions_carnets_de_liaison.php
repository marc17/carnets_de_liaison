<?php

// l'utilisateur a-t-il accès au script $chemin_script ?
function calcul_autorisation_carnets_de_liaison($login,$chemin_script)
	{
	global $mysqli;
	$script=basename($chemin_script);
	switch ($script)
		{
		case "index.php":
			// uniquement les responsables
			return ($_SESSION['statut']=="responsable");
			break;
		case "index_eleve.php":
			// uniquement les élèves si consultation autorisée
			return ($_SESSION['statut']=="eleve" && getSettingValue('carnets_de_liaison_consultation_eleve')=="oui");
			break;
		case "admin.php":
			// uniquement les administrateurs
			return ($_SESSION['statut']=="administrateur");
			break;
		case "saisie.php":
			if ($_SESSION['statut']=="autre")
				{
				// uniquement les comptes 'autre' autorisés à rédiger des mots
				$r_sql="SELECT * FROM `carnets_de_liaison_droits` WHERE login='".$_SESSION['login']."' LIMIT 1";
				$R_utilisateur=mysqli_query($mysqli, $r_sql);
				return (mysqli_num_rows($R_utilisateur)>0);
				}
				// ou uniquement les administrateurs, professeurs, cpe et scolarité
				else return (($_SESSION['statut']=="administrateur") || ($_SESSION['statut']=="professeur") || ($_SESSION['statut']=="cpe") || ($_SESSION['statut']=="scolarite"));
			break;
		case "consultation.php":
			if ($_SESSION['statut']=="autre")
				{
				// uniquement les comptes 'autre' autorisés à rédiger des mots
				$r_sql="SELECT * FROM `carnets_de_liaison_droits` WHERE login='".$_SESSION['login']."' LIMIT 1";
				$R_utilisateur=mysqli_query($mysqli, $r_sql);
				return (mysqli_num_rows($R_utilisateur)>0);
				}
				// ou uniquement les administrateurs, professeurs, cpe et scolarité
				else return (($_SESSION['statut']=="administrateur") || ($_SESSION['statut']=="professeur") || ($_SESSION['statut']=="cpe") || ($_SESSION['statut']=="scolarite"));
			break;
		default:
			return false;
		}
	}


function post_installation_carnets_de_liaison()
	{
	// pour palier à un bug de settings.inc saveSetting()
	global $mysqli,$gepiSettings;
	if (!isset($gepiSettings['carnets_de_liaison_affiche_trombines_eleves'])) $gepiSettings['carnets_de_liaison_affiche_trombines_eleves']="oui";
	if (!isset($gepiSettings['carnets_de_liaison_affiche_trombines_profs'])) $gepiSettings['carnets_de_liaison_affiche_trombines_profs']="non";

	$message_d_erreur="";
	// on fixe les paramètres d'affichage ou non des photos aux valeurs globales de GEPI
	// les photos des élèves sont-elles affichées par défaut ?
	$carnets_de_liaison_affiche_trombines_eleves=getSettingAOui('active_module_trombinoscopes')?"oui":"non";
	// les photos des profs sont-elles affichées par défaut  ?
	$carnets_de_liaison_affiche_trombines_profs=getSettingAOui('active_module_trombino_pers')?"oui":"non";
	if (!saveSetting('carnets_de_liaison_affiche_trombines_eleves',$carnets_de_liaison_affiche_trombines_eleves)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	if (!saveSetting('carnets_de_liaison_affiche_trombines_profs',$carnets_de_liaison_affiche_trombines_profs)) $message_d_erreur.="Erreur MySQL : <br />".mysqli_error($mysqli)."<br />";
	return $message_d_erreur;
	}


function post_desinstallation_carnets_de_liaison()
	{
	global $mysqli;
	// suppression des documents joints
	$message_d_erreur="";
	$documents=opendir("carnets_de_liaison/documents/");
	while ($fichier = readdir($documents))
		{
		if(is_file("carnets_de_liaison/documents/".$fichier) && ($fichier!="index.html") && ($fichier!="index.php"))
			if (!unlink("carnets_de_liaison/documents/".$fichier))
				$message_d_erreur.="<br />Impossible de supprimer le fichier mod_plugins/carnets_de_liaison/documents/".$fichier."<br />";
		}
	// suppression des messages
	$sql="DELETE FROM `messages`WHERE `texte` REGEXP '<!-- carnets de liaison -->'";
	if (!mysqli_query($mysqli, $sql)) $message_d_erreur.="<br />Impossible de supprimer les messages générés par le plugin : ".mysqli_error($mysqli)."<br />";
	return $message_d_erreur;
	}

?>