<?php

namespace Usuarios\Model\Dao;
ini_set('max_execution_time', 3000); //300 seconds = 5 minutes

use Usuarios\Model\Entity\Articulo;
use Usuarios\MisClases\Meli;

class ArticuloDao extends BaseDao implements IArticuloDao {

    private $listaCategoria;
    protected $tableGateway;
    private $adapter;

    public function __construct($tableGateway = null, $adapter = null,$config = null) {
        $this->tableGateway = $tableGateway;
        $this->adapter = $adapter;
        $this->config = $config;
    }

    public function getCategorias() {
        $articulos = array();

        $sql = $this->tableGateway->getSql();

        $select = $this->tableGateway->getSql()->select();

        $select->columns(array('categories'));
        $select->quantifier(\Zend\Db\Sql\Select::QUANTIFIER_DISTINCT);
        //echo $sql->getSqlstringForSqlObject($select); die ;
        //$select->where(array("estado"=>"1"));

        $select->order('categories ASC');

        $this->listaCategoria = $this->tableGateway->selectWith($select);

        $adapter = new \Zend\Paginator\Adapter\DbSelect($select, $sql);
        $paginator = new \Zend\Paginator\Paginator($adapter);

        foreach ($this->listaCategoria as $cat) {
            /////////////////////////////
            $mapeo = $this->getCategoriaMapeo(null,$cat["categories"]);

            $catMl = explode (",",$mapeo["categorias_ml"]);

            $mapeo = $this->getCategoriaMapeo(null,$cat["categories"]);

            $catMl = explode (",",$mapeo["categorias_ml"]);

            $unArticulo = new Articulo();
            $categoriasMl = array();
            foreach($catMl as $c){
                $categoriaMl = new  \stdClass();
                $categoriaMl->id = $c;
                $res = $this->getCateogriaMlDb($c);
                $categoriaMl->text = $res["nombre_categoria_ml"];
                $categoriasMl[] = $categoriaMl;
            }


            $unArticulo->setCategorias($cat["categories"]);
            $unArticulo->setMlCategorias($categoriasMl);

            $articulos[] = $unArticulo;
        }

        return array("categorias" => $articulos, "paginator" => $paginator);
    }

    public function getEstados(){
        $estados = array();
        $this->adapter = $this->tableGateway->getAdapter();

        $sql = "SELECT DISTINCT * FROM estados_publicacion";

        $res = $this->adapter->query($sql);

        if($res){
            $result = $res->execute();
            foreach($result as $r){
                $estados[] = $r;
            }
        }

        return $estados;
    }

    public function getTotalArticulos($filtros=null){
        $total = false;
        $this->adapter = $this->tableGateway->getAdapter();

        $where = "WHERE visibility = '4' ";

        if($filtros["categorias"] && $filtros["categorias"] != "todas"){
            $where .= "AND categories='".$filtros["categorias"]."' ";
        }

        if($filtros["estado"]){
            $where .= "AND estado='".$filtros["estado"]."' ";
        }

        $sql = "SELECT COUNT(*) as total FROM articulos $where";

        $res = $this->adapter->query($sql);

        if($res){
            $result = $res->execute();
            foreach($result as $r){
                $total = $r["total"];
            }
        }

        return array("total" => $total);
    }

    public function checkCatIsMapped($cat){
        $this->adapter = $this->tableGateway->getAdapter();
        $sql = "SELECT * FROM mapeo_categorias WHERE UPPER(categoria_geopos)='".strtoupper(($cat))."'";

        //die($sql);

        $res = $this->adapter->query($sql);

        if($res){
            $result = $res->execute();
            foreach($result as $r){
                if($r["categoria_ml"]){
                    return $r;
                }
            }
        }

        return false;
    }

    public function republicarArticulo($pArticulo,$mapeo){
        $pArticulo = $pArticulo["articulo"];
        $articulo = $this->mapeoArticulo($pArticulo,$mapeo);

        $params = array('access_token' => $_SESSION['access_token']);
        $meli = new Meli($this->config["appId"], $this->config["key"], $_SESSION['access_token'], $_SESSION['refresh_token']);

        $articulo = array(
            "price"=>(int) $pArticulo["price"],
            "quantity" => (int) $pArticulo["qty"],
            "listing_type_id" => $this->config["listing_type"],
        );

        $resp = $meli->post("items/".$pArticulo["ml_articulo_id"]."/relist",$articulo,$params);


        if($this->actualizarEstadoArticulo($pArticulo,$resp)){
            return (array(
                "error" => false,
                "body" => $resp["body"],
                "httpCode" => $resp["httpCode"]
            ));
        }else{
            return (array(
                "error" => true,
                "body" => "",
                "httpCode" => "99"
            ));
        }
    }

    public function publicarArticulo($filtros=null){
        $articulo = $this->getArticulo($filtros);
        if($articulo["articulo"]){

            if($this->isEnabled($articulo)){

                $chk = $this->checkCatIsMapped($articulo["articulo"]["categories"]);
                if($chk){
                    $mapeo = $this->getCategoriaMapeo(null,$articulo["articulo"]["categories"]);

                    if($filtros["accion"] == "PUBLICAR"){
                        return $this->publicarArticuloSendJson($articulo,$mapeo);
                    }elseif($filtros["accion"] == "REPUBLICAR"){
                        return $this->republicarArticulo($articulo,$mapeo);
                    }else{
                        return $this->modificarPublicacion($articulo,$mapeo,$filtros["accion"]);
                    }
                }

                return (array(
                    "error" => true,
                    "body" => "Categoria sin mapear.",
                    "httpCode" => ""
                ));
            }
        }

        return (array(
            "error" => false,
            "body" => "end process.",
            "httpCode" => "101"
        ));
    }

    public function modificarPublicacion($pArticulo,$mapeo,$estado){

        $pArticulo = $pArticulo["articulo"];
        $articulo = array();$this->mapeoArticulo($pArticulo,$mapeo);

        $params = array('access_token' => $_SESSION['access_token']);
        $meli = new Meli($this->config["appId"], $this->config["key"], $_SESSION['access_token'], $_SESSION['refresh_token']);

        $articuloMl = $this->getArticuloMl($pArticulo["ml_articulo_id"]);
        $error_estado = false;

        if($estado == "PAUSAR"){
            if($articuloMl->status == "active"){
                $articulo["status"] = "paused";
                $idEstado = "4";
            }else{
                $error_estado = true;
            }
        }

        if($estado == "DESPAUSAR"){
            if($articuloMl->status == "paused"){
                $articulo["status"] = "active";
                $idEstado = "2";
            }else{
                $error_estado = true;
            }
        }

        if($estado == "FINALIZAR"){
            if($articuloMl->status != "closed"){
                $articulo["status"] = "closed";
                $idEstado = "3";
            }else{
                $error_estado = true;
            }
        }

        if($error_estado){
            $this->sincronizarEstadoConMl($articuloMl);
            return (array(
                "error" => false,
                "body" => "status updated",
                "httpCode" => ""
            ));
        }

        $resp = $meli->put("items/".$pArticulo["ml_articulo_id"]."/",$articulo,$params);

        /*echo "<pre>";print_r(array(
            "articuloMl" =>$pArticulo["ml_articulo_id"],
            "articuloMl" => $articuloMl,
            "estado" => $estado,
            "articulo" => $articulo,
            "resp" => $resp,
        ));die();*/

        $error = false;

        if(isset($resp["body"])){
            if(isset($resp["body"]->error)){

                //ERROR PUBLISH
                return (array(
                    "error" => $resp["body"]->error,
                    "message" => $resp["body"]->message,
                    "resp" => $resp
                ));
            }else{
                //SUCCESS PUBLISH
                if($this->actualizarEstadoArticulo($pArticulo,$resp,$idEstado)){
                    return (array(
                        "error" => false,
                        "body" => $resp["body"],
                        "httpCode" => $resp["httpCode"]
                    ));
                }else{
                    return (array(
                        "error" => true,
                        "body" => "",
                        "httpCode" => "99"
                    ));
                }
            }
        }else{
            //ERROR PUBLISH (ERROR REQUEST)

            return (array(
                "error" => true,
                "body" => "",
                "httpCode" => "500"
            ));
        }
    }

    public function getArticuloMl($id){
        return json_decode(file_get_contents("https://api.mercadolibre.com/items/".$id));
    }

    public function parseoMensajesMl($mlMsg){
        $mensaje = $mlMsg;

        switch($mlMsg){
            case "seller.unable_to_list":
                $mensaje = "El usuario de mercadolibre no esta habilitado para vender.";//USUARIO_ML_NO_HABILITADO;
                break;
        }

        return $mensaje;
    }

    public function getUserData(){
        $params = array('access_token' => $_SESSION['access_token']);
        $meli = new Meli($this->config["appId"], $this->config["key"], $_SESSION['access_token'], $_SESSION['refresh_token']);

        $resp = $meli->get("/users/me",$params);

    }

    public function publicarArticuloSendJson($pArticulo,$mapeo=null){
        $pArticulo = $pArticulo["articulo"];
        $articulo = $this->mapeoArticulo($pArticulo,$mapeo);

        $params = array('access_token' => $_SESSION['access_token']);
        $meli = new Meli($this->config["appId"], $this->config["key"], $_SESSION['access_token'], $_SESSION['refresh_token']);

        $resp = $meli->post("items",$articulo,$params);

        $error = false;

        if(isset($resp["body"])){
            if(isset($resp["body"]->error)){

                //ERROR PUBLISH
                if($resp["body"]->message == "seller.unable_to_list" ){
                    $resp["body"]->message = $this->parseoMensajesMl($resp["body"]->message);
                }
                return (array(
                    "error" => $resp["body"]->error,
                    "message" => $resp["body"]->message,
                    "resp" => $resp
                ));
            }else{
                //SUCCESS PUBLISH
                if($this->actualizarEstadoArticulo($pArticulo,$resp)){
                    return (array(
                        "error" => false,
                        "body" => $resp["body"],
                        "httpCode" => $resp["httpCode"]
                    ));
                }else{
                    return (array(
                        "error" => true,
                        "body" => "",
                        "httpCode" => "99"
                    ));
                }
            }
        }else{
            //ERROR PUBLISH (ERROR REQUEST)

            return (array(
                "error" => true,
                "body" => "",
                "httpCode" => "500"
            ));
        }

    }


    public function isEnabled($pArticulo){
        if($pArticulo["articulo"]["visibility"]){
            return true;
        }

        return false;
    }

    public function getArticulo($filtros){
        $this->adapter = $this->tableGateway->getAdapter();
        $articulos = array();
        $where = " WHERE visibility='4' ";

        if($filtros["categorias"] && $filtros["categorias"] != "todas"){
            $where .= "AND categories='".$filtros["categorias"]."'";
        }

        if($filtros["estado"]){
            $where .= " AND estado='".$filtros["estado"]."'";
        }

        $sql = "SELECT * FROM articulos $where ORDER BY id ASC LIMIT 1";

        $res = $this->adapter->query($sql);

        if($res){
            $result = $res->execute();
            foreach($result as $r){
                return array("articulo" => $r);
            }
        }

        return array("articulo" => false);
    }

    public function sincronizarEstadoConMl($articuloMl){
        $this->adapter = $this->tableGateway->getAdapter();

        switch ($articuloMl->status) {
            case "closed":
                $estado="3";
                break;
            case "active":
                $estado="2";
                break;
            case "paused":
                $estado="4";
                break;
            default:
                $estado="0";
                break;
        }

        $date_created = "";
        $last_updated = "";
        $articuloMlId = "";
        $permalink = "";

        if(isset($articuloMl->date_created)){
            $date_created = $articuloMl->date_created;
        }

        if(isset($articuloMl->last_updated)){
            $last_updated = $articuloMl->last_updated;
        }

        if(isset($articuloMl->id)){
            $articuloMlId = $articuloMl->id;
        }

        if(isset($articuloMl->permalink)){
            $permalink = $articuloMl->permalink;
        }

        $sql = "UPDATE articulos SET estado='".$estado."', 
				permalink='".$permalink."', 
				date_created='".$date_created."', 
				last_updated='".$last_updated."' 
				WHERE ml_articulo_id='".$articuloMlId."'";

        $res = $this->adapter->query($sql);

        if($res){
            $res->execute();
            return true;
        }

        return false;
    }

    public function actualizarEstadoArticulo($articulo,$resp,$estado = "2"){
        $this->adapter = $this->tableGateway->getAdapter();
        $resp = $resp["body"];

        $sql = "UPDATE articulos SET estado='".$estado."', ml_articulo_id='".$resp->id."', permalink='".$resp->permalink."', date_created='".$resp->date_created."', last_updated='".$resp->last_updated."' WHERE id='".$articulo["id"]."'";



        $res = $this->adapter->query($sql);

        /*echo "<pre>";
        print_r(array(
            "resp" => $resp,
            "articulo" => $articulo,
            "sql" => $sql,
            "res" => $res,
        ));die();*/

        if($res){
            $res->execute();
            return true;
        }

        return false;
    }

    public function mapeoArticulo($articulo,$mapeo){

        /*
            [id] => 1
            [sku] => 10000
            [_attribute_set] => Default
            [_type] => simple
            [controled] =>
            [categories] => Farmacia/CARDIOVASCULAR/
            [_root_category] => Base
            [_product_websites] => base
            [description] => AKLIS 20MG 30 COMPRIMIDOS
            [name] => AKLIS 20MG 30 COMPRIMIDOS
            [price] => 551
            [special_price] =>
            [short_description] => AKLIS 20MG 30 COMPRIMIDOS
            [status] => 1
            [tax_class_id] => 0
            [visibility] => 4
            [weight] => 1
            [image] => 10000.jpg
            [small_image] => 10000.jpg
            [thumbnail] => 10000.jpg
            [qty] => 9999
            [magmi:delete] => 0
            [estado] => 1
            [fecha_publicacion] =>
';*/

        $template = $this->setDefaultTemplate($articulo);

        $articulo = array(
            "title" => $articulo["name"],
            "category_id"=> $mapeo["categoria_ml"],
            "official_store_id" => $this->config["store_id"],
            "price" => $articulo["price"],
            "currency_id" => "UYU",
            "available_quantity" => $articulo["qty"],
            "buying_mode" => "buy_it_now",
            "listing_type_id" => $this->config["listing_type"],
            "condition" => "new",
            "description" => $template,
            "video_id" => "",
            "warranty" => "",
            "pictures" => array(
                array("source" => $this->config["urlServidorImagenes"].$articulo["id"] . "." . $this->config["extensionImagenes"]),
            ),
        );

        return $articulo;
    }

    public function setDefaultTemplate($articulo){
        $productImg = $this->config["urlServidorImagenes"].$articulo["id"] . "." . $this->config["extensionImagenes"];
        $template = '<table width="918" border="0" cellpadding="0" cellspacing="0" align="center">
			   <tbody>
				  <tr>
					 <td>
						<table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#004694" style=" background-color:#004694;">
						   <tbody>
							  <tr>
								 <td align="center"><img src="'.$this->config["urlServidorImagenes"].'template/cabezal.png" width="230" height="110" alt=""/></td>
							  </tr>
						   </tbody>
						</table>
					 </td>
				  </tr>
				  <tr>
					 <td>
						<table width="100%" border="0" cellpadding="0" cellspacing="0">
						   <tbody>
							  <tr>
								 <td bgcolor="#FFFFFF">
									<table width="100%" border="0" cellpadding="0" cellspacing="0">
									   <tbody>
										  <tr>
											 <td width="2%"><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="30" alt=""/></td>
											 <td width="96%">&nbsp;</td>
											 <td width="2%"><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="30" alt=""/></td>
										  </tr>
										  <tr>
											 <td><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="30" alt=""/></td>
											 <td style="color: #0061F8; font-size: 16px; font-family: Helvetica, Arial, sans-serif">
												<table width="100%" border="0" cellspacing="0" cellpadding="0">
												   <tbody>
													  <tr>
														 <td valign="top"><img src="'.$productImg.'" width="400" height="402" alt=""/></td>
														 <td><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="30" alt=""/></td>
														 <td valign="top">
															<h1 style="color:#004694;">'.$articulo["name"].'</h1>
															<p style="color: #444; font-size: 20px; ">'.$articulo["name"].'</p>
															<ul style="margin: 10px 0 0 0;
															   padding: 15px 15px 10px 15px;
															   background-color: #eee;
															   border-radius: 3px;
															   -khtml-border-radius: 3px;
															   -moz-border-radius: 3px;
															   -webkit-border-radius: 3px;
															   font-size: 14px;
															   color:#444;
															   background-color: #f7f7f9;">
															   <li style="list-style:none; margin:0 0 10px 0;">Envíos sin costo en las zonas de entrega programada.</li>
															   <li style="list-style:none; margin:0 0 10px 0;">Programá el día y hora para la entrega de tu pedido.</li>
															   <li style="list-style:none; margin:0 0 10px 0;">Pedido Express: Entregas a domicilio de 08:00 a 22:00 horas.</li>
															   <li style="list-style:none; margin:0 0 10px 0;">Envíos a todo el interior del país (pueden aplicar costos de envío).</li>
															   <li style="list-style:none; margin:0 0 10px 0;">Política de devolución flexible con posibilidad de devolución en todos nuestros locales.</li>
															</ul>
														 </td>
													  </tr>
													  <tr>
														 <td valign="top">&nbsp;</td>
														 <td>&nbsp;</td>
														 <td valign="top">&nbsp;</td>
													  </tr>
												   </tbody>
												</table>
												<div style="margin: 10px 0 0 0;
												   padding: 15px 15px 10px 15px;
												   background-color: #0151CA;
												   border-radius: 3px;
												   -khtml-border-radius: 3px;
												   -moz-border-radius: 3px;
												   -webkit-border-radius: 3px;
												   font-size: 14px;
												   color:#fff;">
												   <table width="100%" border="0" cellspacing="0" cellpadding="0">
													  <tbody>
														 <tr>
															<td width="47%">
															   <p>MEDIOS DE PAGO ACEPTADOS</p>
															   <p style="list-style:none; margin:0 0 10px 0;"><img src="'.$this->config["urlServidorImagenes"].'template/pmethods.png" width="395" height="40" alt=""/></p>
															</td>
															<td width="2%"><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="30" alt=""/></td>
															<td width="51%">
															   <p><strong>Antes de recibir tu producto debes coordinar previamente por correo electrónico.</strong></p>
															   <p>La empresa se reserva el derecho a retirar esta promoción</p>
															</td>
														 </tr>
													  </tbody>
												   </table>
												</div>
											 </td>
											 <td><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="30" alt=""/></td>
										  </tr>
										  <tr>
											 <td><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="30" alt=""/></td>
											 <td>&nbsp;</td>
											 <td><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="30" alt=""/></td>
										  </tr>
									   </tbody>
									</table>
								 </td>
							  </tr>
						   </tbody>
						</table>
					 </td>
				  </tr>
				  <tr>
					 <td>
						<table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#004694" style="background-color:#004694;">
						   <tbody>
							  <tr>
								 <td width="2%"><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="20" alt=""/></td>
								 <td width="96%">&nbsp;</td>
								 <td width="2%"><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="20" alt=""/></td>
							  </tr>
							  <tr>
								 <td>&nbsp;</td>
								 <td>
									<table width="100%" border="0" cellpadding="0" cellspacing="0">
									   <tbody>
										  <!-- CODIGO REMOVIDO PARA QUE NO BANEEN lA CUENTA -->
										  <!--<tr>
											 <td width="6%" valign="middle"><img src="'.$this->config["urlServidorImagenes"].'template/iso.png" width="54" height="30"></td>
											 <td width="23%" align="right" valign="middle" style="color:#fff; font-size:12px; font-family:Helvetica, Arial, sans-serif">Seguinos en:</td>
											 <td width="8%" align="right" valign="middle" style="color:#fff; font-size:12px; font-family:Helvetica, Arial, sans-serif"><a href=""><img src="'.$this->config["urlServidorImagenes"].'template/fb.png" width="18" height="18" alt="" border="0" /></a> <a href=""><img src="'.$this->config["urlServidorImagenes"].'template/tw.png" width="18" height="18" alt="" border="0"/></a> <a href=""><img src="'.$this->config["urlServidorImagenes"].'template/in.png" width="18" height="18" alt="" border="0"/></a></td>
										  </tr>-->
									   </tbody>
									</table>
								 </td>
								 <td>&nbsp;</td>
							  </tr>
							  <tr>
								 <td><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="20" alt=""/></td>
								 <td>&nbsp;</td>
								 <td><img src="'.$this->config["urlServidorImagenes"].'template/spacer.gif" width="20" height="20" alt=""/></td>
							  </tr>
						   </tbody>
						</table>
					 </td>
				  </tr>
			   </tbody>
			</table>';

        return $template;
    }

