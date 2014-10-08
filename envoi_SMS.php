<?php

//************************
// Copyleft Marc Leygnac
//************************

$tab_prestataires_SMS=array("pluriware.fr","tm4b.com","123-SMS.net");

function filtrage_numero($numero,$international=false) {
	// suuprime les caract�res ind�sirables et ajoute �ventuellement l'indicatif 33
	$numero=ereg_replace("[^0-9]","",$numero);
	if ($international) $numero='33'.substr($numero, 1);
}

function envoi_requete_http($url,$script,$t_parametres,$methode="POST") {
	/*
	$methode : GET ou par d�faut POST
	$url : truc.com
	$script : machin.php
	$t_parametres : array("param1" => "val1","param2" => "val2",...)
	retour : cha�ne de caract�res contenant la r�ponse du serveu sans l'en-t�te
	*/

	/*$parametres='';
	foreach($t_parametres as $clef => $valeur)  {
		if ($parametres!='') $parametres.='&';
	    $parametres.=$clef.'='.urlencode($valeur);
		} */
	$parametres=http_build_query($t_parametres);

	if (in_array('curl',get_loaded_extensions())) {

	    // avec cURL
		$ch=curl_init();
		if ($methode=="GET") {
			if ($parametres!='') $script=$script."?".$parametres;  // M�thode GET
			curl_setopt($ch,CURLOPT_URL,$url.$script); // M�thode GET
			curl_setopt($ch,CURLOPT_HTTPGET,true); // M�thode GET
		} else {
			curl_setopt($ch,CURLOPT_URL,$url.$script);
			curl_setopt($ch,CURLOPT_POST,true);
			curl_setopt($ch,CURLOPT_POSTFIELDS,$parametres);
		}

		//curl_setopt($ch,CURLOPT_HEADER,true);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		$r_exec=curl_exec($ch); $error=curl_error($ch);
		if ($r_exec===false) return "Erreur : ".$error; else return $r_exec;
		curl_close($ch);

	} else {
	
		// sans cURL
		if ($methode=="GET") {
			$entete="GET ".$script."?".$parametres." HTTP/1.1\r\n";  // M�thode GET
		} else {
			$entete="POST ".$script." HTTP/1.1\r\n";
		}
		$entete.="Host: ".$url."\r\n";
		$entete.="Content-Type: application/x-www-form-urlencoded\r\n";
		if ($methode=="POST") $entete.="Content-Length: ".strlen($parametres)."\r\n";
		$entete.="Connection: Close\r\n\r\n";
		$socket=@fsockopen($url,80,$errno,$errstr);
		if($socket) {
			fputs($socket,$entete); // envoi de l'en-t�te
			if ($methode=="POST") fputs($socket,$parametres);
			// on saute l'en-t�te de la r�ponse HTTP
			$line="";
			// on saute les lignes vides
			while (!feof($socket) && $line=="") {
				$line=trim(fgets($socket));
			}
			// on saute l'en-t�te
			while (!feof($socket) && $line!="") {
				$line=trim(fgets($socket));
			}
			// on saute les lignes vides
			while (!feof($socket) && $line=="") {
				$line=trim(fgets($socket));
			}
			// ici $line contient la premi�re ligne apr�s l'en-t�te de la r�ponse HTTP
			$retour=$line;
			while (!feof($socket)) {
				$retour.=trim(fgets($socket));
			}
			return $retour;
			fclose($socket);
			}
			else  return 'Erreur : no socket available.';
	}
}


