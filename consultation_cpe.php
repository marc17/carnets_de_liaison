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
// *** include("verification_autorisations.inc.php");

// tableaux des noms de jours et mois en français
include("jours_et_mois.inc");
include("affiche_calendrier.php");

function date_en_clair($d)
	{
	global $noms_mois;
	$t_d=explode("-",$d);
	//il y a une astuce :-)
	return ($t_d[2]+0)." ".$noms_mois[$t_d[1]-1]." ".$t_d[0]; 
	}

function supp_guillements($ch)
	{
	$remplace=array("\""=>"'");
	return strtr($ch,$remplace);
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
$depuis_le=(isset($_GET['depuis_le']))?$depuis_le=$_GET['depuis_le']:(isset($_POST['depuis_le'])?$_POST['depuis_le']:date("Y-m-d",time()-1209600));
$id_classe=(isset($_GET['id_classe']))?$id_classe=$_GET['id_classe']:(isset($_POST['id_classe'])?$_POST['id_classe']:0);

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
$titre_page = "Carnets de liaison : Consultation CPE";
unset($_SESSION['ariane']);
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************
?>

<p class=bold><a href="../../accueil.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/>Retour à l'accueil</a>
 | <a href="consultation.php">Consultation</a>
 | <a href="consultation_mots_recus.php">Mots reçus</a>
 | <a href="consultation_reponses.php">Réponses reçues</a>
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
	<p style="color: red; margin-left: 10px;"><?php echo $message_d_erreur; ?></p>
<?php
	}
?>
		<div id="calendriers" style="float: right; margin-left: 10px; background-color: #cccccc;">
		<table>
			<tr>
				<td style="text-align: center;">
				Cliquer sur une date
				<?php
				$url="consultation_cpe.php?";
				$url.="id_classe=".$id_classe;
				$url.="&depuis_le=";
				affiche_calendrier($depuis_le,$url);
				?>
				</td>
			</tr>
		</table>
		</div>

<h2>Consultation de carnets de liaison</h2>

<h3 style="margin-left: 40px;">
	<div style="width: 760px; height: 120px;">

		<div style="width: 550px; padding-top:30px;">
		<form method="post" name="consultation_1" action="consultation_cpe.php">
		<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		Mots rédigés depuis le&nbsp;:&nbsp;
		<input readonly="readonly" maxlength="40" size="20" value="<?php echo date_en_clair($depuis_le); ?>">
		<input type="hidden" name="id_classe" value="<?php echo $id_classe; ?>">
		<div style="font-size: 10pt; margin-left: 20px;">(utiliser le calendrier pour changer de date)</div>
		</form>
		<form method="post" name="consultation_2" action="consultation_cpe.php" style="float: left;">
		<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		<input type="hidden" name="depuis_le" value="<?php echo $depuis_le; ?>">
		Elèves de la classe&nbsp;:&nbsp;
		<select name="id_classe" onChange="document.forms['consultation_2'].submit();">
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
if ($id_classe!=0)
	{
?>
		<form method="post" action="saisie_par_classe.php" target="_blank" style="float: left; margin-left: 10px;">
		<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
		<input type="hidden" name="id_classe" value="<?php echo $id_classe; ?>">
		<button name="saisie" value="saisir" type="submit"> Saisir un nouveau mot </button>
		</form>
<?php
	}
?>
		</div>
	</div>
</h3>
<br />
<br />

<?php 
	$aucun_mot=true;

	// on récupère la liste des mots adressés à la classe
	$ids_mots_classe="-1";
	if ($id_classe!=0)
		{
		$r_sql="SELECT `carnets_de_liaison_classe`.`ids_mots`,`classes`.`nom_complet` FROM `carnets_de_liaison_classe`,`classes` WHERE (`carnets_de_liaison_classe`.`id_classe`='".$id_classe."'  AND `classes`.`id`='".$id_classe."') LIMIT 1";
		$R_classe=mysqli_query($mysqli, $r_sql);
		if (mysqli_num_rows($R_classe)!=0)
			{
			$classe=mysqli_fetch_assoc($R_classe);
			$ids_mots_classe=$classe['ids_mots'];
			$nom_complet_classe="à la classe ".$classe['nom_complet'];
			}
		}
	// on affiche les mots adressés à la classe

