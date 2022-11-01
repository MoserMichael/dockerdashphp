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
[ <a href="/src/gen.php?cmd=cprune&id=a">Remove Unused Containers</a> ]

<h3>Containers</h3>
Command: <code>docker ps -a</code>

<?php

require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";


function make_inspect_link($row_val, $json) : string {

    $ps="";
    if ($json["State"]=="running") {
        $ps="&nbsp; <a href='gen.php?cmd=top&id={$row_val}'>/top/</a> &nbsp; <a href='/src/gen.php?cmd=stats&id={$row_val}'>/stats/</a>";
    }
    return "<a title='inspect' href='/src/gen.php?cmd=inspectc&id={$row_val}'>{$row_val}</a>&nbsp; <a href='/src/logs.php?id={$row_val}&since=10m'>/logs/</a>{$ps}";
}

function make_state_link($row_val, $json) : string {
    $id = $json["ID"];
    $links = "";
    if ($row_val == "running") {
        $links = "<a href='/src/attach.php?id={$id}'>/Attach/</a>";
        $links = "{$links}&nbsp;<a href='/src/gen.php?cmd=pause&id={$id}'>/Pause/</a>";
        $links = "{$links}&nbsp; <a href='/src/gen.php?cmd=stop&id={$id}'>/Stop/</a>";
    }
    if ($row_val == "paused") {
        $links = "&nbsp;<a href='/src/gen.php?cmd=resume&id={$id}'>/Resume/</a>";
    }

    return $row_val . $links;
}


$runner = new base\Runner("docker ps -a --format='{{json .}}'");
$json = $runner->run();
$tbl = new base\FmtTable(array(
    "ID" => array("ID",  __NAMESPACE__ . "\\make_inspect_link"),
    "Names" => "Names",
    "Image" => "Image",
    "Labels" => "Labels",
    "State" => array("State", __NAMESPACE__ . "\\make_state_link"),
    "Status" => "Status",
    "Created At" => "CreatedAt",
    "Running For" => "RunningFor",
    "Command" => "Command",
    "Local Volumes" => "LocalVolumes",
    "Mounted Volumes" => "Mounts",
    "Disk Size" => "Size",
    "Attached Networks" => "Networks",
    "Exposed Ports" => "Ports",
));

echo $tbl->format($json);