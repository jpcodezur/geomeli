<?php
ini_set('display_errors', 1);
ob_start( );
ob_implicit_flush(true);
if (ob_get_length()) {
	ob_end_clean();
}
	
if (session_status() == PHP_SESSION_NONE) {
	session_start('teste');
}

require 'Meli/meli.php';

if(!isset($_GET['code'])){
	$_GET['code'] = "";
}

if(!isset($_SESSION['access_token'])){
	$_SESSION['access_token'] = "";
}

if(!isset($_SESSION['refresh_token'])){
	$_SESSION['refresh_token'] = "";
}

$meli = new Meli('4270558986407679', 'ArbqnANwnlxz9W0EXwnfvb0niBAWZhGf', $_SESSION['access_token'], $_SESSION['refresh_token']);


if($_GET['code'] || $_SESSION['access_token']) {
	
	// If code exist and session is empty
	if($_GET['code'] && !($_SESSION['access_token'])) {
		// If the code was in get parameter we authorize
		$user = $meli->authorize($_GET['code'], 'https://geeksuy.com/ml/auth.php');
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

			// Now we create the sessions with the new parameters
			$_SESSION['access_token'] = $refresh['body']->access_token;
			$_SESSION['expires_in'] = time() + $refresh['body']->expires_in;
			$_SESSION['refresh_token'] = $refresh['body']->refresh_token;
			
		}
		sendPost($_SESSION);
	}
} else {
		$newURL = $meli->getAuthUrl('https://geeksuy.com/ml/auth.php', Meli::$AUTH_URL['MLU']);
		echo '<a href="' . $newURL . '">Login using MercadoLibre oAuth 2.0</a>';
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
		flush();
		sleep(1);
		echo "<div>".$mensaje."</div>";
		flush();
		
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
		$meli = new Meli('4270558986407679', 'ArbqnANwnlxz9W0EXwnfvb0niBAWZhGf', $_SESSION['access_token'], $_SESSION['refresh_token']);

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

