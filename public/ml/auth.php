<?php
ini_set('display_errors', 1);

define("URL_BASE","https://meli.farmashop.com.uy/index.php/public/ml/");
define("API_ID","8628636544666008");
define("SECRET","CNexT25r1WdzDUEtQXmLMqVatXYibekG");

if (session_status() == PHP_SESSION_NONE) {
	session_start('teste');
}

require 'Meli/meli.php';
//require 'simple_html_dom.php';

if(!isset($_GET['code'])){
	$_GET['code'] = "";
}

if(!isset($_SESSION['access_token'])){
	$_SESSION['access_token'] = "";
}

if(!isset($_SESSION['refresh_token'])){
	$_SESSION['refresh_token'] = "";
}

if(!isset($_REQUEST['mysession'])){
		$_REQUEST['mysession'] = "";
}

if($_REQUEST['mysession']){
	$meli = new Meli( API_ID, SECRET, $_REQUEST['mysession']);
	$_SESSION['access_token'] = $_REQUEST['mysession'];
}else{
	$meli = new Meli( API_ID, SECRET, $_SESSION['access_token'], $_SESSION['refresh_token']);
}

if($_GET['code'] || $_SESSION['access_token']) {
	
	// If code exist and session is empty
	if($_GET['code'] && !($_SESSION['access_token'])) {
		// If the code was in get parameter we authorize
		$user = $meli->authorize($_GET['code'], URL_BASE.'auth.php');
		// Now we create the sessions with the authenticated user
		$_SESSION['access_token'] = $user['body']->access_token;
		$_SESSION['expires_in'] = time() + $user['body']->expires_in;
		$_SESSION['refresh_token'] = $user['body']->refresh_token;
		sendPost($_SESSION);
	} else {
		
		// We can check if the access token in invalid checking the time
		if(isset($_SESSION['expires_in'])){
			if($_SESSION['expires_in'] < time() ) {
				try {
					// Make the refresh proccess
					$refresh = $meli->refreshAccessToken();

					// Now we create the sessions with the new parameters
					$_SESSION['access_token'] = $refresh['body']->access_token;
					$_SESSION['expires_in'] = time() + $refresh['body']->expires_in;
					$_SESSION['refresh_token'] = $refresh['body']->refresh_token;
				} catch (Exception $e) {
					echo "Exception: ",  $e->getMessage(), "\n";
				}
			}
		}else{
			
			$refresh = $meli->refreshAccessToken();
			
			echo "<pre>";
			print_r(array("refresh" => $refresh));die();

			// Now we create the sessions with the new parameters
			$_SESSION['access_token'] = $refresh['body']->access_token;
			$_SESSION['expires_in'] = time() + $refresh['body']->expires_in;
			$_SESSION['refresh_token'] = $refresh['body']->refresh_token;
			
		}
		sendPost($_SESSION);
	}
} else {
	
		$newURL = $meli->getAuthUrl(URL_BASE.'auth.php', Meli::$AUTH_URL['MLU']);
		$newURL = getLoginUrl($newURL);
		$newURL = getLoginUrlFromPost($newURL);
		tryLogin($newURL);
		//header('Location: '.$newURL);
		//echo '<a href="' . $newURL . '">Login using MercadoLibre oAuth 2.0</a>';
	
}

function getLoginUrl($url){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Must be set to true so that PHP follows any "Location:" header
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$a = curl_exec($ch); // $a will contain all headers

	$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); // This is what you need, it will return you the last effective URL

	return $url; // Voila
}

function getLoginUrlFromPost($url){
	$html = file_get_html($url);
	//$form = $html->find('#authForm')->plaintext;
	$form = $html->find('form[id=authForm]',0);
	return $form->action;
}

function tryLogin($loginUrl){
	
	$ch = curl_init();
	$cookieFile = "";	
	curl_setopt($ch, CURLOPT_URL, $loginUrl);
	curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, 'user_id=jotapey&password=g4cir9dx');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_COOKIESESSION, true);
	
	curl_setopt($ch,CURLOPT_COOKIEJAR, $cookieFile);  //tell cUrl where to write cookie data
	curl_setopt($ch,CURLOPT_COOKIEFILE, $cookieFile);
	
	curl_setopt($ch, CURLOPT_HEADER, 1);
	
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Must be set to true so that PHP follows any "Location:" header

	$answer = curl_exec($ch);
	
	$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); // This is what you need, it will return you the last effective URL
	
	// PARA TESTEAR SI ANDA SIN CAPTCHA 
	
	/*echo "<pre>";
	print_r(array("redirect" => $url));
	die();*/
	
	preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $answer, $matches);
	$cookies = array();
	foreach($matches[1] as $item) {
		parse_str($item, $cookie);
		$cookies = array_merge($cookies, $cookie);
	}
	
	if(isset($cookies["_d2id"])){
		$session = getSessionToken($ch);
	}
	
	gotToSite($session,$ch);
}

function getLoginRedirectCode($url){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Must be set to true so that PHP follows any "Location:" header
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$a = curl_exec($ch); // $a will contain all headers

	$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); // This is what you need, it will return you the last effective URL

	return $url; // Voila
}

