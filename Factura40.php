<?php
require_once "clases/conexion/conexion.php";
require_once "configuracion.php";

class Factura40 {
    private $documentPath;
    private $configuracion;
    private $test;
    private $conexion;

    function __construct(){
        date_default_timezone_set('America/Tijuana');
        $this->configuracion = new configuracion();
        $this->test = $this->configuracion->test === 'true'? true: false;
        $this->documentPath = $this->configuracion->documentPath;
        $this->conexion = new conexion;
    }
    
    function prepararFactura($idRemision){
  
        // Venta
        $stmVenta = $this->conexion->preparar("SELECT IDRemision,Consecutivo,Fecha,Total,Iva,Credito,Informal,Sucursal,Ciudad,Ruta,Factura FROM [VentasApp] where IDRemision = ".$idRemision);
        $stmVenta->execute();
        $venta = $stmVenta->fetch(PDO::FETCH_OBJ);
        
        if(empty($venta->IDRemision)){
            return json_encode("err");
        }
        
        if($venta->Consecutivo==''){

            // actualizar consecutivo para que no se duplique solicitud
            $queryActualizarConsecutivo ="update  VentasApp set consecutivo='TIMBRANDO' where IDRemision = '".$idRemision."' ";
            $stmActualizarConsecutivo = $this->conexion->preparar($queryActualizarConsecutivo);
            $stmActualizarConsecutivo->execute();

            $sFecha = $venta->Fecha;
            $dt = new DateTime();
            $dt = date_time_set($dt,0,0,0,0);
            $date = $dt->format('c');


            // Comprobante
            $factura = new stdClass();
            $factura->Version = "4.0";
            $factura->Serie = ""; 
            $factura->Folio = ""; 
            $factura->Fecha = $date;
            $factura->Sello = "";
            $factura->FormaPago = "";
            $factura->NoCertificado = "";
            $factura->Certificado = "";
            $factura->CondicionesDePago = null;
            $factura->SubTotal = number_format((float)($venta->Total - $venta->Iva), 2, '.', '');
            $factura->Descuento = null;
            $factura->Moneda = "MXN";
            $factura->TipoCambio = "1";
            $factura->Total = number_format((float)($venta->Total), 2, '.', '');
            $factura->TipoDeComprobante = $this->configuracion->tipoDeComprobante; //Ingreso = I, Egreso = E
            $factura->Exportacion = "01"; //01=No Aplica, 02=Definitiva, 03=Temporal
            $factura->MetodoPago = ($venta->Credito==1&&$venta->Informal==0) ? "PPD" : "PUE" ;
            $factura->LugarExpedicion = "";//cp de la matriz o sucursal

            // InformacionGlobal
            $factura->InformacionGlobal=new StdClass();
            $factura->InformacionGlobal->Periodicidad = null;
            $factura->InformacionGlobal->Meses = null;
            $factura->InformacionGlobal->Anio = null;

            // CfdiRelacionados
            $cfdiArray = array();
            $cfdiArray["TipoRelacion"]="";

            $uuidArray = array();
            $uuidArray["UUID"]="";
            $uuidRelacionadosArray[]=$uuidArray;

            $cfdiArray["CfdiRelacionado"] = $uuidRelacionadosArray;

            $CfdiRelacionadosArray[]=$cfdiArray;
            $factura->CfdiRelacionados = $CfdiRelacionadosArray;

            // Emisor
            $queryEmisor = "SELECT TOP (1) Nombre, RFC, Calle, Exterior, Interior, Colonia, Localidad, Referencia, CP, GLN, Telefono, SerieFC, SerieNC, SerieCP,VersionCfdi as version
            FROM CFDiDatosEmisor";
            $stmEmisor = $this->conexion->preparar($queryEmisor);
            $stmEmisor->execute();
            $emisor = $stmEmisor->fetch(PDO::FETCH_OBJ);

            $factura->LugarExpedicion = $emisor->CP;//cp de la matriz o sucursal

            $factura->Emisor=new StdClass();
            $factura->Emisor->Rfc = $this->test ? $this->configuracion->testEmisorRfc : $emisor->RFC;
            $factura->Emisor->Nombre = $this->test ? $this->configuracion->testEmisorNombre : $emisor->Nombre;
            $factura->Emisor->RegimenFiscal = $this->test ? $this->configuracion->testEmisorRegimenFiscal: "601"; //601 = General de Ley Personas Morales	

            // Sucursal
            $querySucursal = "SELECT TOP 1 * FROM [Sucursales] WHERE Sucursal = ".$venta->Sucursal;
            $stmSucursal =  $this->conexion->preparar($querySucursal);
            $stmSucursal->execute();
            $sucursal = $stmSucursal->fetch(PDO::FETCH_OBJ);
            $ivaSucursal = $sucursal->Iva;

            // Receptor (Cliente)
            $queryCliente="SELECT * FROM [VwCFDiReceptor] where Cliente = ".$sucursal->Cliente;
            $stmCliente =  $this->conexion->preparar($queryCliente);
            $stmCliente->execute();
            $cliente = $stmCliente->fetch(PDO::FETCH_OBJ);

            $factura->FormaPago = ($venta->Credito==1&&$venta->Informal==0) ? "99" : $cliente->MetodoPago ;

            $factura->Receptor = new StdClass();
            $factura->Receptor->Rfc=   $this->test ?  $this->configuracion->testReceptorRfc  : $cliente->RFC;
            $factura->Receptor->Nombre = $this->test ? $this->configuracion->testReceptorNombre : $cliente->RazonSocial;
            $factura->Receptor->DomicilioFiscalReceptor=  $this->test ? $this->configuracion->testReceptorDomicilioFiscalReceptor : $cliente->CP; // codigo postal del cliente
            $factura->Receptor->RegimenFiscalReceptor =    $this->test ? $this->configuracion->testReceptorRegimenFiscalReceptor : $cliente->RegimenFiscal;
            $factura->Receptor->ResidenciaFiscal = null; // aplica cuando es un cliente extranjero
            $factura->Receptor->NumRegIdTrib = null; // aplica cuando es un cliente extranjero
            $factura->Receptor->UsoCFDI= $cliente->UsoCFdi;  //can UsoCFdi //covanosa  usoCFDI
        
            // Conceptos
            $queryConceptos ="SELECT [Numero]
            ,[IDRemision]
            ,[VentasAppMov].[Producto]
            ,[VentasAppMov].[Unidades]
            ,[VentasAppMov].[Precio]
            ,[VentasAppMov].[Iva]
            ,[VentasAppMov].[Ieps]
            ,[PrecioLista],
            [BarCode],
            [Nombre],
            [ClaveSAT],
            [UnidadSAT]
            FROM [VentasAppMov] ,[Productos] where [Productos].[Producto] =[VentasAppMov].[Producto] and IDRemision =   ".$venta->IDRemision;
            $stmConceptos = $this->conexion->preparar($queryConceptos);
            $stmConceptos->execute();
            $Conceptos = $stmConceptos->fetchAll(PDO::FETCH_OBJ);

            $conceptosArray = array();
            $impuestosTrasladadosArray = array();
            $tasasImpuestosTrasladadosArray = array();

            $totalIva = 00;
            $totalBase = 00;
            foreach ($Conceptos as $key => $value) {
                
                $Importe =$value->Precio*$value->Unidades;

                $conceptoArray = new StdClass();
                $conceptoArray->ClaveProdServ = $value->ClaveSAT;
                $conceptoArray->NoIdentificacion = $value->BarCode;
                $conceptoArray->Cantidad = $value->Unidades;
                $conceptoArray->ClaveUnidad = $value->UnidadSAT; 
                $conceptoArray->Unidad = "Pieza";
                $conceptoArray->Descripcion = $value->Nombre;
                $conceptoArray->ValorUnitario = number_format((float)$value->Precio, 2, '.', '');
                $conceptoArray->Importe = number_format((float)$Importe, 2, '.', '');
                $conceptoArray->Descuento = null;
                $conceptoArray->ObjetoImp = "02"; // 01=No objeto de impuesto, 02=Sí objeto de impuesto, 03=Sí objeto del impuesto y no obligado al desglose, 04=Sí objeto del impuesto y no causa impuesto

                //Impuestos
                $importeIva = $Importe*( $ivaSucursal/100);

                $secImpuestosArray = new StdClass();
                $secImpuestosArray->TipoImpuesto = "Traslado";
                $secImpuestosArray->Base = number_format((float)$Importe, 2, '.', '');
                $secImpuestosArray->Impuesto = "002"; //001=ISR, 002=IVA, 003=IEPS
                $secImpuestosArray->TipoFactor = "Tasa"; 
                $secImpuestosArray->TasaOCuota = number_format((float)( $value->Iva >0 ? $ivaSucursal/100  : 0), 6, '.', ''); 
                $secImpuestosArray->Importe = number_format((float)($value->Iva * $value->Unidades ), 2, '.', '');

                $totalIva+=$secImpuestosArray->Importe;
                $totalBase+=$secImpuestosArray->Base;

                $conceptoArray->Impuestos = new StdClass();
                $conceptoArray->Impuestos->SecImpuesto = array($secImpuestosArray);
                
                //Informacion aduanera
                $InformacionAduanera = new StdClass();
                $InformacionAduanera->NumeroPedimento = null;
                $conceptoArray->InformacionAduanera = array($InformacionAduanera); 

                $conceptosArray[] = $conceptoArray;


                // impuestos trasladados
                $impuestoTrasladado = new StdClass();
                $impuestoTrasladado->TipoImpuesto = $secImpuestosArray->TipoImpuesto;
                $impuestoTrasladado->TasaOCuota = $secImpuestosArray->TasaOCuota;
                $impuestoTrasladado->Base = $secImpuestosArray->Base;
                $impuestoTrasladado->Importe = $secImpuestosArray->Importe;
                $impuestoTrasladado->Impuesto = $secImpuestosArray->Impuesto;
                $impuestoTrasladado->TipoFactor = $secImpuestosArray->TipoFactor;
                $impuestosTrasladadosArray[] = $impuestoTrasladado;
                $tasasImpuestosTrasladadosArray[] = $secImpuestosArray->TasaOCuota;
            }
            $factura->Conceptos = new StdClass();
            $factura->Conceptos->Concepto = $conceptosArray;

            //$factura->Concepto = $conceptosArray;

            //Impuestos
            $factura->Impuestos = new StdClass();
            //$factura->Impuestos->TotalImpuestosRetenidos = number_format((float)(0), 2, '.', '');
            $factura->Impuestos->TotalImpuestosTrasladados = number_format((float)($totalIva ), 2, '.', '');

            //Impuestos por Tasa o cuota
            $SecImpuestoArray = array();
            $tasasImpuestosTrasladadosArray = array_unique($tasasImpuestosTrasladadosArray); //eliminar repetidos
            foreach ($tasasImpuestosTrasladadosArray as $keyBase => $tipoFactor) {
                
                $tipoImpuesto = new StdClass();
                $tipoImpuesto->TipoImpuesto = "";
                $tipoImpuesto->Base = 00;
                $tipoImpuesto->Impuesto = "";
                $tipoImpuesto->TipoFactor = "";
                $tipoImpuesto->TasaOCuota = "";
                $tipoImpuesto->Importe = 00;
                
                foreach ($impuestosTrasladadosArray as $key => $value) {
                    if($value->TasaOCuota == $tipoFactor ){
                        $tipoImpuesto->TipoImpuesto = $value->TipoImpuesto;
                        $tipoImpuesto->Base += $value->Base;
                        $tipoImpuesto->Base = number_format((float)($tipoImpuesto->Base), 2, '.', ''); 
                        $tipoImpuesto->Impuesto = $value->Impuesto;
                        $tipoImpuesto->TipoFactor = $value->TipoFactor;
                        $tipoImpuesto->TasaOCuota = $value->TasaOCuota;
                        $tipoImpuesto->Importe += $value->Importe; 
                        $tipoImpuesto->Importe = number_format((float)($tipoImpuesto->Importe), 2, '.', ''); 
                    }
                }
                $SecImpuestoArray[] = $tipoImpuesto; //agrega elemento al array
            }
            $factura->Impuestos->SecImpuesto = $SecImpuestoArray;

            //Complementos
            $factura->Complementos = array(new StdClass());
            
            //Correos
            $correos = $this->obtenerCorreos($venta->Ciudad, $sucursal->Cliente, $venta->Ruta, $venta->Sucursal);

            $factura->Addenda = new StdClass();
            $factura->Addenda->EdiFactMxDatos = new StdClass();
            $factura->Addenda->EdiFactMxDatos->ReceptorDatos = new StdClass();
            $factura->Addenda->EdiFactMxDatos->ReceptorDatos->Email = $correos;
            $factura->Addenda->EdiFactMxDatos->Observaciones = "CLIENTE ".$sucursal->Cliente." ".substr($sucursal->Nombre,0,30)." Folio: ".$venta->IDRemision." Ruta: ".$venta->Ruta;

            //Folios
            $consumirFolio = $venta->Factura==''? true : false;

            $queryFolios = "SELECT SerieApp, ConsecutivoApp  FROM CFDIEdiFact WHERE Ciudad = ".$venta->Ciudad;
            $stmFolios = $this->conexion->preparar($queryFolios);
            $stmFolios->execute();
            $CFDIEdiFact = $stmFolios->fetch(PDO::FETCH_OBJ);

            //Actualziar folios
            if(!$this->test && $consumirFolio){
                $queryActualizarFolio = "UPDATE CFDIEdiFact Set ConsecutivoApp = ConsecutivoApp + 1 WHERE Ciudad  = ".$venta->Ciudad;
                $stmActualizarFolio = $this->conexion->preparar($queryActualizarFolio);
                $stmActualizarFolio->execute();
            }
            
            $factura->Serie = $CFDIEdiFact->SerieApp; 
            $factura->Folio = $consumirFolio ? $CFDIEdiFact->ConsecutivoApp++ : $venta->Factura;

            //actualizar factura para no consumir mas folios
            if($consumirFolio){
                $queryActualizarFactura ="update  VentasApp set factura  = '".$factura->Folio."' where IDRemision = '".$idRemision."' ";
                $stmActualizarFactura = $this->conexion->preparar($queryActualizarFactura);
                $stmActualizarFactura->execute();
            }

            return json_encode($factura, JSON_PRETTY_PRINT);
        } else {
            $retorno = new StdClass();
            $retorno->consecutivo = $venta->Consecutivo;
            $retorno->idGeneral = $venta->IDRemision;
            $retorno->solicitada = true;
            return ($retorno);            
        }
    }

