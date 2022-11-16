<?php

require_once __DIR__ . "/base/fmttable.php";

function show_hdr($idx) {
    $tbl = array(
        "Containers" => "/src/containers.php",
        "Images" => "/src/images.php",
        "Pull/Search" => "/src/reg.php",
        "Versions/EngineInfo" => "/src/version.php"
    );
    $hdr = new base\FmtHeader($tbl);
    echo $hdr->format($idx);
}

function use_docker_api() {
    return false;
}