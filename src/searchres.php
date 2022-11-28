
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

$term = $_GET['id'];
?>
<h3>Search Result</h3>
Command: <code>docker search <?php echo $term; ?></code>

<?php
require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";


if (use_docker_api()) {

    $runner = new DockerRest\DockerEngineApi();
    list ($ok,$jsonRaw) = $runner->imageSearch($term);

    $tbl = new base\FmtTable(array(

        "name" => "name",
        "is_official" => "is_official",
        "star_count" => "star_count",
        "is_automated" => "is_automated",
        "description" => "description"
    ));

    $json = json_decode($jsonRaw, JSON_OBJECT_AS_ARRAY);

    echo $tbl->format($json);
}
