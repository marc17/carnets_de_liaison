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

// l'utilisateur est-il autorisé à exécuter ce script ?
include("verification_autorisations.inc.php");

function compare_index($a, $b)
// l'index -1 est plus petit que tout autre 
{
    if ($a == $b) return 0;
	if ($a==-1) return 1 ;
		else if ($b==-1) return -1 ;
	// sinon on compare les valeurs absolues (mais en principe aucun index est négatif, sauf -1)
    return (abs($a )> abs($b)) ? -1 : 1;
}

function supp_element_liste($element,$liste)
	{
	$t_liste=explode(",",$liste);
	if (in_array($element,$t_liste)) unset($t_liste[array_search($element,$t_liste)]);
	return implode(",",$t_liste);
	}

// a priori pas d'erreur
$message_d_erreur="";


//**************** EN-TETE *****************
$style_specifique="mod_plugins/carnets_de_liaison/styles";
$titre_page = "Carnets de liaison : administration";
unset($_SESSION['ariane']);
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<p class=bold><a href="admin.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/>Retour</a>
</p>

<?php
if ($message_d_erreur!="")
	{
?>
	<p style="color: red; margin-left: 40px;"><?php echo $message_d_erreur; ?></p>
<?php
	}
?>

	<h2>Carnets de liaison : traitement des incohérences</h2>

<?php

// corrections de la table 'carnets_de_liaison_mots' 
// suppression des fin de listes '0' et remplacement par '-1' (scories bug version 1.4)
$nb_corrections=0;
$r_sql="SELECT `id_mot`,`type`,`ids_destinataires` FROM `carnets_de_liaison_mots` WHERE (`type`='prof' OR `type`='eleve')";
$R_listes=mysqli_query($mysqli, $r_sql);
while ($une_liste=mysqli_fetch_assoc($R_listes))
	{
	$correction=false;
	$ids_destinataires=$une_liste['ids_destinataires'];
	if (strlen($ids_destinataires)>=5 && substr($ids_destinataires,-5)==",0,-1")
		{
		$correction=true;
		$ids_destinataires=substr($ids_destinataires,0,strlen($ids_destinataires)-4)."-1";
		}
	if (substr($ids_destinataires,-2)==",0")
		{
		$correction=true;
		$ids_destinataires=substr($ids_destinataires,0,strlen($ids_destinataires)-1)."-1";
		}
	if ($ids_destinataires=="0")
		{
		$correction=true;
		$ids_destinataires="-1";
		}
	if ($ids_destinataires=="0,-1")
		{
		$correction=true;
		$ids_destinataires="-1";
		}
	if ($correction)
		{
		$nb_corrections++;
		$r_sql="UPDATE `carnets_de_liaison_mots` SET `ids_destinataires`='".$ids_destinataires."' WHERE `id_mot`='".$une_liste['id_mot']."'";
		if (!mysqli_query($mysqli, $r_sql)) $message_maj_tables="Impossible de modifier la table `carnets_de_liaison_mots` : ".mysqli_error($mysqli)."<br />";
		}
	}


# vérifier que chaque liste se termine bien par '-1'

$nb_listes_non_conformes=0;

