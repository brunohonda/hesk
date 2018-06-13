FROM php:7.2-apache

MAINTAINER Bruno Honda <bruno.honda@live.com>

RUN apt-get update && docker-php-ext-install mysqli

VOLUME /var/www/html/

COPY ./php.custom.ini /usr/local/etc/php/conf.d/php.custom.ini
COPY ./hesk/ /var/www/html/

TAG brunoah/hesk
TAG brunoah/hesk:latest
TAG brunoah/hesk:2.8.1