<?php
require_once "clases/conexion/conexion.php";

$url = '\\\\25.0.43.135\\htdocs2\\facturacion40';
$urlTimbrado ='http://189.223.248.234:3008/facturacion40Pruebas/factura2/index.php';


    if($_SERVER['REQUEST_METHOD']=='POST'){
      echo "Hola post";
    } else 	if($_SERVER['REQUEST_METHOD']=='GET'){

      $idRemision = isset($_REQUEST['idRemision']) ? $_REQUEST['idRemision']: null;
      if(!isset($idRemision)){
        $jsonReq =  json_decode(file_get_contents('php://input'));
        $idRemision = isset($jsonReq->idRemision) ? $jsonReq->idRemision : null;
      }

      if(!isset($idRemision)){
        echo json_encode("Remision no encontrada"); 
      }

      $conexion = new conexion;
      $query = "select 
                d.RFC as emisor , a.Consecutivo , c.RFC as receptor, a.Cancelado  
                from VentasYCobranzaCAN.dbo.VentasApp a,
                VentasYCobranzaCAN.dbo.Sucursales b,
                VentasYCobranzaCAN.dbo.Clientes c,
                VentasYCobranzaCAN.dbo.CFDiDatosEmisor d 
                where b.Sucursal = a.Sucursal 
                and c.Cliente = b.Cliente 
                and d.Ciudad = a.Ciudad 
                and a.IDRemision = ".$idRemision.";";

      $stmVenta = $conexion->preparar( $query);
      $stmVenta->execute();
      $venta = $stmVenta->fetch(PDO::FETCH_OBJ);
            
      // Facturar si no esta facturada
      if(!isset($venta->Consecutivo)){

        $jsonResult = ""; 
        //datos a enviar
        $data = array("idGeneral" => $idRemision);
        //url contra la que atacamos
        $ch = curl_init($urlTimbrado);
        //a true, obtendremos una respuesta de la url, en otro caso, 
        //true si es correcto, false si no lo es
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //establecemos el verbo http que queremos utilizar para la petición
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        //enviamos el array data
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
        //obtenemos la respuesta
        $response = curl_exec($ch);
        // Se cierra el recurso CURL y se liberan los recursos del sistema
        curl_close($ch);
        if(!$response) {
            echo json_encode("Aviso: sin respuesta del timbrado");
            return;
        } else {
            $jsonResult = json_decode($response);
            if(isset($jsonResult->uuid)) { 

              $stmVenta = $conexion->preparar( $query);
              $stmVenta->execute();
              $venta = $stmVenta->fetch(PDO::FETCH_OBJ);
              $url = '\\\\25.0.43.135\\htdocs2\\facturacion40Pruebas\\factura2';
            } else {
              echo json_encode($jsonResult->mensaje);
              return;
            }
        }
      }

      $path = $url.'\\FacturasGeneradas\\';
      $fileName = $venta->emisor.'_'.$venta->Consecutivo.'_'.$venta->receptor.'.pdf';
      
      // Ruta del archivo PDF
      $filePath =  $path.$fileName;

      // Establecer las cabeceras adecuadas
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
      header('Content-Length: ' . filesize($filePath));

      // Leer el archivo y enviarlo al navegador
      readfile($filePath);
      exit;
    }
?>
