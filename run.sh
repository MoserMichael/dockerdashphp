#!/usr/bin/env bash

set -x

PHP_CLI_SERVER_WORKERS=10 php -S 0.0.0.0:8001 &
PID_PHP=$!
php wssrv.php 8002  &
PID_WSOCK=$!

trap ctrl_c INT

function ctrl_c() {
   echo "Ctrl+C ... killing processes." 
   kill ${PID_WSOCK} ${PID_PHP}
}
wait


