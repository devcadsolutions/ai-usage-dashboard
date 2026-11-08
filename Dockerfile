FROM php:8.3-apache
RUN a2enmod rewrite headers
COPY . /var/www/html/
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data && chmod 700 /var/www/html/data
EXPOSE 80
