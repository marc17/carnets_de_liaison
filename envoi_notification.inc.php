<?php

// fonctions d'envoi SMS
include("envoi_SMS.inc.php");

// Max de mails de notification qu'il est possible d'envoyer
$carnets_de_liaison_max_mails_notification=(getSettingValue("carnets_de_liaison_max_mails_notification")==NULL)?0:intval(getSettingValue("carnets_de_liaison_max_mails_notification"));

// URL GEPI
$carnets_de_liaison_url_gepi=getSettingValue("carnets_de_liaison_url_gepi");

// nom de domaine fictif pour l'envoi de courriel anonyme
$carnets_de_liaison_email_notification=getSettingValue("carnets_de_liaison_email_notification");

// max de sms de notification qu'il est possible d'envoyer
$carnets_de_liaison_max_sms_notification=intval(getSettingValue("carnets_de_liaison_max_sms_notification"));

function message_d_erreur_mail($errno, $errstr)
	{
	/*
	E_ERROR           => "Erreur",
	E_WARNING         => "Alerte",
	E_PARSE           => "Erreur d'analyse",
	E_NOTICE          => "Note",
	E_CORE_ERROR      => "Core Error",
	E_CORE_WARNING    => "Core Warning",
	E_COMPILE_ERROR   => "Compile Error",
	E_COMPILE_WARNING => "Compile Warning",
	E_USER_ERROR      => "Erreur spécifique",
	E_USER_WARNING    => "Alerte spécifique",
	E_USER_NOTICE     => "Note spécifique",
	E_STRICT          => "Runtime Notice"
	*/
	global $message_d_erreur_mail;
	switch ($errno)
		{
		case E_ERROR :
		case E_WARNING :
		case E_NOTICE :
			//$message_d_erreur_mail=preg_replace('/&/','//',$errstr);
			$message_d_erreur_mail=html_entity_decode($errstr);
			break;
		default :
			$message_d_erreur="Erreur PHP ".$errno;
		}
	}

function message_notification($login,$texte,$id_eleve)
	{
	global $id_mot;
	if (function_exists("message_accueil_utilisateur"))
		{
		$bouton_voir="<button type=\"submit\" title=\" Voir ce mot \" style=\"border: none; background: none; float: right;\"><img style=\"width:16px; height:16px; vertical-align: bottom;\" src=\"mod_plugins/carnets_de_liaison/bouton_voir_carnet.png\"></button>";
		$message="<!-- carnets de liaison -->";
		$message.="<span style=\"font-weight:bold\">Carnets de liaison : </span><br />";
		$message.="<form method=\"post\" name=\"consultation_carnet\" action=\"mod_plugins/carnets_de_liaison/index.php#ancre_retour".$id_mot."\">";
		if (function_exists("add_token_field")) $message.=add_token_field(false,false);
		$message.="<input type=\"hidden\" name=\"id_eleve\" value=\"".$id_eleve."\">";
		$message.=$texte;
		$message.=$bouton_voir."</form>";
		if (!message_accueil_utilisateur($login,$message,time(),time()+3600*24*7,0,true))
			$t_bilan_envoi_notification[]=array('type'=>"erreur_sql",'erreur'=>"Impossible d'enregister la notification : ".mysqli_error($mysqli));
		}
	}

function envoi_mail_notification($to,$subject,$bcc,$texte,$redacteur_email)
	{
	global $carnets_de_liaison_email_notification,$message_d_erreur_mail;
	$bcc.=$_SESSION['prenom']." ".$_SESSION['nom']."<".$redacteur_email.">";
	$subject = "=?UTF-8?B?".base64_encode($subject)."?=";
	$headers="From: [GEPI- carnets de liaison]  <".$carnets_de_liaison_email_notification.">".PHP_EOL;
	$headers.="Content-type: text/plain; charset=utf-8".PHP_EOL;
	$headers.="MIME-Version: 1.0".PHP_EOL;
	$headers.="Bcc: ".$bcc."".PHP_EOL;
	//on capte le message d'erreur éventuelle généré par l'appel à mail()
	//error_reporting(0);
	$old_error_handler=set_error_handler("message_d_erreur_mail");
	@mail($to,$subject,$texte,$headers);
	restore_error_handler();
	return $message_d_erreur_mail;
	}