    function prepararComplementoPago($folio){
        // Complementos de pago
        $stmComplementos = $this->conexion->preparar("SELECT DISTINCT  [Folio],[Cliente],[Importe],[FechaPago],[FormaPago],[LactoMark]
         FROM [VwCFDiComplementoPago] where Saldo = 0 AND Folio = ".$folio);
        $stmComplementos->execute();
        $complementos = $stmComplementos->fetch(PDO::FETCH_OBJ);
        
        if(empty($complementos->Folio)){
            echo "No se encontro el folio ".$folio."\n";
            return json_encode("err");
        }
        
        // Comprobante
        $factura = new stdClass();
        $factura->Version = "4.0";
        $factura->Serie = ""; 
        $factura->Folio = ""; 
        $factura->Fecha = date("c",time());
        $factura->Sello = "";
        $factura->FormaPago = "";
        $factura->CondicionesDePago = null;
        $factura->SubTotal = "0";
        $factura->Descuento = null;
        $factura->Moneda = "XXX";
        $factura->TipoCambio = null;
        $factura->Total = "0";
        $factura->TipoDeComprobante = "P"; //Pagos = P
        $factura->Exportacion = "01"; //01=No Aplica, 02=Definitiva, 03=Temporal
        $factura->MetodoPago = null;
        $factura->LugarExpedicion = "";//cp de la matriz o sucursal
        $factura->InformacionGlobal = null;
        $factura->CfdiRelacionados =  null;
        $factura->Impuestos =  null;

        // Emisor
        $queryEmisor = "SELECT TOP (1) Nombre, RFC, Calle, Exterior, Interior, Colonia, Localidad, Referencia, CP, GLN, Telefono, SerieFC, SerieNC, SerieCP,VersionCfdi as version
        FROM CFDiDatosEmisor";
        $stmEmisor = $this->conexion->preparar($queryEmisor);
        $stmEmisor->execute();
        $emisor = $stmEmisor->fetch(PDO::FETCH_OBJ);

        $factura->LugarExpedicion = $emisor->CP;//cp de la matriz o sucursal

        $factura->Emisor=new StdClass();
        $factura->Emisor->Rfc = $this->test ? $this->configuracion->testEmisorRfc : $emisor->RFC;
        $factura->Emisor->Nombre = $this->test ? $this->configuracion->testEmisorNombre : $emisor->Nombre;
        $factura->Emisor->RegimenFiscal = $this->test ? $this->configuracion->testEmisorRegimenFiscal: "601"; //601 = General de Ley Personas Morales	

        // Receptor (Cliente)
        $queryCliente="SELECT * FROM [VwCFDiReceptor] where Cliente = ".$complementos->Cliente;
        $stmCliente =  $this->conexion->preparar($queryCliente);
        $stmCliente->execute();
        $cliente = $stmCliente->fetch(PDO::FETCH_OBJ);

        $factura->Receptor = new StdClass();
        $factura->Receptor->Rfc=   $this->test ?  $this->configuracion->testReceptorRfc  : $cliente->RFC;
        $factura->Receptor->Nombre = $this->test ? $this->configuracion->testReceptorNombre : $cliente->RazonSocial;
        $factura->Receptor->DomicilioFiscalReceptor=  $this->test ? $this->configuracion->testReceptorDomicilioFiscalReceptor : $cliente->CP; // codigo postal del cliente
        $factura->Receptor->RegimenFiscalReceptor =    $this->test ? $this->configuracion->testReceptorRegimenFiscalReceptor : $cliente->RegimenFiscal;
        $factura->Receptor->ResidenciaFiscal = null; // aplica cuando es un cliente extranjero
        $factura->Receptor->NumRegIdTrib = null; // aplica cuando es un cliente extranjero
        $factura->Receptor->UsoCFDI= "CP01";  // CP01    can UsoCFdi //covanosa  usoCFDI   

        // Conceptos
        $conceptosArray = array();
        
        $conceptoArray = new StdClass();
        $conceptoArray->ClaveProdServ = "84111506";
        $conceptoArray->Cantidad = "1";
        $conceptoArray->ClaveUnidad = "ACT"; 
        $conceptoArray->Descripcion = "Pago";
        $conceptoArray->ValorUnitario = "0";
        $conceptoArray->Importe = "0";
        $conceptoArray->ObjetoImp = "01"; // 01=No objeto de impuesto, 02=Sí objeto de impuesto, 03=Sí objeto del impuesto y no obligado al desglose, 04=Sí objeto del impuesto y no causa impuesto
        $conceptoArray->Impuestos = null;
        $conceptoArray->InformacionAduanera = null;

        $conceptosArray[] = $conceptoArray;
        
        $factura->Conceptos = new StdClass();
        $factura->Conceptos->Concepto = $conceptosArray;
        
        $Pagos20 = new StdClass();
        $Pagos20->Pagos20 = new StdClass(); 
        $Pagos20->Pagos20->VersionPagos= "2.0";
        
        $Totales = new StdClass();
        $Totales->TotalRetencionesIVA = null;  // no aplica
        $Totales->TotalRetencionesISR = null; // no aplica
        $Totales->TotalRetencionesIEPS = null; // no aplica
        $Totales->TotalTrasladosBaseIVA16 = null;
        $Totales->TotalTrasladosImpuestoIVA16 = null;
        $Totales->TotalTrasladosBaseIVA8 = null;
        $Totales->TotalTrasladosImpuestoIVA8 = null;
        $Totales->TotalTrasladosBaseIVA0 = null;
        $Totales->TotalTrasladosImpuestoIVA0 = null;
        $Totales->TotalTrasladosBaseIVAExento = null;
        $Totales->MontoTotalPagos = null; //total de lo montos
        $Pagos20->Pagos20->Totales= $Totales;

        $factura->Complementos = new StdClass();
        $factura->Complementos = array($Pagos20);
        
        
        $stmComplementos = $this->conexion->preparar("SELECT  [FechaPago],[FormaPago],[UUID],[Factura],[Total],[ImportePago],[Iva],[Tasa]
        FROM [VwCFDiComplementoPago] where Folio = ".$folio ." ORDER By Tasa desc ");
        $stmComplementos->execute();
        $pagos = $stmComplementos->fetchAll(PDO::FETCH_OBJ);
        // echo json_encode($pagos, JSON_PRETTY_PRINT);
        

        $MontoTotalPagos = 00;
        $TotalTrasladosBaseIVA16 = 00;
        $TotalTrasladosImpuestoIVA16 = 00;
        $TotalTrasladosBaseIVA8 = 00;
        $TotalTrasladosImpuestoIVA8 = 00;
        $TotalTrasladosBaseIVA0 = 00;
        $TotalTrasladosImpuestoIVA0 = 00;
        

        $pagosArray = array();
       foreach ($pagos as $key => $value) {
           $pagoArray = new StdClass();
           $pagoArray->FechaPago = date("c",strtotime($value->FechaPago)); 
           $pagoArray->FormaDePagoP = $value->FormaPago == "E" ? "01" : ($value->FormaPago == "C" ? "02" : ($value->FormaPago =="T" ? "03":"NO SE ENCONTRO FORMA DE PAGO")); // E EFECTIVO 01, C CHEQUE NOMINATIVO 02, T TRANSFERENCIA DE FONDOS 03
           $pagoArray->MonedaP = "MXN";    // ---> preguntar de donde lo puedo obtener
           $pagoArray->TipoCambioP = "1";
           $pagoArray->Monto =  number_format((float)($value->ImportePago), 2, '.', '');
           $pagoArray->NumOperacion = null;
           $pagoArray->RfcEmisorCtaOrd = "";
           $pagoArray->NomBancoOrdExt = "";
           $pagoArray->CtaOrdenante = "";
           $pagoArray->RfcEmisorCtaBen = "";
           $pagoArray->CtaBeneficiario = null;
           $pagoArray->TipoCadPago = null;
           $pagoArray->CertPago = null;
           $pagoArray->CadPago = null;
           $pagoArray->SelloPago = null;

           $MontoTotalPagos += $pagoArray->Monto;


           $DoctoRelacionadoArray = new StdClass();
           $DoctoRelacionadoArray->IdDocumento = $value->UUID;
           $DoctoRelacionadoArray->Serie = null;
           $DoctoRelacionadoArray->Folio =  $value->Factura;
           $DoctoRelacionadoArray->MonedaDR = "MXN";
           $DoctoRelacionadoArray->EquivalenciaDR = "1";
           $DoctoRelacionadoArray->NumParcialidad = "1";
           $DoctoRelacionadoArray->ImpSaldoAnt = number_format((float)($value->Total), 2, '.', '');
           $DoctoRelacionadoArray->ImpPagado = number_format((float)($value->ImportePago), 2, '.', '');
           $DoctoRelacionadoArray->ImpSaldoInsoluto =  number_format((float)($value->Total-$value->ImportePago), 2, '.', '');
           $DoctoRelacionadoArray->ObjetoImpDR = "02";


           $SecImpuestoDRArray = array();

           $iva = round($value->Iva/($value->ImportePago - $value->Iva));
           $tasa = ($value->Tasa/100);
           
           if($iva==$tasa){
            $SecImpuestoDRItem = new StdClass();
            $SecImpuestoDRItem->TipoImpuestoDR = "Traslado";
            $SecImpuestoDRItem->BaseDR = number_format((float)($value->ImportePago - $value->Iva), 2, '.', '');
            $SecImpuestoDRItem->ImpuestoDR = "002";
            $SecImpuestoDRItem->TipoFactorDR = "Tasa";
            $SecImpuestoDRItem->TasaOCuotaDR = number_format((float)($value->Tasa/100), 6, '.', '');
            $SecImpuestoDRItem->ImporteDR = $value->Tasa >0 ? $value->Iva : 0.00;
            
            $SecImpuestoDRArray[] = $SecImpuestoDRItem; //agrega elemento al array
        } else {
            $SecImpuestoDRItem1 = new StdClass();
            $SecImpuestoDRItem1->TipoImpuestoDR = "Traslado";
            $SecImpuestoDRItem1->ImpuestoDR = "002";
            $SecImpuestoDRItem1->TipoFactorDR = "Tasa";
            $SecImpuestoDRItem1->TasaOCuotaDR = number_format((float)($value->Tasa/100), 6, '.', '');
            $SecImpuestoDRItem1->ImporteDR = $value->Tasa >0 ? $value->Iva : 0.00;
            $SecImpuestoDRItem1->BaseDR = number_format((float)($SecImpuestoDRItem1->ImporteDR / $SecImpuestoDRItem1->TasaOCuotaDR), 2, '.', '');
            $SecImpuestoDRArray[] = $SecImpuestoDRItem1; //agrega elemento al array

            if($DoctoRelacionadoArray->ImpPagado - $SecImpuestoDRItem1->BaseDR - $SecImpuestoDRItem1->ImporteDR>0){
                $SecImpuestoDRItem2 = new StdClass();
                $SecImpuestoDRItem2->TipoImpuestoDR = "Traslado";
                $SecImpuestoDRItem2->BaseDR = number_format((float)($DoctoRelacionadoArray->ImpPagado - $SecImpuestoDRItem1->BaseDR - $SecImpuestoDRItem1->ImporteDR), 2, '.', '');
                $SecImpuestoDRItem2->ImpuestoDR = "002";
                $SecImpuestoDRItem2->TipoFactorDR = "Tasa";
                $SecImpuestoDRItem2->TasaOCuotaDR = number_format((float)(0), 6, '.', '');
                $SecImpuestoDRItem2->ImporteDR = 0.00;
                $SecImpuestoDRArray[] = $SecImpuestoDRItem2; //agrega elemento al array
            }
        }

           $DoctoRelacionadoArray->ImpuestosDR = new StdClass();
           $DoctoRelacionadoArray->ImpuestosDR->SecImpuestoDR = ($SecImpuestoDRArray);
           $pagoArray->DoctoRelacionado = array($DoctoRelacionadoArray);

           $ImpuestosP = new StdClass();
           $ImpuestosP->TipoImpuestoP = "Traslado";
           $ImpuestosP->BaseP = number_format((float)($value->ImportePago - $value->Iva), 2, '.', '');
           $ImpuestosP->ImpuestoP = "002";
           $ImpuestosP->TipoFactorP = "Tasa";
           $ImpuestosP->TasaOCuotaP = number_format((float)($value->Tasa/100), 6, '.', '');
           $ImpuestosP->ImporteP = $value->Iva;

           switch($value->Tasa){
            case(16):
                $TotalTrasladosBaseIVA16 += $ImpuestosP->BaseP;
                $TotalTrasladosImpuestoIVA16 += $ImpuestosP->ImporteP;
                break;
            case(8):
                $TotalTrasladosBaseIVA8 += $ImpuestosP->BaseP;
                $TotalTrasladosImpuestoIVA8 += $ImpuestosP->ImporteP;
                break;
            case(0):
                $TotalTrasladosBaseIVA0 += $ImpuestosP->BaseP;
                $TotalTrasladosImpuestoIVA0 += $ImpuestosP->ImporteP;
                break;
        }

           $pagoArray->ImpuestosP = new StdClass();
           $pagoArray->ImpuestosP->SecImpuestoP = array($ImpuestosP);
           $pagosArray[] = $pagoArray;
       }

       $Totales->TotalTrasladosBaseIVA16 = number_format((float)($TotalTrasladosBaseIVA16), 2, '.', '');
       $Totales->TotalTrasladosImpuestoIVA16 = number_format((float)($TotalTrasladosImpuestoIVA16), 2, '.', '');
       $Totales->TotalTrasladosBaseIVA8 = number_format((float)($TotalTrasladosBaseIVA8), 2, '.', '');
       $Totales->TotalTrasladosImpuestoIVA8 = number_format((float)($TotalTrasladosImpuestoIVA8), 2, '.', '');
       $Totales->TotalTrasladosBaseIVA0 = number_format((float)($TotalTrasladosBaseIVA0), 2, '.', '');
       $Totales->TotalTrasladosImpuestoIVA0 = number_format((float)($TotalTrasladosImpuestoIVA0), 2, '.', '');
       $Totales->MontoTotalPagos = number_format((float)($MontoTotalPagos), 2, '.', '');; //total de lo montos

       $Pagos20->Pagos20->Pago= new StdClass();
       $Pagos20->Pagos20->Pago= $pagosArray;

        return json_encode($factura, JSON_PRETTY_PRINT);
    }

    function prepararFacturaGlobal($ruta){
  
        // Venta
        $queryVentas = 
        "SELECT CAST(IDRemision AS varchar) AS IDRemision ,Total,Iva,Tasa
        FROM [VentasApp] 
        where Consecutivo is NULL 
        and Credito =0 
        AND Tipo = 'V' 
        AND Cancelado = 0 
        AND (Fecha between '".date("Ymd")." 00:00:00' and '".date("Ymd")." 23:59:59') AND Ruta = ".$ruta.
        " UNION 
        SELECT Remision AS IDRemision,Total,Iva,Tasa
        FROM [Ventas] 
        where Consecutivo is NULL 
        and Credito =0 
        AND Tipo = 'V' 
        AND Positivo =1 
        AND Total >0
        AND (Fecha between '".date("Ymd")." 00:00:00' and '".date("Ymd")." 23:59:59') AND Ruta = ".$ruta;

        $stmVenta = $this->conexion->preparar($queryVentas);
        $stmVenta->execute();
        $ventas = $stmVenta->fetchAll(PDO::FETCH_OBJ);
        
        if(empty($ventas)){
            echo "No se encontraron movimientos para la ruta ".$ruta."";
            return json_encode("err");
        }
        
        // Comprobante
        $factura = new stdClass();
        $factura->Version = "4.0";
        $factura->Serie = ""; 
        $factura->Folio = ""; 
        $factura->Fecha = date("c",time());
        $factura->Sello = "";
        $factura->FormaPago = "01"; // E EFECTIVO 01, C CHEQUE NOMINATIVO 02, T TRANSFERENCIA DE FONDOS 03
        $factura->NoCertificado = "";
        $factura->Certificado = "";
        $factura->CondicionesDePago = null;
        $factura->SubTotal = 0.00; 
        $factura->Descuento = null;
        $factura->Moneda = "MXN";
        $factura->TipoCambio = "1";
        $factura->Total = 0.00;
        $factura->TipoDeComprobante = "I"; //Ingreso = I, Egreso = E
        $factura->Exportacion = "01"; //01=No Aplica, 02=Definitiva, 03=Temporal
        $factura->MetodoPago = "PUE" ;
        $factura->LugarExpedicion = "";//cp de la matriz o sucursal

        // InformacionGlobal
        $factura->InformacionGlobal=new StdClass();
        $factura->InformacionGlobal->Periodicidad = "01"; // 01 Diario, 02	Semanal, 03	Quincenal, 04	Mensual, 05	Bimestral
        $factura->InformacionGlobal->Meses = date("m");
        $factura->InformacionGlobal->Anio = date("Y");      

        // CfdiRelacionados
        $cfdiArray = array();
        $cfdiArray["TipoRelacion"]="";

        $uuidArray = array();
        $uuidArray["UUID"]="";
        $uuidRelacionadosArray[]=$uuidArray;

        $cfdiArray["CfdiRelacionado"] = $uuidRelacionadosArray;

        $CfdiRelacionadosArray[]=$cfdiArray;
        $factura->CfdiRelacionados = $CfdiRelacionadosArray;

        // Emisor
        $queryEmisor = "SELECT TOP (1) Nombre, RFC, Calle, Exterior, Interior, Colonia, Localidad, Referencia, CP, GLN, Telefono, SerieFC, SerieNC, SerieCP,VersionCfdi as version
        FROM CFDiDatosEmisor";
        $stmEmisor = $this->conexion->preparar($queryEmisor);
        $stmEmisor->execute();
        $emisor = $stmEmisor->fetch(PDO::FETCH_OBJ);

        $factura->LugarExpedicion = $emisor->CP;//cp de la matriz o sucursal

        $factura->Emisor=new StdClass();
        $factura->Emisor->Rfc = $this->test ? $this->configuracion->testEmisorRfc : $emisor->RFC;
        $factura->Emisor->Nombre = $this->test ? $this->configuracion->testEmisorNombre : $emisor->Nombre;
        $factura->Emisor->RegimenFiscal = $this->test ? $this->configuracion->testEmisorRegimenFiscal: "601"; //601 = General de Ley Personas Morales	

        // Receptor
        $factura->Receptor = new StdClass();
        $factura->Receptor->Rfc=   "XAXX010101000";
        $factura->Receptor->Nombre = "PUBLICO EN GENERAL";
        $factura->Receptor->DomicilioFiscalReceptor=  $factura->LugarExpedicion; // codigo postal del emisor
        $factura->Receptor->RegimenFiscalReceptor =   "616";
        $factura->Receptor->ResidenciaFiscal = null; // aplica cuando es un cliente extranjero
        $factura->Receptor->NumRegIdTrib = null; // aplica cuando es un cliente extranjero
        $factura->Receptor->UsoCFDI= "S01";  //SO1 Sin efectos fiscales

        // Conceptos
        $conceptosArray = array();
        $impuestosTrasladadosArray = array();
        $tasasImpuestosTrasladadosArray = array();

        $totalIva = 00;
        $totalBase = 00;

        $SubTotal = 00;
        $Total = 00;
        foreach ($ventas as $key => $value) {
            $SubTotal += $value->Total - $value->Iva;
            $Total += $value->Total;

            $conceptoArray = new StdClass();
            $conceptoArray->ClaveProdServ = "01010101";
            $conceptoArray->NoIdentificacion = $value->IDRemision;
            $conceptoArray->Cantidad = "1";
            $conceptoArray->ClaveUnidad = "ACT"; 
            $conceptoArray->Unidad = "Pieza";
            $conceptoArray->Descripcion = "Venta";
            $conceptoArray->ValorUnitario = number_format((float)($value->Total - $value->Iva), 2, '.', '');
            $conceptoArray->Importe = number_format((float)($value->Total - $value->Iva), 2, '.', '');
            $conceptoArray->Descuento = null;
            $conceptoArray->ObjetoImp = "02"; // 01=No objeto de impuesto, 02=Sí objeto de impuesto, 03=Sí objeto del impuesto y no obligado al desglose, 04=Sí objeto del impuesto y no causa impuesto
            
            $SecImpuestoDRArray = array();

            $iva = round($value->Iva/($value->Total - $value->Iva));
            $tasa = $value->Iva >0 ? ($value->Tasa/100) :0.00;
            
            if($iva==$tasa){
                //Impuestos
                $SecImpuestoDRItem = new StdClass();
                $SecImpuestoDRItem->TipoImpuesto = "Traslado";
                $SecImpuestoDRItem->Base = number_format((float)$value->Total, 2, '.', '');
                $SecImpuestoDRItem->Impuesto = "002"; //001=ISR, 002=IVA, 003=IEPS
                $SecImpuestoDRItem->TipoFactor = "Tasa"; 
                $SecImpuestoDRItem->TasaOCuota = number_format((float)( $value->Iva >0 ? $value->Tasa/100  : 0), 6, '.', ''); 
                $SecImpuestoDRItem->Importe = number_format((float)($value->Iva ), 2, '.', '');
            
                $totalIva+=$SecImpuestoDRItem->Importe;
                $SecImpuestoDRArray[] = $SecImpuestoDRItem; //agrega elemento al array

                $impuestoTrasladado = new StdClass();
                $impuestoTrasladado->TipoImpuesto = $SecImpuestoDRItem->TipoImpuesto;
                $impuestoTrasladado->TasaOCuota = $SecImpuestoDRItem->TasaOCuota;
                $impuestoTrasladado->Base = $SecImpuestoDRItem->Base;
                $impuestoTrasladado->Importe = $SecImpuestoDRItem->Importe;
                $impuestoTrasladado->Impuesto = $SecImpuestoDRItem->Impuesto;
                $impuestoTrasladado->TipoFactor = $SecImpuestoDRItem->TipoFactor;
                $impuestosTrasladadosArray[] = $impuestoTrasladado;
                $tasasImpuestosTrasladadosArray[] = $SecImpuestoDRItem->TasaOCuota;
            } else {
                $SecImpuestoDRItem1 = new StdClass();
                $SecImpuestoDRItem1->TipoImpuesto = "Traslado";
                $SecImpuestoDRItem1->Impuesto = "002";
                $SecImpuestoDRItem1->TipoFactor = "Tasa";
                $SecImpuestoDRItem1->TasaOCuota = number_format((float)( $value->Iva >0 ? $value->Tasa/100  : 0), 6, '.', '');
                $SecImpuestoDRItem1->Importe = $value->Tasa >0 ? $value->Iva : 0.00;
                $SecImpuestoDRItem1->Base = number_format((float)($SecImpuestoDRItem1->Importe / $SecImpuestoDRItem1->TasaOCuota), 2, '.', '');

                $totalIva+=$SecImpuestoDRItem1->Importe;
                $SecImpuestoDRArray[] = $SecImpuestoDRItem1; //agrega elemento al array

                $impuestoTrasladado = new StdClass();
                $impuestoTrasladado->TipoImpuesto = $SecImpuestoDRItem1->TipoImpuesto;
                $impuestoTrasladado->TasaOCuota = $SecImpuestoDRItem1->TasaOCuota;
                $impuestoTrasladado->Base = $SecImpuestoDRItem1->Base;
                $impuestoTrasladado->Importe = $SecImpuestoDRItem1->Importe;
                $impuestoTrasladado->Impuesto = $SecImpuestoDRItem1->Impuesto;
                $impuestoTrasladado->TipoFactor = $SecImpuestoDRItem1->TipoFactor;
                $impuestosTrasladadosArray[] = $impuestoTrasladado;
                $tasasImpuestosTrasladadosArray[] = $SecImpuestoDRItem1->TasaOCuota;
                
                if($value->Total - $SecImpuestoDRItem1->Base - $SecImpuestoDRItem1->Importe>0){
                    $SecImpuestoDRItem2 = new StdClass();
                    $SecImpuestoDRItem2->TipoImpuesto = "Traslado";
                    $SecImpuestoDRItem2->Base = number_format((float)($value->Total - $SecImpuestoDRItem1->Base - $SecImpuestoDRItem1->Importe), 2, '.', '');
                    $SecImpuestoDRItem2->Impuesto = "002";
                    $SecImpuestoDRItem2->TipoFactor = "Tasa";
                    $SecImpuestoDRItem2->TasaOCuota = number_format((float)(0), 6, '.', '');
                    $SecImpuestoDRItem2->Importe = "0.00";

                    $totalIva+=$SecImpuestoDRItem2->Importe;
                    $SecImpuestoDRArray[] = $SecImpuestoDRItem2; //agrega elemento al array

                    $impuestoTrasladado = new StdClass();
                    $impuestoTrasladado->TipoImpuesto = $SecImpuestoDRItem2->TipoImpuesto;
                    $impuestoTrasladado->TasaOCuota = $SecImpuestoDRItem2->TasaOCuota;
                    $impuestoTrasladado->Base = $SecImpuestoDRItem2->Base;
                    $impuestoTrasladado->Importe = $SecImpuestoDRItem2->Importe;
                    $impuestoTrasladado->Impuesto = $SecImpuestoDRItem2->Impuesto;
                    $impuestoTrasladado->TipoFactor = $SecImpuestoDRItem2->TipoFactor;
                    $impuestosTrasladadosArray[] = $impuestoTrasladado;
                    $tasasImpuestosTrasladadosArray[] = $SecImpuestoDRItem2->TasaOCuota;
                }
            }
            
            $conceptoArray->Impuestos = new StdClass();
            $conceptoArray->Impuestos->SecImpuesto = ($SecImpuestoDRArray);
    
            //Informacion aduanera
            $InformacionAduanera = new StdClass();
            $InformacionAduanera->NumeroPedimento = null;
            $conceptoArray->InformacionAduanera = array($InformacionAduanera); 

            $conceptosArray[] = $conceptoArray;
        }
        $factura->Conceptos = new StdClass();
        $factura->Conceptos->Concepto = $conceptosArray;

        $factura->SubTotal = number_format((float)($SubTotal), 2, '.', '');                        
        $factura->Total = number_format((float)($Total), 2, '.', '');                                          

        //Impuestos
        $factura->Impuestos = new StdClass();
        $factura->Impuestos->TotalImpuestosRetenidos = number_format((float)(0), 2, '.', '');
        $factura->Impuestos->TotalImpuestosTrasladados = number_format((float)($totalIva ), 2, '.', '');

        //Impuestos por Tasa o cuota
        $SecImpuestoArray = array();
        $tasasImpuestosTrasladadosArray = array_unique($tasasImpuestosTrasladadosArray); //eliminar repetidos
        foreach ($tasasImpuestosTrasladadosArray as $keyBase => $tipoFactor) {
            $tipoImpuesto = new StdClass();
            $tipoImpuesto->TipoImpuesto = "";
            $tipoImpuesto->Base = 00;
            $tipoImpuesto->Impuesto = "";
            $tipoImpuesto->TipoFactor = "";
            $tipoImpuesto->TasaOCuota = "";
            $tipoImpuesto->Importe = 00;
            
            foreach ($impuestosTrasladadosArray as $key => $value) {
                if($value->TasaOCuota == $tipoFactor ){
                    $tipoImpuesto->TipoImpuesto = $value->TipoImpuesto;
                    $tipoImpuesto->Base += $value->Base;
                    $tipoImpuesto->Base = number_format((float)($tipoImpuesto->Base), 2, '.', ''); 
                    $tipoImpuesto->Impuesto = $value->Impuesto;
                    $tipoImpuesto->TipoFactor = $value->TipoFactor;
                    $tipoImpuesto->TasaOCuota = $value->TasaOCuota;
                    $tipoImpuesto->Importe += $value->Importe; 
                    $tipoImpuesto->Importe = number_format((float)($tipoImpuesto->Importe), 2, '.', ''); 
                }
            }
            $SecImpuestoArray[] = $tipoImpuesto; //agrega elemento al array
        }
        $factura->Impuestos->SecImpuesto = $SecImpuestoArray;

        //Complementos
        $factura->Complementos = array(new StdClass());

        // Ruta
        $queryRuta="SELECT Ciudad FROM [Rutas] where Ruta = ".$ruta;
        $stmRuta =  $this->conexion->preparar($queryRuta);
        $stmRuta->execute();
        $Ruta = $stmRuta->fetch(PDO::FETCH_OBJ);

        //Folios
        $queryFolios = "SELECT * FROM [Cia] where Ciudad = ".$Ruta->Ciudad;
        $stmFolios = $this->conexion->preparar($queryFolios);
        $stmFolios->execute();
        $Cia = $stmFolios->fetch(PDO::FETCH_OBJ);

        $factura->Serie = $Cia->Iniciales; 
        $factura->Folio = $Cia->Consecutivo++;

        return json_encode($factura, JSON_PRETTY_PRINT);
    }

    function generarFacturaJSON($response, $idRemision) {
        $jsonResponse = json_decode($response);
        
        if(isset($jsonResponse->uuid)) {
            $jsonResponse->idGeneral = $idRemision;
            $jsonResponse->consecutivo= $this->leerConsecutivoXML__($response);
            $jsonResponse->solicitada=true;
            $jsonResponse->FacturaJSON = $this->leerXML__($response);
        } else {
            if(!isset($jsonResponse->mensaje)) {
                $jsonResponse->mensaje= "error al timbrar, informar a soporte."; 
            }
        } 
        return json_encode($jsonResponse);
    }

    function leerConsecutivoXML__($response){
        $jsonResponse = json_decode($response);
        // leer el xml en base 64 y convertirlo a xml
        $sxml= base64_decode($jsonResponse->xml);
        $xml = simplexml_load_string($sxml);
        $ns = $xml->getNamespaces(true);
        $ns = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('c', $ns['cfdi']);
        $xml->registerXPathNamespace('t', $ns['tfd']);
        
        $consecutivo = "";
    
        foreach ($xml->xpath('//cfdi:Comprobante') as $cfdiComprobante){
            $consecutivo = (string)$cfdiComprobante['Serie'].(string)$cfdiComprobante['Folio'];
        }
   
        return  $consecutivo;
    }

    function leerEmisorXML__($response){
        $jsonResponse = json_decode($response);
        // leer el xml en base 64 y convertirlo a xml
        $sxml= base64_decode($jsonResponse->xml);
        $xml = simplexml_load_string($sxml);
        $ns = $xml->getNamespaces(true);
        $ns = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('c', $ns['cfdi']);
        $xml->registerXPathNamespace('t', $ns['tfd']);
        
        $rfcEmisor = "";
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor') as $Emisor){
            $rfcEmisor =  (string)$Emisor['Rfc'];
        }      
   
        return  $rfcEmisor;
    }

    function leerReceptorXML__($response){
        $jsonResponse = json_decode($response);
        // leer el xml en base 64 y convertirlo a xml
        $sxml= base64_decode($jsonResponse->xml);
        $xml = simplexml_load_string($sxml);
        $ns = $xml->getNamespaces(true);
        $ns = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('c', $ns['cfdi']);
        $xml->registerXPathNamespace('t', $ns['tfd']);
        
        $rfcReceptor = "";
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Receptor') as $Receptor){
            $rfcReceptor= (string)$Receptor['Rfc'];
        }
   
        return  $rfcReceptor;
    }

