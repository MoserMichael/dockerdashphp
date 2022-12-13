<?php require_once __DIR__ . "/base/nocache.php"; ?>
<html>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(2);

?>

[ <a href="/createVolume.php">Create Volume</a> ] &nbsp [ <a href="/gen.php?cmd=vprune&id=a">Remove/Prune Unused Volumes</a> ]
<h3>Volumes</h3>
Command: <code>docker volume ls</code>

<?php
require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";


function make_labels($row_val, $json) : string {
     $res = "";
     $labels = $json['Labels'];
     if ($labels != null) {
         foreach ($labels as $k => $v) {
             if ($res != "") {
                 $res .= " ";
             }
             $res .= "$k=$v";
         }
     }
     return $res;
}

function make_options($row_val, $json) : string {
    $res = "";
    foreach($json['Options'] as $k => $v) {
        $res .= "$k => $v";
    }
    return $res;
}

$runner = new DockerRest\DockerEngineApi();
list ($ok,$jsonRaw) = $runner->volumeList();

$tbl = new base\FmtTable(array(
        "CreatedAt" => "CreatedAt",
        "Name" => "Name",
        "Labels" => array("Labels", "make_labels"),
        "Scope" => "Scope",
        "Driver" => "Driver",
        "Options" => array("Options", "make_labels"),
        "Mountpoint" => "Mountpoint",
));

$json = json_decode($jsonRaw, JSON_OBJECT_AS_ARRAY);

echo $tbl->format(@$json['Volumes']);

