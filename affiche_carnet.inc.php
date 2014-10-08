<?php
// affiche un carnet de liaison

$nom_complet_classe="";

// on récupère la liste des mots adressés à la classe
$ids_mots_classe="-1";
if ($id_classe!=0)
	{
	$r_sql="SELECT `carnets_de_liaison_classe`.`ids_mots`,`classes`.`nom_complet` FROM `carnets_de_liaison_classe`,`classes` WHERE (`carnets_de_liaison_classe`.`id_classe`='".$id_classe."' AND `classes`.`id`='".$id_classe."') LIMIT 1"; 
	$R_classe=mysqli_query($mysqli, $r_sql);
	if (mysqli_num_rows($R_classe)!=0)
		{
		$classe=mysqli_fetch_assoc($R_classe);
		$ids_mots_classe=$classe['ids_mots'];
		$nom_complet_classe="à la classe ".$classe['nom_complet'];
		}
	}

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
		}
	}

// on récupère la liste des mots adressés aux AID suivies par l'élève
$ids_mots_aid="-1";
if ($ele_id!==0)
	{
	$r_sql="SELECT `carnets_de_liaison_aid`.`ids_mots`,`aid_config`.`nom_complet` FROM `eleves`,`j_aid_eleves`,`aid`,`aid_config`,`carnets_de_liaison_aid` WHERE (`eleves`.`ele_id`='".$ele_id."' AND `eleves`.`login`=`j_aid_eleves`.`login` AND `j_aid_eleves`.`id_aid`=`aid`.`id` AND `aid`.`id`=`carnets_de_liaison_aid`.`id_aid` AND `aid`.`indice_aid`=`aid_config`.`indice_aid`)";
	$R_aid=mysqli_query($mysqli, $r_sql);
	if (mysqli_num_rows($R_aid)!=0)
		{
		while($aid=mysqli_fetch_assoc($R_aid))
			$ids_mots_aid=substr($aid['ids_mots'],0,-2).$ids_mots_aid;
		}
	}

// on récupère la liste des mots adressés aux groupes auxquels appartient l'élève
$ids_mots_groupe="-1";
if ($ele_id!==0)
	{
	$r_sql="SELECT DISTINCT `carnets_de_liaison_groupe`.`ids_mots` FROM `eleves`,`j_eleves_groupes`,`groupes`,`carnets_de_liaison_groupe` WHERE (`eleves`.`ele_id`='".$ele_id."' AND `eleves`.`login`=`j_eleves_groupes`.`login` AND `j_eleves_groupes`.`id_groupe`=`groupes`.`id` AND `groupes`.`id`=`carnets_de_liaison_groupe`.`id_groupe`)";
	$R_groupe=mysqli_query($mysqli, $r_sql);
	if (mysqli_num_rows($R_groupe)!=0)
		{
		while($groupe=mysqli_fetch_assoc($R_groupe))
			$ids_mots_groupe=substr($groupe['ids_mots'],0,-2).$ids_mots_groupe;
		}
	}


?>
<div style="margin-top:30px;">
<?php
if ($ids_mots_classe!="-1" || $ids_mots_eleve!="-1" || $ids_mots_aid!="-1" || $ids_mots_groupe!="-1")
	{
	$r_sql="SELECT `utilisateurs`.`civilite`,`utilisateurs`.`nom`,`utilisateurs`.`prenom`,`carnets_de_liaison_mots`.* FROM `utilisateurs`,`carnets_de_liaison_mots` WHERE( `carnets_de_liaison_mots`.`visible`='1' AND `utilisateurs`.`login`=`carnets_de_liaison_mots`.`login_redacteur` AND (";
	if ($ids_mots_classe!="-1") $r_sql.="FIND_IN_SET(`carnets_de_liaison_mots`.`id_mot`,'".$ids_mots_classe."')";
	if ($ids_mots_eleve!="-1") 
		{
		if ($ids_mots_classe!="-1") $r_sql.=" OR ";
		$r_sql.="FIND_IN_SET(`carnets_de_liaison_mots`.`id_mot`,'".$ids_mots_eleve."')";
		}
	if ($ids_mots_aid!="-1") 
		{
		if (($ids_mots_classe!="-1") || ($ids_mots_eleve!="-1")) $r_sql.=" OR ";
		$r_sql.="FIND_IN_SET(`carnets_de_liaison_mots`.`id_mot`,'".$ids_mots_aid."')";
		}
	if ($ids_mots_groupe!="-1") 
		{
		if (($ids_mots_classe!="-1") || ($ids_mots_eleve!="-1") || ($ids_mots_aid!="-1")) $r_sql.=" OR ";
		$r_sql.="FIND_IN_SET(`carnets_de_liaison_mots`.`id_mot`,'".$ids_mots_groupe."')";
		}
	$r_sql.=")";
	// un responsable ne peut pas voir les mots rédigés par un autre responsable
	if ($_SESSION['statut']=="responsable")
		$r_sql.=" AND ((`carnets_de_liaison_mots`.`type`='prof' AND `carnets_de_liaison_mots`.`login_redacteur`='".$_SESSION['login']."') OR (`carnets_de_liaison_mots`.`type`<>'prof'))";
	$r_sql.=") ORDER BY `carnets_de_liaison_mots`.`date` DESC, `carnets_de_liaison_mots`.`id_mot` DESC";
	$R_mots=mysqli_query($mysqli, $r_sql);
	if(mysqli_num_rows($R_mots)>0)
		{
		$afficher_identite_eleve=false;
		while($un_mot=mysqli_fetch_assoc($R_mots))
			{
			include("affiche_mot.inc.php");
			}
		}
	else
		if (($id_classe!=0) || ($ele_id!==0))
			{
?>
			<h4>Le carnet ne contient aucun mot.</h4>
<?php
			}
	}
else
	if (($id_classe!=0) || ($ele_id!==0))
		{
?>
		<h4>Le carnet ne contient aucun mot.</h4>
<?php
		}
?>
</div>