if ($ids_mots_classe!="-1")
	{
	$r_sql="SELECT `utilisateurs`.`civilite`,`utilisateurs`.`nom`,`utilisateurs`.`prenom`,`carnets_de_liaison_mots`.* FROM `utilisateurs`,`carnets_de_liaison_mots` WHERE (`carnets_de_liaison_mots`.`visible`='1' AND `carnets_de_liaison_mots`.`date`>='".$depuis_le."' AND `utilisateurs`.`login`=`carnets_de_liaison_mots`.`login_redacteur` AND (FIND_IN_SET(`carnets_de_liaison_mots`.`id_mot`,'".$ids_mots_classe."'))) ORDER BY `carnets_de_liaison_mots`.`date` DESC, `carnets_de_liaison_mots`.`id_mot` DESC";
	$R_mots=mysqli_query($mysqli, $r_sql);
	if(mysqli_num_rows($R_mots)>0)
		{
		$aucun_mot=false;
		?>
		<hr />
		<h2>Mots adressés à la classe</h2>
		<?php
		$afficher_identite_eleve=false;
		while($un_mot=mysqli_fetch_assoc($R_mots))
			{
			include("affiche_mot.inc.php");
			}
		}
	}

	// Pour chaque élève de la classe
	$r_sql="SELECT DISTINCT `ele_id`,`nom`,`prenom`,`id_eleve`,`elenoet`,`periode` FROM `eleves`,`j_eleves_classes` WHERE (`eleves`.`login`=`j_eleves_classes`.`login` AND `j_eleves_classes`.`id_classe`=".$id_classe." AND `periode`='".derniere_periode_active($id_classe)."') ORDER BY `nom`,`prenom`";
	$R_eleves=mysqli_query($mysqli, $r_sql);
	while($un_eleve=mysqli_fetch_assoc($R_eleves))
		{
		$ele_id=$un_eleve['ele_id'];
		$ele_photo=nom_photo($un_eleve['elenoet'],$repertoire="eleves",$arbo=2);
		// on récupère la liste des mots adressés à l'élève
		$ids_mots_eleve="-1";
		if ($ele_id!==0)
			{
			$r_sql="SELECT `ids_mots` FROM `carnets_de_liaison_eleve` WHERE `ele_id`='".$ele_id."' LIMIT 1";
			$R_eleve=mysqli_query($mysqli, $r_sql);
			if (mysqli_num_rows($R_eleve)!=0)
				{
				$eleve=mysqli_fetch_assoc($R_eleve);
				$ids_mots_eleve=$eleve['ids_mots'];
				// on affiche chaque mot adressé à l'élève
				if ($ids_mots_eleve!="-1")
					{
					$r_sql="SELECT `utilisateurs`.`civilite`,`utilisateurs`.`nom`,`utilisateurs`.`prenom`,`carnets_de_liaison_mots`.* FROM `utilisateurs`,`carnets_de_liaison_mots` WHERE( `carnets_de_liaison_mots`.`visible`='1' AND `carnets_de_liaison_mots`.`date`>='".$depuis_le."' AND`utilisateurs`.`login`=`carnets_de_liaison_mots`.`login_redacteur` AND (FIND_IN_SET(`carnets_de_liaison_mots`.`id_mot`,'".$ids_mots_eleve."'))) ORDER BY `carnets_de_liaison_mots`.`date` DESC, `carnets_de_liaison_mots`.`id_mot` DESC";

					$R_mots=mysqli_query($mysqli, $r_sql);
					if(mysqli_num_rows($R_mots)>0)
						{
						$aucun_mot=false;
						?>
						<hr />
						<?php
						if (getSettingAOui('active_module_trombinoscopes') && ($carnets_de_liaison_affiche_trombines_eleves=="oui"))
							{
							if ($ele_photo!=NULL)
								{
								$dimensions=getimagesize($ele_photo);
								$rapport_largeur=($dimensions[0]==0)?1:($width_trombine/$dimensions[1]);
								$rapport_longueur=($dimensions[1]==0)?1:($width_trombine/$dimensions[1]);
								$rapport=max($rapport_largeur,$rapport_longueur);
								?>
								<div id="trombine" style="position: absolute; margin-left: 565px;">
								<img style="width: <?php echo floor($dimensions[0]*$rapport); ?>px; height: <?php echo floor($dimensions[1]*$rapport); ?>px; vertical-align: top;" src="<?php echo $ele_photo; ?>">
								</div>
								<?php
								}
							}
							?>
						<div id="id_carnet" style="min-width: 550px; width: 550px;">
							<form method="POST" action="consultation.php" target="_blank">
							<h2>Dans le carnet de <?php echo $un_eleve['prenom'] ?> <?php echo $un_eleve['nom'] ?>
								<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
								<input type="hidden" name="ele_id" value="<?php echo $ele_id; ?>">
								<input type="hidden" name="id_classe" value="<?php echo $id_classe; ?>">
								<button type="submit" title=" Voir le carnet " style="border: none; background: none;"><img style="width:16px; height:16px; vertical-align: bottom;" src="bouton_voir_carnet.png"></button>
							</h2><br />
							</form>
						</div>
						<?php
						///$identite_eleve="################";
						$afficher_identite_eleve=false;
						while($un_mot=mysqli_fetch_assoc($R_mots))
							{
							include("affiche_mot.inc.php");
							}
						}
						?>
						<?php
					}
				}
			}
		}
	if (($aucun_mot) && ($id_classe>0))
		echo "<h3 style=\"margin-left: 200px;\">Aucun mot trouvé.</h3>";
?>
<br /><br />
</div>
<?php
include("../../lib/footer.inc.php");

// retour d'envoi de réponse
if (isset($script_bilan_envoi_mail)) echo $script_bilan_envoi_mail;
?>