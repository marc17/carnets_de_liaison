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

// si l'appel se fait avec passage de paramètre alors test du token
if ((function_exists("check_token")) && ((count($_POST)<>0) || (count($_GET)<>0))) check_token();

//if (isset($_GET['telecharger_document']) $_POST['telecharger_document']=$_GET['telecharger_document'];

if (isset($_POST['telecharger_document']))
	{
	$post=explode("_",basename($_POST['telecharger_document']));
	$prefixe=$post[0];
	$id_mot=$post[1];
	$r_sql="SELECT `document` FROM `carnets_de_liaison_mots` WHERE `id_mot`='".$id_mot."' LIMIT 1";
	$R_document=mysqli_query($mysqli, $r_sql);
	$le_mot=mysqli_fetch_assoc($R_document);
	$document=unserialize($le_mot['document']);
	header("Content-Description: File Transfer");
	header("Content-Disposition: attachment; filename=".$document['nom']);
	header("Content-Type: ".$document['type']);
	header("Content-Transfer-Encoding: binary");
	// pb de download avec IE
	if (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))
		{
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		} 
		else {header('Pragma: no-cache');
		}
	// une vérification d'ordre paranoïaque
	if ($prefixe==$document['prefixe'])
		{
		@readfile("documents/".$_POST['telecharger_document']);
		}
	exit;
	}

// l'utilisateur est-il autorisé à exécuter ce script ?
//include("verification_autorisations.inc.php");


//**************** EN-TETE *****************
$style_specifique="mod_plugins/carnets_de_liaison/styles";
$titre_page = "Carnets de liaison : téléchargement";
require_once("../../lib/header.inc");
//**************** FIN EN-TETE *************

?>

<?php
include("../../lib/footer.inc.php");
?>