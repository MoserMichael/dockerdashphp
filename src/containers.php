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


function make_docker_inspect_link($row_val, $json) : string {

    $ps="";
    $state = $json["State"];
    if ($state == "running") {
        $ps="&nbsp; <a href='/gen.php?cmd=top&id={$row_val}'>/top/</a> &nbsp; <a href='/gen.php?cmd=stats&id={$row_val}'>/stats/</a>";
    }

    $id = $json["ID"];
    
    if ($state == "running") {
        $ps = "{$ps}&nbsp;<a href='/attach.php?id={$id}'><br/><b>/Console/</b></a>";
    }

    return "<a title='inspect container' href='/gen.php?cmd=inspectc&id={$row_val}'>{$row_val}</a>&nbsp; <a href='/logs.php?id={$row_val}&since=10m'>/logs/</a>{$ps}";

    //return "<a title='inspect' href='/gen.php?cmd=inspectc&id={$row_val}'>{$row_val}</a>&nbsp; <a href='/logs.php?id={$row_val}&since=10m'>/logs/</a>";

}

function make_docker_state_link($row_val, $json) : string {
    $id = $json["ID"];
    $links = "";
    if ($json["State"] == "running") {
        $links = "{$links}&nbsp;<a href='/gen.php?cmd=pause&id={$id}'>/Pause/</a>";
        $links = "{$links}&nbsp;<a href='/gen.php?cmd=stop&id={$id}'>/Stop/</a>";
        $links = "{$links}&nbsp;<a href='/gen.php?cmd=kill&id={$id}'>/Kill/</a>";
    }
    if ($row_val == "paused") {
        $links = "&nbsp;<a href='/gen.php?cmd=resume&id={$id}'>/Resume/</a>";
    }

    return $row_val . $links;
}


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
        $ps = "{$ps}&nbsp;<br/><a href='/attach.php?id={$id}'><b>/Console/</b></a>";
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
    return $json["State"] . " - " . $json["Status"] . " " . $links;
}

function make_api_names($row_val, $json) : string {
    $names = $json["Names"];
    return implode(" ", $names);
}

function make_api_created_at($row_val, $json) : string {
    return date('Y-m-d H:i:s' ,$row_val);
}

function make_api_networks($row_val, $json) : string {
    $obj = $json['NetworkSettings']['Networks'];

    $ret = "";
    foreach($obj as $key => $value) {
        $ret = $ret . $key . " ";
    }

    foreach($json["Ports"] as $portDef) {
       $ip = $portDef['IP'];
       $from = $portDef['PrivatePort'];
       $to = $portDef['PublicPort'];
       $ty = $portDef['Type'];
       $ret = $ret . "{$ip}:{$from}->{$to}/{$ty} ";
    }
    return $ret;
}

function make_api_mounts($row_val, $json) : string {
    $ret = "";
    foreach($json['Mounts'] as $key => $value) {
        $ret = $ret . " " . $value['Source'] . " -> " . $value['Destination'] . " ";
    }
    return $ret;
}

function make_api_image($row_val, $json) : string {

    $hash = $row_val;
    if (str_starts_with($row_val, "sha256:")) {
        $hash = substr($row_val, strlen("sha256:"), 12);
    }
    $link = "<a title='inspect image' href='/gen.php?cmd=inspecti&id={$hash}'>{$hash}</a>";
    return $link;
}

if (!use_docker_api()) {

    $runner = new base\Runner("docker ps -a --format='{{json .}}'");
    $json = $runner->run();
    $tbl = new base\FmtTable(array(
        "ID" => array("ID", __NAMESPACE__ . "\\make_docker_inspect_link"),
        "State" => array("State", __NAMESPACE__ . "\\make_docker_state_link"),
        "Names" => "Names",
        "Image" => "Image",
        "Status" => "Status",
        "Created At" => "CreatedAt",
        "Running For" => "RunningFor",
        "Command" => "Command",
        "Local Volumes" => "LocalVolumes",
        "Mounted Volumes" => "Mounts",
        "Disk Size" => "Size",
        "Attached Networks" => "Networks",
        "Exposed Ports" => "Ports",
        "Labels" => "Labels",
    ));

    echo $tbl->format($json);
} else {

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
        "RootFs Size" => "SizeRootFs",
        "Mounts" => array("Mounts",  __NAMESPACE__ . "\\make_api_mounts"),
        /*
        "ID" => array("ID", __NAMESPACE__ . "\\make_docker_inspect_link"),
        "State" => array("State", __NAMESPACE__ . "\\make_docker_state_link"),
        "Names" => "Names",
        "Image" => "Image",
        "Status" => "Status",
        "Created At" => "CreatedAt",
        "Running For" => "RunningFor",
        "Command" => "Command",
        "Local Volumes" => "LocalVolumes",
        "Mounted Volumes" => "Mounts",
        "Disk Size" => "Size",
        "Attached Networks" => "Networks",
        "Exposed Ports" => "Ports",
        "Labels" => "Labels",
        */
    ));
    $json = json_decode($jsonRaw, JSON_OBJECT_AS_ARRAY);
    echo $tbl->format($json);
}
