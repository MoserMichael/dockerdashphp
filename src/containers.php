<?php require_once __DIR__ . "/base/nocache.php"; ?>
<html>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
<?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(0);
?>
[ <a href="/gen.php?cmd=cprune&id=a">Remove/Prune Unused Containers</a> ]

<h3>Containers</h3>
Command: <code>docker ps -a</code>

<?php

require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";


function make_api_id($row_val, $json) : string {
    $state = $json["State"];

    $ps = "";
    
    if ($state == "running") {
        $ps="<a href='/gen.php?cmd=top&id={$row_val}'>/top/</a>&nbsp;";
        $ps="{$ps}<a href='/gen.php?cmd=stats&id={$row_val}'>/stats/</a>&nbsp;";
    }

    $id= substr($row_val, 0, 12);

    $ps = "{$ps}<a href='/logs.php?id={$id}&since=10m'>/logs/</a>";

    if ($state == "running") {
        $diffs = "<a href='/containerDiff.php?id={$id}'>/diffs/</a>";
        $ps = "{$ps}&nbsp;{$diffs}<br/><a href='/attach.php?id={$id}'><b>/Console/</b></a>";
    }

    return "<a title='inspect container' href='/gen.php?cmd=inspectc&id={$id}'>{$id}</a><br/>{$ps}";
}


function make_api_status($row_val, $json) : string {
    $links = "";
    $id = $json["Id"];
    if ($json["State"] == "running") {
        $links = "{$links}&nbsp;<a href='/gen.php?cmd=pause&id={$id}'>/Pause/</a>";
        $links = "{$links}&nbsp;<a href='/gen.php?cmd=stop&id={$id}'>/Stop/</a>";
        $links = "{$links}&nbsp;<a href='/gen.php?cmd=kill&id={$id}'>/Kill/</a>";
    }
    if ($json["State"] == "paused") {
        $links = "{$links}&nbsp;<a href='/gen.php?cmd=resume&id={$id}'>/Resume/</a>";
    }
    return $json["State"] . " - " . $json["Status"] . "<br/>" . $links;
}

function make_api_names($row_val, $json) : string {
    $names = $json["Names"];
    return implode(" ", $names);
}

function make_api_created_at($row_val, $json) : string {
    return date('Y-m-d H:i:s' ,$row_val);
}

function make_size($row_val, $json) : string {
    return base\human_readable_size($row_val);
}

function make_api_networks($row_val, $json) : string {
    $obj = @$json['NetworkSettings']['Networks'];

    $ret = "";
    foreach($obj as $key => $value) {
        $ret = $ret . $key . " ";
    }

    foreach($json["Ports"] as $portDef) {
       $ip = @$portDef['IP'];
       $from = @$portDef['PrivatePort'];
       $to = @$portDef['PublicPort'];
       $ty = @$portDef['Type'];
       $ret = $ret . "{$ip}:{$from}->{$to}/{$ty} ";
    }
    return $ret;
}

function make_api_mounts($row_val, $json) : string {
    $ret = "";
    foreach($json['Mounts'] as $key => $value) {
        $ret = $ret . " " . @$value['Source'] . " -> " . @$value['Destination'] . " ";
    }
    return $ret;
}

function make_api_image($row_val, $json) : string {

    $hash = $row_val;
    if (str_starts_with($row_val, "sha256:")) {
        $hash = substr($row_val, strlen("sha256:"), 12);
    }

    $link = "";

    $runner = new DockerRest\DockerEngineApi();
    list ($status, $body) = $runner->inspectImage($hash);
    if ($status) {
        $arr = json_decode($body, JSON_OBJECT_AS_ARRAY);
        if ($arr != null) {
            $tags = @$arr['RepoTags'];
            if ($tags != 0) {
                $tagNames = join(",", $tags);
                $link = "<a title='inspect image' href='/gen.php?cmd=inspecti&id={$hash}'>{$tagNames}</a>";
            }
        }
    }

    if ($link == "") {
        $link = "<a title='inspect image' href='/gen.php?cmd=inspecti&id={$hash}'>{$hash}</a>";
    }


    $runner->close();

    return $link;
}



$runner = new DockerRest\DockerEngineApi();
list($ok, $jsonRaw) = $runner->containersList();

$tbl = new base\FmtTable(array(
    "Id" => array("ID", __NAMESPACE__ . "\\make_api_id"),
    "State" => array("State", __NAMESPACE__ . "\\make_api_status" ),
    "Names" => array("Names", __NAMESPACE__ . "\\make_api_names" ),
    "Created" => array("Created At", __NAMESPACE__ . "\\make_api_created_at"),
    "Image" => array("Image", "make_api_image"),
    "Command" => "Command",
    "NetworkSettings" => array("NetworkSettings", __NAMESPACE__ . "\\make_api_networks"),
    "SizeRootFs" => array("RootFs Size",  __NAMESPACE__ . "\\make_size"),
    "Mounts" => array("Mounts",  __NAMESPACE__ . "\\make_api_mounts"),
));
$json = json_decode($jsonRaw, JSON_OBJECT_AS_ARRAY);
echo $tbl->format($json);