function getSessionToken($ch){
	
	curl_setopt($ch, CURLOPT_URL, URL_BASE."auth.php");
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_POST, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "");
	$answer = curl_exec($ch);
	if (curl_error($ch)) {
		echo curl_error($ch);
	}
	//preg_match('/PHPSESSID=/', $answer, $matches);
	preg_match("/.*PHPSESSID=([^;]*);.*/", $answer, $matches);
	
	return $matches[1];
}

function gotToSite($session,$ch){
	curl_setopt($ch, CURLOPT_URL, URL_BASE."auth.php?mysession=".$session);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_POST, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "");
	$answer = curl_exec($ch);
	if (curl_error($ch)) {
		echo curl_error($ch);
	}
	
	echo "<pre>";
	print_r(array(
		"answer" => $answer,
	));
	die();
}

function sendPost($param){
	$articulo = "";
	foreach(getArticulos() as $articulo){
		$salida = json_decode(publicarArticulo($articulo));
		$mensaje = "";

		if($salida->error){
			if(isset($salida->body)){
				$mensaje = "Error importando item. sku: ".$salida->body->id;
			}else{
				$mensaje = "Error importando item";
			}
		}else{
			$mensaje = "Item importado satisfactoriamente. sku:".$salida->body->id;
		}
		echo "<div>".$mensaje."</div>";
	}
	
}

function getArticulos(){
	$articulos = array();

	$articulos[] = '{"title":"Gato TEST01","category_id":"MLU1082","price":99,"currency_id":"UYU","available_quantity":1,"buying_mode":"buy_it_now","listing_type_id":"bronze","condition":"new","description": "Item:, <strong> TTTTEEEESSTTT Ray-Ban WAYFARER Gloss Black RB2140 901 </strong> Model: RB2140. Size: 50mm. Name:    WAYFARER. Color: Gloss Black. Includes Ray-Ban Carrying Case and Cleaning Cloth. New in Box","video_id": "YOUTUBE_ID_HERE","warranty": "12 months by Ray Ban","pictures":[{"source":"http://upload.wikimedia.org/wikipedia/commons/f/fd/Ray_Ban_Original_Wayfarer.jpg"},{"source":"http://en.wikipedia.org/wiki/File:Teashades.gif"}]}';
	$articulos[] = '{"title":"Gato TEST02","category_id":"MLU1082","price":99,"currency_id":"UYU","available_quantity":1,"buying_mode":"buy_it_now","listing_type_id":"bronze","condition":"new","description": "Item:, <strong> TTTTEEEESSTTT Ray-Ban WAYFARER Gloss Black RB2140 901 </strong> Model: RB2140. Size: 50mm. Name:    WAYFARER. Color: Gloss Black. Includes Ray-Ban Carrying Case and Cleaning Cloth. New in Box","video_id": "YOUTUBE_ID_HERE","warranty": "12 months by Ray Ban","pictures":[{"source":"http://upload.wikimedia.org/wikipedia/commons/f/fd/Ray_Ban_Original_Wayfarer.jpg"},{"source":"http://en.wikipedia.org/wiki/File:Teashades.gif"}]}';
	$articulos[] = '{"title":"Gato TEST03","category_id":"MLU1082","price":99,"currency_id":"UYU","available_quantity":1,"buying_mode":"buy_it_now","listing_type_id":"bronze","condition":"new","description": "Item:, <strong> TTTTEEEESSTTT Ray-Ban WAYFARER Gloss Black RB2140 901 </strong> Model: RB2140. Size: 50mm. Name:    WAYFARER. Color: Gloss Black. Includes Ray-Ban Carrying Case and Cleaning Cloth. New in Box","video_id": "YOUTUBE_ID_HERE","warranty": "12 months by Ray Ban","pictures":[{"source":"http://upload.wikimedia.org/wikipedia/commons/f/fd/Ray_Ban_Original_Wayfarer.jpg"},{"source":"http://en.wikipedia.org/wiki/File:Teashades.gif"}]}';
	$articulos[] = '';

	return $articulos;
}

function publicarArticulo($article){
	
	if(isset($_SESSION['access_token'])){
		$meli = new Meli( API_ID, SECRET, $_SESSION['access_token'], $_SESSION['refresh_token']);

		$params = array('access_token' => $_SESSION['access_token']);
		$article = json_decode($article,true);

		$resp = $meli->post("items",$article,$params);

		$error = false;

		if(isset($resp["body"])){
			if(isset($resp["body"]->error)){

				//ERROR PUBLISH
				return json_encode(array(
					"error" => $resp["body"]->error,
					"message" => $resp["body"]->message,
				));
			}else{
				//SUCCESS PUBLISH
				return json_encode(array(
					"error" => false,
					"body" => $resp["body"],
					"httpCode" => $resp["httpCode"]
				));
			}
		}else{
			//ERROR PUBLISH (ERROR REQUEST)
			return json_encode(array(
				"error" => true,
				"body" => "",
				"httpCode" => "500"
			));
		}
	}
	
	
}

