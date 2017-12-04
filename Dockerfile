FROM php:7.1-apache

MAINTAINER Bruno Honda <bruno.honda@live.com>

VOLUME /var/www/html/
COPY ./hesk275/ /var/www/html/