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
?>
[ <a href="/gen.php?cmd=iprune&id=a">Remove/Prune Unused Images</a> ]
<h3>Images</h3>
Command: <code>docker image ls -a</code>

<?php
require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";

function make_docker_inspect_link($row_val,$json) : string {

    $image = $json["ID"];

    $rmi="<a href='/gen.php?cmd=rmi&id={$row_val}'>/remove image/</a>";
    $run="<a href='/run.php?ID={$image}'>/run/</a>";

    return "<a title='inspect' href='/gen.php?cmd=inspecti&id={$row_val}'>{$row_val}</a>&nbsp;{$rmi}&nbsp;{$run}";
}


function make_api_id($row_val,$json) : string {
    $id = $json["Id"];
    $row_val = substr($id, strlen("sha256:"), 12);
    $image = $row_val;

    $rmi="<a href='/gen.php?cmd=rmi&id={$row_val}'>/remove image/</a>";
    $run="<a href='/run.php?ID={$image}'>/run/</a>";

    return "<a title='inspect' href='/gen.php?cmd=inspecti&id={$row_val}'>{$row_val}</a>&nbsp;{$rmi}&nbsp;{$run}";

}

function make_api_repo_name($row_val,$json) : string
{
    $tags = $json["RepoTags"];
    if ($tags != null) {
        return implode(" ", $tags);
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


if (!use_docker_api()) {

    $runner = new base\Runner("docker image ls -a --format='{{json .}}'");
    $json = $runner->run();
    $tbl = new base\FmtTable(array(
        "ID" => array("ID", __NAMESPACE__ . "\\make_docker_inspect_link"),
        "Repository" => "Repository",
        "Tag" => "Tag",
        "RepoDigest" => "Digest",
        "Containers" => "Containers",
        "Created Since" => "CreatedSince",
        "Size" => "Size",
        "Shared Size" => "SharedSize",
        "Unique Size" => "UniqueSize",
        "Virtual Size" => "VirtualSize"
    ));

    echo $tbl->format($json);
} else {

    $runner = new DockerRest\DockerEngineApi();
    list ($ok,$jsonRaw) = $runner->imageList();

    $tbl = new base\FmtTable(array(
        "Id" => array("ID", __NAMESPACE__ . "\\make_api_id"),
        "RepoDigests" => array("Repository", __NAMESPACE__ . "\\make_api_repo_name"),
        "Labels" => array("Labels",  __NAMESPACE__ . "\\make_api_labels"),
        "Created" => array("CreatedSince", __NAMESPACE__ . "\\make_api_created"),
        "Size" => "Size",
        "Shared Size" => "SharedSize",
        "Virtual Size" => "VirtualSize"
    ));

    $json = json_decode($jsonRaw, JSON_OBJECT_AS_ARRAY);

    echo $tbl->format($json);
}