function envoi_notification($ids,$type,$envoi_mail_notification,$envoi_sms_notification)
	{
	global $mysqli,$gepiPath,$carnets_de_liaison_mail,$carnets_de_liaison_email_notification,$carnets_de_liaison_url_gepi,$carnets_de_liaison_max_mails_notification;
	$r_sql="SELECT `civilite`,`prenom`,`nom`,`email` FROM `utilisateurs` WHERE `login`='".$_SESSION['login']."' LIMIT 1";

	//$t_bilan_envoi_notification tableau de tableaux associatifs
	// contenant la liste des erreurs qui se sont produites
	//$t_bilan_envoi_notification[]=array('type'=>"erreur_mail",'erreur'=>"Un message d'erreur mail");
	//$t_bilan_envoi_notification[]=array('type'=>"erreur_sql",'erreur'=>"Un message d'erreur SQL");
	//$t_bilan_envoi_notification[]=array('type'=>"autre_erreur",'erreur'=>"Un message d'erreur autre");
	$t_bilan_envoi_notification=array();

	$R_redacteur=mysqli_query($mysqli, $r_sql);
	if ($R_redacteur && mysqli_num_rows($R_redacteur)==1) $redacteur=mysqli_fetch_assoc($R_redacteur);
		else $redacteur=array("","","","");
	// si le rédacteur n'a pas de mail dans GEPI on utilise le mail spécifié dans le mot
	if ($redacteur['email']=="" && isset($_POST['mail'])) $redacteur['email']=$_POST['mail'];
	// si aucun mail n'est défini on envoie pas de notification
	if ($redacteur['email']=="" && $envoi_mail_notification=="oui") 
		{
		$envoi_mail_notification="non";
		$t_bilan_envoi_notification[]=array('type'=>"erreur_mail",'erreur'=>"Les notifications ne peuvent être envoyées par courriel car vous n'avez pas d'adresse pour recevoir le message en BCC.");
		}

	// pour les notifications de mot adressé à un ensemble
	// on concatène les mails des destinataires dans le champ BCC
	$bcc="";
	// on concatène les numéros SMS des destinataires dans un tableau
	$t_sms=array();
	

	switch ($type)
		{
		case "eleve" :
			//$r_sql="SELECT DISTINCT CONCAT_WS('',`eleves`.`prenom`,' ',`eleves`.`nom`) AS `nom_eleve`,`utilisateurs`.`prenom`,`utilisateurs`.`nom`,`utilisateurs`.`email` FROM `eleves`,`responsables2`,`resp_pers`,`utilisateurs` WHERE `utilisateurs`.`email`<>'' AND (FIND_IN_SET(`eleves`.`ele_id`,'".$ids."') AND `eleves`.`ele_id`=`responsables2`.`ele_id` AND `responsables2`.`pers_id`=`resp_pers`.`pers_id` AND `resp_pers`.`login`=`utilisateurs`.`login`) ";
			$r_sql="SELECT DISTINCT `eleves`.`id_eleve`,CONCAT_WS('',`eleves`.`prenom`,' ',`eleves`.`nom`) AS `nom_eleve`,`utilisateurs`.`login`,`utilisateurs`.`email`,`resp_pers`.`tel_port` FROM `eleves`,`responsables2`,`resp_pers`,`utilisateurs` WHERE `utilisateurs`.`email`<>'' AND (FIND_IN_SET(`eleves`.`ele_id`,'".$ids."') AND `eleves`.`ele_id`=`responsables2`.`ele_id` AND `responsables2`.`pers_id`=`resp_pers`.`pers_id` AND `resp_pers`.`login`=`utilisateurs`.`login`) ";
			$R_responsables=mysqli_query($mysqli, $r_sql);
			if ($R_responsables)
				{
				//on envoie pas plus de $carnets_de_liaison_max_mails_notification couuriels de notification (sinon spam)
				if (mysqli_num_rows($R_responsables)>$carnets_de_liaison_max_mails_notification) 
					{
					$envoi_mail_notification="non";
					$t_bilan_envoi_notification[]=array('type'=>"erreur_mail",'erreur'=>"Les notifications n'ont pas été envoyées par courriel,\n trop de destinataires donc risque d'assimilation à du SPAM.\nLe nombre de destinataires est limité à ".$carnets_de_liaison_max_mails_notification);
					}
				//on envoie pas plus de $carnets_de_liaison_max_sms_notification couuriels de notification (sinon spam)
				if (mysqli_num_rows($R_responsables)>$carnets_de_liaison_max_sms_notification) 
					{
					$envoi_sms_notification="non";
					$t_bilan_envoi_notification[]=array('type'=>"erreur_sms",'erreur'=>"Les notifications n'ont pas été envoyées par SMS,\n trop de destinataires.\nLe nombre de destinataires est limité à ".$carnets_de_liaison_max_sms_notification);
					}
				while ($un_responsable=mysqli_fetch_assoc($R_responsables))
					{
					$subject="Carnet de liaison de ".$un_responsable['nom_eleve'];
					$texte="Nouveau mot rédigé par ".$redacteur['civilite']." ".$redacteur['prenom']." ".$redacteur['nom']."\ndans le carnet de liaison de ".$un_responsable['nom_eleve']." : \n".stripslashes($_POST['intitule']);
					// on affiche la notification sur le panneau d'affichage
					message_notification($un_responsable['login'],$texte,$un_responsable['id_eleve']);
					$texte.="\n\n".$carnets_de_liaison_url_gepi;
					// on envoie éventuellement un mail de notification
					if ($envoi_mail_notification=="oui" && $carnets_de_liaison_mail=="oui")
						{
						$retour=envoi_mail_notification($un_responsable['email'],$subject,"",$texte,$redacteur['email']);
						if ($retour!="") $t_bilan_envoi_notification[]=array('type'=>"erreur_mail",'erreur'=>"dernière erreur : ".$retour);
						}
					
					if ($envoi_sms_notification=='oui' && $un_responsable['tel_port']!='') 
						{
						$retour_envoi_SMS=envoi_SMS(array($un_responsable['tel_port']),"Nouveau mot rédigé par ".$redacteur['civilite']." ".$redacteur['prenom']." ".$redacteur['nom']."dans le carnet de liaison de ".$un_responsable['nom_eleve']);
						if ($retour_envoi_SMS!='OK') $t_bilan_envoi_notification[]=array('type'=>"erreur_sms",'erreur'=>$un_responsable['login'].' '.$un_responsable['tel_port'].' '.$retour_envoi_SMS);
						}
					}
				}
			else  $t_bilan_envoi_notification[]=array('type'=>"erreur_sql",'erreur'=>mysqli_error($mysqli));
			break;
		default :
			switch ($type)
				{
				case "classe" :
					$r_sql="SELECT GROUP_CONCAT(`classes`.`nom_complet`) AS `ensemble` FROM `classes` WHERE FIND_IN_SET(`classes`.`id`,'".$ids."')";
					$R_ensemble=mysqli_query($mysqli, $r_sql);
					$R_ensemble=mysqli_query($mysqli, $r_sql);
					if ($R_ensemble && mysqli_num_rows($R_ensemble)==1)
						{
						$t_ensemble=mysqli_fetch_assoc($R_ensemble);
						$ensemble=$t_ensemble['ensemble'];
						}
					else $ensemble="";
					//$r_sql="SELECT DISTINCT `classes`.`nom_complet` AS 'nom_classe','".$ensemble."' AS `ensemble`, `utilisateurs`.`prenom`,`utilisateurs`.`nom`,`utilisateurs`.`email` FROM `classes`,`j_eleves_classes`,`eleves`,`responsables2`,`resp_pers`,`utilisateurs` WHERE FIND_IN_SET(`classes`.`id`,'".$ids."') AND  `classes`.`id`=`j_eleves_classes`.`id_classe` AND `j_eleves_classes`.`login`=`eleves`.`login` AND `eleves`.`ele_id`=`responsables2`.`ele_id` AND `responsables2`.`resp_legal`<>0 AND `responsables2`.`pers_id`=`resp_pers`.`pers_id` AND `resp_pers`.`login`=`utilisateurs`.`login` ORDER BY `classes`.`nom_complet`,`utilisateurs`.`nom`,`utilisateurs`.`prenom`";
					$r_sql="SELECT DISTINCT `classes`.`nom_complet` AS 'nom_classe','".$ensemble."' AS `ensemble`, `utilisateurs`.`login`,`utilisateurs`.`prenom`,`utilisateurs`.`nom`,`utilisateurs`.`email`,`resp_pers`.`tel_port`,`eleves`.`id_eleve`,CONCAT_WS('',`eleves`.`prenom`,' ',`eleves`.`nom`) AS `nom_eleve` FROM `classes`,`j_eleves_classes`,`eleves`,`responsables2`,`resp_pers`,`utilisateurs` WHERE `utilisateurs`.`email`<>'' AND FIND_IN_SET(`classes`.`id`,'".$ids."') AND  `classes`.`id`=`j_eleves_classes`.`id_classe` AND `j_eleves_classes`.`login`=`eleves`.`login` AND `eleves`.`ele_id`=`responsables2`.`ele_id` AND `responsables2`.`resp_legal`<>0 AND `responsables2`.`pers_id`=`resp_pers`.`pers_id` AND `resp_pers`.`login`=`utilisateurs`.`login` ORDER BY `classes`.`nom_complet`,`utilisateurs`.`nom`,`utilisateurs`.`prenom`";
					break;
				case "groupe" :
					$r_sql="SELECT GROUP_CONCAT(`classes`.`nom_complet`) AS `ensemble` FROM `classes`,`j_groupes_classes`,`groupes` WHERE `groupes`.`id`='".$ids."' AND `j_groupes_classes`.`id_groupe`=`groupes`.`id` AND `j_groupes_classes`.`id_classe`=`classes`.`id` GROUP BY `groupes`.`id`";
					$R_ensemble=mysqli_query($mysqli, $r_sql);
					if ($R_ensemble && mysqli_num_rows($R_ensemble)==1)
						{
						$t_ensemble=mysqli_fetch_assoc($R_ensemble);
						$ensemble=$t_ensemble['ensemble'];
						}
					else $ensemble="";
					$r_sql="SELECT `groupes`.`description` FROM `groupes` WHERE `groupes`.`id`='".$ids."'";
					$R_ensemble=mysqli_query($mysqli, $r_sql);
					if ($R_ensemble && mysqli_num_rows($R_ensemble)==1)
						{
						$t_ensemble=mysqli_fetch_assoc($R_ensemble);
						$ensemble=$t_ensemble['description']." ".$ensemble;
						}
					//$r_sql="SELECT DISTINCT DISTINCT '".$ensemble."' AS `ensemble`,`utilisateurs`.`prenom`,`utilisateurs`.`nom`,`utilisateurs`.`email` FROM `groupes`,`j_eleves_groupes`,`eleves`,`responsables2`,`resp_pers`,`utilisateurs` WHERE `groupes`.`id`='".$ids."' AND  `groupes`.`id`=`j_eleves_groupes`.`id_groupe` AND `j_eleves_groupes`.`login`=`eleves`.`login` AND `eleves`.`ele_id`=`responsables2`.`ele_id` AND `responsables2`.`resp_legal`<>0 AND `responsables2`.`pers_id`=`resp_pers`.`pers_id` AND `resp_pers`.`login`=`utilisateurs`.`login`";
					$r_sql="SELECT DISTINCT DISTINCT '".$ensemble."' AS `ensemble`,`utilisateurs`.`login`,`utilisateurs`.`prenom`,`utilisateurs`.`nom`,`utilisateurs`.`email`,`resp_pers`.`tel_port`,`eleves`.`id_eleve`,CONCAT_WS('',`eleves`.`prenom`,' ',`eleves`.`nom`) AS `nom_eleve` FROM `groupes`,`j_eleves_groupes`,`eleves`,`responsables2`,`resp_pers`,`utilisateurs` WHERE `utilisateurs`.`email`<>'' AND `groupes`.`id`='".$ids."' AND  `groupes`.`id`=`j_eleves_groupes`.`id_groupe` AND `j_eleves_groupes`.`login`=`eleves`.`login` AND `eleves`.`ele_id`=`responsables2`.`ele_id` AND `responsables2`.`resp_legal`<>0 AND `responsables2`.`pers_id`=`resp_pers`.`pers_id` AND `resp_pers`.`login`=`utilisateurs`.`login`";
					break;
				case "aid" :
					$r_sql="SELECT `aid`.`nom` FROM `aid` WHERE `aid`.`id`='".$ids."'";
					$R_ensemble=mysqli_query($mysqli, $r_sql);
					if ($R_ensemble && mysqli_num_rows($R_ensemble)==1)
						{
						$t_ensemble=mysqli_fetch_assoc($R_ensemble);
						$ensemble=$t_ensemble['nom'];
						}
					else $ensemble="";
					//$r_sql="SELECT DISTINCT `aid`.`nom` AS `ensemble`,`utilisateurs`.`prenom`,`utilisateurs`.`nom`,`utilisateurs`.`email` FROM `aid`,`j_aid_eleves`,`eleves`,`responsables2`,`resp_pers`,`utilisateurs` WHERE `aid`.`id`='".$ids."' AND  `aid`.`id`=`j_aid_eleves`.`id_aid` AND `j_aid_eleves`.`login`=`eleves`.`login` AND `eleves`.`ele_id`=`responsables2`.`ele_id` AND `responsables2`.`resp_legal`<>0 AND `responsables2`.`pers_id`=`resp_pers`.`pers_id` AND `resp_pers`.`login`=`utilisateurs`.`login`";
					$r_sql="SELECT DISTINCT `aid`.`nom` AS `ensemble`,`utilisateurs`.`login`,`utilisateurs`.`prenom`,`utilisateurs`.`nom`,`utilisateurs`.`email`,`resp_pers`.`tel_port`,`eleves`.`id_eleve`,CONCAT_WS('',`eleves`.`prenom`,' ',`eleves`.`nom`) AS `nom_eleve` FROM `aid`,`j_aid_eleves`,`eleves`,`responsables2`,`resp_pers`,`utilisateurs` WHERE `utilisateurs`.`email`<>'' AND `aid`.`id`='".$ids."' AND  `aid`.`id`=`j_aid_eleves`.`id_aid` AND `j_aid_eleves`.`login`=`eleves`.`login` AND `eleves`.`ele_id`=`responsables2`.`ele_id` AND `responsables2`.`resp_legal`<>0 AND `responsables2`.`pers_id`=`resp_pers`.`pers_id` AND `resp_pers`.`login`=`utilisateurs`.`login`";
					break;
				}
			switch ($type)
				{
				case "classe" :
					$classes_destinataires=(count(explode(",",$ensemble))>1)?"des classes ":"de la classe ";
					$subject="Carnet de liaison ".$classes_destinataires.$ensemble;
					$texte="Nouveau mot rédigé par ".$redacteur['civilite']." ".$redacteur['prenom']." ".$redacteur['nom']."\ndans les carnets de liaison des élèves ".$classes_destinataires.$ensemble." :\n".stripslashes($_POST['intitule']);
					break;
				case "aid" :
				case "groupe" :
					$subject="Carnet de liaison de ".$ensemble;
					$texte="Nouveau mot rédigé par ".$redacteur['civilite']." ".$redacteur['prenom']." ".$redacteur['nom']."\ndans les carnets de liaison des élèves de ".$ensemble." :\n".stripslashes($_POST['intitule']);
				}
				$R_responsbles=mysqli_query($mysqli, $r_sql);
				if ($R_responsbles && mysqli_num_rows($R_responsbles)>0)
					{
					if (mysqli_num_rows($R_responsbles)>$carnets_de_liaison_max_mails_notification) 
						{
						$envoi_mail_notification="non";
						$t_bilan_envoi_notification[]=array('type'=>"erreur_mail",'erreur'=>"Les notifications n'ont pas été envoyées par courriel,\n trop de destinataires donc risque d'assimilation à du SPAM.\nLe nombre de destinataires est limité à ".$carnets_de_liaison_max_mails_notification);
						}
					/*
					if (mysqli_num_rows($R_responsbles)>$carnets_de_liaison_max_sms_notification) 
						{
						$envoi_sms_notification="non";
						$t_bilan_envoi_notification[]=array('type'=>"erreur_sms",'erreur'=>"Les notifications n'ont pas été envoyées par SMS,\n trop de destinataires.\nLe nombre de destinataires est limité à ".$carnets_de_liaison_max_smss_notification);
						}
					*/
					while ($un_responsable=mysqli_fetch_assoc($R_responsbles)) 
						{
						// on affiche la notification sur le panneau d'affichage
						message_notification($un_responsable['login'],$texte,$un_responsable['id_eleve']);
						// on ajoute un responsble au champ BCC
						$bcc.=$un_responsable['email'].",";
						$t_sms[]=$un_responsable['tel_port'];
						}
					// on envoie éventuellement les mails de notification
					if ($envoi_mail_notification=="oui" && $carnets_de_liaison_mail=="oui")
						{
						$retour=envoi_mail_notification($redacteur['email'],$subject,$bcc,$texte."\n\n".$carnets_de_liaison_url_gepi."\n",$redacteur['email']);
						if ($retour!="") $t_bilan_envoi_notification[]=array('type'=>"erreur_mail",'erreur'=>"dernière erreur : ".$retour);
						}
					// on envoie éventuellement les sms de notification
					/* todo : adapter envoi_SMS.inc.php à l'envoi de sms vers plusisurs destinataires (PluriWare entre autres)
					if ($envoi_sms_notification=="oui")
						{
						$retour_envoi_SMS=envoi_SMS($t_sms,$texte;
						if ($retour_envoi_SMS!='OK') $t_bilan_envoi_notification[]=array('type'=>"erreur_sms",$retour_envoi_SMS);
						$retour=envoi_sms_notification($un_responsable['esms'],$subject,"",$texte."\n",$redacteur['esms']);
						}
					*/
					}
				else
					$t_bilan_envoi_notification[]=array('type'=>"erreur_mail",'erreur'=>"Les notifications ne peuvent être envoyées par courriel car aucun des responsables n'a d'adresse de courriel.");
		}
	return $t_bilan_envoi_notification;
	}
	
		
