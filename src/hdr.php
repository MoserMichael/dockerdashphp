<?php require_once __DIR__ . "/base/fmttable.php";

function show_hdr($idx) {
    $tbl = array(
        "Containers" => "/containers.php",
        "Images" => "/images.php",
        "Pull/Search" => "/reg.php",
        "Versions/EngineInfo" => "/version.php"
    );
    $hdr = new base\FmtHeader($tbl);
    echo $hdr->format($idx);
}

function use_docker_api() {
    return true ;
}