    public function getCategoriasFromMl($category=null){

        if(!$category){
            return file_get_contents("https://api.mercadolibre.com/sites/MLU/categories");
        }

        if(is_array($category)){
            if(!$category[0]){
                return file_get_contents("https://api.mercadolibre.com/sites/MLU/categories");
            }
            $category = $category[count($category)-1];
        }

        return file_get_contents("https://api.mercadolibre.com/categories/".$category);
        //path_from_root
    }

    public function getCateogriaMlDb($id){
        $this->adapter = $this->tableGateway->getAdapter();
        $sql = "SELECT * FROM categorias_ml WHERE id_categoria_ml='".$id."'";

        $res = $this->adapter->query($sql);
        if($res){
            $result = $res->execute();
            foreach($result as $r){
                return $r;
            }
        }

        return false;
    }

    public function insertCategoriasML($cat){
        $this->adapter = $this->tableGateway->getAdapter();

        //$categorias = $cat["catMl"];

        foreach($cat["catMl"] as $categoria){
            $objCategoria = json_decode($this->getCategoriasFromMl($categoria));
            $nombreCategoria = $objCategoria->name;
            if(!$this->getCateogriaMlDb($objCategoria->id)){
                $sql = "INSERT INTO categorias_ml(id_categoria_ml, nombre_categoria_ml) VALUES ('".$categoria."','".$nombreCategoria."')";
                $res = $this->adapter->query($sql);
                if($res){
                    $res->execute();
                }
            }
        }

        return false;
    }

    public function editCategoriasArticulos($cat){
        $this->insertCategoriasML($cat);
        $this->adapter = $this->tableGateway->getAdapter();

        $exist = $this->getCategoriaMapeo($cat["name_cat"]);

        $categorias = implode(",",$cat["catMl"]);
        $categoria = $cat["catMl"][count($cat["catMl"])-1];

        if(!$exist){
            $sql = "INSERT INTO mapeo_categorias(input_id, categoria_geopos, categoria_ml,categorias_ml) VALUES ('".$cat["name_cat"]."','".$cat["catGeopos"]."','".$categorias."','".$categoria."')";
        }else{
            $sql = "UPDATE mapeo_categorias set categoria_geopos='".$cat["catGeopos"]."',categoria_ml='".$categoria."',categorias_ml='".$categorias."' WHERE input_id='".$cat["name_cat"]."'";
        }

        $res = $this->adapter->query($sql);

        if($res){
            $res->execute();
            return true;
        }

        return false;
    }

    public function getCategoriaMapeo($inputId,$categoria_geopos=null){
        $this->adapter = $this->tableGateway->getAdapter();

        $campo = "input_id";
        if($categoria_geopos){
            $inputId = $categoria_geopos;
            $campo = "categoria_geopos";
        }

        $sql = "SELECT * FROM mapeo_categorias WHERE $campo='".$inputId."'";
        $res = $this->adapter->query($sql);

        if($res){
            $result = $res->execute();
            foreach($result as $r){
                return $r;
            }
        }

        return false;
    }

    public function validarCategoria($category){
        $ret = file_get_contents("https://api.mercadolibre.com/categories/".$category);

        if($ret){
            $ret = json_decode($ret);
            if(!$ret->settings->listing_allowed){
                return json_encode(array(
                    "error" => true,
                    "message" => "La categoria(s) seleccionada(s) no son aplicables, seleccione una Categoria hija."
                ));
            }
        }
        return json_encode(array(
            "error" => false,
            "message" => ""
        ));
    }

}

