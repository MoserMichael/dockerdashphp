FROM php:8.2-apache

WORKDIR /

# get php composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === 'e21205b207c3ff031906575712edab6f13eb0b361f2085f1f1237b7126d785e826a450292b6cfd1d64d92e6563bbde02') { echo 'Installer verified'; } else { echo 'Installer corrupt see https://getcomposer.org/download/ for instruction (hash probably changed)'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"


#RUN touch /var/run/docker.sock 
RUN chown -R www-data:www-data /var/run


# get git
RUN apt-get update
RUN apt-get install git zip curl jq -y

COPY build/run-apache.sh /run-apache.sh
COPY composer.json /var/www
ADD  src /var/www/html
ADD  wss-src /var/www/wss-src
COPY build  /var/www/build

WORKDIR /var/www

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN php /composer.phar update
RUN php /composer.phar install

RUN ./build/make-shells.sh

#enable mod_ssl
RUN a2enmod ssl
#enable mod_proxy
RUN a2enmod proxy
#enable mod_proxy
RUN a2enmod proxy_http
#enable ssl tunnel to wss
RUN a2enmod proxy_wstunnel
#enable rewrite module
RUN a2enmod rewrite

COPY build/apache2.conf /etc/apache2/apache2.conf
COPY build/default-ssl.conf   /etc/apache2/sites-available/default-ssl.conf
COPY build/default-nossl.conf /etc/apache2/sites-available/default-nossl.conf

CMD /run-apache.sh
