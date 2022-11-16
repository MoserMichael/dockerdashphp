<html>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
    <?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>
</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(-1);

$image = escapeshellcmd($_GET['ID']);
?>
<h3>Docker run command line</h3>
<form action="/src/gen.php">
    <input type="hidden" name="cmd" value="run"/>
    docker run <input name="id" type="input" value="<?php echo $image;?>"/>
    <p/>
    <input type="submit" value="Run!"/>
</form>

<?php

require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";

$runner = new base\Runner("docker run --help", False);
$json = $runner->run();

$tbl = new base\FmtTable(array(
    "docker run --help" => "docker run --help"
));
echo $tbl->format_row_raw($json);

?>
