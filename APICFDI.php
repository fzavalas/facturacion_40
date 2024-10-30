<?php
require_once "clases/conexion/conexion.php";
require_once "configuracion.php";

class APICFDI {

    private $url;
    private $user;
    private $password;

    function __construct(){
      $configuracion = new configuracion();
      $test = $configuracion->test === 'true'? true: false;

      $this->url = $test ? $configuracion->testUrl : $configuracion->prodUrl;
      $this->user = $test ? $configuracion->testUser : $configuracion->prodUser;
      $this->password = $test ? $configuracion->testPassword : $configuracion->prodPassword;
    }

    function timbrar($json){
        //obtener el folio de la factura y actualizar si se timbro correctamente, de lo contrario no actualizar

        $curl = curl_init($this->url);// Ingresamos la url de la api o servicio a consumir 
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt($curl, CURLOPT_POST, true);// Autorizamos enviar datos
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        //curl_setopt($curl, CURLOPT_COOKIEJAR,  __DIR__.'/cookies.txt'); // Archivo para guardar datos de sesion 
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($curl, CURLOPT_USERPWD, "$this->user:$this->password");

        $response = curl_exec($curl);// respuesta generada
        $err = curl_error($curl); // muestra errores en caso de existir

        curl_close($curl); // termina la sesión 

        if ($err) {
            echo "cURL Error #:" . $err; // mostramos el error
        } else {
            return $response;
        }
    }
}
?>