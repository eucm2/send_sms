<?php
//SACAMOS EL MENSAJE
parse_str($argv[1]);
//SACAMOS EL RANGO INI
parse_str($argv[2]);
//SACAMOS EL RANGO FIN
parse_str($argv[3]);

//DECLARAMOS LA VARIABLE QUE VA RECIBIR EL ERROR EN CASO DE QUE SE HAYAN CREADO LAS VARIABLES
$errorFaltanVariables="";
if(!$mensaje){
    $errorFaltanVariables=$errorFaltanVariables."No exist mensaje value</br>";
}
if($rango_ini==null){
    $errorFaltanVariables=$errorFaltanVariables."No exist rango_ini value</br>";
}
if(!$rango_fin){
    $errorFaltanVariables=$errorFaltanVariables."No exist rango_fin value</br>";
}
if($errorFaltanVariables!==""){
    die($errorFaltanVariables);
}

//LLAMAMOS LA CONEXION A LA BD DE SUGAR
require 'config.php';

$db = new mysqli('localhost', $sugar_config[dbconfig][db_user_name], $sugar_config[dbconfig][db_password], $sugar_config[dbconfig][db_name]);
if ($db->connect_errno > 0) {
    die('Unable to connect to database [' . $db->connect_error . ']');
}
//echo "rango_ini=".$rango_ini."-----";
require 'twilio/Services/Twilio.php';

//LEEMOS LOS DATOS DE LA CONFIGURACION COMO modo_prueba,segundos_retrazo
$queryConfiguracion = "SELECT modo_prueba,segundos_retrazo from sm_co_sm_configuracion where deleted='0';";
$resultConfiguracion = $db->query($queryConfiguracion);
$rowConfiguracion = $resultConfiguracion->fetch_array();
//echo "segundos_retrazo=".$rowConfiguracion[segundos_retrazo]."------";
//SACAMOS LA LISTA Y EL API QUE SE VA USAR HOY
$queryListaServicioDeHoy = "SELECT fecha_envio,sm_li_sm_lista_id_c,sm_ap_sm_api_id_c FROM sm_pl_sm_planificador where fecha_envio='".date('Y-m-d')."' and deleted='0';";
$resultListaServicioDeHoy = $db->query($queryListaServicioDeHoy);
$rowListaServicioDeHoy = $resultListaServicioDeHoy->fetch_array();//  $db->fetchRow($resultListaServicioDeHoy);
echo "sm_li_sm_lista_id_c=".$rowListaServicioDeHoy[sm_li_sm_lista_id_c]."------";
//SACAMOS EL KEY Y PASSWORD DE TWILIO CON EL ID DEL API QUE OBTUBIMOS DEL QUERY $queryListaServicioDeHoy
$queryApi = "select id,name,deleted,accountsid,authtoken,telefono_from from sm_ap_sm_api where id='$rowListaServicioDeHoy[sm_ap_sm_api_id_c]' and deleted='0';";
$resultApi = $db->query($queryApi);
$rowApi = $resultApi->fetch_array();
//CREAMOS EL OBJETO DE TWILIO
$client = new Services_Twilio($rowApi[accountsid], $rowApi[authtoken]);
//QUERY QUE OBTIENE LA LISTA DE PERSONAS A ENVIAR MENSAJES DEPENDIENDO DE LA LISTA PRESELECCIONADA DE LA LISTA DEL QUERY $queryListaServicioDeHoy
$queryPersonaLista = "
              SELECT sm_li_sm_lista.`name` as nombre_lista,sm_li_sm_lista.id as id_lista,sm_pe_sm_persona.`name` as nombre_persona,sm_pe_sm_persona.id as id_persona,sm_pe_sm_persona.telefono
              FROM
              sm_li_sm_lista
              LEFT JOIN sm_pe_sm_persona ON sm_pe_sm_persona.sm_li_sm_lista_id_c = sm_li_sm_lista.id
              where sm_li_sm_lista.id='$rowListaServicioDeHoy[sm_li_sm_lista_id_c]' and sm_pe_sm_persona.deleted='0'
              limit $rango_ini,$rango_fin
              ;
              ";
$resultPersonaLista = $db->query($queryPersonaLista);
//SI EL NUMERO DE PERSONAS EN ESA LISTA ES MAYOR A 0 PROCEDEMOS A CREAR EL CICLO QUE VA MANDAR LOS MENSAJES
echo "resultPersonaLista=".mysqli_num_rows($resultPersonaLista);
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
                        (id                                       ,name                              ,sm_pe_sm_persona_id_c             ,sm_li_sm_lista_id_c                          ,date_entered                 ,mensaje             ,enviado,sm_ap_sm_api_id_c) values
                        ('" . date("YmdHis") . rand(100, 999) . "','$rowPersonaLista[nombre_persona]','$rowPersonaLista[id_persona]'    ,'$rowListaServicioDeHoy[sm_li_sm_lista_id_c]','" . date("Y-m-d H:i:s") . "','$mensaje','1'    ,'$rowListaServicioDeHoy[sm_ap_sm_api_id_c]');
                        ";
            $resultGuardaReporte = $db->query($queryGuardaReporte);
            $enviadosBien++;
        }
        //SI EL MENSAJE NO SE ENVIA CACHAMOS EL ERROR Y LO AGREGAMOS AL REPORTE CON UN enviado=0
        catch (Services_Twilio_RestException $e) {
            $queryGuardaReporte = "
                        insert into sm_re_sm_reporte
                        (id                                       ,name                              ,sm_pe_sm_persona_id_c             ,sm_li_sm_lista_id_c                          ,date_entered                 ,mensaje              ,enviado,sm_ap_sm_api_id_c) values
                        ('" . date("YmdHis") . rand(100, 999) . "','$rowPersonaLista[nombre_persona]','$rowPersonaLista[id_persona]'    ,'$rowListaServicioDeHoy[sm_li_sm_lista_id_c]','" . date("Y-m-d H:i:s") . "','Error=$e'           ,'0'    ,'$rowListaServicioDeHoy[sm_ap_sm_api_id_c]');
                        ";
            $resultGuardaReporte = $db->query($queryGuardaReporte);
            $enviadosMal++;
        }
        $i++;
    }
}


