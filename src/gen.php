<?php require_once __DIR__ . "/base/nocache.php"; ?>
<html>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(-1);
?>

<?php

require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";


// functions for api mode.
function make_inspectc($id) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $jsonRaw) = $runner->inspectContainer($id);
    return json_decode($jsonRaw, JSON_OBJECT_AS_ARRAY);
}

function make_inspecti($id) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $jsonRaw) = $runner->inspectImage($id);
    return json_decode($jsonRaw,JSON_OBJECT_AS_ARRAY);
}

function make_cprune($id) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $jsonRaw) = $runner->containerPrune();
    return explode("\n",$jsonRaw);
}

function make_iprune($id) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $jsonRaw) = $runner->imagePrune();
    return explode("\n",$jsonRaw);
}

function format_top_entry($entry) {
    return implode("  ", $entry);
}

function amap($func_name, $arr) : array {
    $ret = array();
    foreach($arr as $k => $v) {
        $ret[$k] = call_user_func($func_name, $v);
    }
    return $ret;
}

function make_top($id) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $jsonRaw) = $runner->containerProcessList($id);

    $pretty = json_decode($jsonRaw,JSON_OBJECT_AS_ARRAY);
    $title =implode("  ", $pretty['Titles']);
    $titles = array($title);

    $procs = amap('format_top_entry', $pretty['Processes']);
    array_push( $titles , ...$procs);

    return $titles;
}

function make_stats($id) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $jsonRaw) = $runner->containerStats($id);

    $pretty = json_decode($jsonRaw,JSON_OBJECT_AS_ARRAY);
    return $pretty;
}

function make_remove_image($id) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $jsonRaw) = $runner->imageRemove($id);

    $pretty = json_decode($jsonRaw,JSON_OBJECT_AS_ARRAY);
    return $pretty;
}

function make_container_pause($id) {
    $runner = new DockerRest\DockerEngineApi();

    list($ok, $msgRaw) = $runner->containerPause($id);
    if ($ok) {
        return array("Container {$id} paused");
    }
    return array("Failed to pause container {$id} : " . $msgRaw);
}

function make_container_resume($id) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $msgRaw) = $runner->containerResume($id);
    if ($msgRaw) {
        return array("Container {$id} resumed");
    }
    return array("Failed to pause container {$id} : " . $msgRaw);
}

function make_container_kill($id) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $msgRaw) = $runner->containerKill($id);
    if ($msgRaw) {
        return array("Container {$id} killed");
    }
    return array("Failed to kill container {$id} : " . $msgRaw);
}

function make_container_stop($id) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $msgRaw) = $runner->containerStop($id);
    if ($ok) {
        return array("Container {$id} stopped");
    }
    return array("Failed to pause container {$id} : " . $msgRaw);
}

function make_search($term) {
    $runner = new DockerRest\DockerEngineApi();
    list($ok, $jsonRaw) = $runner->imageSearch($term);

    $pretty = json_decode($jsonRaw,JSON_OBJECT_AS_ARRAY);
    return $pretty;

}

$cmd=$_GET['cmd'];
$id = $_GET['id'];

$cmd_def = array(
    'inspectc' => array(
            "docker inspect %s --format='{{json .}}'",
            "Inspect",
            "Return low-level information on Container object",
            True,
            "make_inspectc"),
    'inspecti' => array(
            "docker inspect %s --format='{{json .}}'", "Inspect",
            "Return low-level information on Image object",
            True,
            "make_inspecti"),
    'stats' => array(
            "docker stats -a --no-stream %s",
            "Container stats", "Resource Usage Statistics",
            False,
            "make_stats"),
    'top' => array("docker top %s", "Running Processes",
            "Running processes of a container",
            False,
            "make_top"),
    'rmi' => array("docker rmi %s",
            "Remove Image",
            "Remove Image",
            False,
            "make_remove_image"),
    'pause' => array("docker pause %s",
            "Pause Container",
            "Pause all processes of a container",
            False,
            "make_container_pause",
            ""),
    'resume' => array("docker unpause %s",
            "Resume/Unpause Container",
            "Unpause all processes of a container",
            False,
            "make_container_resume",
            ""),

    'kill' => array("docker kill %s",
        "Kill a running container",
        "Kill a running container",
        False,
        "make_container_kill",
        ""),

    'stop' => array(
            "docker stop %s",
            "Stop Container",
            "Stop a running container",
            False,
            "make_container_stop"),
    'cprune' => array(
            "docker container prune -f",
            "Container Prune",
            "Remove all stopped containers",
            False,
            "make_cprune"),
    'iprune' => array(
            "docker image prune -a -f",
            "Image Prune",
            "Remove unused images",
            False,
            "make_iprune"),
    'search' => array(
            "docker search %s",
            "Image Sarch",
            "Search the Docker Hub for images",
            False,
            "make_search"),
    'pull' => array(
            "docker pull %s &",
            "docker pull",
            "Start pulling an image in the background",
            False,
            ""),
    'run' => array(
            "docker run %s &",
            "docker run",
            "Run a command in a new container",
            False,
            ""),
);

list($command, $tbl_title, $page_title, $is_json, $func_name) = $cmd_def[$cmd];

$cmd_str = sprintf($command, escapeshellarg($id));

$json = call_user_func($func_name,$id);
?>

<h3><?php echo $page_title; ?></h3>
Command: <code><?php echo $cmd_str; ?></code>

<?php


$tbl = new base\FmtTable(array(
    $tbl_title => $tbl_title,
));

echo $tbl->format_row($json);

