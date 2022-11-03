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
$until = @$_GET['until'];

$cmd = "docker logs --timestamps ". escapeshellarg($id);

if ($since != "") {
    $cmd = $cmd . " --since " . escapeshellarg($since);
}
if ($until != "") {
    $cmd = $cmd . " --until " . escapeshellarg($until);
}

$cmd = $cmd . " 2>&1 | sort -k 1";
?>

<h3>Container Logs</h3>
Command: <code><?php echo $cmd; ?></code>

<p/>

<form action="/src/logs.php">
    <input type="hidden" name="cmd" value="pull"/>
    <input type="hidden" name="id" value="<?php echo $id;?>"/>
    <table style="">
        <tr>
            <td>
                Logs since:
            </td>
            <td>
                <input name="since" value="<?php echo "{$since}";?>" type="input"/>
            </td>
        </tr>
        <tr>
            <td>
                Logs until:
            </td>
            <td>
                <input name="until" value="<?php echo "{$until}";?>" type="input"/>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <input type="submit" value="Refresh"/>
            </td>
        </tr>
    </table>

</form>
<p/>

Values of since and until:
<ul>
<li>Empty value means earliest for 'Logs since' and latest for 'Logs until'</li>
<li>Relative values for relative value back in time (15m - 15 minutes ago 2h - two hours ago, Can combine 3h30m40s</li>
<li>Absolute timestamp like 2006-01-02, or 2006-01-02T15:04:05 . / <a href="https://docs.docker.com/engine/reference/commandline/logs/">full description</a></li>
</ul>

<p/>

<?php

$runner = new base\TmpFileRunner($cmd);
$json = $runner->run();
$tbl = new base\FmtTable(array(
    "logs" => "logs"
));
$tbl->echo_from_generator( $runner->lineGenerator() );
