<?php require_once __DIR__ . "/base/nocache.php"; ?>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(1);

$show_all = $_GET['all'] ?? "true";

$show_all_checked = "";
if ($show_all=="true") {
    $show_all = true;
    $show_all_checked = 'checked';
} else {
    $show_all = false;
}
?>
<script>

    function onShowAll() {
        let on = document.getElementById('show_all').checked;
        let urlParams = "?all=" + on;
        let location = (window.location.href.split('?')[0]) + urlParams;
        window.location.href = location;

    }
</script>
[ <input type="checkbox" id="show_all" <?php echo $show_all_checked; ?> onchange="onShowAll()"> <label for="show_all">Show All Images</label> ] &nbsp; [ <a href="/gen.php?cmd=iprune&id=a">Remove/Prune Unused Images</a> ]
<h3>Images</h3>
Command: <code>docker image ls <?php if ($show_all) { echo "-a"; } ?> </code>

<?php
require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";


function make_api_id($row_val,$json) : string {
    $id = $json["Id"];
    $row_val = substr($id, strlen("sha256:"), 12);
    $image = $row_val;
    $name = $json["RepoTags"][0] ?? "";

    $rmi="<a href='/gen.php?cmd=rmi&id={$row_val}'>/remove image/</a>";
    $history="<a href='/imageHistory.php?id={$row_val}'>/History/</a>";

    $run="<a href='/run.php?ID={$image}&name={$name}'>/create container/</a>";

    $link = make_inspect_imagelink($row_val);
    return "{$link}&nbsp;{$history}<br/>{$rmi}<br/>{$run}";

}

function make_size($row_val, $json) : string {
    return base\human_readable_size($row_val);
}

function make_inspect_imagelink(string $value ) : string {
    return "<a title='inspect image' href='/gen.php?cmd=inspecti&id={$value}'>{$value}</a>";
}

function make_api_repo_name($row_val,$json) : string
{
    $tags = $json["RepoTags"];
    if ($tags != null) {
        return implode(" ", array_map( "\make_inspect_imagelink", $tags ) );
    }
    return "";
}

function make_api_created($row, $json) : string
{
    return date('Y-m-d H:i:s' ,$json["Created"]);
}

function make_api_labels($row, $json) : string
{
    $labels = $json["Labels"];
    if ($labels != null) {
        return implode(" ", $labels);
    }
    return "";
}


$runner = new DockerRest\DockerEngineApi();
list ($ok,$jsonRaw) = $runner->imageList($show_all);

$tbl = new base\FmtTable(array(
    "Id" => array("ID", __NAMESPACE__ . "\\make_api_id"),
    "RepoDigests" => array("Repository", __NAMESPACE__ . "\\make_api_repo_name"),
    "Labels" => array("Labels",  __NAMESPACE__ . "\\make_api_labels"),
    "Created" => array("CreatedSince", __NAMESPACE__ . "\\make_api_created"),
    "Size" => array("Size", __NAMESPACE__ . "\\make_size"),

    // no one knows what these mean for images (SharedSize is always -1, anv Virtual Size is always equal to size - for images.
    //"Shared Size" => "SharedSize",
    //"VirtualSize" => array("Virtual Size", __NAMESPACE__ . "\\make_size"),
));

$json = json_decode($jsonRaw, JSON_OBJECT_AS_ARRAY);

echo $tbl->format($json);

