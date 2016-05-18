<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html lang="en">
<head>
    <title>Progress Bar</title>
</head>
<body>
<div id="progress" style="width:500px;border:1px solid #ccc;"></div>
<div id="information" style="width"></div>
<?php
$total = 10;
for($i=1; $i<=$total; $i++){
    $percent = intval($i/$total * 100)."%";
    echo '<script language="javascript">
    document.getElementById("progress").innerHTML="<div style=\"width:'.$percent.';background-color:#ddd;\">&nbsp;</div>";
    document.getElementById("information").innerHTML="'.$i.' row(s) processed.";
    </script>';
    echo str_repeat(' ',1024*64);
    flush();
    sleep(1);
}
?>