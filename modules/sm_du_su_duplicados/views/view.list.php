<?php
require_once('include/MVC/View/views/view.list.php');

class sm_du_su_duplicadosViewList extends ViewList {

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
        <form name="envio" action="index.php?module=sm_du_su_duplicados&action=index&parentTab=SMS" method="POST">
            <?php
            $db = DBManagerFactory::getInstance();
            $industry = $_POST['Industry'];
            if ($_REQUEST[a_borrar]) {
                var_dump($_REQUEST[a_borrar]);
                $ids="";
                foreach ($_REQUEST[a_borrar] as $cont=>$val_id){
                    if($cont==0){
                        $ids=$ids." id='$val_id' ";
                    }
                    else{
                        $ids=$ids." or id='$val_id' ";
                    }
                }
                
                $queryBorrarTelefonos = " delete from sm_pe_sm_persona where $ids; ";
                //echo $queryBorrarTelefonos;
                
                $resultBorrarTelefonos = $db->query($queryBorrarTelefonos);
                
            }
            $queryResumenDuplicados = "SELECT telefono,name, COUNT(*) c FROM sm_pe_sm_persona WHERE deleted='0' GROUP BY telefono HAVING c > 1 ;";
            $resultResumenDuplicados = $db->query($queryResumenDuplicados);
            if ($resultResumenDuplicados->num_rows > 0) {
                ?>
                <button type="submit" name="borrar" class="button">Delete</button>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Name</th>
                            <th>Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        while ($rowResumenDuplicados = $db->fetchRow($resultResumenDuplicados)) {
                            $i = 1;
                            ?>
                            <tr>
                                <td colspan="3">Phone: <?php echo $rowResumenDuplicados[telefono]; ?></td>
                            </tr>
                            <?php
                            $queryListaDuplicados = "SELECT id,telefono,name FROM sm_pe_sm_persona where telefono='$rowResumenDuplicados[telefono]';";
                            $resultListaDuplicados = $db->query($queryListaDuplicados);
                            if ($resultListaDuplicados->num_rows > 0) {
                                while ($rowListaDuplicados = $db->fetchRow($resultListaDuplicados)) {
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" name="a_borrar[]" value="<?php echo $rowListaDuplicados[id]; ?>" <?php if (mysqli_num_rows($resultListaDuplicados) !== $i) echo ' checked="checked" '; ?>  /></td>
                                        <td><?php echo $rowListaDuplicados[name]; ?></td>
                                        <td><?php echo $rowListaDuplicados[telefono]; ?></td>
                                    </tr>
                                    <?php
                                    $i++;
                                }
                                //echo $rowResumenDuplicados[id];
                            }
                        }
                        ?>
                    </tbody>
                </table>
                <?php
            }
            ?>
        </form>
        <?php
    }

}
