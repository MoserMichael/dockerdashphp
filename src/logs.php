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

$id = $_GET['id'];
$since = @$_GET['since'];
$cmd = "docker logs --timestamps ". escapeshellarg($id);

if ($since != "") {
    $cmd = $cmd . " --since " . escapeshellarg($since);
}
?>

<h3>Container Logs</h3>
Command: <code><?php echo $cmd; ?></code>

<p/>

<form action="/src/logs.php">
    <input type="hidden" name="cmd" value="pull"/>
    <input type="hidden" name="id" value="<?php echo $id;?>"/>
    Logs since: <input name="since" type="input"/>
    <input type="submit" value="Refresh"/>
</form>
<p/>
For format of the field description of --since option <a href="https://docs.docker.com/engine/reference/commandline/logs/">[link]</a>
Empty value means 'get all logs'
<p/>

<?php

$cmd = $cmd . " | sort -k 1";
$runner = new base\TmpFileRunner($cmd);
$json = $runner->run();
$tbl = new base\FmtTable(array(
    "logs" => "logs"
));
$tbl->echo_from_generator( $runner->lineGenerator() );
