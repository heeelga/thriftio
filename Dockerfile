# Base image with PHP and FPM
FROM php:8.2-fpm

# Install system packages and PHP extensions
RUN apt-get update && apt-get install -y \
    nginx \
    mariadb-client \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    netcat-openbsd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy application
WORKDIR /var/www/html
COPY . /var/www/html

# Copy Nginx configuration
COPY default.conf /etc/nginx/sites-available/default

# Configure Nginx
RUN mkdir -p /etc/nginx/sites-enabled && \
    [ ! -L /etc/nginx/sites-enabled/default ] && \
    ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default || true

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Create required directory for backups
RUN mkdir -p /var/backups/finance && \
    chown -R www-data:www-data /var/backups/finance && \
    chmod -R 755 /var/backups/finance

# Expose port 80 for HTTP
EXPOSE 80

# Copy wait script
COPY wait-for-it.sh /usr/local/bin/wait-for-it.sh
RUN chmod +x /usr/local/bin/wait-for-it.sh

# Add start script
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Set sessions folder rights
RUN mkdir -p /var/lib/php/sessions
RUN chown -R www-data:www-data /var/lib/php/sessions

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/start.sh"]
