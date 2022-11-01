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

$cmd_def=array(
    'logs' => array('docker logs --timestamps %s 2>&1 | sort -k 1', "logs", "Container Logs", False),
    'inspectc' => array("docker inspect %s --format='{{json .}}'", "Inspect", "Return low-level information on Container object", True),
    'inspecti' => array("docker inspect %s --format='{{json .}}'", "Inspect", "Return low-level information on Image object", True),
    'stats' => array("docker stats -a --no-stream %s", "Container stats", "Resource Usage Statistics", False),
    'top' => array("docker top %s", "Running Processes", "Running processes of a container", False),
    'rmi' => array("docker rmi %s",  "Remove Image", "Remove Image", False),
    'pause' => array("docker pause %s",  "Pause Container", "Pause all processes of a container", False),
    'resume' => array("docker unpause %s",  "Resume/Unpause Container", "Unpaue all processes of a container", False),
    'stop' => array("docker stop %s",  "Stop Container", "Stop a running container", False),
    'cprune' => array("docker container prune -f", "Container Prune", "Remove all stopped containers", False ),
    'iprune' => array("docker image prune -a -f", "Image Prune", "Remove unused images", False),
    'search' => array("docker search %s", "Image Sarch", "Search the Docker Hub for images", False),
    'pull' => array("nohup docker pull %s &", "docker pull", "Start pulling an image in the background", False),
    'run' => array("nohup docker run %s &", "docker run", "Run a command in a new container", False),
);

$cmd=$_GET['cmd'];
$id = $_GET['id'];

list($command, $tbl_title, $page_title, $is_json) = $cmd_def[$cmd];

$cmd_str=sprintf($command, escapeshellarg($id));

?>

<h3><?php echo $page_title; ?></h3>
Command: <code><?php echo $cmd_str; ?></code>

<?php

$runner = new base\Runner($cmd_str, $is_json);
$json = $runner->run();
$tbl = new base\FmtTable(array(
    $tbl_title => $tbl_title,
));

echo $tbl->format_row($json);