// envoi de notification et bilan

if (isset($_POST['envoi_mail_notification'])) $envoi_mail_notification=$_POST['envoi_mail_notification'];
else $envoi_mail_notification="non";

if (isset($_POST['envoi_sms_notification'])) $envoi_sms_notification=$_POST['envoi_sms_notification'];
else $envoi_sms_notification="non";

// variable globale pour capter les messages d'erreur d'envoi de mails
$message_d_erreur_mail="";

// on envoie les notifications
$t_bilan_envoi_notification=envoi_notification($liste_destinataires,$type_notification,$envoi_mail_notification,$envoi_sms_notification);

// message éventuel d'erreurs
$message_bilan_notification="";
$nb_erreurs_mail=0;
$erreurs_mail="";
$nb_erreurs_sql=0;
$erreurs_sql="";
$nb_erreurs_sms=0;
$erreurs_sms="";
$autres_erreurs="";
if (count($t_bilan_envoi_notification)>0)
	{
	foreach($t_bilan_envoi_notification as $erreur)
		{
		switch ($erreur['type'])
			{
			case "erreur_mail" :
				$nb_erreurs_mail++;
				$erreurs_mail.=$erreur['erreur']."\n";
				break;
			case "erreur_sql" :
				$nb_erreurs_sql++;
				$erreurs_sql.=$erreur['erreur']."\n";
				break;
			case "erreur_sms" :
				$nb_erreurs_sms++;
				$erreurs_sms.=$erreur['erreur']."\n";
				break;
			default :
				$autres_erreurs.=$erreur['erreur']."\n";
				break;
			}
		}
	$message_bilan_notification.=$autres_erreurs;
	if ($message_bilan_notification!="") $message_bilan_notification.="\n";
	if ($nb_erreurs_mail>0) 
		$message_bilan_notification.=$nb_erreurs_mail." erreur(s) d'envoi de courriel de notification :\n (".$erreurs_mail.")";
	if ($message_bilan_notification!="") $message_bilan_notification.="\n";
	if ($nb_erreurs_sms>0) 
		$message_bilan_notification.=$nb_erreurs_sms." erreur(s) d'envoi de SMS de notification :\n (".$erreurs_sms.")";
	if ($message_bilan_notification!="") $message_bilan_notification.="\n";
	if ($nb_erreurs_sql>0) 
		$message_bilan_notification.=$nb_erreurs_sql." erreur(s) MySQL :\n(".$erreurs_sql.")";
	}
if ($message_bilan_notification!="") $message_bilan_notification.="\n";
if ($envoi_mail_notification=="oui" && $nb_erreurs_mail==0) $message_bilan_notification.="Toutes les notifications ont été envoyées par courriel.";
if ($envoi_sms_notification=="oui" && $nb_erreurs_sms==0) $message_bilan_notification.="Toutes les notifications ont été envoyées par SMS.";

// on retourne sur saisie.php
$url="Location: saisie.php?";
$url.=add_token_in_url(false);
$url.="&message_bilan_notification=".urlencode($message_bilan_notification);
if ($id_modification!=0) $url.="#".$id_modification;
header($url);

?>