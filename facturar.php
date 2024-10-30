
<!-- Creates PDF file-->
<?php
	$error = "";		//error holder
    $jsonResult;
	if(isset($_POST['facturarRemision'])){
        $post = $_POST;	
        if(empty($post['idremision'])) {
			$error .= "Para continuar, ingresa una remision <br/>";
        } else if(is_null($post['idremision'])) {
			$error .= "Para continuar, ingresa una remision <br/>";
        } else {
            $idremision = $post['idremision'];
            $jsonResult = ""; 

            //datos a enviar
            $data = array("idGeneral" => $idremision);
            //url contra la que atacamos
            $ch = curl_init("http://189.223.248.234:3008/facturacion40/index.php");
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
                echo "SIN RESPUESTA";
            } else {
                $jsonResult = json_decode($response);
            }
		} 
    } else {

    }
?>


<!DOCTYPE html>
<html>
<head>
    <title>CAN | FACTURAR</title>
    <link rel="stylesheet" type = "text/css" href="css/estilo.css">
</head>
<body>
    <h1> [CAN] Facturacion 4.0 </h1>
  
<?php if(!empty($error)) { ?>
    <p style=" border:#C10000 1px solid; background-color:#FFA8A8; color:#B00000; padding:10px;  margin:0 auto 10px;"><?php echo $error; ?></p>
  <?php } 
  ?>


<form name="timbrado" method="post">
  <div class="input-field">
  <label>IDRemision:</label> 
  <input type="number" name="idremision" size="40" >
</div>
  <p>
    <input type="submit" name="facturarRemision" value="Facturar"  class="my-button">
  </p>
</form>

<?php if(!empty($jsonResult)) { ?>


<table class="demoTable" style="height: 54px;">

<thead>
<tr>
</tr>
</thead>
  
<tbody>

<tr>
<th>IDRemision</th>
<td><?php echo $idremision; ?></td>
</tr>


<?php if(isset($jsonResult->codigo)) { ?>
<tr>
<th>Codigo</th>
<td><?php echo $jsonResult->codigo; ?></td>
</tr>
<?php  }  ?>

<?php if(isset($jsonResult->mensaje)) { ?>
<tr>
<th>Mensaje</th>
<td><?php $jsonResult->mensaje; ?></td>
</tr>
<?php  }  ?>

<?php if(isset($jsonResult->uuid)) { ?>
<tr>
<th>UUID</th>
<td><?php echo $jsonResult->uuid; ?></td>
</tr>
<?php  }  ?>


<?php if(isset($jsonResult->consecutivo)) { ?>
<tr>
<th>FACTURA</th>
<td><?php echo $jsonResult->consecutivo; ?></td>
</tr>
<tr>
<th>ESTADO</th>
<td><?php echo "La remision ya se encuentra timbrada."; ?></td>
</tr>
<?php  }  ?>


</tbody>
</table>
<p>&nbsp;</p>


<?php } ?>

</body>
</html>