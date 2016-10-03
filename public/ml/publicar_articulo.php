<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start('teste');
require 'Meli/meli.php';
?>


<?

//$article1 = json_encode($article1);'{"title":"Gato TEST01","category_id":"MLU1082","price":99,"currency_id":"UYU","available_quantity":1,"buying_mode":"buy_it_now","listing_type_id":"bronze","condition":"new","description": "Item:, <strong> TTTTEEEESSTTT Ray-Ban WAYFARER Gloss Black RB2140 901 </strong> Model: RB2140. Size: 50mm. Name:    WAYFARER. Color: Gloss Black. Includes Ray-Ban Carrying Case and Cleaning Cloth. New in Box","video_id": "YOUTUBE_ID_HERE","warranty": "12 months by Ray Ban","pictures":[{"source":"http://upload.wikimedia.org/wikipedia/commons/f/fd/Ray_Ban_Original_Wayfarer.jpg"},{"source":"http://en.wikipedia.org/wiki/File:Teashades.gif"}]}';

$article1 = array(
	
);

print_r(publishArticle($article1));


function publishArticle($article){
	if(isset($_SESSION['access_token'])){
		$meli = new Meli('4270558986407679', 'ArbqnANwnlxz9W0EXwnfvb0niBAWZhGf', $_SESSION['access_token'], $_SESSION['refresh_token']);
		
		$body = json_decode($article,true);
		$params = array('access_token' => $_SESSION['access_token']);
		
		$resp = $meli->post("items",$body,$params);
		
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
		}
	}
}