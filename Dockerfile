 FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libssl-dev \
    pkg-config \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

RUN a2enmod rewrite

RUN echo "output_buffering = On" >> /usr/local/etc/php/php.ini

WORKDIR /var/www/html

COPY . .

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

EXPOSE 80 