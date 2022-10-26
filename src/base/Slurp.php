<?php namespace base;


// what jq -s does
function Slurp(string $json_string) : array {
    $arr = explode("\n", $json_string);
    $ret = "[" . rtrim(implode( ",", $arr), ",") . "]";
    return json_decode($ret, true);
}