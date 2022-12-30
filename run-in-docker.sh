#!/bin/bash

PORT="8000"
INTERNAL_PORT="80"
HOST=0.0.0.0
HOST_BIND=""
IMAGE_LOCATION=ghcr.io/mosermichael/phpdocker-mm:latest 
MODE=http
MODE_TITLE=http

Help() {
cat <<EOF

Start docker-web in docker

$0 -r [-p <port>]  [-t] [-v] [-d] [-c <image>] [ -b <bind address> ]

Stop docker-web in docker

Run docker-web web server in a docker; by default the docker image is fetched from a public repository. ($IMAGE_LOCATION)

Start the web server

-r          - start the web server
-p  <port>  - listening base port (default ${PORT} )
-t          - tls with self signed certificate
-b <addr>   - bind address (default ${HOST})
Stop the web server 

-s          - stop the web server

Common options:

-c  <image> - override the container image location (default ${IMAGE_LOCATION})
-v          - run verbosely
-d          - enable trace within container
EOF

exit 1
}

SSL="off"
TRACE=0

while getopts "hvdrstp:b:" opt; do
  case ${opt} in
    h)
        Help
        ;;
    r)
        ACTION="start"
        ;;
    b)
        HOST="$OPTARG"
        HOST_BIND="${OPTARG}:"
        ;;
    t)
        MODE=self-signed
        MODE_TITLE=https
        INTERNAL_PORT="443"
        ;;
    s)
        ACTION="stop"
        ;;
    p)
        PORT="$OPTARG"
        ;;
    v)
        set -x
        export PS4='+(${BASH_SOURCE}:${LINENO}) '
        ;;
    d)
        ((TRACE+=1))
        ;;
    *)
        Help "Invalid option"
        ;;
   esac
done

function assert_bins_in_path {
  if [[ -n $ZSH_VERSION ]]; then
    builtin whence -p "$1" &> /dev/null
  else  # bash:
    builtin type -P "$1" &> /dev/null
  fi
  if [[ $? != 0 ]]; then
    echo "Error: $1 is not in the current path"
    exit 1
  fi    
  if [[ $# -gt 1 ]]; then
    shift  # We've just checked the first one
    assert_bins_in_path "$@"
  fi
}


check_docker_engine_running() {
    assert_bins_in_path "docker"
    docker ps >/dev/null 2>&1
    if [[ $? != 0 ]]; then
        echo "Error: docker engine not running"
        exit 1
    fi
}

if [[ $ACTION == 'start' ]]; then

    check_docker_engine_running
 
    D="$(docker version --format='{{json .Client.APIVersion}}')"
 
    export DOCKER_API_VERSION="v${D//\"/}"

    docker run -v /var/run/docker.sock:/var/run/docker.sock --name docker-php-admin -p ${HOST_BIND}${PORT}:${INTERNAL_PORT} -e MODE="${MODE}" -e HOST="${HOST}" -e DOCKER_API_VERSION=${DOCKER_API_VERSION} -e PORT_PHP=${PORT} -e PORT_WSS=${NEXT_PORT} -e TRACE=${TRACE} --rm -dt ${IMAGE_LOCATION}
    if [[ $? == 0 ]]; then
        echo "Listen on ${MODE_TITLE}://${HOST}:${PORT}"
    fi
else 
  if [[ $ACTION == 'stop' ]]; then
    DOCKER_ID=$(docker ps | grep ${IMAGE_LOCATION}[[:space:]] | awk '{ print $1 }')
    if [[ ${DOCKER_ID} == "" ]]; then
        echo "Docker is already stopped"
        exit 1
    fi
    echo "stopping docker container: $DOCKER_ID ..."
    docker stop $DOCKER_ID
    docker rm $DOCKER_ID || true
  else
    Help 'must use either to start the server -r or to stop it -s'
  fi
fi

