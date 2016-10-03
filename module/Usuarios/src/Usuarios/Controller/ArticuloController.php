<?php

namespace Usuarios\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Usuarios\Model\Dao\ArticuloDao;
use Zend\View\Model\JsonModel;

class ArticuloController extends AbstractActionController {

    private $articuloDao;

    public function setDao($dao){
        $this->dao = new ArticuloDao($dao);
    }

    public function setArticuloDao($articuloDao) {
        $this->articuloDao = $articuloDao;
    }

    public function indexAction(){
        $result = $this->articuloDao->getCategorias();
		$categoriasBase = ($this->articuloDao->getCategoriasFromMl());
        return new ViewModel(array("categorias" => $result["categorias"], "categoriasBase" => $categoriasBase));
    }

    public function publicarAction(){
        $result = $this->articuloDao->getCategorias();
        $estados = $this->articuloDao->getEstados();
        return new ViewModel(array("categorias" => $result["categorias"],"estados" => $estados));
    }
	
	public function importCSVAction(){
        return new ViewModel(array());
    }
	

    public function getTotalArticulosFilterAction(){
        $filters = $this->getRequest()->getPost("filters", null);
        $result = $this->articuloDao->getTotalArticulos($filters);
        return new JsonModel(array("total" => $result["total"]));
    }

	public function publicarArticuloAction(){
		$filters = $this->getRequest()->getPost("filters", null);
		$result = $this->articuloDao->publicarArticulo($filters);
		
		if(isset($result["httpCode"])){
			if($result["httpCode"] == "101"){
				
			}
		}
		
		return new JsonModel($result);
	}
	
	public function getCategoriasFromMlAction(){
		$category = $this->getRequest()->getQuery("category", null);
		$result = ($this->articuloDao->getCategoriasFromMl($category));
		$result = json_decode($result);
		
		if(isset($result->children_categories)){
			$result = $result->children_categories;
		}
		
		$items = array();
		
		foreach($result as $item){
			//echo "<pre>";print_r($item);die();
			$temp = new \stdClass();
			$temp->id = $item->id;
			$temp->text = $item->name;
			$items[] = $temp;
		}
		
		echo json_encode($items);
		die();
	}
	
	public function editCategoriasArticulosAction(){
		$res = array();
//		echo "<pre>";print_r($_REQUEST);die();
		foreach($_REQUEST as $key => $value){
			$pos = strpos($key,"ml_categoria_");
			if($pos!==false){
				$name_cat = "geopos".substr($key,2,strlen($key));
				$catGeopos = $_REQUEST[$name_cat];
				$catMl = $_REQUEST[$key];
				$res[] = array(
					"name_cat" => $name_cat,
					"catGeopos" => $catGeopos,
					"catMl" => $catMl,
				);

			}
				
		}
		
		foreach($res as $cat){
			$result = $this->articuloDao->editCategoriasArticulos($cat);
		}
		$this->flashMessenger()->addMessage('Categoria Mapeada.');
		return $this->redirect()->toRoute('usuarios', array('controller' => 'articulo', 'action' => 'index'));
	}
	
	public function validarCategoriaAction(){
		$category = $this->getRequest()->getPost("category", null);
		$result = $this->articuloDao->validarCategoria($category);
		return new JsonModel(array("result" => $result));
	}
	
	

}
