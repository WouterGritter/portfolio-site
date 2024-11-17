# Use the trafex/php-nginx base image
FROM trafex/php-nginx:latest

# Set working directory
WORKDIR /var/www/html

# Remove template files
RUN rm *

# Copy application source code to the container
COPY ./www/ .

# Command to start Nginx and PHP-FPM
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
