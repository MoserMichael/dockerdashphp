#!/bin/bash

set -ex

PORT_PHP="${PORT_PHP:=8001}"
PORT_WSS="${PORT_WSS:=8002}"
export TRACE="${TRACE}"

chown www-data:www-data /var/run/docker.sock

export APP_ROOT=/var/www/html
php /var/www/wss-src/wssrv.php "${PORT_WSS}" &
apache2-foreground
