#!/bin/bash
set -e

# Wait for database
echo "Waiting for database..."
/usr/local/bin/wait-for-it.sh db:3306 --timeout=30 --strict -- echo "Database ready."

# Run setup.php if present, then delete
if [ -f /var/www/html/setup.php ]; then
    echo "Running setup.php..."
    php /var/www/html/setup.php && rm -f /var/www/html/setup.php
    echo "setup.php executed succesfully. File deleted."
else
    echo "setup.php not found, skipping."
fi

# Run Nginx and PHP-FPM
echo "Starting Nginx and PHP-FPM..."
service nginx start
php-fpm
