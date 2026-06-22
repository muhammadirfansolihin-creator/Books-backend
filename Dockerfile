FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql && a2enmod headers
COPY . /var/www/html
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf