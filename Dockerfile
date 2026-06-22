FROM php:8.2-apache
# REMOVED "headers" from a2enmod to stop Apache from overriding or duplicating PHP's headers
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite
COPY . /var/www/html
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf
RUN echo "<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" >> /etc/apache2/sites-available/000-default.conf