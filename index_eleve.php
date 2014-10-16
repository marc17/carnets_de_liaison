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
$carnets_de_liaison_mail="non";

// les rédacteurs peuvent-ils joindre un fichier aux mots ?
$carnets_de_liaison_documents="non";

// les responsables peuvent-ils rédiger des mots ?
$carnets_de_liaison_saisie_responsable="non";

// les responsables peuvent-ils répondre aux mots ?
$carnets_de_liaison_reponses_responsables="non";

// les photos des élèves sont-elles affichées ?
$carnets_de_liaison_affiche_trombines_eleves=getSettingValue("carnets_de_liaison_affiche_trombines_eleves");

// les photos des profs sont-elles affichées ?
$carnets_de_liaison_affiche_trombines_profs=getSettingValue("carnets_de_liaison_affiche_trombines_profs");

// paramètres trombinoscope
$width_trombine=getSettingValue("l_max_aff_trombinoscopes");
$height_trombine=getSettingValue("h_max_aff_trombinoscopes");

// initialisations
// $ele_id indice de l'élève dans la table 'eleves'

$r_sql="SELECT `nom`,`prenom`,`ele_id`,`elenoet` FROM `eleves` WHERE `login`='".$_SESSION['login']."' LIMIT 1";
$r_eleve=mysqli_query($mysqli,$r_sql);
$eleve=mysqli_fetch_assoc($r_eleve);
$ele_id=$eleve['ele_id'];
$elenoet=$eleve['elenoet'];
$r_sql="SELECT `id_classe` FROM `j_eleves_classes` WHERE `login`='".$_SESSION['login']."' ORDER BY `periode` DESC LIMIT 1";
$r_classe=mysqli_query($mysqli,$r_sql);
$classe=mysqli_fetch_assoc($r_classe);
$id_classe=$classe['id_classe'];


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
?>
<h2>Carnet de liaison de <?php  echo $eleve['prenom']." ".$eleve['nom']; ?></h2>
<?php


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
?>

<?php

if ($ele_id!==0)
	{
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