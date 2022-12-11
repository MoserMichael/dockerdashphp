<?php require_once __DIR__ . "/base/nocache.php"; ?>
<html>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(1);
$id = $_GET['id'];
?>

<h3>Inspect changes to files or directories on a container's filesystem</h3>
Command: <code>docker diff <?php echo "{$id}"; ?></php></code>

<?php
require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";


$runner = new DockerRest\DockerEngineApi();
list ($ok,$jsonRaw) = $runner->containerDiff($id);



function make_kind($row_val)  : string {
    $descr = array( 0 => "Modified", 1 => "Added", 2 => "Deleted");
    return $descr[$row_val];
}

$tbl = new base\FmtTable(array(
    "Kind" => array("Kind", __NAMESPACE__ . "\\make_kind"),
    "Path" => "Path"
));

$json = json_decode($jsonRaw, JSON_OBJECT_AS_ARRAY);

echo $tbl->format($json);

