FROM php:8.2-apache
RUN pecl install mongodb && docker-php-ext-enable mongodb
WORKDIR /var/www/html
COPY . .
EXPOSE 80
