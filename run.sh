#!/usr/bin/env bash

set -x

PORT_PHP="${PORT_PHP:=8001}"
PORT_WSS="${PORT_WSS:=8002}"

PHP_CLI_SERVER_WORKERS=10 php -S "0.0.0.0:${PORT_PHP}" &
PID_PHP=$!

php wssrv.php "${PORT_WSS}"  &
PID_WSOCK=$!

trap ctrl_c INT EXIT

function ctrl_c() {
   echo "Ctrl+C ... killing processes." 
   kill ${PID_WSOCK} ${PID_PHP}
}
wait


