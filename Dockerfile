FROM php:8.2-apache

# Install MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable mod_rewrite
RUN a2enmod rewrite

# Copy project files directly into default Apache DocumentRoot
COPY . /var/www/html/

EXPOSE 80