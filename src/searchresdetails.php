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

$term = $_GET['arg'] ?? "";
$page = $_GET['page'] ?? "1";

$term_decoded = urldecode($term);

$pos = strpos($term, "/");
if ($pos === false) {
    $url = "https://registry.hub.docker.com/v2/repositories/library/{$term}/tags/?page={$page}";
} else {
    $url = "https://registry.hub.docker.com/v2/repositories/{$term}/tags/?page={$page}";
}
$data = file_get_contents($url);

$page_int = intval($page);
if ($page_int > 1) {
    $prev_prev = $page_int - 1;
    echo "<a href='/searchresdetails.php?arg={$term}&page={$prev_prev}'>&lt;&lt;&lt;</a>&nbsp;&nbsp;&nbsp;";
}

echo "Page {$page_int}&nbsp;";

$arr = json_decode($data, true);
$next = $arr['next'] ?? "";

if ($next != "") {
    $next_int = $page_int + 1;
    echo "<a href='/searchresdetails.php?arg={$term}&page={$next_int}'>&gt;&gt;&gt;</a><br/>";
}

?>
<h3>Search result details on tags</h3>

for artifact <b><?php echo $term_decoded; ?></b>

<?php
require_once __DIR__ . "/base/runner.php";
require_once __DIR__ . "/base/fmttable.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__  . "/DockerRest/DockerRest.php";


function make_name($row_val, $json) : string {
    return "<a href='/searchresdetails.php' title='search result details'>{$row_val}</a>";
}

function make_digest($row_val, $json) : string {
    if (str_starts_with($row_val, "sha256:")) {
        return substr($row_val, strlen("sha256:"), 12);
    }
    return $row_val ?? "";
}

function make_os($row_val, $json) : string {
    $os = $json["os"] ?? "";
    $os_features = $json["os_features"] ?? "";
    $os_version = $json["os_version"] ?? "";

    return "${os} {$os_version} {$os_features}";
}

function make_size($row_val, $json) : string {
    return base\human_readable_size($row_val);
}

function make_architecture($row_val, $json) : string {
    $arch = $json['architecture'];
    $var = $json['variant'];
    return "{$arch} {$var}";
}


$all_results=array();




$results = $arr['results'] ?? null;
if ($results != null) {
    foreach ($results as $entry) {
        $images = $entry['images'];
        foreach ($images as $sub_entry) {
            $copy_entry = unserialize(serialize($sub_entry));
            $copy_entry['tag'] = $entry['name'];
            array_push($all_results, $copy_entry);
        }
    }
}


$tmp = json_encode($all_results, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

$tbl = new base\FmtTable(array(
    "tag" => "tag",
    "digest" => array("digest", "make_digest"),
    "os" => array("os", "make_os"),
    "architecture" => array("architecture", "make_architecture"),
    "size" => array("size", "make_size"),
    "status" => "status",
    "last_pushed" => "last_pushed"
));

echo $tbl->format($all_results);
