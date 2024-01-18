<?php

$PWFILE="/pw.txt";

function checkAuthorization() {
    global $PWFILE;

    $txt = file_get_contents($PWFILE);
    $res="";
    $ln=strlen($txt);

    for($i=0; $i < $ln; $i+= 2) {
        $ch = substr($txt, $i, 2);
        $ch = chr( intval($ch) ^ 42 );
        $res .= $ch;
    }
    $cred = explode("\n", $res);


    return $cred[0] == $_SERVER['PHP_AUTH_USER'] && $cred[1] == $_SERVER['PHP_AUTH_PW'] ;
}


function sendAuthChallenge() {
    header('WWW-Authenticate: Basic realm="DockerDashPHP"');
    header("HTTP/1.1 401 Unauthorized");
    echo "Enter credentials";
    exit;

}

function checkSecurity() {
    global $PWFILE;

    if (file_exists($PWFILE)) {
        session_start();

        if (!isset($_SESSION['auth'])) {
            $headers = getallheaders();
            if (array_key_exists('Authorization', $headers)) {
                if (checkAuthorization()) {
                    $_SESSION['auth'] = 1;
                } else {
                    print("why?");
                    sendAuthChallenge();
                }

            } else {
                sendAuthChallenge();
            }
        } 
    }
}

checkSecurity();


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/html">
    <head>
        <meta charset="utf-8">
        <title>Docker Dashboard</title>
    </head>

