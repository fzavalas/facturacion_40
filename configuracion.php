<?php

class configuracion {
    public $testUrl;
    public $testUser;
    public $testPassword;
    public $prodUrl;
    public $prodUser;
    public $prodPassword;
    public $tipoDeComprobante;
    public $documentPath;
    public $test;
    public $testEmisorRfc;
    public $testEmisorNombre;
    public $testEmisorRegimenFiscal;
    public $testReceptorRfc;
    public $testReceptorNombre;
    public $testReceptorDomicilioFiscalReceptor;
    public $testReceptorRegimenFiscalReceptor;

    function __construct(){
        $listadatos = $this->leerConfig();
        foreach($listadatos as $key => $value) {
            $this-> testUrl = $value['testApicfdi_url'];
            $this-> testUser = $value['testApicfdi_user'];
            $this-> testPassword = $value['testApicfdi_password'];
            $this-> prodUrl = $value['prodApicfdi_url'];
            $this-> prodUser = $value['prodApicfdi_user'];
            $this-> prodPassword = $value['prodApicfdi_password'];
            $this-> tipoDeComprobante = $value['tipoDeComprobante'];
            $this-> documentPath = $value['documentPath'];
            $this-> test = $value['test'];
            $this-> testEmisorRfc = $value['testEmisorRfc'];
            $this-> testEmisorNombre = $value['testEmisorNombre'];
            $this-> testEmisorRegimenFiscal = $value['testEmisorRegimenFiscal'];
            $this-> testReceptorRfc = $value['testReceptorRfc'];
            $this-> testReceptorNombre = $value['testReceptorNombre'];
            $this-> testReceptorDomicilioFiscalReceptor = $value['testReceptorDomicilioFiscalReceptor'];+
            $this-> testReceptorRegimenFiscalReceptor = $value['testReceptorRegimenFiscalReceptor'];          
        }
    }
    
    private function leerConfig(){
        $direccion = dirname(__FILE__);
        $jsondata = file_get_contents($direccion."/"."config");
        return json_decode($jsondata, true);
    }
}

?>