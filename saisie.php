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

function liste_noms_de_classe($liste)
	{
	global $mysqli;
	$liste_noms="";
	$r_sql="SELECT `nom_complet` FROM `classes` WHERE FIND_IN_SET(`id`,'".$liste."')";
	$R_classes=mysqli_query($mysqli, $r_sql);
	while($une_classe=mysqli_fetch_assoc($R_classes)) $liste_noms.=" ".$une_classe['nom_complet'];
	return $liste_noms;
	}

function liste_noms_d_aid($liste)
	{
	global $mysqli;
	$liste_noms="";
	$r_sql="SELECT `nom` FROM `aid` WHERE FIND_IN_SET(`id`,'".$liste."') LIMIT 1";
	$R_aid=mysqli_query($mysqli, $r_sql);
	while($une_aid=mysqli_fetch_assoc($R_aid)) $liste_noms.=" ".$une_aid['nom'];
	return $liste_noms;
	}

function liste_noms_de_groupe($liste)
	{
	global $mysqli;
	$liste_noms="";
	$r_sql="SELECT `groupes`.`description`,GROUP_CONCAT(`classes`.`nom_complet`) FROM `groupes`,`j_groupes_classes`,`classes` WHERE (FIND_IN_SET(`groupes`.`id`,'".$liste."') AND `j_groupes_classes`.`id_groupe`=`groupes`.`id` AND `j_groupes_classes`.`id_classe`=`classes`.`id`) GROUP BY `groupes`.`id` LIMIT 1";
	$R_groupes=mysqli_query($mysqli, $r_sql);
	while($un_groupe=mysqli_fetch_assoc($R_groupes)) $liste_noms.=" ".$un_groupe['description']." ".$un_groupe['GROUP_CONCAT(`classes`.`nom_complet`)'];
	return $liste_noms;
	}

function t_liste_eleves($liste)
	{
	global $mysqli;
	$liste_eleves=array();
	$r_sql="SELECT DISTINCT `ele_id`,`nom`,`prenom`,`classe`,`nom_complet` FROM `eleves`,`j_eleves_classes`,`classes` WHERE (FIND_IN_SET(ele_id,'".$liste."') AND `eleves`.`login`=`j_eleves_classes`.`login` AND `j_eleves_classes`.`id_classe`=`classes`.`id`) ORDER BY `j_eleves_classes`.`periode` DESC,`classe`,`nom`,`prenom`";
	$R_eleves=mysqli_query($mysqli, $r_sql);
	while($un_eleve=mysqli_fetch_assoc($R_eleves))
		// si un élève a changé de classe on doit éliminer le doublon correspondant
		if (!array_key_exists($un_eleve['ele_id'],$liste_eleves)) $liste_eleves[$un_eleve['ele_id']]=$un_eleve['prenom']." ".$un_eleve['nom']." (".$un_eleve['nom_complet'].")";
	return $liste_eleves;
	}

function elenoet_par_ele_id($ele_id)
	{
	global $mysqli;
	$r_sql="SELECT `elenoet` FROM `eleves` WHERE `ele_id`='".$ele_id."'";
	$R_elenoet=mysqli_query($mysqli, $r_sql);
	if (mysqli_num_rows($R_elenoet)>0) 
		{
		$t_elenoet=mysqli_fetch_assoc($R_elenoet);
		return $t_elenoet['elenoet'];
		}
		else return 0;
	}

// si l'appel se fait avec passage de paramètre alors test du token
if ((function_exists("check_token")) && ((count($_POST)<>0) || (count($_GET)<>0))) check_token();

// l'envoi de mail est-il activé ?
$carnets_de_liaison_mail=getSettingValue("carnets_de_liaison_mail");

// les rédacteurs peuvent-ils joindre un fichier aux mots ?
$carnets_de_liaison_documents=getSettingValue("carnets_de_liaison_documents");

// les photos des élèves sont-elles affichées ?
$carnets_de_liaison_affiche_trombines_eleves=getSettingValue("carnets_de_liaison_affiche_trombines_eleves");

// les photos des profs sont-elles affichées ?
$carnets_de_liaison_affiche_trombines_profs=getSettingValue("carnets_de_liaison_affiche_trombines_profs");

// paramètres trombinoscope
$width_trombine=getSettingValue("l_max_aff_trombinoscopes");
$height_trombine=getSettingValue("h_max_aff_trombinoscopes");

// a priori pas d'erreur
$message_d_erreur="";

// bilan envoi notification
// si $message_bilan_notification est non vide une alerte javascript sera affichée
$message_bilan_notification="";
if (isset($_GET['message_bilan_notification']))
	$message_bilan_notification=urldecode($_GET['message_bilan_notification']);

