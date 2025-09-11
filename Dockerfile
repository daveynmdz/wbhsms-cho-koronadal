# Use official PHP image with Apache
FROM php:8.3-apache

# Enable Apache mod_rewrite (recommended for PHP apps)
RUN a2enmod rewrite

# Copy all files from your repo to Apache's web root
COPY . /var/www/html/

# Set working directory to Apache's web root
WORKDIR /var/www/html

# Give proper permissions (optional, helps with uploads)
RUN chown -R www-data:www-data /var/www/html

# Install PHP extensions if you need them (example: mysqli, pdo, gd, etc.)
RUN docker-php-ext-install mysqli

# Expose port 80 for web traffic
EXPOSE 80

# Install additional PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql