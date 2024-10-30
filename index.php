<?php
include_once("Factura40.php"); 
include_once("APICFDI.php"); 

error_reporting(E_ALL);
ini_set("display_errors", 0);

$idRemision = 0;
if($_SERVER['REQUEST_METHOD']=='GET'){
    $idRemision = isset($_REQUEST['idGeneral']) ? $_REQUEST['idGeneral']: null;
    $folio = isset($_REQUEST['folio']) ? $_REQUEST['folio']: null;
    $ruta = isset($_REQUEST['ruta']) ? $_REQUEST['ruta']: null;
    $reimpresion = isset($_REQUEST['reimpresion']) ? $_REQUEST['reimpresion']: null; //manda el idremision
    echo "Utilice el siguiente link para timbrar: http://189.223.248.234:3008/facturacion40/facturar.php";

/*
    $jsonReq =  json_decode(file_get_contents('php://input'));
    if(!isset($idRemision)){
        $idRemision = isset($jsonReq->idGeneral) ? $jsonReq->idGeneral : null;
    }
    if(!isset($folio)){
        $folio = isset($jsonReq->folio) ? $jsonReq->folio : null;
    }
    if(!isset($ruta)){
        $ruta = isset($jsonReq->ruta) ? $jsonReq->ruta : null;
    }
    if(!isset($reimpresion)){
        $reimpresion = isset($jsonReq->reimpresion) ? $jsonReq->reimpresion : null;
    }

    $factura = new Factura40();
    $apicfdi = new APICFDI();
    $json = null;
    if(isset($folio)){
        $json = $factura->prepararComplementoPago($folio);
    } else  if(isset($idRemision)){
        error_log("- Solicitud de la remision por link: ".$idRemision . PHP_EOL,3,"error_log.txt");
        $json = $factura->prepararFactura($idRemision);
    } else  if(isset($ruta)){
        $json = $factura->prepararFacturaGlobal($ruta);
    } else  if(isset($reimpresion)){
        error_log("- leyendo: ".$reimpresion . PHP_EOL,3,"error_log.txt");
        echo $factura->leerXMLUrl__($reimpresion);
    }

    if(isset($json)) {
        //si consecutivo existe es que ya se timbro anteriormente
        if(isset($json->consecutivo)){
            if($json->consecutivo == 'TIMBRANDO'){
                error_log("   La remision: ".$idRemision." esta en proceso: ".$json->consecutivo . PHP_EOL,3,"error_log.txt");
            } else {
                error_log("   La remision: ".$idRemision." Ya esta facturada: ".$json->consecutivo . PHP_EOL,3,"error_log.txt");
                echo json_encode($json);
            }
        } else {
            //error_log("".$json . PHP_EOL,3,"error_log.txt");

            //si nconsecutivo no exisrte entonces mandar timbrar
            $response = $apicfdi->timbrar($json);
            $resultado = json_decode($response);
            if(isset($resultado->uuid)) {
                error_log("   Json timbrado correctamente La remision: ".$idRemision.", UUID: ".$resultado->uuid . PHP_EOL,3,"error_log.txt");
            } else {
                error_log("   Error al timbrar La remision: ".$idRemision.", ".$resultado->mensaje . PHP_EOL,3,"error_log.txt");
                $factura->removerConsecutivoTemporal($idRemision);
                $factura->actualizarUUIDFallido($idRemision, $resultado->mensaje);
            }

            if(isset($folio)){
            } else  if(isset($idRemision)){
                $factura->actualizarVenta($response, $idRemision);
            } else  if(isset($ruta)){
            }

            $factura->generarArchivos($response);

            $jsonResult = $factura->generarFacturaJSON($response, $idRemision);
            echo $jsonResult;
       }
    }*/
}

if($_SERVER['REQUEST_METHOD']=='POST'){
    $idRemision = isset($_REQUEST['idGeneral']) ? $_REQUEST['idGeneral']: null;
    $folio = isset($_REQUEST['folio']) ? $_REQUEST['folio']: null;
    $ruta = isset($_REQUEST['ruta']) ? $_REQUEST['ruta']: null;
    $reimpresion = isset($_REQUEST['reimpresion']) ? $_REQUEST['reimpresion']: null; //manda el idremision


    $jsonReq =  json_decode(file_get_contents('php://input'));
    if(!isset($idRemision)){
        $idRemision = isset($jsonReq->idGeneral) ? $jsonReq->idGeneral : null;
    }
    if(!isset($folio)){
        $folio = isset($jsonReq->folio) ? $jsonReq->folio : null;
    }
    if(!isset($ruta)){
        $ruta = isset($jsonReq->ruta) ? $jsonReq->ruta : null;
    }
    if(!isset($reimpresion)){
        $reimpresion = isset($jsonReq->reimpresion) ? $jsonReq->reimpresion : null;
    }

    $factura = new Factura40();
    $apicfdi = new APICFDI();
    $json = null;
    if(isset($folio)){
        $json = $factura->prepararComplementoPago($folio);
    } else  if(isset($idRemision)){
        error_log("- Solicitud de la remision: ".$idRemision . PHP_EOL,3,"error_log.txt");
        $json = $factura->prepararFactura($idRemision);
    } else  if(isset($ruta)){
        $json = $factura->prepararFacturaGlobal($ruta);
    } else  if(isset($reimpresion)){
        error_log("- leyendo: ".$reimpresion . PHP_EOL,3,"error_log.txt");
        echo $factura->leerXMLUrl__($reimpresion);
    }

    if(isset($json)) {
        //si consecutivo existe es que ya se timbro anteriormente
        if(isset($json->consecutivo)){
            if($json->consecutivo == 'TIMBRANDO'){
                error_log("   La remision: ".$idRemision." esta en proceso: ".$json->consecutivo . PHP_EOL,3,"error_log.txt");
            } else {
                error_log("   La remision: ".$idRemision." Ya esta facturada: ".$json->consecutivo . PHP_EOL,3,"error_log.txt");
                echo json_encode($json);
            }
        } else {
            //error_log("".$json . PHP_EOL,3,"error_log.txt");

            //si nconsecutivo no exisrte entonces mandar timbrar
            $response = $apicfdi->timbrar($json);
            $resultado = json_decode($response);
            if(isset($resultado->uuid)) {
                error_log("   Json timbrado correctamente La remision: ".$idRemision.", UUID: ".$resultado->uuid . PHP_EOL,3,"error_log.txt");
            } else {
                error_log("   Error al timbrar La remision: ".$idRemision.", ".$resultado->mensaje . PHP_EOL,3,"error_log.txt");
                $factura->removerConsecutivoTemporal($idRemision);
                $factura->actualizarUUIDFallido($idRemision, $resultado->mensaje);
            }

            if(isset($folio)){
            } else  if(isset($idRemision)){
                $factura->actualizarVenta($response, $idRemision);
            } else  if(isset($ruta)){
            }

            $factura->generarArchivos($response);

            $jsonResult = $factura->generarFacturaJSON($response, $idRemision);
            echo $jsonResult;
       }
    }
}
?>