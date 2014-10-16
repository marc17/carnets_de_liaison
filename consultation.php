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

function derniere_periode_active($id_classe)
	// renvoie l'indice de la première période non vérouillée si elle existe, de la dernière période sinon
	{
	global $mysqli;
	$R_periode=mysqli_query($mysqli,"SELECT `num_periode`,`verouiller` FROM `periodes` WHERE `id_classe`='".$id_classe."' ORDER BY `num_periode`");
	$retour=0; $p_courante=0;
	while ($periode=mysqli_fetch_assoc($R_periode))
		{
		$p_courante=$periode['num_periode'];
		if ($periode['verouiller']=='N' && $retour==0) $retour=$p_courante;
		}
	if ($retour==0) $retour=$p_courante;
	return $retour;
	}

// si l'appel se fait avec passage de paramètre alors test du token
if ((function_exists("check_token")) && ((count($_POST)<>0) || (count($_GET)<>0))) check_token();

// initialisation de variables
$id_classe=(int) (isset($_POST['id_classe'])?$_POST['id_classe']:0);
$ele_id=(string) (isset($_POST['ele_id'])?$_POST['ele_id']:"0");

// l'envoi de mail est-il activé ?
$carnets_de_liaison_mail=getSettingValue("carnets_de_liaison_mail");

// les rédacteurs peuvent-ils joindre un fichier aux mots ?
$carnets_de_liaison_documents=getSettingValue("carnets_de_liaison_documents");

// les responsables peuvent-ils répondre aux mots ?
$carnets_de_liaison_reponses_responsables=getSettingValue("carnets_de_liaison_reponses_responsables");

// les photos des élèves sont-elles affichées ?
$carnets_de_liaison_affiche_trombines_eleves=getSettingValue("carnets_de_liaison_affiche_trombines_eleves");

// les photos des profs sont-elles affichées ?
$carnets_de_liaison_affiche_trombines_profs=getSettingValue("carnets_de_liaison_affiche_trombines_profs");

// paramètres trombinoscope
$width_trombine=getSettingValue("l_max_aff_trombinoscopes");
$height_trombine=getSettingValue("h_max_aff_trombinoscopes");

// mail et civilité de l'utilisateur
$r_sql="SELECT `email`,`show_email`,`civilite` FROM `utilisateurs` WHERE `login`='".$_SESSION['login']."' LIMIT 1";
$R_coords=mysqli_query($mysqli,$r_sql);
$t_coords=mysqli_fetch_assoc($R_coords);
$mail_utilisateur=$t_coords['email'];
$show_email_utilisateur=$t_coords['show_email'];
$civilite_utilisateur=$t_coords['civilite'];

// envoi d'une réponse
include("envoi_reponse.inc.php");

// a priori pas d'erreur
$message_d_erreur="";



//**************** EN-TETE *****************
$style_specifique="mod_plugins/carnets_de_liaison/styles";
$titre_page = "Carnets de liaison : consultation";
unset($_SESSION['ariane']);
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<p class=bold><a href="../../accueil.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/>Retour à l'accueil</a>
 | <a href="consultation_mots_recus.php">Mots reçus</a>
 | <a href="consultation_reponses.php">Réponses reçues</a>
 | <a href="saisie.php">Saisie</a>
<?php
if (($_SESSION['statut']=="administrateur") || ($_SESSION['statut']=="cpe"))
	{
?>
 | <a href="consultation_cpe.php">Consultation CPE</a>
<?php
	}
?>
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

<h2>Consultaton d'un carnet de liaison</h2>

<h3 style="margin-left: 40px;">
<form method="post" name="consultation_1" action="consultation.php" style="float: left;">
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Sélectionner une classe&nbsp;:&nbsp;
	<select name="id_classe" onChange="document.forms['consultation_2'].ele_id.value='0';document.forms['consultation_1'].submit();">
		<option value="0"></option>
<?php
	$r_sql="SELECT * FROM `classes` ORDER BY `classe`";
	$R_classes=mysqli_query($mysqli, $r_sql);
	while($une_classe=mysqli_fetch_assoc($R_classes)) 
		{
?>
		<option value="<?php echo $une_classe['id']; ?>" <?php if ($une_classe['id']==$id_classe) {echo "selected=\"selected\""; $classe_selectionnee=$une_classe['nom_complet'];} ?>><?php echo $une_classe['nom_complet'].($une_classe['nom_complet']!=$une_classe['classe']?" (".$une_classe['classe'].")":""); ?></option>
<?php
		}