$r_sql="SELECT `id_mot`,`ids_destinataires` FROM `carnets_de_liaison_mots`";
$R_mots=mysqli_query($mysqli, $r_sql);
while($un_mot=mysqli_fetch_assoc($R_mots))
	{
	$ids_destinataires="";
	if ($un_mot['ids_destinataires']=="") $ids_destinataires="-1";
		else
		{
		$t_destinataires=explode(",",$un_mot['ids_destinataires']);
		if (end($t_destinataires)!=-1) $ids_destinataires=$un_mot['ids_destinataires'].",-1";
		}
	if ($ids_destinataires!="")
		{
		$nb_listes_non_conformes++;
		$r_sql="UPDATE `carnets_de_liaison_mots` SET `ids_destinataires`='".$ids_destinataires."' WHERE `id_mot`='".$un_mot['id_mot']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

$r_sql="SELECT `ele_id`,`ids_mots` FROM `carnets_de_liaison_eleve`";
$R_eleves=mysqli_query($mysqli, $r_sql);
while($un_eleve=mysqli_fetch_assoc($R_eleves))
	{
	$ids_mots="";
	if ($un_eleve['ids_mots']=="") $ids_mots="-1";
		else
		{
		$t_destinataires=explode(",",$un_eleve['ids_mots']);
		if (end($t_destinataires)!=-1) $ids_mots=$un_eleve['ids_mots'].",-1";
		}
	if ($ids_mots!="")
		{
		$nb_listes_non_conformes++;
		$r_sql="UPDATE `carnets_de_liaison_eleve` SET `ids_mots`='".$ids_mots."' WHERE `ele_id`='".$un_eleve['ele_id']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

$r_sql="SELECT `id_classe`,`ids_mots` FROM `carnets_de_liaison_classe`";
$R_classes=mysqli_query($mysqli, $r_sql);
while($une_classe=mysqli_fetch_assoc($R_classes))
	{
	$ids_mots="";
	if ($une_classe['ids_mots']=="") $ids_mots="-1";
		else
		{
		$t_destinataires=explode(",",$une_classe['ids_mots']);
		if (end($t_destinataires)!=-1) $ids_mots=$une_classe['ids_mots'].",-1";
		}
	if ($ids_mots!="")
		{
		$nb_listes_non_conformes++;
		$r_sql="UPDATE `carnets_de_liaison_classe` SET `ids_mots`='".$ids_mots."' WHERE `id_classe`='".$une_classe['id_classe']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

$r_sql="SELECT `id_groupe`,`ids_mots` FROM `carnets_de_liaison_groupe`";
$R_groupes=mysqli_query($mysqli, $r_sql);
while($un_groupe=mysqli_fetch_assoc($R_groupes))
	{
	$ids_mots="";
	if ($un_groupe['ids_mots']=="") $ids_mots="-1";
		else
		{
		$t_destinataires=explode(",",$un_groupe['ids_mots']);
		if (end($t_destinataires)!=-1) $ids_mots=$un_groupe['ids_mots'].",-1";
		}
	if ($ids_mots!="")
		{
		$nb_listes_non_conformes++;
		$r_sql="UPDATE `carnets_de_liaison_groupe` SET `ids_mots`='".$ids_mots."' WHERE `id_groupe`='".$un_groupe['id_groupe']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

$r_sql="SELECT `id_aid`,`ids_mots` FROM `carnets_de_liaison_aid`";
$R_aid=mysqli_query($mysqli, $r_sql);
while($une_aid=mysqli_fetch_assoc($R_aid))
	{
	$ids_mots="";
	if ($une_aid['ids_mots']=="") $ids_mots="-1";
		else
		{
		$t_destinataires=explode(",",$une_aid['ids_mots']);
		if (end($t_destinataires)!=-1) $ids_mots=$une_aid['ids_mots'].",-1";
		}
	if ($ids_mots!="")
		{
		$nb_listes_non_conformes++;
		$r_sql="UPDATE `carnets_de_liaison_aid` SET `ids_mots`='".$ids_mots."' WHERE `id_aid`='".$une_aid['id_aid']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}


# suppression des doublons

$nb_doublons=0;

# suppression des doublons dans le champ ids_destinataires de la table carnets_de_liaison_mots

$r_sql="SELECT `id_mot`,`ids_destinataires` FROM `carnets_de_liaison_mots`";
$R_mots=mysqli_query($mysqli, $r_sql);
while($un_mot=mysqli_fetch_assoc($R_mots))
	{
	$t_initial_destinataires=explode(",",$un_mot['ids_destinataires']);
	// usort pour placer le ou les -1 en fin de tableau
	usort($t_initial_destinataires,"compare_index");
	$t_final_destinataires=array_unique($t_initial_destinataires);
	$diff=count($t_initial_destinataires)-count($t_final_destinataires);
	if ($diff!=0)
		{
		$nb_doublons+=$diff;
		$r_sql="UPDATE `carnets_de_liaison_mots` SET `ids_destinataires`='".implode(",",$t_final_destinataires)."' WHERE `id_mot`='".$un_mot['id_mot']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# suppression des doublons dans le champ ids_mots de la table carnets_de_liaison_eleve
$r_sql="SELECT `ele_id`,`ids_mots` FROM `carnets_de_liaison_eleve`";
$R_eleves=mysqli_query($mysqli, $r_sql);
while($un_eleve=mysqli_fetch_assoc($R_eleves))
	{
	$t_initial_mots=explode(",",$un_eleve['ids_mots']);
	// usort pour placer le ou les -1 en fin de tableau
	usort($t_initial_mots,"compare_index");
	$t_final_mots=array_unique($t_initial_mots);
	$diff=count($t_initial_mots)-count($t_final_mots);
	if ($diff!=0)
		{
		$nb_doublons+=$diff;
		$r_sql="UPDATE `carnets_de_liaison_eleve` SET `ids_mots`='".implode(",",$t_final_mots)."' WHERE `ele_id`='".$un_eleve['ele_id']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# suppression des doublons dans le champ ids_mots de la table carnets_de_liaison_classe
$r_sql="SELECT `id_classe`,`ids_mots` FROM `carnets_de_liaison_classe`";
$R_classes=mysqli_query($mysqli, $r_sql);
while($une_classe=mysqli_fetch_assoc($R_classes))
	{
	$t_initial_mots=explode(",",$une_classe['ids_mots']);
	// usort pour placer le ou les -1 en fin de tableau
	usort($t_initial_mots,"compare_index");
	$t_final_mots=array_unique($t_initial_mots);
	$diff=count($t_initial_mots)-count($t_final_mots);
	if ($diff!=0)
		{
		$nb_doublons+=$diff;
		$r_sql="UPDATE `carnets_de_liaison_classe` SET `ids_mots`='".implode(",",$t_final_mots)."' WHERE `id_classe`='".$une_classe['id_classe']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# suppression des doublons dans le champ ids_mots de la table carnets_de_liaison_groupe
$r_sql="SELECT `id_groupe`,`ids_mots` FROM `carnets_de_liaison_groupe`";
$R_groupes=mysqli_query($mysqli, $r_sql);
while($un_groupe=mysqli_fetch_assoc($R_groupes))
	{
	$t_initial_mots=explode(",",$un_groupe['ids_mots']);
	// usort pour placer le ou les -1 en fin de tableau
	usort($t_initial_mots,"compare_index");
	$t_final_mots=array_unique($t_initial_mots);
	$diff=count($t_initial_mots)-count($t_final_mots);
	if ($diff!=0)
		{
		$nb_doublons+=$diff;
		$r_sql="UPDATE `carnets_de_liaison_groupe` SET `ids_mots`='".implode(",",$t_final_mots)."' WHERE `id_groupe`='".$un_groupe['id_groupe']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# suppression des doublons dans le champ ids_mots de la table carnets_de_liaison_aid
$r_sql="SELECT `id_aid`,`ids_mots` FROM `carnets_de_liaison_aid`";
$R_aid=mysqli_query($mysqli, $r_sql);
while($une_aid=mysqli_fetch_assoc($R_aid))
	{
	$t_initial_mots=explode(",",$une_aid['ids_mots']);
	// usort pour placer le ou les -1 en fin de tableau
	usort($t_initial_mots,"compare_index");
	$t_final_mots=array_unique($t_initial_mots);
	$diff=count($t_initial_mots)-count($t_final_mots);
	if ($diff!=0)
		{
		$nb_doublons+=$diff;
		$r_sql="UPDATE `carnets_de_liaison_aid` SET `ids_mots`='".implode(",",$t_final_mots)."' WHERE `id_aid`='".$une_aid['id_aid']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

// suppressions des incohérences

$nb_incoherences=0;

# élèves destinataires d'un mot mais dont le mot n'est pas dans sa liste de mots -> ajouter id_mot à ids_mots
$r_sql="SELECT `id_mot`,`ele_id` FROM `carnets_de_liaison_mots`,`carnets_de_liaison_eleve` WHERE (`type`='eleve' AND FIND_IN_SET(`ele_id`,`ids_destinataires`) AND NOT FIND_IN_SET(`id_mot`,`ids_mots`))";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if(!$R_incoherences) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
$nb_trouves=mysqli_num_rows($R_incoherences);
if ($nb_trouves>0)
	{
	while($une_incoherence=mysqli_fetch_assoc($R_incoherences))
		{
		$nb_incoherences+=$nb_trouves;
		// il faut récupérer de nouveau la liste ids_mots qui a pu déjà être modifiée dans cette boucle
		$r_sql="SELECT `ids_mots` FROM `carnets_de_liaison_eleve` WHERE `ele_id`='".$une_incoherence['ele_id']."' LIMIT 1";
		$t_ids_mots=mysqli_fetch_assoc(mysqli_query($mysqli, $r_sql));
		$r_sql="UPDATE `carnets_de_liaison_eleve` SET `ids_mots`='".$une_incoherence['id_mot'].",".$t_ids_mots['ids_mots']."' WHERE `ele_id`='".$une_incoherence['ele_id']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# élèves ayant un mot dans sa liste de mots mais n'étant pas destinataire du mot -> retirer id_mot de ids_mots
$r_sql="SELECT `id_mot`,`ele_id`,`ids_mots` FROM `carnets_de_liaison_mots`,`carnets_de_liaison_eleve` WHERE (`type`='eleve' AND NOT FIND_IN_SET(`ele_id`,`ids_destinataires`) AND FIND_IN_SET(`id_mot`,`ids_mots`))";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if(!$R_incoherences) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
$nb_trouves=mysqli_num_rows($R_incoherences);
if ($nb_trouves>0)
	{
	while($une_incoherence=mysqli_fetch_assoc($R_incoherences))
		{
		$nb_incoherences+=$nb_trouves;
		// il faut récupérer de nouveau la liste ids_mots qui a pu déjà être modifiée dans cette boucle
		$r_sql="SELECT `ids_mots` FROM `carnets_de_liaison_eleve` WHERE `ele_id`='".$une_incoherence['ele_id']."' LIMIT 1";
		$t_ids_mots=mysqli_fetch_assoc(mysqli_query($mysqli, $r_sql));
		$r_sql="UPDATE `carnets_de_liaison_eleve` SET `ids_mots`='".supp_element_liste($une_incoherence['id_mot'],$t_ids_mots['ids_mots'])."' WHERE `ele_id`='".$une_incoherence['ele_id']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# classes destinataires d'un mot mais dont le mot n'est pas dans sa liste de mots -> ajouter id_mot à ids_mots
$r_sql="SELECT `id_mot`,`id_classe` FROM `carnets_de_liaison_mots`,`carnets_de_liaison_classe` WHERE (`type`='classe' AND FIND_IN_SET(`id_classe`,`ids_destinataires`) AND NOT FIND_IN_SET(`id_mot`,`ids_mots`))";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if(!$R_incoherences) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
$nb_trouves=mysqli_num_rows($R_incoherences);
if ($nb_trouves>0)
	{
	while($une_incoherence=mysqli_fetch_assoc($R_incoherences))
		{
		$nb_incoherences+=$nb_trouves;
		// il faut récupérer de nouveau la liste ids_mots qui a pu déjà être modifiée dans cette boucle
		$r_sql="SELECT `ids_mots` FROM `carnets_de_liaison_classe` WHERE `id_classe`='".$une_incoherence['id_classe']."' LIMIT 1";
		$t_ids_mots=mysqli_fetch_assoc(mysqli_query($mysqli, $r_sql));
		$r_sql="UPDATE `carnets_de_liaison_classe` SET `ids_mots`='".$une_incoherence['id_mot'].",".$t_ids_mots['ids_mots']."' WHERE `id_classe`='".$une_incoherence['id_classe']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# classes ayant un mot dans sa liste de mots mais n'étant pas destinataire du mot -> retirer id_mot de ids_mots
$r_sql="SELECT `id_mot`,`id_classe`,`ids_mots` FROM `carnets_de_liaison_mots`,`carnets_de_liaison_classe` WHERE (`type`='classe' AND NOT FIND_IN_SET(`id_classe`,`ids_destinataires`) AND FIND_IN_SET(`id_mot`,`ids_mots`))";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if(!$R_incoherences) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
$nb_trouves=mysqli_num_rows($R_incoherences);
if ($nb_trouves>0)
	{
	while($une_incoherence=mysqli_fetch_assoc($R_incoherences))
		{
		$nb_incoherences+=$nb_trouves;
		// il faut récupérer de nouveau la liste ids_mots qui a pu déjà être modifiée dans cette boucle
		$r_sql="SELECT `ids_mots` FROM `carnets_de_liaison_classe` WHERE `id_classe`='".$une_incoherence['id_classe']."' LIMIT 1";
		$t_ids_mots=mysqli_fetch_assoc(mysqli_query($mysqli, $r_sql));
		$r_sql="UPDATE `carnets_de_liaison_classe` SET `ids_mots`='".supp_element_liste($une_incoherence['id_mot'],$t_ids_mots['ids_mots'])."' WHERE `id_classe`='".$une_incoherence['id_classe']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# aid destinataires d'un mot mais dont le mot n'est pas dans sa liste de mots -> ajouter id_mot à ids_mots
$r_sql="SELECT `id_mot`,`id_aid` FROM `carnets_de_liaison_mots`,`carnets_de_liaison_aid` WHERE (`type`='aid' AND FIND_IN_SET(`id_aid`,`ids_destinataires`) AND NOT FIND_IN_SET(`id_mot`,`ids_mots`))";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if(!$R_incoherences) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
$nb_trouves=mysqli_num_rows($R_incoherences);
if ($nb_trouves>0)
	{
	while($une_incoherence=mysqli_fetch_assoc($R_incoherences))
		{
		$nb_incoherences+=$nb_trouves;
		// il faut récupérer de nouveau la liste ids_mots qui a pu déjà être modifiée dans cette boucle
		$r_sql="SELECT `ids_mots` FROM `carnets_de_liaison_aid` WHERE `id_aid`='".$une_incoherence['id_aid']."' LIMIT 1";
		$t_ids_mots=mysqli_fetch_assoc(mysqli_query($mysqli, $r_sql));
		$r_sql="UPDATE `carnets_de_liaison_aid` SET `ids_mots`='".$une_incoherence['id_mot'].",".$t_ids_mots['ids_mots']."' WHERE `id_aid`='".$une_incoherence['id_aid']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# aid ayant un mot dans sa liste de mots mais n'étant pas destinataire du mot -> retirer id_mot de ids_mots
$r_sql="SELECT `id_mot`,`id_aid`,`ids_mots` FROM `carnets_de_liaison_mots`,`carnets_de_liaison_aid` WHERE (`type`='aid' AND NOT FIND_IN_SET(`id_aid`,`ids_destinataires`) AND FIND_IN_SET(`id_mot`,`ids_mots`))";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if(!$R_incoherences) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
$nb_trouves=mysqli_num_rows($R_incoherences);
if ($nb_trouves>0)
	{
	while($une_incoherence=mysqli_fetch_assoc($R_incoherences))
		{
		$nb_incoherences+=$nb_trouves;
		// il faut récupérer de nouveau la liste ids_mots qui a pu déjà être modifiée dans cette boucle
		$r_sql="SELECT `ids_mots` FROM `carnets_de_liaison_aid` WHERE `id_aid`='".$une_incoherence['id_aid']."' LIMIT 1";
		$t_ids_mots=mysqli_fetch_assoc(mysqli_query($mysqli, $r_sql));
		$r_sql="UPDATE `carnets_de_liaison_aid` SET `ids_mots`='".supp_element_liste($une_incoherence['id_mot'],$t_ids_mots['ids_mots'])."' WHERE `id_aid`='".$une_incoherence['id_aid']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# groupes destinataires d'un mot mais dont le mot n'est pas dans sa liste de mots -> ajouter id_mot à ids_mots
$r_sql="SELECT `id_mot`,`id_groupe` FROM `carnets_de_liaison_mots`,`carnets_de_liaison_groupe` WHERE (`type`='groupe' AND FIND_IN_SET(`id_groupe`,`ids_destinataires`) AND NOT FIND_IN_SET(`id_mot`,`ids_mots`))";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if(!$R_incoherences) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
$nb_trouves=mysqli_num_rows($R_incoherences);
if ($nb_trouves>0)
	{
	while($une_incoherence=mysqli_fetch_assoc($R_incoherences))
		{
		$nb_incoherences+=$nb_trouves;
		// il faut récupérer de nouveau la liste ids_mots qui a pu déjà être modifiée dans cette boucle
		$r_sql="SELECT `ids_mots` FROM `carnets_de_liaison_groupe` WHERE `id_groupe`='".$une_incoherence['id_groupe']."' LIMIT 1";
		$t_ids_mots=mysqli_fetch_assoc(mysqli_query($mysqli, $r_sql));
		$r_sql="UPDATE `carnets_de_liaison_groupe` SET `ids_mots`='".$une_incoherence['id_mot'].",".$t_ids_mots['ids_mots']."' WHERE `id_groupe`='".$une_incoherence['id_groupe']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# groupes ayant un mot dans sa liste de mots mais n'étant pas destinataire du mot -> retirer id_mot de ids_mots
$r_sql="SELECT `id_mot`,`id_groupe`,`ids_mots` FROM `carnets_de_liaison_mots`,`carnets_de_liaison_groupe` WHERE (`type`='groupe' AND NOT FIND_IN_SET(`id_groupe`,`ids_destinataires`) AND FIND_IN_SET(`id_mot`,`ids_mots`))";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if(!$R_incoherences) $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
$nb_trouves=mysqli_num_rows($R_incoherences);
if ($nb_trouves>0)
	{
	while($une_incoherence=mysqli_fetch_assoc($R_incoherences))
		{
		$nb_incoherences+=$nb_trouves;
		// il faut récupérer de nouveau la liste ids_mots qui a pu déjà être modifiée dans cette boucle
		$r_sql="SELECT `ids_mots` FROM `carnets_de_liaison_groupe` WHERE `id_groupe`='".$une_incoherence['id_groupe']."' LIMIT 1";
		$t_ids_mots=mysqli_fetch_assoc(mysqli_query($mysqli, $r_sql));
		$r_sql="UPDATE `carnets_de_liaison_groupe` SET `ids_mots`='".supp_element_liste($une_incoherence['id_mot'],$t_ids_mots['ids_mots'])."' WHERE `id_groupe`='".$une_incoherence['id_groupe']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}


# orphelins dans la table carnets_de_liaison_reponses
$r_sql="SELECT `carnets_de_liaison_reponses`.`id_mot` `id_orphelin`, `carnets_de_liaison_mots`.`id_mot`FROM `carnets_de_liaison_reponses`
	LEFT JOIN `carnets_de_liaison_mots` ON `carnets_de_liaison_reponses`.`id_mot`=`carnets_de_liaison_mots`.`id_mot`
	WHERE `carnets_de_liaison_mots`.`id_mot` is NULL";
$R_orphelins=mysqli_query($mysqli, $r_sql);
$nb_orphelins=mysqli_num_rows($R_orphelins);
if ($nb_orphelins>0)
	{
	while($un_orphelin=mysqli_fetch_assoc($R_orphelins))
		{
		$r_sql="DELETE FROM `carnets_de_liaison_reponses` WHERE `id_mot`='".$un_orphelin['id_orphelin']."'";
		if (!mysqli_query($mysqli, $r_sql))  $message_d_erreur.="Erreur MySQL : <br />".$r_sql." => ".mysqli_error($mysqli)."<br />";
		}
	}

# orphelins dans le dossier "documents"
$nb_docs_orphelins=0;
$d_documents = opendir("documents"); 
while(false!==($document = readdir($d_documents)))
	{
	$doc_a_supprimer=false;
	// premier filtre : les documents ne sont pas des dossiers et n'ont pas d'extension
	if ((!is_dir($document)) && (basename($document)==$document))
		{
		$t_document=explode("_",$document);
		// second filtre : les noms sont de la forme "prefixe_idmot"
		if (count($t_document)==2)
			{
			$prefixe=$t_document[0];
			$id_mot=$t_document[1];
			$r_sql="SELECT `document` FROM `carnets_de_liaison_mots` WHERE `id_mot`='".$id_mot."' LIMIT 1";
			$R_mot=mysqli_query($mysqli, $r_sql);
			if (mysqli_num_rows($R_mot)==0)
				$doc_a_supprimer=true;
				else
					{
					$un_mot=mysqli_fetch_assoc($R_mot);
					if ($un_mot['document']=="")
						$doc_a_supprimer=true;
						else
							{
							$t_un_document=unserialize($un_mot['document']);
							if ($prefixe!=$t_un_document['prefixe']) $doc_a_supprimer=true;
							}
					}
			}
		}
if ($doc_a_supprimer)
		{
		// on supprime ce document orphelin
		if (!unlink("documents/".$document))
		$message_d_erreur.="Impossible de supprimer le fichier documents/".$document."<br />";
		$nb_docs_orphelins++;
		}
	}
closedir($d_documents);

// cohérence des noms d'AID
$nb_incoherences_noms_aid=0;
$r_sql="UPDATE `carnets_de_liaison_mots`,`aid`,`aid_config` SET `carnets_de_liaison_mots`.`ensemble_destinataire`=`aid_config`.`nom_complet` WHERE (`carnets_de_liaison_mots`.`type`='aid' AND FIND_IN_SET(`aid`.`id` ,`carnets_de_liaison_mots`.`ids_destinataires`)=1 AND `aid`.`indice_aid`=`aid_config`.`indice_aid`)";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if (mysqli_affected_rows($mysqli)>0) $nb_incoherences_noms_aid=mysqli_affected_rows($mysqli);

$r_sql="UPDATE `carnets_de_liaison_aid`,`aid`,`aid_config` SET `carnets_de_liaison_aid`.`nom_aid`=`aid_config`.`nom_complet` WHERE (`aid`.`id` =`carnets_de_liaison_aid`.`id_aid` AND `aid`.`indice_aid`=`aid_config`.`indice_aid`)";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if (mysqli_affected_rows($mysqli)>0) $nb_incoherences_noms_aid+=mysqli_affected_rows($mysqli);


// cohérence des noms de groupe
$nb_incoherences_noms_groupe=0;
$r_sql="UPDATE `carnets_de_liaison_mots`,`groupes` SET `carnets_de_liaison_mots`.`ensemble_destinataire`=`groupes`.`description` WHERE (`carnets_de_liaison_mots`.`type`='groupe' AND FIND_IN_SET(`groupes`.`id` ,`carnets_de_liaison_mots`.`ids_destinataires`)=1)";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if (mysqli_affected_rows($mysqli)>0) $nb_incoherences_noms_groupe=mysqli_affected_rows($mysqli);

$r_sql="UPDATE `carnets_de_liaison_groupe`,`groupes` SET `carnets_de_liaison_groupe`.`nom_groupe`=`groupes`.`description` WHERE (`groupes`.`id` =`carnets_de_liaison_groupe`.`id_groupe`)";
$R_incoherences=mysqli_query($mysqli, $r_sql);
if (mysqli_affected_rows($mysqli)>0) $nb_incoherences_noms_groupe+=mysqli_affected_rows($mysqli);

?>

<div style="margin-left: 20px;">

<?php
// bilan

if ($nb_corrections>0) echo "Corrections apportées à la table `carnets_de_liaison_mots` : ".$nb_corrections."<br />";

if ($nb_listes_non_conformes>0) echo "Nombre de listes non conformes corrigées : ".$nb_listes_non_conformes."<br />";
	else echo "Aucune liste non conforme.<br />";

if ($nb_doublons>0) echo "Nombre de doublons supprimés : ".$nb_doublons."<br />";
	else echo "Aucun doublon.<br />";

if ($nb_incoherences>0) echo "Nombre d'incohérences supprimées : ".$nb_incoherences."<br />";
	else echo "Aucune incohérence.<br />";

if ($nb_orphelins>0) echo "Nombre d'enregistrements orphelins supprimés dans la table \"carnets_de_liaison_reponses\" : ".$nb_orphelins."<br />";
	else echo "Aucun enregistrement orphelin dans la table \"carnets_de_liaison_reponses\".<br />";

if ($nb_docs_orphelins>0) echo "Nombre de documents orphelins supprimés dans le dossier \"documents\" : ".$nb_docs_orphelins."<br />";
	else echo "Aucun document orphelin dans le dossier \"documents\".<br />";

if ($nb_incoherences_noms_aid>0) echo "Nombre d'incohérences corrigées dans les noms d'AID : ".$nb_incoherences_noms_aid."<br />";
	else echo "Aucune incohérence dans les noms d'AID.<br />";

if ($nb_incoherences_noms_groupe>0) echo "Nombre d'incohérences corrigées dans les noms de groupe : ".$nb_incoherences_noms_groupe."<br />";
	else echo "Aucune incohérence dans les noms de groupe.<br />";

?>
<br /><br />
</div>

<?php
include("../../lib/footer.inc.php");
?>