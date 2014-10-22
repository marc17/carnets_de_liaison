<?php

//************************
// Copyleft Marc Leygnac
//************************

?>
			<a name="ancre_retour<?php echo $un_mot['id_mot']; ?>"></a>
			<div style="max-width: 550px;">
			<?php
			// pour les responsables on affiche éventuellement la pho du rédacteur du mot
			if (($_SESSION['statut']=="responsable") && getSettingAOui('active_module_trombino_pers') && ($carnets_de_liaison_affiche_trombines_profs=="oui"))
				{
				$photo=nom_photo($un_mot['login_redacteur'],$repertoire="personnels",$arbo=2);
				if ($photo!=NULL)
					{
					$dimensions=getimagesize($photo);
					$rapport_largeur=($dimensions[0]==0)?1:($width_trombine/$dimensions[1]);
					$rapport_longueur=($dimensions[1]==0)?1:($width_trombine/$dimensions[1]);
					$rapport=max($rapport_largeur,$rapport_longueur);
					?>
					<div id="trombine" style="position: absolute; margin-left: 565px;">
					<img style="width: <?php echo floor($dimensions[0]*$rapport); ?>px; height: <?php echo floor($dimensions[1]*$rapport); ?>px; vertical-align: top;" src="<?php echo $photo; ?>">
					</div>
					<?php
					}
				}
			?>
			<h4 style="margin: 0px; color: MediumBlue;">Le <?php echo date_en_clair($un_mot['date']); ?>, 
			<?php echo $un_mot['civilite']." ".$un_mot['prenom']." ".$un_mot['nom'];
			switch ($un_mot['type'])
				{
				case "classe" :
					echo " (".$nom_complet_classe.")";
					break;
				case "aid" :
					echo " (à l'AID ".$un_mot['ensemble_destinataire'].")";
					break;
				case "groupe" :
					echo " (au groupe ".$un_mot['ensemble_destinataire'].")";
					break;
				}
			echo "&nbsp;:";
			?>
			</h4>
			<?php if ($afficher_identite_eleve) 
				{
			?>
				<form method="POST" action="consultation.php#ancre_retour<?php echo $un_mot['id_mot']; ?>" target="_blank">
				<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
				<?php
				// le lien vers le carnet ne figure éventuellement que dans consultation_mots_recus.php et consultation_reponses.php
				$chemin_script_d_appel=pathinfo($_SERVER['PHP_SELF']);
				$script_d_appel=$chemin_script_d_appel['basename'];
				if ($script_d_appel=="consultation_mots_recus.php")
					{
				?>
					<input type="hidden" name="id_classe" value="<?php echo $eleve['id_classe']; ?>">
					<input type="hidden" name="ele_id" value="<?php echo $t_destinataires[1]; ?>">
				<?php
					}
				?>
				<?php
				if ($script_d_appel=="consultation_reponses.php")
					{
				?>
					<input type="hidden" name="id_classe" value="<?php echo $eleve['id_classe']; ?>">
					<input type="hidden" name="ele_id" value="<?php echo $t_destinataires[0]; ?>">
				<?php
					}
				?>
				Carnet de <?php echo $identite_eleve; ?>
				<button type="submit" title=" Voir le carnet " style="border: none; background: none;"><img style="width:16px; height:16px; vertical-align: bottom;" src="bouton_voir_carnet.png"></button>
				</form>
			<?php
				}
			?>
			</div>
			<div class="texte"">
			<p class="intitule"><?php echo $un_mot['intitule']; ?></p>
			<?php echo nl2br($un_mot['texte']); ?>
			<br />
			<?php
			// on affiche les réponses
			$r_sql="SELECT * FROM `carnets_de_liaison_reponses` WHERE (`id_mot`='".$un_mot['id_mot']."'";
			if (($_SESSION['statut']=="responsable") && ($un_mot['type']!="prof") ) $r_sql.=" AND `login_redacteur`='".$_SESSION['login']."'";
			$r_sql.=") ORDER BY `date` DESC";
			$R_reponses=mysqli_query($mysqli, $r_sql);
			while ($une_reponse=mysqli_fetch_assoc($R_reponses))
				{
				echo "<hr><span style=\"font-style: italic;\">".nl2br($une_reponse['texte'])."</span>";
				}
			?>
			</div>
			<?php
			//if (($carnets_de_liaison_documents=="oui") && ($un_mot['document']!=""))
			if ($un_mot['document']!="")
				{
				$document=unserialize($un_mot['document']);
				// si multisite
				if (isset($GLOBALS['multisite']) && isset($_COOKIE['RNE']) && $GLOBALS['multisite']=='y') $dossier_multisite=$_COOKIE['RNE']."/"; else $dossier_multisite="";
			?>
				<div>
				<form method="POST" action="telecharger_document.php">
				<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
				<input type="hidden" name="telecharger_document" value="<?php echo $dossier_multisite.$document['prefixe']."_".$un_mot['id_mot']; ?>">
				Document joint&nbsp;: <?php	echo $document['nom']; ?>&nbsp;<button type="submit" title=" Télécharger ce document " style="border: none; background: none;"><img style="width:16px; height:16px; vertical-align: bottom;" src="bouton_download.png"></button>
				</form>
				</div>
			<?php
				}
			?>
			<?php
			if ($_SESSION['login']!=$un_mot['login_redacteur'] && $un_mot['reponse_destinataire']=="oui" && ($_SESSION['statut']!="eleve" && ($_SESSION['statut']!="responsable" ||$carnets_de_liaison_reponses_responsables=="oui")))
				{
			?>
				<div id="repondre<?php echo $un_mot['id_mot']; ?>" style="display: inline;">
				<button type="button" onClick="document.getElementById('repondre<?php echo $un_mot['id_mot']; ?>').style.display='none';document.getElementById('reponse<?php echo $un_mot['id_mot']; ?>').style.display='block';"> Répondre à ce mot </button>
				</div>
				<div id="reponse<?php echo $un_mot['id_mot']; ?>" class="saisie" style="display: none; width: 530px;" >
				<h4>
				<form method="post" name="reponse" action="<?php echo $_SERVER['PHP_SELF']; ?>#ancre_retour<?php echo $un_mot['id_mot']; ?>">
				<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
				<input type="hidden" value="<?php echo $un_mot['id_mot']; ?>" name="id_mot">
				<input type="hidden" value="<?php if (isset($id_eleve)) echo $id_eleve; // $id_eleve défini dans index.php, consultation_reponses.php, consultation_mots_recus.php?>" name="id_eleve">
				<input type="hidden" name="id_classe" value="<?php if (isset($id_classe))echo $id_classe; // $id_classe défini dans index.php, consultation.php, consultation_reponses.php, consultation_mots_recus.php ?>">
				<input type="hidden" name="ele_id" value="<?php if (isset($ele_id)) echo $ele_id; // $ele_id défini dans index.php, consultation.php, consultation_reponses.php, consultation_mots_recus.php ?>">
			<?php
			if (isset($identite_eleve))
				{
			?>
				<input type="hidden" name="identite_eleve" value="<?php echo $identite_eleve; // $identite_eleve défini dans index.php, consultation.php, consultation_reponses.php, consultation_mots_recus.php ?>">
			<?php
				}
			?>
				Rédiger votre réponse :<br />
				<span style="margin-left: 8px;"><textarea name="texte" class="texte" height: 250px;"></textarea></span>
				<br />
				<button style="margin-left: 10px;" type="submit" name="envoyer_reponse" value=" Envoyer la réponse "> Envoyer la réponse </button>
				</form>
				</h4>
				</div>
			<?php
				}
			?>
			<?php
			if (($_SESSION['login']==$un_mot['login_redacteur']) && ($_SESSION['statut']!="responsable"))
				{
				switch ($un_mot['type'])
					{
					case "eleve" :
						$script="saisie_par_eleve";
						break;
					case "classe" :
						$script="saisie_par_classe";
						break;
					case "aid" :
						$script="saisie_par_aid";
						break;
					case "groupe" :
						$script="saisie_par_groupe";
						break;
					default :
						$script="";
					}
				if ($script!="")
					{
			?>
					<form method="post" action="<?php echo $script; ?>.php" style="margin-left: 0px; float: left;">
					<?php if (function_exists("add_token_field")) echo add_token_field(); ?>
					<input type="hidden" name="modifier" value="<?php echo $un_mot['id_mot']; ?>" >
					<button type="submit"> Modifier ce mot </button>
					</form>
			<?php
					}
				}
			?>
			<br /><br /><br />
<?php
?>