    function leerXML__($response){
        $jsonResponse = json_decode($response);
        // leer el xml en base 64 y convertirlo a xml
        $sxml= base64_decode($jsonResponse->xml);
        $xml = simplexml_load_string($sxml);
        $ns = $xml->getNamespaces(true);
        $ns = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('c', $ns['cfdi']);
        $xml->registerXPathNamespace('t', $ns['tfd']);
        $retorno = new StdClass();
    
        foreach ($xml->xpath('//cfdi:Comprobante') as $cfdiComprobante){
            $retorno->version= $cfdiComprobante['Version'];
            $retorno->fecha = (string) $cfdiComprobante['Fecha'];
            $retorno->sello =(string) $cfdiComprobante['Sello'];
            $retorno->total= (string)$cfdiComprobante['Total'];
            $retorno->subTotal = (string)$cfdiComprobante['SubTotal'];
            $retorno->certificado =  (string)$cfdiComprobante['Certificado'];
            $retorno->lugarExpedicion = (string)$cfdiComprobante['LugarExpedicion'];
            $retorno->formaDePago= (string)$cfdiComprobante['FormaPago'];
            $retorno->metodoPago = (string)$cfdiComprobante['MetodoPago'];
            $retorno->noCertificado= (string)$cfdiComprobante['NoCertificado'];
            $retorno->condicionesDePago = (string)$cfdiComprobante['CondicionesDePago'];
            $retorno->tipoDeComprobante=  (string)$cfdiComprobante['TipoDeComprobante'];
            $retorno->folio=  (string)$cfdiComprobante['Folio'];
            $retorno->serie=  (string)$cfdiComprobante['Serie'];
        }

        $retorno->emisor = new StdClass();
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor') as $Emisor){
            $retorno->emisor->regimenFiscal = (string)$Emisor['RegimenFiscal'];
            $retorno->emisor->rfc =  (string)$Emisor['Rfc'];
            $retorno->emisor->nombre=  (string)$Emisor['Nombre'];
        }