function envoi_SMS($prestataire,$login,$password,$tab_to,$from,$sms) {
	// $tab_to : tableau des num�ros de t�l�phone auxquels envoyer le sms
	// retourne "OK" si envoi r�ussi, un message d'erreur sinon
	switch ($prestataire) {
		case "pluriware.fr" :
			$url="sms.pluriware.fr";
			$script="/httpapi.php";
			$parametres['cmd']='sendsms';            
			$parametres['txt']=$sms; // message a envoyer
			$parametres['user']=getSettingValue("carnets_de_liaison_identifiant_sms"); // identifiant Pluriware
			$parametres['pass']=getSettingValue("carnets_de_liaison_password_sms"); // mot de passe Pluriware
			
			foreach($tab_to as $key => $to) $tab_to[$key]=filtrage_numero($to,true);
			$to=$tab_to[0]; // ! un seul num�ro
			$parametres['to']=$to; // num�ro de t�l�phone auxquel on envoie le message (! un seul num�ro)
			$parametres['from']=$from; // exp�diteur du message (facultatif)

			$reponse=envoi_requete_http($url,$script,$parametres);
			if (substr($reponse,0,3)=='ERR' || substr($reponse, 0, 6)=='Erreur') {
				return 'SMS non enoy�(s) : '.$reponse;
				} 
			else return "OK";

			break;

		case "123-SMS.net" :
			$url="www.123-SMS.net";
			$script="/http.php";
			$hote="123-SMS.net";
			$script="/http.php";
			$parametres['email']=getSettingValue("carnets_de_liaison_identifiant_sms"); // identifiant 123-SMS.net
			$parametres['pass']=getSettingValue("carnets_de_liaison_password_sms"); // mot de passe 123-SMS.net
			$parametres['message']=$sms; // message que l'on d�sire envoyer
			
			foreach($tab_to as $key => $to) $tab_to[$key]=filtrage_numero($to);
			$to=implode("-",$tab_to);
			$parametres['numero']=$to; // num�ros de t�l�phones auxquels on envoie le message s�par�s par des tirets
			$t_erreurs=array(80 => "Le message a �t� envoy�", 81 => "Le message est enregistr� pour un envoi en diff�r�", 82 => "Le login et/ou mot de passe n�est pas valide",  83 => "vous devez cr�diter le compte", 84 => "le num�ro de gsm n�est pas valide", 85 => "le format d�envoi en diff�r� n�est pas valide", 86 => "le groupe de contacts est vide", 87 => "la valeur email est vide", 88 => "la valeur pass est vide",  89 => "la valeur numero est vide", 90 => "la valeur message est vide", 91 => "le message a d�j� �t� envoy� � ce num�ro dans les 24 derni�res heures");
			$reponse=envoi_requete_http($url,$script,$parametres);
			if ($reponse!='80') {
				return 'SMS non enoy�(s) : '.$reponse.' '.$t_erreurs[$reponse];
				} 
			else return "OK";
			
			break;

		case "tm4b.com" :
			$url="www.tm4b.com";
			$script="/client/api/http.php";
			$hote="tm4b.com";
			$script="/client/api/http.php";
			$parametres['username']=getSettingValue("carnets_de_liaison_identifiant_sms"); // identifiant  TM4B
			$parametres['password']=getSettingValue("carnets_de_liaison_password_sms"); // mot de passe  TM4B
			$parametres['type']='broadcast'; // envoi de sms
			$parametres['msg']=$sms; // message a envoyer
			
			foreach($tab_to as $key => $to) $tab_to[$key]=filtrage_numero($to,true);
			$to=implode("%7C",$tab_to);
			$parametres['to']=$to; // num�ros de t�l�phones auxquels on envoie le message s�par�s par des pipe %7C

			$parametres['from']=$from; // exp�diteur du message (first class uniquement)
			$parametres['route']='business'; // type de route (pour la france, business class uniquement)
			$parametres['version']='2.1';
			// $parametres['sim']='yes'; // on active le mode simulation, pour tester notre script
			
			$reponse=envoi_requete_http($url,$script,$parametres);
			if (mb_substr($reponse, 0, 5)=='error' || substr($reponse, 0, 6)=='Erreur') {
				return 'SMS non enoy�(s) : '.$reponse;
				} 
			else return "OK";

			break;

		default :
			return "SMS non enoy�(s) : prestataire SMS non d�fini.";
		}
	
	return $reponse;
}

/* Tests prestataires

echo "tm4b<br>";
$url="www.tm4b.com";
$script="/client/api/http.php";
$parametres=array("username"=>"toto");
echo envoi_requete_http($url,$script,$parametres);

echo "<hr>Pluriware<br>";
$url="sms.pluriware.fr";
$script="/httpapi.php";
$parametres=array("cmd"=>"sendsms");
echo envoi_requete_http($url,$script,$parametres);

echo "<hr>123-SMS<br>";			
$url="www.123-SMS.net";
$script="/http.php";
$parametres=array("email"=>"toto","message"=>"test");	
$r=envoi_requete_http($url,$script,$parametres);
$t_erreurs=array(80 => "Le message a �t� envoy�", 81 => "Le message est enregistr� pour un envoi en diff�r�", 82 => "Le login et/ou mot de passe n�est pas valide",  83 => "vous devez cr�diter le compte", 84 => "le num�ro de gsm n�est pas valide", 85 => "le format d�envoi en diff�r� n�est pas valide", 86 => "le groupe de contacts est vide", 87 => "la valeur email est vide", 88 => "la valeur pass est vide",  89 => "la valeur numero est vide", 90 => "la valeur message est vide", 91 => "le message a d�j� �t� envoy� � ce num�ro dans les 24 derni�res heures");
echo $r." : ".$t_erreurs[$r];


echo "<hr>Pluriware-SMS XML<br>";			
$url="sms.pluriware.fr";
$script="/xmlapi.php";
$parametres=array("data"=>'<pluriAPI><login></login><password></password><sendMsg><to>330628000000</to><txt>Test msg 1</txt><climsgid></climsgid><status></status></sendMsg></pluriAPI>');	
echo envoi_requete_http($url,$script,$parametres,"GET");
			
$url="www.ac-poitiers.fr";
$script="/";
$parametres=array();
echo envoi_requete_http($url,$script,$parametres,"GET");
*/

?>