// cacher ou montrer un mot
if (isset($_POST['cacher_mot']) OR isset($_POST['montrer_mot']))
	{
	$r_sql="UPDATE `carnets_de_liaison_mots` SET `visible`=";
	if (isset($_POST['montrer_mot'])) $r_sql.="TRUE "; else $r_sql.="FALSE ";
	$r_sql.="WHERE (`id_mot`='";
	if (isset($_POST['montrer_mot'])) $r_sql.=$_POST['montrer_mot']; else $r_sql.=$_POST['cacher_mot'];
	$r_sql.="' AND `login_redacteur`='".$_SESSION['login']."')";
	$R=mysqli_query($mysqli, $r_sql);
	if (!$R) 
		$message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		else header('Location: saisie.php');
	}


//**************** EN-TETE *****************
$style_specifique="mod_plugins/carnets_de_liaison/styles";
$titre_page = "Carnets de liaison : saisie";
unset($_SESSION['ariane']);
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************
?>

<p class=bold><a href="../../accueil.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/>Retour à l'accueil</a>
 | <a href="saisie_par_eleve.php">Saisie par élève</a>
 | <a href="saisie_par_classe.php">Saisie par classe</a>
<?php
if ($_SESSION['statut']=="professeur")
	{
?>
 | <a href="saisie_par_aid.php">Saisie par AID</a>
 | <a href="saisie_par_groupe.php">Saisie par groupe</a>
<?php
	}
?> 
 | <a href="consultation.php">Consultation</a>
<?php
if ($_SESSION['statut']=="administrateur")
	{
?>
 | <a href="admin.php">Administration</a>
<?php
	}
