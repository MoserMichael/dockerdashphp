<html>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
<?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(3);
?>
<h3>Docker version</h3>
Command: <code>docker version</code>

<?php

require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";

$runner = new base\Runner("docker version", False);
$json = $runner->run();
$tbl = new base\FmtTable(array(
    "Docker Version" => "Docker Version",
));
echo $tbl->format_row($json);
?>

<h3>Disk Usage</h3>
Command: <code>docker system df</code>

<?php
$runner = new base\Runner("docker system df", False);
$json = $runner->run();
$tbl = new base\FmtTable(array(
    "Docker Disk Usage" => "Docker Disk Usage",
));
echo $tbl->format_row($json);
?>


<h3>Engine info</h3>
Command: <code>docker info</code>

<?php

$runner = new base\Runner("docker info --format='{{json .}}'");
$json = $runner->run();
$tbl = new base\FmtTable(array(
    "Docker Info" => "Docker Info",
));
echo $tbl->format_row($json);
?>


