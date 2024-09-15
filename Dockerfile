FROM php:apache
LABEL maintainer="Bruno Honda <bruno.honda@live.com>"

RUN apt-get update && docker-php-ext-install mysqli

COPY ./php.custom.ini /usr/local/etc/php/conf.d/php.custom.ini
COPY ./hesk/ /var/www/html/

RUN chmod 666 /var/www/html/hesk_settings.inc.php
RUN chmod -R a=rwx /var/www/html/attachments
RUN chmod -R a=rwx /var/www/html/cache
RUN chown -R www-data:www-data /var/www/html