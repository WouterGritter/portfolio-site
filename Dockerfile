# Use the trafex/php-nginx base image
FROM trafex/php-nginx:latest

# Copy over the default nginx config file
COPY ./default.conf /etc/nginx/conf.d/default.conf

# Set working directory
WORKDIR /var/www/html

# Remove template files
RUN rm *

# Copy application source code to the container
COPY ./www/ .

# Command to start Nginx and PHP-FPM
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
