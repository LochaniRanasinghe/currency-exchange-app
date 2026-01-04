FROM php:8.2-fpm
# Install system dependencies
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev zip git unzip
# Install PHP extensions for MySQL and AWS
RUN docker-php-ext-install pdo_mysql gd
# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install