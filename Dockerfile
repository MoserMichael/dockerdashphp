FROM php:7.4-apache

WORKDIR /

# get php composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === '55ce33d7678c5a611085589f1f3ddf8b3c52d662cd01d4ba75c0ee0459970c2200a51f492d557530c71c15d8dba01eae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
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