?>
	</select>
</form>
<?php
if (($id_classe!=0) && ($ele_id==="0"))
	{
?>
	<form method="post" action="saisie_par_classe.php" target="_blank" style="float: left; margin-left: 20px;">
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	<input type="hidden" name="id_classe" value="<?php echo $id_classe; ?>">
	<input type="hidden" name="ele_id" value="<?php echo $ele_id; ?>">
	<button name="saisie" value="saisir" type="submit"> Saisir un nouveau mot </button>
	</form>
<?php
	}
?>
</h3>
<br />
<h3 style="margin-left: 40px; margin-bottom: 40px;">
<?php if (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 7'))) echo "<br />"; // compatibilté avec IE7 ?>
<form method="post" name="consultation_2" action="consultation.php" style="float: left;">
<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
Sélectionner un élève&nbsp;:&nbsp;
	<input type="hidden" name="id_classe" value="<?php echo $id_classe; ?>">
	<select name="ele_id" onChange="document.forms['consultation_2'].submit();">
		<option value="0">(aucun)</option>
<?php
	$r_sql="SELECT DISTINCT `ele_id`,`nom`,`prenom`,`id_eleve`,`periode` FROM `eleves`,`j_eleves_classes` WHERE (`eleves`.`login`=`j_eleves_classes`.`login` AND `j_eleves_classes`.`id_classe`=".$id_classe." AND `periode`='".derniere_periode_active($id_classe)."') ORDER BY `nom`,`prenom`";
	$R_eleves=mysqli_query($mysqli, $r_sql);
	while($un_eleve=mysqli_fetch_assoc($R_eleves))
		{
?>
		<option value="<?php echo $un_eleve['ele_id'] ?>" <?php if ($un_eleve['ele_id']==$ele_id) {echo "selected=\"selected\""; $eleve_selectionne=$un_eleve['prenom']." ".$un_eleve['nom']; $id_eleve=$un_eleve['id_eleve'];} ?>><?php echo $un_eleve['prenom']." ".$un_eleve['nom']; ?></option>
<?php
	}
?>
	</select>
</form>
<?php
if ($ele_id!=="0")
	{
	$identite_eleve=$eleve_selectionne." ".$classe_selectionnee;
?>
	<form method="post" action="saisie_par_eleve.php" target="_blank" style="float: left; margin-left: 20px;">
	<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
	<input type="hidden" name="id_classe" value="<?php echo $id_classe; ?>">
	<input type="hidden" name="ele_id" value="<?php echo $ele_id; ?>">
	<button name="saisie" value="saisir" type="submit"> Saisir un nouveau mot </button>
	</form>
<?php
	}
?>
</h3>
<br />

<?php
if ($ele_id!=="0" && getSettingAOui('active_module_trombinoscopes') && ($carnets_de_liaison_affiche_trombines_eleves=="oui"))
	{
	$elenoet=elenoet_par_ele_id($ele_id);
	$ele_photo=nom_photo($elenoet,$repertoire="eleves",$arbo=2);
	if ($ele_photo!=NULL)
		{
		$dimensions=getimagesize($ele_photo);
		$rapport_largeur=($dimensions[0]==0)?1:($width_trombine/$dimensions[1]);
		$rapport_longueur=($dimensions[1]==0)?1:($width_trombine/$dimensions[1]);
		$rapport=max($rapport_largeur,$rapport_longueur);
		?>
		<div id="trombine" style="position: absolute; margin-left: 595px; margin-top: 20px">
		<img style="width: <?php echo floor($dimensions[0]*$rapport); ?>px; height: <?php echo floor($dimensions[1]*$rapport); ?>px; vertical-align: top;" src="<?php echo $ele_photo; ?>">
		</div>
		<?php
		}
	}
?>


<?php
include("affiche_carnet.inc.php");
?>

<br /><br />
</div>
<?php
include("../../lib/footer.inc.php");

// retour d'envoi de réponse
if (isset($script_bilan_envoi_mail)) echo $script_bilan_envoi_mail;
?>