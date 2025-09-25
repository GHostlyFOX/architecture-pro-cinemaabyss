FROM php:8.2-fpm

RUN sed -i 's/9000/9001/g' /usr/local/etc/php-fpm.d/www.conf
RUN sed -i 's/9000/9001/g' /usr/local/etc/php-fpm.d/zz-docker.conf

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY index.php .

# Expose port 9000 for PHP-FPM
EXPOSE 9001
