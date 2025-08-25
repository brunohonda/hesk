FROM php:apache
LABEL maintainer="Bruno Honda <bruno.honda@live.com>"

RUN apt-get update && docker-php-ext-install mysqli

VOLUME /var/www/html/

COPY ./php.custom.ini /usr/local/etc/php/conf.d/php.custom.ini
COPY ./hesk/ /var/www/html/

RUN chmod 666 hesk_settings.inc.php
RUN chmod 777 attachments/ cache/