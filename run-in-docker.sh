#!/bin/bash

PORT="8000"
NEXT_PORT="8001"
HOST=0.0.0.0
IMAGE_LOCATION=ghcr.io/mosermichael/phpdocker-mm:latest 

Help() {
cat <<EOF

Start docker-web in docker

$0 -r [-p <port>] [-i <host>] [-d <dir>] [-v] [-c <image>]

Stop docker-web in docker

Run docker-web web server in a docker; by default the docker image is fetched from a public repository. ($IMAGE_LOCATION)

Start the web server for s9k

-r          - start the web server
-p  <port>  - listening port (default ${PORT})

Stop the web server for s9k

-s          - stop the web server

Common options:

-c  <image> - override the container image location (default ${IMAGE_LOCATION})
-v          - run verbosely

EOF

exit 1
}

SSL="off"

while getopts "hrsv:p:c:" opt; do
  case ${opt} in
    h)
        Help
        ;;
    r)
        ACTION="start"
        ;;
    s)
        ACTION="stop"
        ;;
    p)
        PORT="$OPTARG"
        NEXT_PORT=$((PORT+1))
        ;;
    c)
        IMAGE_LOCATION="$OPTARG"
        ;;
    v)
        set -x
        export PS4='+(${BASH_SOURCE}:${LINENO}) '
        ;; 
    *)
        Help "Invalid option"
        ;;
   esac
done

if [[ $ACTION == 'start' ]]; then
  
    DOCKER_API_VERSION=$(docker version --format='{{json .Client.APIVersion}}')

    if [[ $DOCKER_API_VERSION == "" ]]; then
        echo "Error: Can't get api version, is docker daemon installed and running?"
        exit 1
    fi

    docker run --rm --name docker-web -v /var/run/docker.sock:/var/run/docker.sock -p $PORT:$PORT -p $NEXT_PORT:$NEXT_PORT -e DOCKER_API_VERSION=${DOCKER_API_VERSION} -e PORT_PHP=${PORT} -e PORT_WSS=${NEXT_PORT} -dt ${IMAGE_LOCATION} 
    echo "Listen on http://${HOST}:${PORT}/src/images.php"
else 
  if [[ $ACTION == 'stop' ]]; then
    DOCKER_ID=$(docker ps | grep ${IMAGE_LOCATION}[[:space:]] | awk '{ print $1 }')
    docker stop $DOCKER_ID
  else
    Help 'must use either to start the server -r or to stop it -s'
  fi
fi

