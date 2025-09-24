FROM php:8.2-fpm-alpine

# Install curl extension for PHP
RUN apk --no-cache add curl-dev \
    && docker-php-ext-install curl

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY index.php .

# Expose port 9000 for PHP-FPM
EXPOSE 9000
