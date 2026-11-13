FROM php:8.2-apache

# Install MySQL & PostgreSQL extensions so your PHP database connection works
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql pdo_pgsql

# Enable Apache URL rewriting
RUN a2enmod rewrite

# Copy your project files into the web server root
COPY . /var/www/html/

# Allow uploaded images/files in upload.php to save properly
RUN chown -R www-data:www-data /var/www/html/
