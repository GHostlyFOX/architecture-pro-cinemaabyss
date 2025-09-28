FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y libfcgi-bin

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY index.php .

# Expose port 9000 for PHP-FPM
EXPOSE 9000
