<?php

//***********************
// Copyleft Marc Leygnac 
//***********************

function affiche_calendrier($date,$url)
	{
	global $noms_mois;
	//$noms_mois=array('Janvier','F&eacute;vrier','Mars','Avril','Mai','Juin','Juillet','Ao&ucirc;t','Septembre','Octobre','Novembre','D&eacute;cembre');

	$ladate=explode("-",$date);
	$jour = $ladate[2];
	$mois = $ladate[1]; 
	$annee = $ladate[0];
	$d_premier_du_mois = getDate(mktime(0, 0, 0, $mois, 1, $annee));
	$d_dernier_du_mois = getDate(mktime(0, 0, 0, $mois+1, 1, $annee) - 86400);
	$nb_jours_du_mois = $d_dernier_du_mois["mday"];
	$ind_premier_du_mois = $d_premier_du_mois["wday"];
	$date_premier_du_mois_suivant = date("Y-m-d",mktime(0, 0, 0, $mois + 1, 1, $annee));
	$date_dernier_du_mois_precedent = date("Y-m-d",mktime(0, 0, 0, $mois, 1, $annee) - 86400);

	// gestion du token
	$url_token=function_exists("add_token_in_url")?add_token_in_url(false):"";

	echo "<table class='calendrier'>";
	echo "<tbody>";
	echo "		<tr>";
	echo "			<td class='calendrier_cell_titre'><a href='".$url.$date_dernier_du_mois_precedent.$url_token."'><b><<</b></a></td>";
	echo "		<td colspan='5' rowspan='1' class='calendrier_cell_titre'>".$noms_mois[$mois-1]." ".$annee."</td>";
	echo "		<td class='calendrier_cell_titre'><a href='".$url.$date_premier_du_mois_suivant.$url_token."'><b>>></b></a></td>";
	echo "		</tr>";
	echo "		<tr class='calendrier_cell'>";
	echo "			<td class='calendrier_cell_dim'>D</td>";
	echo "			<td class='calendrier_cell'>L</td>";
	echo "			<td class='calendrier_cell'>M</td>";
	echo "			<td class='calendrier_cell'>M</td>";
	echo "			<td class='calendrier_cell'>J</td>";
	echo "			<td class='calendrier_cell'>V</td>";
	echo "			<td class='calendrier_cell'>S</td>";
	echo "		</tr>";

	$b_sup=$nb_jours_du_mois+$ind_premier_du_mois;
	if (($b_sup % 7)!=0) $b_sup=(floor($b_sup / 7)+1)*7;
	for ($i=1; $i<=$b_sup; $i++)
		{
		//ce qui doit être affiché pour chaque jour
		$n_jour=$i-$ind_premier_du_mois;
		if (($n_jour<=0) || ($n_jour>$nb_jours_du_mois))
			{
			$n_jour="";
			$date="";
			}
			else
			{
			$date=date("Y-m-d",mktime(0, 0, 0, $mois, $n_jour, $annee));
			}
	if ($n_jour>$nb_jours_du_mois) $n_jour="";
	if ($n_jour==$jour) $n_jour="<span style='font-weight: bold; text-decoration: underline; color: red;'>".$n_jour."</span>";

	//nécessaire sinon la cellule n'est pas encadrée (CSS)
	if ($n_jour=="") $n_jour="&nbsp;";

	$n_jour="<a href='".$url.$date.$url_token."'>".$n_jour."</a>";
	if (($i %7)==1)
		{
		echo  "	<tr>";
		echo "		<td class='calendrier_cell_dim'>$n_jour</td>";
		}
		else
		echo "		<td class='calendrier_cell'>$n_jour</td>";
	if (($i % 7)==0)
		{
		echo "	</tr>";
		}
	}
	echo "</tbody>";
	echo "</table>";
	}

?>