# Use official PHP image with Apache
FROM php:8.3-apache

# Enable Apache mod_rewrite (if you use .htaccess rewrites)
RUN a2enmod rewrite

# Copy all files from your repo to Apache's web root
COPY . /var/www/html/

# Make index.php the default (in addition to index.html)
RUN sed -ri 's/DirectoryIndex .*$/DirectoryIndex index.php index.html/' /etc/apache2/mods-enabled/dir.conf

# Allow .htaccess overrides if you rely on them
RUN printf '<Directory /var/www/html>\n  AllowOverride All\n  Require all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/z-override.conf \
 && a2enconf z-override

# Set working directory to Apache's web root
WORKDIR /var/www/html

# Permissions (optional)
RUN chown -R www-data:www-data /var/www/html

# PHP extensions you need
RUN docker-php-ext-install mysqli pdo pdo_mysql

EXPOSE 80