?>
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
<?php
// alerte javascript affichant le bilan de l'envoi de notification
if ($message_bilan_notification!="")
		echo 	"
				<script type=\"text/javascript\">
				<!--
				alert(\"".$message_bilan_notification."\");
				-->
				</script>
				";
?>

<?php
$r_sql="SELECT * FROM `carnets_de_liaison_mots` WHERE `login_redacteur`='".$_SESSION['login']."' ORDER BY `date` DESC,`id_mot` DESC";
$R_mots=mysqli_query($mysqli, $r_sql);
if (!$R_mots) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
if (mysqli_num_rows($R_mots)>0)
	{
?>
	<h2 style="margin-bottom: 0px;">Liste des mots précédemment saisis</h2>
	( <button title=" mot visible, l'occulter " style="border: none; background: none;"><img style="width:16px; height:16px; vertical-align: bottom;" src="bouton_OK.png"></button>&nbsp;:&nbsp;mot visible, l'occulter &nbsp;<button title=" mot occulté, le montrer " style="border: none; background: none;"><img style="width:16px; height:16px; vertical-align: bottom;" src="bouton_not_OK.png"></button> : mot occulté, le montrer )
	<div style="margin-left: 20px; margin-top: 30px; line-height: 1.2;">

<?php
	while($un_mot=mysqli_fetch_assoc($R_mots))
		{
?>
		<a name="<?php echo $un_mot['id_mot']; ?>"></a>
		<h4 style="margin: 0px; color: MediumBlue;">Le <?php echo date_en_clair($un_mot['date']); ?> :</h4>
		<?php
		if (getSettingAOui('active_module_trombinoscopes') && ($carnets_de_liaison_affiche_trombines_eleves=="oui"))
			{
			// un seul élève destinataire, on affiche sa photo
			$t_ids_destinataires=explode(',',$un_mot['ids_destinataires']);
			if ((count($t_ids_destinataires)==2) && ($un_mot['type']=="eleve"))
				{
				$elenoet=elenoet_par_ele_id($t_ids_destinataires[0]);
				$ele_photo=nom_photo($elenoet,$repertoire="eleves",$arbo=2);
				if ($ele_photo!=NULL)
					{
					$dimensions=getimagesize($ele_photo);
					$rapport_largeur=($dimensions[0]==0)?1:($width_trombine/$dimensions[1]);
					$rapport_longueur=($dimensions[1]==0)?1:($width_trombine/$dimensions[1]);
					$rapport=max($rapport_largeur,$rapport_longueur);
					?>
					<div id="trombine" style="position: absolute; margin-left: 555px;">
					<img style="width: <?php echo floor($dimensions[0]*$rapport); ?>px; height: <?php echo floor($dimensions[1]*$rapport); ?>px; vertical-align: top;" src="<?php echo $ele_photo; ?>">
					</div>
					<?php
					}
				}
			}
		?>
		<div class="texte" style="<?php if (!$un_mot['visible']) echo " color: gray;"; ?>">

			<form method="POST" action="saisie.php#<?php echo $un_mot['id_mot']; ?>">
			<p  class="intitule">
			<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		<?php
		if ($un_mot['visible'])
			{
		?>
			<input type="hidden" name="cacher_mot" value="<?php echo $un_mot['id_mot']; ?>">
			<button style="margin-right: 5px; margin-left: 5px; border: none; background: none;" type="submit" title=" Occulter ce mot "><img style="width:16px; height:16px; vertical-align: bottom;" src="bouton_OK.png"></button>
		<?php
			}
			else
			{
		?>
			<input type="hidden" name="montrer_mot" value="<?php echo $un_mot['id_mot']; ?>">
			<button style="margin-right: 5px; margin-left: 5px; border: none; background: none;" type="submit" title=" Montrer ce mot "><img style="width:16px; height:16px; vertical-align: bottom;" src="bouton_not_OK.png"></button>
		<?php
			}
		?>
			<?php echo $un_mot['intitule']; ?>
			</p>
			</form>
		<br />

		<?php echo nl2br($un_mot['texte']); ?>
		</div>
		<?php
		if ($un_mot['reponse_destinataire']=="oui")
			{
		?>
		Les destinataires peuvent répondre à ce mot.<br />
		<?php
			}
		if ($carnets_de_liaison_mail=="oui" && $un_mot['mail']!="" && $un_mot['reponse_destinataire']=="oui")
			{
		?>
		Adresse de courriel pour les réponses&nbsp;:&nbsp;<?php echo $un_mot['mail']; ?>
		<br />
		<?php
			}
		?>
		<?php
		//if (($un_mot['document']!="") && ($carnets_de_liaison_documents=="oui"))
		if ($un_mot['document']!="")
			{
			$document=unserialize($un_mot['document']);
			// si multisite
			if (isset($GLOBALS['multisite']) && isset($_COOKIE['RNE']) && $GLOBALS['multisite']=='y') $dossier_multisite=$_COOKIE['RNE']."/"; else $dossier_multisite="";
		?>
		<form method="POST" action="telecharger_document.php">
		<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		<input type="hidden" name="telecharger_document" value="<?php echo $dossier_multisite.$document['prefixe']."_".$un_mot['id_mot']; ?>">
		Document joint&nbsp;:&nbsp;<?php echo $document['nom']; ?>&nbsp;&nbsp;<button type="submit" title=" Télécharger ce document " style="border: none; background: none;"><img style="width:16px; height:16px; vertical-align: bottom;" src="bouton_download.png"></button>
		</form>
		<?php
			}
		?>

		<?php
		if($un_mot['type']=="classe")
			{
			if ($un_mot['ids_destinataires']!="-1")
				{
		?>
				&nbsp;&nbsp;&nbsp;Classe(s)&nbsp;:&nbsp;<?php echo liste_noms_de_classe($un_mot['ids_destinataires']); ?><br />
		<?php
				}
		?>
			<form method="post" action="saisie_par_classe.php" style="margin-left: 0px; float: left;">
			<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
			<input type="hidden" name="modifier" value="<?php echo $un_mot['id_mot']; ?>" >
			<button type="submit"> Modifier ce mot </button>
			</form>
		<?php
			}
		?>


		<?php
		if(($un_mot['type']=="aid"))
			{
			if ($un_mot['ids_destinataires']!="-1")
				{
		?>
				&nbsp;&nbsp;&nbsp;AID&nbsp;:&nbsp;<?php echo liste_noms_d_aid($un_mot['ids_destinataires']); ?><br />
		<?php
				}
		?>
			<form method="post" action="saisie_par_aid.php" style="margin-left: 0px; float: left;">
			<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
			<input type="hidden" name="modifier" value="<?php echo $un_mot['id_mot']; ?>" >
			<button type="submit"> Modifier ce mot </button>
			</form>
		<?php
			}
		?>

		<?php
		if(($un_mot['type']=="groupe"))
			{
			if ($un_mot['ids_destinataires']!="-1")
				{
		?>
				&nbsp;&nbsp;&nbsp;Groupe&nbsp;:&nbsp;<?php echo liste_noms_de_groupe($un_mot['ids_destinataires']); ?><br />
		<?php
				}
		?>
			<form method="post" action="saisie_par_groupe.php" style="margin-left: 0px; float: left;">
			<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
			<input type="hidden" name="modifier" value="<?php echo $un_mot['id_mot']; ?>" >
			<button type="submit"> Modifier ce mot </button>
			</form>
		<?php
			}
		?>



		<?php
		if(($un_mot['type']=="eleve"))
			{
			if ($un_mot['ids_destinataires']!="-1")
				{
		?>
				Élève(s)&nbsp;:&nbsp;<br />
		<?php

				foreach(t_liste_eleves($un_mot['ids_destinataires']) as $un_eleve)
					{
		?>
					&nbsp;&nbsp;&nbsp;<?php echo $un_eleve; ?><br />
		<?php
					}
				}
		?>
			<form method="post" action="saisie_par_eleve.php" style="margin-left: 0px; float: left;">
			<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
			<input type="hidden" name="modifier" value="<?php echo $un_mot['id_mot']; ?>">
			<button type="submit"> Modifier ce mot </button>
			</form>
			<form method="post" action="saisie_par_eleve.php" style="float: left; margin-left: 20px;">
			<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
			<input type="hidden" name="ele_ids" value="<?php echo $un_mot['ids_destinataires']; ?>">
			<button type="submit"> Nouveau mot avec cette liste d'élèves </button>
			</form>
		<?php
			}
		?>
		<br /><br /><br /><br />
<?php
		}
	}
?>
	</div>
</div>
<?php
include("../../lib/footer.inc.php");
?>