<?php

//************************
// Copyleft Marc Leygnac
//************************

// URL GEPI
$carnets_de_liaison_url_gepi=getSettingValue("carnets_de_liaison_url_gepi");

// nom de domaine pour l'envoi de mail anonyme
$carnets_de_liaison_email_notification=getSettingValue("carnets_de_liaison_email_notification");

if (isset($_POST['envoyer_reponse']) && ($_POST['texte']!=""))
	{
	// ajout de la réponse
	$reponse="Réponse de ".$civilite_utilisateur." ".$_SESSION['prenom']." ".$_SESSION['nom']." (le ".date_du_jour().") : \n".$_POST['texte']."\n";
	$r_sql="INSERT INTO `carnets_de_liaison_reponses` VALUES('".$_POST['id_mot']."','".$_SESSION['login']."',CURRENT_DATE,'".$reponse."')";
	if (!mysqli_query($mysqli, $r_sql)) $message_d_erreur.="Impossible d'enregistrer la réponse : ".mysqli_error($mysqli);
	// on récupère des données du mot
	$r_sql="SELECT `login_redacteur`,`mail`,`intitule`,`date` FROM `carnets_de_liaison_mots` WHERE `id_mot`='".$_POST['id_mot']."' LIMIT 1";
	$R_mot=mysqli_query($mysqli, $r_sql);
	if ($R_mot) $le_mot=mysqli_fetch_assoc($R_mot);
	
	// envoi de la réponse par mail
	$mail=(isset($le_mot))?$le_mot['mail']:""; 
	if ($carnets_de_liaison_mail=="oui" && $mail!="")
		{
		// on masque éventuellement l'adresse expéditeur
		if ($_SESSION['statut']!="responsable" && $show_email_utilisateur=="no") $mail_utilisateur=$carnets_de_liaison_email_notification;
		$to=$mail;
		$subject="[GEPI - carnets de liaison] réponse au mot : ".stripslashes($le_mot['intitule']);
		$subject = "=?UTF-8?B?".base64_encode($subject)."?=";
		$message="(réponse de ".$civilite_utilisateur." ".$_SESSION['prenom']." ".$_SESSION['nom']." au mot \"".stripslashes($le_mot['intitule'])."\" du ".date_en_clair($le_mot['date']);
		if (isset($_POST['identite_eleve'])) $message.=" dans le carnet de ".$_POST['identite_eleve'];
		$message.=")\n\n".stripslashes(slashe_n2nl($_POST['texte']));
		$message.="\n\n".$carnets_de_liaison_url_gepi;
		$headers="From: \"".$_SESSION['prenom']." ".$_SESSION['nom']."\" <".$mail_utilisateur.">".PHP_EOL;
		$headers.="Content-type: text/plain; charset=utf-8".PHP_EOL;
		$headers.="MIME-Version: 1.0".PHP_EOL;
		if (!mail($to, $subject, $message, $headers))
			{
			$script_bilan_envoi_mail="
				<script type=\"text/javascript\">
				<!--
				alert(\"Echec lors de l'envoi de la réponse par courriel.\");
				-->
				</script>
				";
			}
			else
			{
			$script_bilan_envoi_mail="
				<script type=\"text/javascript\">
				<!--
				alert(\"La réponse a été envoyée par courriel.\");
				-->
				</script>
				";
			}
		}

	// message émis sur la page d'accueil du destinataire
	if (function_exists("message_accueil_utilisateur"))
		{
		// quel est le statut du redacteur du mot auquel on a répondu ?
		$r_sql="SELECT `statut` FROM `utilisateurs` WHERE `login`='".$le_mot['login_redacteur']."' LIMIT 1";
		$R=mysqli_query($mysqli, $r_sql);
		if (mysqli_num_rows($R)>0) 
			{
			$t_statut_redacteur=mysqli_fetch_assoc($R);
			$statut_redacteur=$t_statut_redacteur['statut'];
			}
			else $statut_redacteur="?";
		switch ($statut_redacteur)
			{
			case "?" :
				$bouton="Carnets de liaison : <br />";
				$action="index.php";
				break;
			case "responsable" :
				$action="index.php";
				$bouton="<button type=\"submit\" title=\" Voir ce mot \" style=\"border: none; background: none; float: right;\"><img style=\"width:16px; height:16px; vertical-align: bottom;\" src=\"".$gepiPath."/mod_plugins/carnets_de_liaison/bouton_voir_carnet.png\"></button>";
				break;
			default :
				$action="consultation_reponses.php";
				$bouton="<button type=\"submit\" title=\" Voir ce mot \" style=\"border: none; background: none; float: right;\"><img style=\"width:16px; height:16px; vertical-align: bottom;\" src=\"".$gepiPath."/mod_plugins/carnets_de_liaison/bouton_voir_carnet.png\"></button>";
			}

		$message="<!-- carnets de liaison -->";
		$message.="<span style=\"font-weight:bold\">Carnets de liaison : </span>";
		$message.="<form method=\"post\" name=\"consultation_carnet\" action=\"".$gepiPath."/mod_plugins/carnets_de_liaison/".$action."#ancre_retour".$_POST['id_mot']."\">";
		if (function_exists("add_token_field")) $message.=add_token_field(false,false);
		$message.="<input type=\"hidden\" name=\"id_classe\" value=\"".$_POST['id_classe']."\">";
		$message.="<input type=\"hidden\" name=\"id_eleve\" value=\"".$_POST['id_eleve']."\">";
		$message.="réponse de ".$civilite_utilisateur." ".$_SESSION['prenom']." ".$_SESSION['nom']." au mot \"".stripslashes($le_mot['intitule'])."\" du ".date_en_clair($le_mot['date']);
		if (isset($_POST['identite_eleve'])) 
				$message.=" dans le carnet de ".$_POST['identite_eleve'];
		$message.=".&nbsp;".$bouton."</form>";
		message_accueil_utilisateur($le_mot['login_redacteur'],$message,time(),time()+3600*24*7,0,true);
		}

	// on oublie tout
	$_POST['destinataire']="";
	$_POST['intitule']="";
	$_POST['texte']="";
	}
?>