        $retorno->domicilioFiscal = new StdClass();
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor//cfdi:DomicilioFiscal') as $DomicilioFiscal){
            $retorno->domicilioFiscal->codigoPostal=(string) $DomicilioFiscal['CodigoPostal'];
        }
        
        $retorno->receptorDomicilio=new StdClass();
        $retorno->receptor = new  StdClass();
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Receptor') as $Receptor){
            $retorno->receptor->rfc= (string)$Receptor['Rfc'];
            $retorno->receptor->nombre= (string)$Receptor['Nombre'];
            $retorno->receptor->usoCFDI = (string)$Receptor['UsoCFDI'];
            $retorno->receptor->domicilio = (string)$Receptor['DomicilioFiscalReceptor'];
            $retorno->receptor->regimenFiscal =  (string)$Receptor['RegimenFiscalReceptor'];
        }
       
        $retorno->receptorDomicilio=new StdClass();
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Receptor//cfdi:Domicilio') as $ReceptorDomicilio){
            $retorno->receptorDomicilio->pais = (string) $ReceptorDomicilio['pais'];
            $retorno->receptorDomicilio->calle = (string) $ReceptorDomicilio['calle'];
            $retorno->receptorDomicilio->estado = (string) $ReceptorDomicilio['estado'];
            $retorno->receptorDomicilio->colonia = (string) $ReceptorDomicilio['colonia'];
            $retorno->receptorDomicilio->municipio = (string) $ReceptorDomicilio['municipio'];
            $retorno->receptorDomicilio->noExterior = (string) $ReceptorDomicilio['noExterior'];
            $retorno->receptorDomicilio->noInterior = (string) $ReceptorDomicilio['noInterior'];
            $retorno->receptorDomicilio->codigoPostal =  (string)$ReceptorDomicilio['codigoPostal'];
        }

        $retorno ->conceptos= array();
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto') as $Concepto){
            $concepto = new StdClass();
            $concepto->ClaveProdServ=  (string)$Concepto['ClaveProdServ'];
            $concepto->importe = (string)$Concepto['Importe'];
            $concepto->cantidad =(string) $Concepto['Cantidad'];
            $concepto->descripcion =(string) $Concepto['Descripcion'];
            $concepto->valorUnitario =(string) $Concepto['ValorUnitario'];
            $concepto->claveUnidad = (string)$Concepto['ClaveUnidad'];
            $retorno->conceptos[] = $concepto;
        }

        $retorno->impuestosTrasladados =array();//
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado') as $Traslado){
        $impuestoTrasladado	= new StdClass();
            $impuestoTrasladado->tasa = (string)$Traslado['TasaOCuota'];
            $impuestoTrasladado->importe = (string)$Traslado['Importe'];
            $impuestoTrasladado->impuesto = (string)$Traslado['Impuesto'];
            $impuestoTrasladado->tipoFactor = (string)$Traslado['TipoFactor'];
            $retorno->impuestosTrasladados[(int)$impuestoTrasladado->tasa]=$impuestoTrasladado;
        }
    
        $retorno->timbreFiscalDigital = new StdClass();
        foreach ($xml->xpath('//t:TimbreFiscalDigital') as $tfd) {
            $retorno->timbreFiscalDigital->selloCFD = (string)$tfd['SelloCFD'];
            $retorno->timbreFiscalDigital->FechaTimbrado = (string)$tfd['FechaTimbrado'];
            $retorno->timbreFiscalDigital->UUID = (string)$tfd['UUID'];
            $retorno->timbreFiscalDigital->noCertificadoSAT = (string)$tfd['NoCertificadoSAT'];
            $retorno->timbreFiscalDigital->version =(string) $tfd['Version'];
            $retorno->timbreFiscalDigital->selloSAT = (string)$tfd['SelloSAT'];
        }
        return  json_encode($retorno);
    }

    function leerXMLUrl__($idRemision){
        // Venta
        $queryRemision = "select c.RFC AS rfcReceptor,va.Consecutivo AS serie,d.RFC as emisor 
        from VentasApp va, Sucursales s, Clientes c,CFDiDatosEmisor d   
        where 
        s.Sucursal = va.Sucursal 
        and va.Ciudad  = d.Ciudad 
        and c.Cliente = s.Cliente 
        and Consecutivo = Factura  
        and va.IDRemision ='$idRemision';";
    
        $stmRemision = $this->conexion->preparar($queryRemision);
        $stmRemision->execute();
        $venta = $stmRemision->fetch(PDO::FETCH_OBJ);
    
        $nombreArchivo =  $venta->emisor."_".$venta->serie."_".$venta->rfcReceptor.".xml";
        
        $xml = simplexml_load_file($this->documentPath.'/'.$nombreArchivo,null,null,"cfdi");
        $ns = $xml->getNamespaces(true);
        $ns = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('c', $ns['cfdi']);
        $xml->registerXPathNamespace('t', $ns['tfd']);
        $retorno = new StdClass();
        
        foreach ($xml->xpath('//cfdi:Comprobante') as $cfdiComprobante){
            $retorno->version= (string)$cfdiComprobante['Version'];
            $retorno->fecha = (string) $cfdiComprobante['Fecha'];
            $retorno->sello =(string) $cfdiComprobante['Sello'];
            $retorno->total= (string)$cfdiComprobante['Total'];
            $retorno->subTotal = (string)$cfdiComprobante['SubTotal'];
            $retorno->certificado =  (string)$cfdiComprobante['Certificado'];
            $retorno->lugarExpedicion = (string)$cfdiComprobante['LugarExpedicion'];
            $retorno->formaDePago= (string)$cfdiComprobante['FormaPago'];
            $retorno->metodoPago = (string)$cfdiComprobante['MetodoPago'];
            $retorno->noCertificado= (string)$cfdiComprobante['NoCertificado'];
            $retorno->condicionesDePago = (string)$cfdiComprobante['CondicionesDePago'];
            $retorno->tipoDeComprobante=  (string)$cfdiComprobante['TipoDeComprobante'];
            $retorno->folio=  (string)$cfdiComprobante['Folio'];
            $retorno->serie=  (string)$cfdiComprobante['Serie'];
        }
        
        $retorno->emisor = new StdClass();
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor') as $Emisor){
            //var_dump($Emisor);
            $retorno->emisor->regimenFiscal = (string)$Emisor['RegimenFiscal'];
            $retorno->emisor->rfc =  (string)$Emisor['Rfc'];
            $retorno->emisor->nombre=  (string)$Emisor['Nombre'];
        }
        
        $retorno->domicilioFiscal = new StdClass();
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor//cfdi:DomicilioFiscal') as $DomicilioFiscal){
            $retorno->domicilioFiscal->codigoPostal=(string) $DomicilioFiscal['CodigoPostal'];
        }
        
        $retorno->receptorDomicilio=new StdClass();
        $retorno->receptor = new  StdClass();
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Receptor') as $Receptor){
            $retorno->receptor->rfc= (string)$Receptor['Rfc'];
            $retorno->receptor->nombre= (string)$Receptor['Nombre'];
            $retorno->receptor->usoCFDI = (string)$Receptor['UsoCFDI'];
            $retorno->receptor->domicilio = (string)$Receptor['DomicilioFiscalReceptor'];
            $retorno->receptor->regimenFiscal =  (string)$Receptor['RegimenFiscalReceptor'];
        }
        
        $retorno->receptorDomicilio=new StdClass();
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Receptor//cfdi:Domicilio') as $ReceptorDomicilio){
            $retorno->receptorDomicilio->pais = (string) $ReceptorDomicilio['pais'];
            $retorno->receptorDomicilio->calle = (string) $ReceptorDomicilio['calle'];
            $retorno->receptorDomicilio->estado = (string) $ReceptorDomicilio['estado'];
            $retorno->receptorDomicilio->colonia = (string) $ReceptorDomicilio['colonia'];
            $retorno->receptorDomicilio->municipio = (string) $ReceptorDomicilio['municipio'];
            $retorno->receptorDomicilio->noExterior = (string) $ReceptorDomicilio['noExterior'];
            $retorno->receptorDomicilio->noInterior = (string) $ReceptorDomicilio['noInterior'];
            $retorno->receptorDomicilio->codigoPostal =  (string)$ReceptorDomicilio['codigoPostal'];
        }
        
        $retorno ->conceptos= array();
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto') as $Concepto){
            $concepto = new StdClass();
            $concepto->ClaveProdServ=  (string)$Concepto['ClaveProdServ'];
            $concepto->importe = (string)$Concepto['Importe'];
            $concepto->cantidad =(string) $Concepto['Cantidad'];
            $concepto->descripcion =(string) $Concepto['Descripcion'];
            $concepto->valorUnitario =(string) $Concepto['ValorUnitario'];
            $concepto->claveUnidad = (string)$Concepto['ClaveUnidad'];
            $retorno->conceptos[] = $concepto;
        }
        
        $retorno->impuestosTrasladados =array();//
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado') as $Traslado){
            $impuestoTrasladado	= new StdClass();
            $impuestoTrasladado->tasa = (string)$Traslado['TasaOCuota'];
            $impuestoTrasladado->importe = (string)$Traslado['Importe'];
            $impuestoTrasladado->impuesto = (string)$Traslado['Impuesto'];
            $impuestoTrasladado->tipoFactor = (string)$Traslado['TipoFactor'];
            $retorno->impuestosTrasladados[(int)$impuestoTrasladado->tasa]=$impuestoTrasladado;
        }
        
        $retorno->timbreFiscalDigital = new StdClass();
        foreach ($xml->xpath('//t:TimbreFiscalDigital') as $tfd) {
            $retorno->timbreFiscalDigital->selloCFD = (string)$tfd['SelloCFD'];
            $retorno->timbreFiscalDigital->FechaTimbrado = (string)$tfd['FechaTimbrado'];
            $retorno->timbreFiscalDigital->UUID = (string)$tfd['UUID'];
            $retorno->timbreFiscalDigital->noCertificadoSAT = (string)$tfd['NoCertificadoSAT'];
            $retorno->timbreFiscalDigital->version =(string) $tfd['Version'];
            $retorno->timbreFiscalDigital->selloSAT = (string)$tfd['SelloSAT'];
        }

        $jsonResponse = new StdClass();
        $jsonResponse->idGeneral = $idRemision;
        $jsonResponse->FacturaJSON = json_encode($retorno);
        $jsonResponse->consecutivo= $venta->serie;        
        return json_encode($jsonResponse);
    }

    function actualizarVenta($response, $idRemision) {
        $resultado = json_decode($response);
        if(isset($resultado->uuid)) {
            $uuid= $resultado->uuid; 
            $consecutivo = $this->leerConsecutivoXML__($response);   
            
            //si es cliente de contado actualizar la tabla venta
            $queryActualizar ="update  VentasApp set factura  = '".$consecutivo."',consecutivo='".$consecutivo."',UUID = '".$uuid."' where IDRemision = '".$idRemision."' ";
            $stmActualizar = $this->conexion->preparar($queryActualizar);
            $stmActualizar->execute();

            // Cliente
            $queryCliente = "SELECT a.Total, a.ciudad, a.Plazo, a.tasa, a.Iva, CONVERT(DATE,a.Fecha) as fecha , c.*   
            FROM [VentasApp] a, [Sucursales] b, [Clientes] c 
            WHERE a.Sucursal = b.Sucursal 
            AND b.Cliente = c.Cliente 
            AND IDRemision = ".$idRemision;

            $stmCliente = $this->conexion->preparar($queryCliente);
            $stmCliente->execute();
            $cliente = $stmCliente->fetch(PDO::FETCH_OBJ);

            if($cliente->Credito){
                //si es cliente de credito insertar registro en la tabla factura
                $queryFactura =
                "INSERT INTO Facturas
                (Factura, Ciudad, Fecha, Cliente, Tipo, Total, Saldo, Plazo, Poliza, Iva, Ieps, UUID, Tasa)
                VALUES('".$consecutivo."', ".$cliente->ciudad.", '".$cliente->fecha."', ".$cliente->Cliente.", 'V', ".$cliente->Total.", ".$cliente->Total.", ".$cliente->Plazo.", '', ".$cliente->Iva.", 0, '".$uuid."', ".($cliente->Iva >0 ? $cliente->tasa : 0).");";
                
                $stmActualizarFactura = $this->conexion->preparar($queryFactura);
                $stmActualizarFactura->execute();
            }
        } 
    }

    function generarArchivos($jsonFactura) {
        //echo "\n".$jsonFactura;
        $resultado = json_decode($jsonFactura);
        
        if(isset($resultado->uuid)) {
            //$codigo= $resultado->codigo;
            //$mensaje= $resultado->mensaje; 
            $xml= $resultado->xml; 
            $pdf= $resultado->pdf; 
            $uuid= $resultado->uuid; 

            $consecutivo = $this->leerConsecutivoXML__($jsonFactura);  
            $emisorRfc = $this->leerEmisorXML__($jsonFactura);  
            $receptorRfc = $this->leerReceptorXML__($jsonFactura); 
            $nombreArchivo =  $emisorRfc."_".$consecutivo."_".$receptorRfc;

            $this->validatePath($this->documentPath);
            $this->generarArchivo($pdf, $nombreArchivo, $this->documentPath, "pdf");
            $this->generarArchivo($xml, $nombreArchivo, $this->documentPath, "xml");
        } else {
            //echo "no se timbro correctamente";
        }
    }
    
    function generarArchivo($xml64, $nombreArchivo, $path, $type) {
        $bin = base64_decode($xml64, true);
        # Write the PDF contents to a local file
        file_put_contents($path."/".$nombreArchivo.'.'.$type, $bin);
    }
    
    function validatePath($path) {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
    }

    function removerConsecutivoTemporal($idRemision) {
        $queryActualizarConsecutivo ="update  VentasApp set consecutivo= NULL where IDRemision = '".$idRemision."' ";
        $stmActualizarConsecutivo = $this->conexion->preparar($queryActualizarConsecutivo);
        $stmActualizarConsecutivo->execute();
    }

    function actualizarUUIDFallido($idRemision, $mensaje) {
        $string = (strlen($mensaje) > 50) ? substr($mensaje,0,50) : $mensaje;

        $queryActualizarUUID ="update  VentasApp set UUID = '".$string."' where IDRemision = '".$idRemision."' ";
        $stmActualizarUUID = $this->conexion->preparar($queryActualizarUUID);
        $stmActualizarUUID->execute();
    }

    function obtenerCorreos($pCiudad, $pCliente, $pRuta, $pSucursal) {

        $correos = "fzavala@bmsinergia.com";

        if(!$this->test){
            //Folios
            $queryCia = "SELECT Correo FROM [Cia] where Ciudad = ".$pCiudad;
            $stmCia = $this->conexion->preparar($queryCia);
            $stmCia->execute();
            $cia = $stmCia->fetch(PDO::FETCH_OBJ);

            $correos = $cia->Correo; 

            $queryCliente = "SELECT Correos, CorreoSucursal FROM [Clientes] where Cliente = ".$pCliente;
            $stmCliente = $this->conexion->preparar($queryCliente);
            $stmCliente->execute();
            $cliente = $stmCliente->fetch(PDO::FETCH_OBJ);

            $correos .= $cliente->Correos != ''? (";".$cliente->Correos) : ""; 

            $queryRuta = "SELECT Correo FROM [Rutas] where Ruta = ".$pRuta;
            $stmRuta = $this->conexion->preparar($queryRuta);
            $stmRuta->execute();
            $ruta = $stmRuta->fetch(PDO::FETCH_OBJ);

            $correos .= $ruta->Correo != ''? (";".$ruta->Correo) : ""; 

            $querySucursal = "SELECT EmailSucursal FROM [Sucursales] where Sucursal = ".$pSucursal;
            $stmSucursal = $this->conexion->preparar($querySucursal);
            $stmSucursal->execute();
            $sucursal = $stmSucursal->fetch(PDO::FETCH_OBJ);

            //$correos .= $cliente->CorreoSucursal ==true ? (";".$sucursal->EmailSucursal) : "";

            if($cliente->CorreoSucursal ==true) {
                if($sucursal->EmailSucursal === null || trim($sucursal->EmailSucursal) === '') {
                } else {
                    $correos .=";".$sucursal->EmailSucursal;
                }
            }

            $correos .= $correos != ''? (";asanchez@bmsinergia.com;fzavala@bmsinergia.com") : "asanchez@bmsinergia.com;fzavala@bmsinergia.com"; 
        }
        return $correos;
    }
}
?>