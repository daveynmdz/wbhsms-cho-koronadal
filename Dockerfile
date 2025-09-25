# Use official PHP image with Apache
FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite (if you use .htaccess rewrites)
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better Docker layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy all files from your repo to Apache's web root
COPY . /var/www/html/

# Make index.php the default (in addition to index.html)
RUN sed -ri 's/DirectoryIndex .*$/DirectoryIndex index.php index.html/' /etc/apache2/mods-enabled/dir.conf

# Allow .htaccess overrides if you rely on them
RUN printf '<Directory /var/www/html>\n  AllowOverride All\n  Require all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/z-override.conf \
 && a2enconf z-override

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
