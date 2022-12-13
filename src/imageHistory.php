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

<h3>Image Build History</h3>
Command: <code>docker history <?php echo "{$id}"; ?></php></code>

<?php
require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";

$seq = 1;

function make_seq($row_val, $json) : string {
    global $seq;
    $ret = "{$seq}";
    $seq += 1;
    return $ret;
}

function make_created($row_val,$json) : string {
    return date('Y-m-d H:i:s',$row_val);
}

function make_tags($row_val,$json) : string {
    if (is_array($row_val)) {
        return join(",", $row_val);
    }
    return "";
}

function make_size($row_val) : string {
    return base\human_readable_size($row_val);
}

function make_id($imageId, $json) : string {
    if (str_starts_with($imageId,"sha256:")) {
        $imageId = substr($imageId, strlen("sha256:"), 12);
        return $imageId;
    }
    return "";
}
$runner = new DockerRest\DockerEngineApi();
list ($ok,$jsonRaw) = $runner->imageHistory($id);

$tbl = new base\FmtTable(array(
     "Sequence" => array("Sequence" ,__NAMESPACE__ . "\\make_seq"),
     "Id"=>array("Id",__NAMESPACE__ . "\\make_id"),
     "Created"=>array("Created", __NAMESPACE__ . "\\make_created"),
     "Size"=>array("Size", __NAMESPACE__ . "\\make_size"),
     "Tags"=>array("Tags", __NAMESPACE__ . "\\make_tags"),
     "Comment"=>"Comment",
     "CreatedBy"=>"CreatedBy"
));

$json = json_decode($jsonRaw, JSON_OBJECT_AS_ARRAY);

echo $tbl->format($json);

