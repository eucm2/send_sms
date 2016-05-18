<?php
ini_set('display_errors', 'On');
require_once('include/MVC/View/views/view.list.php');

class sm_ex_sm_excelViewList extends ViewList {

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
        <?php
        $db = DBManagerFactory::getInstance();

        global $current_user;        
        
        //SI SE DIO CLICK EN SUBIR
        if ($_REQUEST[accion] == "Upload") {
            //TOMAMOS EL NOMBRE DEL ARCHIVO
            $archivo = $_FILES['excel']['name'];
            //ESTE ES EL DESTINO DONDE VA CAER EL ARCHIVO Y CON ESTA VARIABLE SE VAN HACER TODAS LAS OPERACIONES
            $destino = "temp/bak_" . $archivo;
            if (copy($_FILES['excel']['tmp_name'], $destino)) {
                //
            } else {
                $error = "File no found";
                goto error;
            }
            //SI EL ARCHIVO EXISTE
            if (file_exists($destino)) {
                require_once('Classes/PHPExcel.php');
                require_once('Classes/PHPExcel/Reader/Excel2007.php');
                //CARGANDO LA HOJA DE CÁLCULO
                $objReader = new PHPExcel_Reader_Excel2007();
                $objPHPExcel = $objReader->load($destino);
                //ASIGNAR HOJA DE EXCEL ACTIVA
                $objPHPExcel->setActiveSheetIndex(0);
                $highestRow = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
                if ($highestRow == 0) {
                    $error = "Excel empty";
                    goto error;
                }
                //LLENAMOS EL ARREGLO CON LOS DATOS  DEL ARCHIVO XLSX
                for ($i = 1; $i <= $highestRow; $i++) {
                    $_DATOS_EXCEL_NAME[$i] = $objPHPExcel->getActiveSheet()->getCell('A' . $i)->getCalculatedValue();
                    $_DATOS_EXCEL_PHONE[$i] = $objPHPExcel->getActiveSheet()->getCell('B' . $i)->getCalculatedValue();
                }
                $_DATOS_EXCEL_PHONE = array_unique($_DATOS_EXCEL_PHONE);
                $conReg = 0;
                for ($i = 1; $i <= $highestRow; $i++) {
                    if ($_DATOS_EXCEL_PHONE[$i]) {
                        $_DATOS_EXCEL[$conReg][phone] = str_replace("'", "\'", $_DATOS_EXCEL_PHONE[$i]);
                        $_DATOS_EXCEL[$conReg][name] = str_replace("'", "\'", $_DATOS_EXCEL_NAME[$i]);
                        $conReg++;
                    }
                }
            }

            //SI POR ALGO NO CARGO EL EXCEL
            else {
                $error = "File no found";
                goto error;
            }
            // INICIO DE QUERY PARA HACER MULTIPLES INSERT
            $queryInsertPersona = "INSERT INTO sm_pe_sm_persona (id,name,telefono,sm_li_sm_lista_id_c) VALUES";
            //CONTADOR PARA VER CUANTOS REGISTROS SE INSERTARON Y PARA DETERMINAR SI LLEVA "," O NO AL INICIO DEL INSERT
            $cont = 0;
            // FOREACH SACA LOS REGISTROS DEL ARRAY DEL EXCEL
            foreach ($_DATOS_EXCEL as $campo => $valor) {
                //CONSULTA QUE BUSCA UN TELEFONO REPETIDO
                $queryBuscaTelefonoRepetido = "SELECT telefono FROM sm_pe_sm_persona where telefono='$valor[phone]';";
                $resultBuscaTelefonoRepetido = $db->query($queryBuscaTelefonoRepetido);
                $rowBuscaTelefonoRepetido = $db->fetchRow($resultBuscaTelefonoRepetido);
                //SI EL TELEFONO YA ESTA EN LA BD NO LO PONEMOS DE NUEVO
                if (!$rowBuscaTelefonoRepetido[telefono]) {
                    //GENERAMOS EL UNIQID DE SUGAR
                    $hash = md5(uniqid());
                    $guid = substr($hash, 0, 8) . "-" . substr($hash, 8, 4) . "-" . substr($hash, 12, 4) . "-" . substr($hash, 16, 4) . "-" . substr($hash, 20, 12);
                    //SI CONTADOR == 0 PONEMOS ", AL PRINCIPIO DEL INSERT"
                    if ($cont == 0) {
                        $queryInsertPersona = $queryInsertPersona . "('$guid','$valor[name]','$valor[phone]','$_REQUEST[lista]]')";
                    } else {
                        $queryInsertPersona = $queryInsertPersona . ",('$guid','$valor[name]','$valor[phone]','$_REQUEST[lista]]')";
                    }
                    $cont++;
                }
            }
            //SI SE INSERTARON MAS DE 1 REGISTRO
            if ($cont > 0) {
                //FINALIZAMOS INSERT CON ";"
                $queryInsertPersona = $queryInsertPersona . ";";
                //EJECUTAMOS LA CONSULTA
                $resultInsertPersona = $db->query($queryInsertPersona);

                //ELIMINAMOS EL ARCHIVO SUBIDO
                unlink($destino);
                //SI NO SE EJECUTO BIEN LA CONSULTA MANDAMOS UN ERROR DE MYSQL
                if ($resultInsertPersona) {
                    ?>
                    <div class="alert alert-success">
                        <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                        <div><strong>Successful!</strong> <?php echo $cont; ?> row inserted</div>
                    </div>
                    <?php
                } else {
                    $error = "MySql error  $queryInsertPersona   mysqli_error=" . mysqli_error($db);
                    goto error;
                }
            }
            //SI NO SE INSERTO NINGUN REGISTRO
            else {
                ?>
                <div class="alert alert-success  alert-info alert-warning alert-danger">
                    <strong>0</strong> rows to insert
                </div>
                <?php
            }
        }

        if ($errores == 1) {
            error:
            ?>
            <div class="alert alert-warning">
                <strong>Error!</strong> <?php echo $error; ?>
            </div>
            <?php
        }
        ?>

        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <form name="importa" method="post" action="<?php echo $PHP_SELF; ?>" enctype="multipart/form-data" >
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
                            <label for="excel">
                                File input
                            </label>
                            <input type="file" name="excel" id="excel" />
                        </div>
                        <button type="submit" value="Upload" name="accion" class="btn btn-default">Upload</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

}
