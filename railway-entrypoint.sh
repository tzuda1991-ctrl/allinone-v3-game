#!/usr/bin/env bash
set -e

PORT_TO_USE="${PORT:-8080}"
sed -i "s/Listen 80/Listen ${PORT_TO_USE}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT_TO_USE}>/g" /etc/apache2/sites-available/000-default.conf
echo "ServerName localhost" >> /etc/apache2/apache2.conf

export DB_HOST="${MYSQLHOST:-${DB_HOST:-127.0.0.1}}"
export DB_PORT="${MYSQLPORT:-${DB_PORT:-3306}}"
export DB_USERNAME="${MYSQLUSER:-${DB_USERNAME:-root}}"
export DB_PASSWORD="${MYSQLPASSWORD:-${DB_PASSWORD:-}}"
export DB_NAME="${MYSQLDATABASE:-${DB_NAME:-railway}}"

if [ -f "/var/www/html/db/AllInOneDemoV3.sql" ]; then
  export MYSQL_PWD="${DB_PASSWORD}"
  for i in $(seq 1 20); do
    if mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" "${DB_NAME}" -e "SELECT 1" >/dev/null 2>&1; then
      TABLE_EXISTS=$(mysql -N -s -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" "${DB_NAME}" -e "SHOW TABLES LIKE 'tbl_admin';" 2>/dev/null || true)
      if [ -z "$TABLE_EXISTS" ]; then
        echo "Importing db/AllInOneDemoV3.sql into Railway MySQL..."
        { echo "SET SESSION sql_mode='';"; cat /var/www/html/db/AllInOneDemoV3.sql; } | mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" "${DB_NAME}" || true
        echo "SQL import finished."
      else
        echo "Database already initialized (tbl_admin found)."
      fi
      break
    fi
    echo "Waiting for MySQL... ($i/20)"
    sleep 2
  done
  unset MYSQL_PWD
fi

(
  while true; do
    php /var/www/html/index.php api cron all >/dev/null 2>&1 || true
    php /var/www/html/index.php api cron cron_draw >/dev/null 2>&1 || true
    php /var/www/html/index.php api cron cron_rummy_draw >/dev/null 2>&1 || true
    sleep 5
  done
) &

exec apache2-foreground
