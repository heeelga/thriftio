# Basis-Image mit PHP und FPM
FROM php:8.2-fpm

# Installiere Systempakete und PHP-Erweiterungen
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

# Anwendung kopieren
WORKDIR /var/www/html
COPY . /var/www/html

# Nginx-Konfiguration kopieren
COPY default.conf /etc/nginx/sites-available/default

# Nginx konfigurieren
RUN mkdir -p /etc/nginx/sites-enabled && \
    [ ! -L /etc/nginx/sites-enabled/default ] && \
    ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default || true

# Rechte setzen
RUN chown -R www-data:www-data /var/www/html

# Erstelle das benötigte Verzeichnis für Backups
RUN mkdir -p /var/backups/finance && \
    chown -R www-data:www-data /var/backups/finance && \
    chmod -R 755 /var/backups/finance

# Exponiere Port 80 für HTTP
EXPOSE 80

# Warte-Skript kopieren
COPY wait-for-it.sh /usr/local/bin/wait-for-it.sh
RUN chmod +x /usr/local/bin/wait-for-it.sh


# Start-Skript hinzufügen
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Set sessions folder rights
RUN mkdir -p /var/lib/php/sessions
RUN chown -R www-data:www-data /var/lib/php/sessions

# Set Entrypoint
ENTRYPOINT ["/usr/local/bin/start.sh"]
