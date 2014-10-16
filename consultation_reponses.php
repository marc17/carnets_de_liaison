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


// si l'appel se fait avec passage de paramètre alors test du token
if ((function_exists("check_token")) && ((count($_POST)<>0) || (count($_GET)<>0))) check_token();

// initialisation de variables
$id_classe=isset($_POST['id_classe'])?$_POST['id_classe']:0;
$ele_id=isset($_POST['ele_id'])?$_POST['ele_id']:0;

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

// a priori pas d'erreur
$message_d_erreur="";

// mail et civilité de l'utilisateur
$r_sql="SELECT `email`,`show_email`,`civilite` FROM `utilisateurs` WHERE `login`='".$_SESSION['login']."' LIMIT 1";
$R_coords=mysqli_query($mysqli,$r_sql);
$t_coords=mysqli_fetch_assoc($R_coords);
$mail_utilisateur=$t_coords['email'];
$show_email_utilisateur=$t_coords['show_email'];
$civilite_utilisateur=$t_coords['civilite'];

// envoi d'une réponse
//include("envoi_reponse.inc.php");



//**************** EN-TETE *****************
$style_specifique="mod_plugins/carnets_de_liaison/styles";
$titre_page = "Carnets de liaison : réponses";
unset($_SESSION['ariane']);
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<p class=bold><a href="../../accueil.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/>Retour à l'accueil</a>
 | <a href="consultation.php">Consultation</a>
 | <a href="consultation_mots_recus.php">Mots reçus</a>
 | <a href="saisie.php">Saisie</a>
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

<h2>Réponses reçues</h2>

<?php
	$r_sql="SELECT DISTINCT `utilisateurs`.`civilite`,`utilisateurs`.`nom`,`utilisateurs`.`prenom`,`carnets_de_liaison_mots`.*,`carnets_de_liaison_reponses`.`id_mot`,`carnets_de_liaison_reponses`.`date` as `r_date` FROM `utilisateurs`,`carnets_de_liaison_mots`,`carnets_de_liaison_reponses`
	WHERE(`carnets_de_liaison_mots`.`id_mot`=`carnets_de_liaison_reponses`.`id_mot` AND `carnets_de_liaison_mots`.`visible`='1'
	AND `utilisateurs`.`login`=`carnets_de_liaison_mots`.`login_redacteur` AND `carnets_de_liaison_mots`.`login_redacteur`='".$_SESSION['login']."')
	ORDER BY   `r_date` DESC, `carnets_de_liaison_mots`.`id_mot` DESC";
	$R_mots=mysqli_query($mysqli, $r_sql);
	if(mysqli_num_rows($R_mots)>0)
		{
		while($un_mot=mysqli_fetch_assoc($R_mots))
			{
			$t_destinataires=explode(',',$un_mot['ids_destinataires']);
			// si le mot figure dans le carnet d'un seul élève on affiche son identité
			if ((count($t_destinataires)==2) && ($un_mot['type']=="eleve"))
				{
				$r_sql="SELECT `eleves`.`prenom`,`eleves`.`nom`,`eleves`.`login`,`eleves`.`id_eleve`,`eleves`.`ele_id`,`j_eleves_classes`.*,`classes`.`id`,`classes`.`nom_complet` FROM `eleves`,`j_eleves_classes`,`classes`
				WHERE `eleves`.`ele_id`='".$t_destinataires[0]."' AND `eleves`.`login`=`j_eleves_classes`.`login` AND `j_eleves_classes`.`id_classe`=`classes`.`id` ORDER BY `j_eleves_classes`.`periode` DESC LIMIT 1";
				if ($R_eleve=mysqli_query($mysqli, $r_sql))
					{
					$eleve=mysqli_fetch_assoc($R_eleve);
					$afficher_identite_eleve=true;
					$identite_eleve=$eleve['prenom']." ".$eleve['nom']." ".$eleve['nom_complet'];
					$ele_id=$eleve['ele_id'];
					/*
					$id_eleve=$eleve['id_eleve'];
					$id_classe=$eleve['id'];
					*/
					}
					else
						{
						$afficher_identite_eleve=false;
						$identite_eleve="[identité élève non déterminée]";
						$ele_id=0;
						/*
						$id_eleve=0;
						$id_classe=0;
						*/
						};
				}
				else $afficher_identite_eleve=false;
			// nom(s) de la (des) classe(s)
			$nom_complet_classe="";
			if ($un_mot['type']=="classe")
				{
				$r_sql="SELECT `classes`.`nom_complet` FROM `classes` WHERE FIND_IN_SET(`id`,'".$un_mot['ids_destinataires']."')";
				$R_classe=mysqli_query($mysqli, $r_sql);
				if (mysqli_num_rows($R_classe)!=0)
					{
					while ($classe=mysqli_fetch_assoc($R_classe)) 
						{
						if ($nom_complet_classe!="") $nom_complet_classe.=", ";
						$nom_complet_classe.=$classe['nom_complet'];
						}
					if (mysqli_num_rows($R_classe)==1) $nom_complet_classe="à la classe : ".$nom_complet_classe."";
						else $nom_complet_classe="aux classes : ".$nom_complet_classe."";
					}
				}

			if ($ele_id!==0 && getSettingAOui('active_module_trombinoscopes') && ($carnets_de_liaison_affiche_trombines_eleves=="oui"))
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

			include("affiche_mot.inc.php");
			}
		}
	else
		{
?>
		<h4>Aucune réponse reçue.</h4>
<?php
		}
?>
</div>


<br /><br />
<?php
include("../../lib/footer.inc.php");
?>