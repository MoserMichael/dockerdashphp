#!/bin/bash

set -x

# from https://stackoverflow.com/questions/360201/how-do-i-kill-background-processes-jobs-when-my-shell-script-exits
# without cleanup up the mess: the container will remain, if stopped - despite running docker --rm !!!
#trap "exit" INT TERM
#trap "kill 0" EXIT

export PORT_WSS="${PORT_WSS:=8002}"
export TRACE="${TRACE}"

# account for php needs to acccess the pipe for communication with docker
chown www-data:www-data /var/run/docker.sock

if [[ -f /mnt/cwd/pw.txt ]]; then
    mv /mnt/cwd/pw.txt /pw.txt
    rm -f /mnt/cwd/pw.txt
fi

if [[ "$MODE" == "self-signed" ]]; then 

  CERT="/etc/ssl/certs/ssl-cert-snakeoil.pem"
  KEY="/etc/ssl/private/ssl-cert-snakeoil.key" 

  openssl req -new -x509 -days 256 -nodes -newkey rsa:4096 -out $CERT -keyout $KEY  -subj '/CN='"${HOST}"'/O='"${HOST}"'/C=US/OU=dockerphp'

  ln -s /etc/apache2/sites-available/default-ssl.conf /etc/apache2/sites-enabled/.
else
  ln -s /etc/apache2/sites-available/default-nossl.conf /etc/apache2/sites-enabled/.
fi
rm -f /etc/apache2/sites-enabled/000-default.conf

export APP_ROOT=/var/www/html

bash -c 'while [ true ]; do php /var/www/wss-src/wssrv.php "${PORT_WSS}"; done' &

WSS_PID=$!

trap "trap - SIGTERM; kill -9 ${WSS_PID}; apachectl -k stop || true; kill -9 0; exit 0" SIGINT SIGTERM EXIT


apache2-foreground
