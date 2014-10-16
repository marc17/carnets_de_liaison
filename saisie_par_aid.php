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

// les rédacteurs peuvent-ils envoyer un sms de notification ?
$carnets_de_liaison_notification_sms_aux_responsables=getSettingValue("carnets_de_liaison_notification_sms_aux_responsables");

// variables de configuration pour traitement_saisie_par_ensemble.php
$message_erreur_saisie_destinataires="Vous devez sélectionner une AID.<br />";
$table_carnet="`carnets_de_liaison_aid`"; 
$champ_id_table_carnet="`id_aid`";
$type_mot="aid";
$ensemble_destinataire="";
if (isset($_POST['ids_destinataires']))
	{
	$r_sql="SELECT `aid_config`.`nom_complet` FROM `aid_config`,`aid` WHERE (`aid`.`id`='".$_POST['ids_destinataires']."' AND `aid`.`indice_aid`=`aid_config`.`indice_aid`) LIMIT 1";
	$R=mysqli_query($mysqli, $r_sql);
	if (mysqli_num_rows($R)>0)
		{
		$t_ensemble=mysqli_fetch_assoc($R);
		$ensemble_destinataire=addslashes($t_ensemble['nom_complet']);
		}
	}


include("traitement_saisie_par_ensemble.php");

// le rédacteur est-il associé à une ou plusieurs AID ?
$r_sql="SELECT `aid`.`id`,`aid`.`nom` FROM `utilisateurs`,`j_aid_utilisateurs`,`aid` WHERE (`utilisateurs`.`login`='".$_SESSION['login']."' AND `utilisateurs`.`login`=`j_aid_utilisateurs`.`id_utilisateur` AND `j_aid_utilisateurs`.`id_aid`=`aid`.`id`) ORDER BY `aid`.`id`";
$R_aid=mysqli_query($mysqli, $r_sql);
if (mysqli_num_rows($R_aid)==0) 	header("Location: saisie.php");


//**************** EN-TETE *****************
$style_specifique="mod_plugins/carnets_de_liaison/styles";
$titre_page = "Carnets de liaison : saisie";
unset($_SESSION['ariane']);
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<p class=bold><a href="saisie.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/> Retour</a></p>
<div id="conteneur" style="margin: auto; width: 800px;">

<h2>Saisie d'un mot dans un carnet de liaison d'AID</h2>

<?php
if ($message_d_erreur!="")
	{
?>
	<p style="color: red; margin-left: 40px;"><?php echo $message_d_erreur; ?></p>
<?php
	}
?>
<div class="saisie" style="width: 560px;">

	<form method="post" name="saisie" action="saisie_par_aid.php" enctype="multipart/form-data">
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	<input type="hidden" name="id_modification" value="<?php echo $id_modification; ?>">
	<input type="hidden" name="ids_destinataires_initial" value="<?php echo $ids_destinataires_initial; ?>">
	<h4 style="margin-left:20px;">AID&nbsp;:
	<select name="ids_destinataires" style="min-width: 100px; max-width: 260px;">
		<option value="-1"></option>
	<?php
	while($une_aid=mysqli_fetch_assoc($R_aid)) 
		{
	?>
		<option value="<?php echo $une_aid['id']; ?>" <?php if ((isset($_POST['ids_destinataires'])) && ($une_aid['id']==((count($_POST['ids_destinataires'])>0)?$_POST['ids_destinataires'][0]:0))) echo "selected=\"selected\""; ?>><?php echo $une_aid['nom']; ?></option>
	<?php
		}
	?>
	</select>
	</h4>
	<h4 style="margin-left:20px;">
	Intitulé&nbsp;:&nbsp;<input type="text" class="intitule" name="intitule" value="<?php if (isset($_POST['intitule'])) echo stripslashes($_POST['intitule']); ?>">
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
			<br />Courriel de notification aux responsables : 
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
		<br />SMS de notification aux responsables : 
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
	<div style="margin-left:200px"><button name="saisie_ok" value="ok" type="submit">&nbsp;&nbsp;&nbsp;&nbsp;Valider la saisie&nbsp;&nbsp;&nbsp;&nbsp;</button></div>

	</form>
<br />
</div>
<br /><br />
</div>
<?php
include("../../lib/footer.inc.php");
?>