<?php
require_once('include/MVC/View/views/view.list.php');

class sm_en_sm_envioViewList extends ViewList {

    function ActivitiesViewList() {
        parent::ViewList();
    }

    function display() {
        ?>
        <style>
            .alert {
                padding: 6px;
                margin-bottom: 5px;
            }
        </style>
        <script>
            jQuery(document).ready(function () {
                jQuery("#form_enviar").submit(function (event) {
                    jQuery(".progress").show("slow");
                });
            });
        </script>
        <?php
        $enviadosBien = 0;
        $enviadosMal = 0;
        $db = DBManagerFactory::getInstance();
        $industry = $_POST['Industry'];
        //LEEMOS LOS DATOS DE LA CONFIGURACION COMO modo_prueba,segundos_retrazo
        $queryConfiguracion = "SELECT modo_prueba,segundos_retrazo from sm_co_sm_configuracion where deleted='0';";
        $resultConfiguracion = $db->query($queryConfiguracion);
        $rowConfiguracion = $db->fetchRow($resultConfiguracion);
        //SI SE DIO CLICK EN EVIR
        if ($_REQUEST[enviar]) {
            require 'twilio/Services/Twilio.php';
            //OBTENEMOS EL EL API SELECCIONADO
            $queryApi = "select id,name,deleted,accountsid,authtoken,telefono_from from sm_ap_sm_api where id='$_REQUEST[servicio]' and deleted='0';";
            $resultApi = $db->query($queryApi);
            $rowApi = $db->fetchRow($resultApi);
            //CREAMOS EL OBJETO DE TWILIO
            $client = new Services_Twilio($rowApi[accountsid], $rowApi[authtoken]);
            //QUERY QUE OBTIENE LA LISTA DE PERSONAS A ENVIAR MENSAJES DEPENDIENDO DE LA LISTA PRESELECCIONADA
            $queryPersonaLista = "
              SELECT sm_li_sm_lista.`name` as nombre_lista,sm_li_sm_lista.id as id_lista,sm_pe_sm_persona.`name` as nombre_persona,sm_pe_sm_persona.id as id_persona,sm_pe_sm_persona.telefono
              FROM
              sm_li_sm_lista
              LEFT JOIN sm_pe_sm_persona ON sm_pe_sm_persona.sm_li_sm_lista_id_c = sm_li_sm_lista.id
              where sm_li_sm_lista.id='$_REQUEST[lista]' and sm_pe_sm_persona.deleted='0';
              ";
            //echo $queryPersonaLista;
            $resultPersonaLista = $db->query($queryPersonaLista);
            $numeroPersonas = $resultPersonaLista->num_rows;
            if ($numeroPersonas > 0) {
                $i = 1;
                while ($rowPersonaLista = $db->fetchRow($resultPersonaLista)) {
                    sleep($rowConfiguracion[segundos_retrazo]);
                    //PREVENIMOS QUE EL MENSAJE SEA ENVIADO
                    try {
                        //SI EL MODO PRUEBA ESTA DESACTIVADO =0 REALIZA LA CONEXION Y EL ENVIO DEL MENSAJE
                        if ($rowConfiguracion[modo_prueba] == "0") {
                            $message = $client->account->messages->create(array(
                                "From" => $rowApi[telefono_from],
                                "To" => $rowPersonaLista[telefono],
                                "Body" => "$_REQUEST[mensaje]"
                            ));
                        }
                        //QUERY QUE INSERTA UN REGISTRO EN EL REPORTE QUE EL MENSAJE SE GUARDO
                        $queryGuardaReporte = "
                        insert into sm_re_sm_reporte
                        (id                                       ,name                              ,sm_pe_sm_persona_id_c             ,sm_li_sm_lista_id_c         ,date_entered                 ,mensaje             ,enviado,sm_ap_sm_api_id_c) values
                        ('" . date("YmdHis") . rand(100, 999) . "','$rowPersonaLista[nombre_persona]','$rowPersonaLista[id_persona]'    ,'$rowPersonaLista[id_lista]','" . date("Y-m-d H:i:s") . "','$_REQUEST[mensaje]','1'    ,'$_REQUEST[servicio]]');
                        ";
                        $resultGuardaReporte = $db->query($queryGuardaReporte);
                        $enviadosBien++;
                        //echo $rowPersonaLista[telefono];
                    }
                    //SI EL MENSAJE NO SE ENVIA CACHAMOS EL ERROR Y LO AGREGAMOS AL REPORTE CON UN enviado=0
                    catch (Services_Twilio_RestException $e) {
                        $queryGuardaReporte = "
                        insert into sm_re_sm_reporte
                        (id                                       ,name                              ,sm_pe_sm_persona_id_c             ,sm_li_sm_lista_id_c         ,date_entered                 ,mensaje              ,enviado,sm_ap_sm_api_id_c) values
                        ('" . date("YmdHis") . rand(100, 999) . "','$rowPersonaLista[nombre_persona]','$rowPersonaLista[id_persona]'    ,'$rowPersonaLista[id_lista]','" . date("Y-m-d H:i:s") . "','Error=$e'           ,'0'    ,'$_REQUEST[servicio]]');
                        ";
                        $resultGuardaReporte = $db->query($queryGuardaReporte);
                        $enviadosMal++;
                    }
                    $i++;
                }
            }
            //SI NO HAY PERSONAS EN ESTA LISTA MUESTRA UN MENSAJE DE QUE NO HAY PERSONAS
            else {
                ?>
                <div class="alert alert-warning">No person in this list</div>
                <?php
            }
        }
        ?>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <?php
                    if ($rowConfiguracion[modo_prueba] == "1") {
                        ?>
                        <div class="alert alert-warning" style="margin-bottom: 15px;">
                            <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                            Test mode activated
                        </div>
                        <?php
                    }
                    ?>
                    <form name="form_enviar" id="form_enviar" action="index.php?module=sm_en_sm_envio&action=index&parentTab=SMS" method="POST" role="form" >
                        <?php
                        $queryServicio = "SELECT id,`name` FROM sm_ap_sm_api where deleted='0';";
                        $resultServicio = $db->query($queryServicio);
                        ?>
                        <div class="form-group">
                            <label for="servicio">Select a service</label>
                            <select name="servicio" id="servicio" required="required" class="form-control">
                                <option value=""  >Select a service</option>
                                <?php
                                while ($rowServicio = $db->fetchRow($resultServicio)) {
                                    ?>
                                    <option value="<?php echo $rowServicio[id]; ?>" <?php if ($_REQUEST[servicio] == $rowServicio[id]) echo " selected='selected' "; ?> ><?php echo $rowServicio[name]; ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                        <?php
                        $queryLista = "SELECT id,`name`,deleted FROM sm_li_sm_lista where deleted='0';";
                        $resultLista = $db->query($queryLista);
                        ?>
                        <div class="form-group">
                            <label for="lista">Select a people list</label>
                            <select name="lista" id="lista" required="required"  class="form-control">
                                <option value="">Select a people list</option>
                                <?php
                                while ($rowLista = $db->fetchRow($resultLista)) {
                                    ?>
                                    <option value="<?php echo $rowLista[id]; ?>" <?php if ($_REQUEST[lista] == $rowLista[id]) echo " selected='selected' "; ?>><?php echo $rowLista[name]; ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="mensaje">Mensaje</label>
                            <textarea name="mensaje" id="mensaje" required="required" class="form-control"><?php echo $_REQUEST[mensaje]; ?></textarea>
                        </div>
                        <input type="submit" name="enviar" id="enviar" value="Send" class="button"/>
                        <div class="progress" style="display:none;">
                            <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="width:40%">Sending</div>
                        </div>

                        <?php
                        if ($_REQUEST[enviar]) {
                            ?>
                            <div class="alert alert-success" style="margin-top:15px;">
                                <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                                <strong>Sent messages</strong>: <?php echo $enviadosBien; ?>
                            </div>
                            <div class="alert alert-warning">
                                <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                                <strong>Unsent messages</strong>: <?php echo $enviadosMal; ?>
                            </div>
                            <?php
                        }
                        ?>

                    </form>
                </div>
            </div>
        </div>

        <?php
    }

}
