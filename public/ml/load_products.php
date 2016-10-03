<?php

class oAuth{
	static $instance;

    private function __construct(){}

    public static function getInstance(){
        if (!self::$instance) {
            $instance = new oAuth();
        }

        return $instance;
    }
}

class Ml extends oAuth
{
    static $instance;
    public $meli;

    private function __construct(){
        $this->meli = new Meli('4270558986407679', 'ArbqnANwnlxz9W0EXwnfvb0niBAWZhGf');
    }

    public static function getInstance(){
        if (!self::$instance) {
            $instance = new Ml();
        }

        return $instance;
    }

    public function publishItems($items){
        if(is_array($items)){
            $items = json_encode($items);
        }
        $result=$this->meli->post('/items',$items);
        echo "<pre>";print_r($result);die();
	}

}

$items = array();
$item = new stdClass();
$item->title = "Item de ejemplo - 1";
$item->price = 100;
$item->available_quantity = 1;

$items[] = $item;

Ml::getInstance()->publishItems($items);