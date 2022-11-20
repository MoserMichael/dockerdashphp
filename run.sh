#!/usr/bin/env bash

set -x

PORT_PHP="${PORT_PHP:=8001}"
PORT_WSS="${PORT_WSS:=8002}"

if [[ ${DOCKER_API_VERSION} == "" ]]; then
    DOCKER_API_VERSION=$(docker version --format='{{json .Client.APIVersion}}') 
    export DOCKER_API_VERSION="v${DOCK0ER_API_VERSION//\"/}"
fi

if [[ $DOCKER_API_VERSION == "" ]]; then
    echo "Error: Can't get api version, is docker daemon installed and running?"
    exit 1
fi
echo "version: ${DOCKER_API_VERSION"}"

PHP_CLI_SERVER_WORKERS=10 php -S "0.0.0.0:${PORT_PHP}" -t src &
PID_PHP=$!

php wssrv.php "${PORT_WSS}" &
PID_WSOCK=$!

trap ctrl_c INT EXIT

function ctrl_c() {
   echo "Ctrl+C ... killing processes." 
   kill ${PID_WSOCK} ${PID_PHP}
}
wait


