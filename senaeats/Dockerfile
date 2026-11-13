FROM php:8.2-apache

# Install MySQL, PostgreSQL, and SQLite extensions
RUN apt-get update && apt-get install -y libpq-dev libsqlite3-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql pdo_pgsql pdo_sqlite

# Enable Apache URL rewriting
RUN a2enmod rewrite

# Copy your project files into the web server root
COPY . /var/www/html/

# Set working directory, make setup.sh executable, and run it
WORKDIR /var/www/html
RUN chmod +x setup.sh && ./setup.sh

# Fix file permissions for Apache
RUN chown -R www-data:www-data /var/www/html/
