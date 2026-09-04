FROM php:8.2-apache

# Install MySQL extension for PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite (if your app uses rewrite rules)
RUN a2enmod rewrite

# Copy project files into the web root
COPY . /var/www/html/

# Expose HTTP port
EXPOSE 80