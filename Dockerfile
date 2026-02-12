# Use the official PHP image with Apache
FROM php:8.2-apache

# Copy your local project files into the default web root in the container
COPY . /var/www/html/

# (Optional) Install any PHP extensions you might need (e.g., mysqli for databases)
# If you don't use a database, you can remove the next line.
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite (useful if you have an .htaccess file)
RUN a2enmod rewrite

# Tell Render that this container listens on port 80
EXPOSE 80