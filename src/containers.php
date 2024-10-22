<?php require_once __DIR__ . "/base/nocache.php"; ?>
<?php include( __DIR__ . "/static-files/css.css"); ?>
<script>
<?php include( __DIR__ . "/static-files/sorttable/sort-table.min.js"); ?>

function showTab(id,title) {
    window.open(encodeURI('/attach.php?id=' + id + '&title=' + title),'_blank');
}

</script>
<body>
<?php
require_once __DIR__ . "/hdr.php";

show_hdr(0);

$show_all = $_GET['all'] ?? "true";

$show_all_checked = "";
if ($show_all=="true") {
    $show_all = true;
    $show_all_checked = 'checked';
} else {
    $show_all = false;
}

?>
<script>

    function onShowAll() {
        let on = document.getElementById('show_all').checked;
        let urlParams = "?all=" + on;
        let location = (window.location.href.split('?')[0]) + urlParams;
        window.location.href = location;

    }
</script>

[ <input type="checkbox" id="show_all" <?php echo $show_all_checked; ?> onchange="onShowAll()"> <label for="show_all">Show All Container</label> ] &nbsp; [ <a href="/gen.php?cmd=cprune&id=a">Remove/Prune Unused Containers</a> ]

<h3>Containers</h3>
Command: <code>docker ps <?php if ($show_all) { echo "-a"; } ?></code>

<?php

require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";

function make_cnt_inspect_link($id, $title) {
    return "<a title='inspect container' href='/gen.php?cmd=inspectc&id={$id}'>{$title}</a>";
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
        $diffs = "<a href='/containerDiff.php?id={$id}'>/diffs/</a>";
        $title = "";
        if ($json["Names"] != "") {
            $val = join(' ', $json['Names']);
            $title .= "Container {$val} ";
        }
        if ($json["Image"] != "") {
            $title .= "Image {$json['Image']}";
        }

        $urlTitle = urlencode($title);
        $ps = "{$ps}&nbsp;{$diffs}<br/><a href='/attach.php?id={$id}&title={$urlTitle}'><b>/Console/</b>&nbsp;<a onclick=\"showTab('{$id}','{$title}');\"><b>/New Tab/</b></a></a>";
    }

    $inspect = make_cnt_inspect_link($id, $id);
    return "{$inspect}<br/>{$ps}";
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
    return $json["State"] . " - " . $json["Status"] . "<br/>" . $links;
}


function make_api_names($row_val, $json) : string {
    $names = $json["Names"];
    $ret = "";

    foreach($names as $k => $v) {
        $link = make_cnt_inspect_link($json['Id'] , $v);
        if ($ret != "") {
            $ret = " , ";
        }
        $ret = $ret . $link;
    }
    return $ret;
}

function make_api_created_at($row_val, $json) : string {
    return date('Y-m-d H:i:s' ,$row_val);
}

function make_size($row_val, $json) : string {

    $ret = base\human_readable_size($json["SizeRootFs"] ?? 0) . " / " . base\human_readable_size($json["SizeRw"] ?? 0);

    return $ret;
}

function make_api_networks($row_val, $json) : string {
    $obj = $json['NetworkSettings']['Networks'] ?? null;

    $ret = "";
    foreach($obj as $key => $value) {
        $ret = $ret . $key . " ";
    }

    foreach($json["Ports"] as $portDef) {
       $ip = $portDef['IP'] ?? "";
       $to = $portDef['PrivatePort'] ?? "";
       $from = $portDef['PublicPort'] ?? "";
       $ty = $portDef['Type'] ?? "";
       $ret = $ret . "{$ip}:{$from}->{$to}/{$ty} ";
    }
    return $ret;
}

function make_api_mounts($row_val, $json) : string {

    $ret = "";
    $mounts = $json['Mounts'];


    foreach($mounts as $key => $value) {
        $src = $value['Source'] ?? "";

        // some magic incantations...
        if ($src == "/run/host-services/docker.proxy.sock") {
            $src= "/var/run/docker.sock";
        }
        $dst = $value['Destination'] ?? "";
        $ret = $ret . " " . $src . " -> " . $dst;
    }
    return $ret;
}

function make_api_image($row_val, $json) : string {

    $hash = $row_val;
    if (str_starts_with($row_val, "sha256:")) {
        $hash = substr($row_val, strlen("sha256:"), 12);
    }

    $link = "";

    $runner = new DockerRest\DockerEngineApi();
    list ($status, $body) = $runner->inspectImage($hash);
    if ($status) {
        $arr = json_decode($body, JSON_OBJECT_AS_ARRAY);
        if ($arr != null) {
            $tags = $arr['RepoTags'] ?? null;
            if ($tags != null) {
                $tagNames = join(",", $tags);
                $link = "<a title='inspect image' href='/gen.php?cmd=inspecti&id={$hash}'>{$tagNames}</a>";
            }
        }
    }

    if ($link == "") {
        $link = "<a title='inspect image' href='/gen.php?cmd=inspecti&id={$hash}'>{$hash}</a>";
    }


    $runner->close();

    return $link;
}



$runner = new DockerRest\DockerEngineApi();
list($ok, $jsonRaw) = $runner->containersList($show_all);

$tbl = new base\FmtTable(array(
    "Id" => array("ID", __NAMESPACE__ . "\\make_api_id"),
    "State" => array("State", __NAMESPACE__ . "\\make_api_status" ),
    "Names" => array("Names", __NAMESPACE__ . "\\make_api_names" ),
    "Created" => array("Created At", __NAMESPACE__ . "\\make_api_created_at"),
    "Image" => array("Image", "make_api_image"),
    "Command" => "Command",
    "NetworkSettings" => array("NetworkSettings", __NAMESPACE__ . "\\make_api_networks"),
    "SizeRootFs/ReadWrite" => array("SizeRootFs",  __NAMESPACE__ . "\\make_size"),
    "Mounts" => array("Mounts",  __NAMESPACE__ . "\\make_api_mounts"),
));
$json = json_decode($jsonRaw, JSON_OBJECT_AS_ARRAY);
echo $tbl->format($json);
