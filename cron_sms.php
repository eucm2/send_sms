<?php
parse_str($argv[1]);
parse_str($argv[2]);
parse_str($argv[3]);
parse_str($argv[4]);
parse_str($argv[5]);

$mensajeError="";

if(!$servicio){
    $mensajeError=$mensajeError."No exist servicio value</br>";
}
if(!$lista){
    $mensajeError=$mensajeError."No exist lista value</br>";
}
if(!$mensaje){
    $mensajeError=$mensajeError."No exist mensaje value</br>";
}
if($rango_ini==null){
    $mensajeError=$mensajeError."No exist rango_ini value</br>";
}
if(!$rango_fin){
    $mensajeError=$mensajeError."No exist rango_fin value</br>";
}
if($mensajeError!==""){
    die($mensajeError);
}
require 'config.php';


$db = new mysqli('localhost', $sugar_config[dbconfig][db_user_name], $sugar_config[dbconfig][db_password], $sugar_config[dbconfig][db_name]);
if ($db->connect_errno > 0) {
    die('Unable to connect to database [' . $db->connect_error . ']');
}

require 'twilio/Services/Twilio.php';

//LEEMOS LOS DATOS DE LA CONFIGURACION COMO modo_prueba,segundos_retrazo
$queryConfiguracion = "SELECT modo_prueba,segundos_retrazo from sm_co_sm_configuracion where deleted='0';";
$resultConfiguracion = $db->query($queryConfiguracion);
$rowConfiguracion = $resultConfiguracion->fetch_array();
echo "segundos_retrazo=" . $rowConfiguracion[segundos_retrazo] . "</br>";


$queryApi = "select id,name,deleted,accountsid,authtoken,telefono_from from sm_ap_sm_api where id='$servicio' and deleted='0';";
$resultApi = $db->query($queryApi);
$rowApi = $resultApi->fetch_array();
echo "name servicio " . $rowApi[name] . "</br>";

//CREAMOS EL OBJETO DE TWILIO
$client = new Services_Twilio($rowApi[accountsid], $rowApi[authtoken]);

//QUERY QUE OBTIENE LA LISTA DE PERSONAS A ENVIAR MENSAJES DEPENDIENDO DE LA LISTA PRESELECCIONADA
$queryPersonaLista = "
              SELECT sm_li_sm_lista.`name` as nombre_lista,sm_li_sm_lista.id as id_lista,sm_pe_sm_persona.`name` as nombre_persona,sm_pe_sm_persona.id as id_persona,sm_pe_sm_persona.telefono
              FROM
              sm_li_sm_lista
              LEFT JOIN sm_pe_sm_persona ON sm_pe_sm_persona.sm_li_sm_lista_id_c = sm_li_sm_lista.id
              where sm_li_sm_lista.id='$lista' and sm_pe_sm_persona.deleted='0'
              limit $rango_ini,$rango_fin
              ;
              ";
//echo $queryPersonaLista;
$resultPersonaLista = $db->query($queryPersonaLista);
echo "mysqli_num_rows=" . mysqli_num_rows($resultPersonaLista) . "</br>";
if (mysqli_num_rows($resultPersonaLista) > 0) {
    while ($rowPersonaLista = $resultPersonaLista->fetch_assoc()) {

        sleep($rowConfiguracion[segundos_retrazo]);
        //PREVENIMOS QUE EL MENSAJE SEA ENVIADO
        try {
            //SI EL MODO PRUEBA ESTA DESACTIVADO =0 REALIZA LA CONEXION Y EL ENVIO DEL MENSAJE
            if ($rowConfiguracion[modo_prueba] == "0") {
                $message = $client->account->messages->create(array(
                    "From" => $rowApi[telefono_from],
                    "To" => $rowPersonaLista[telefono],
                    "Body" => $mensaje
                ));
            }
            //QUERY QUE INSERTA UN REGISTRO EN EL REPORTE QUE EL MENSAJE SE GUARDO
            $queryGuardaReporte = "
                        insert into sm_re_sm_reporte
                        (id                                       ,name                              ,sm_pe_sm_persona_id_c             ,sm_li_sm_lista_id_c         ,date_entered                 ,mensaje             ,enviado,sm_ap_sm_api_id_c) values
                        ('" . date("YmdHis") . rand(100, 999) . "','$rowPersonaLista[nombre_persona]','$rowPersonaLista[id_persona]'    ,'$rowPersonaLista[id_lista]','" . date("Y-m-d H:i:s") . "','$mensaje','1'    ,'$servicio');
                        ";
            $resultGuardaReporte = $db->query($queryGuardaReporte);
            $enviadosBien++;
        }
        //SI EL MENSAJE NO SE ENVIA CACHAMOS EL ERROR Y LO AGREGAMOS AL REPORTE CON UN enviado=0
        catch (Services_Twilio_RestException $e) {
            $queryGuardaReporte = "
                        insert into sm_re_sm_reporte
                        (id                                       ,name                              ,sm_pe_sm_persona_id_c             ,sm_li_sm_lista_id_c         ,date_entered                 ,mensaje              ,enviado,sm_ap_sm_api_id_c) values
                        ('" . date("YmdHis") . rand(100, 999) . "','$rowPersonaLista[nombre_persona]','$rowPersonaLista[id_persona]'    ,'$rowPersonaLista[id_lista]','" . date("Y-m-d H:i:s") . "','Error=$e'           ,'0'    ,'$servicio');
                        ";
            $resultGuardaReporte = $db->query($queryGuardaReporte);
            $enviadosMal++;
        }
        $i++;
    }
}


