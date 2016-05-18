<?php
require 'config.php';


$db = new mysqli('localhost', $sugar_config[dbconfig][db_user_name], $sugar_config[dbconfig][db_password], $sugar_config[dbconfig][db_name]);
if ($db->connect_errno > 0) {
    die('Unable to connect to database [' . $db->connect_error . ']');
}
//QUERY QUE INSERTA UN REGISTRO EN EL REPORTE QUE EL MENSAJE SE GUARDO
$queryGuardaReporte = "
                        insert into sm_re_sm_reporte
                        (id                                       ,name                              ,sm_pe_sm_persona_id_c             ,sm_li_sm_lista_id_c         ,date_entered                 ,mensaje             ,enviado,sm_ap_sm_api_id_c) values
                        ('" . date("YmdHis") . rand(100, 999) . "','$rowPersonaLista[nombre_persona]','$rowPersonaLista[id_persona]'    ,'$rowPersonaLista[id_lista]','" . date("Y-m-d H:i:s") . "','$_REQUEST[mensaje]','1'    ,'$_REQUEST[servicio]]');
                        ";
$resultGuardaReporte = $db->query($queryGuardaReporte);

