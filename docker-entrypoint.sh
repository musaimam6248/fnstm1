#!/bin/sh
set -eu

APP_PORT="${PORT:-10000}"
sed -i "s/Listen 80/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -i "s|:80>|:${APP_PORT}>|" /etc/apache2/sites-enabled/000-default.conf

mkdir -p /var/data/uploads/staff /var/data/uploads/gallery /var/data/uploads/committee \
    /var/data/uploads/winners /var/data/uploads/videos /var/data/uploads/lectures \
    /var/data/uploads/reports /var/data/uploads/monthly_reports /var/data/backups \
    /var/www/html/database/sessions

php /var/www/html/database/migrate.php

chown -R www-data:www-data /var/data /var/www/html/database/sessions

exec apache2-foreground
