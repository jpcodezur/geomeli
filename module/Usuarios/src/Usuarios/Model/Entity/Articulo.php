<?php

namespace Usuarios\Model\Entity;

class Articulo {

    private $id;
    public $categorias;

    public function __construct($id = null, $categorias = null) {
        $this->id = $id;
        $this->categorias = $categorias;
    }
    
    public function getId() {
        return $this->id;
    }

    public function getCategorias() {
        return $this->categorias;
    }

    public function setId($param) {
        $this->id = $param;
    }

    public function setCategorias($param) {
        $this->categorias = $param;
    }
	
	public function setMlCategorias($param) {
        $this->mlCategorias = $param;
    }
	
	public function getMlCategorias($param) {
        return $this->mlCategorias;
    }
	
	

}