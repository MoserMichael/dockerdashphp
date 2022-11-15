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
[ <a href="/src/gen.php?cmd=iprune&id=a">Remove Unused Images</a> ]
<h3>Images</h3>
Command: <code>docker image ls -a</code>

<?php
require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";

function make_inspect_link($row_val,$json) : string {

    $image = $json["ID"];

    $rmi="<a href='/src/gen.php?cmd=rmi&id={$row_val}'>/remove image/</a>";
    $run="<a href='/src/run.php?ID={$image}'>/run/</a>";

    return "<a title='inspect' href='/src/gen.php?cmd=inspecti&id={$row_val}'>{$row_val}</a>&nbsp;{$rmi}&nbsp;{$run}";
}

$runner = new base\Runner("docker image ls -a --format='{{json .}}'");
$json = $runner->run();
$tbl = new base\FmtTable(array(
    "ID" => array("ID",  __NAMESPACE__ . "\\make_inspect_link"),
    "Repository" => "Repository",
    "Tag" => "Tag",
    "Digest" => "Digest",
    "Containers" => "Containers",
    "Created Since" => "CreatedSince",
    "Size" => "Size",
    "Shared Size" => "SharedSize",
    "Unique Size" => "UniqueSize",
    "Virtual Size" => "VirtualSize"
 ));

echo $tbl->format($json);
