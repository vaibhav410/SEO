#!/bin/bash
# Container start-up: MariaDB -> app config -> schema + demo data -> admin password -> Apache.
set -euo pipefail
cd /var/www/html

PORT="${PORT:-10000}"
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"
export DB_PASS="${DB_PASS:-$(head -c 32 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24)}"
export APP_SECRET="${APP_SECRET:-$(head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 40)}"

# Apache listens on the port the platform assigns.
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# MariaDB, local to the container and reachable only on 127.0.0.1. Tuned for a 512 MB instance.
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql > /dev/null
fi
mariadbd --user=mysql --datadir=/var/lib/mysql --bind-address=127.0.0.1 --port=3306 \
    --innodb-buffer-pool-size=32M --performance-schema=OFF --max-connections=40 --skip-name-resolve &
for _ in $(seq 1 60); do mariadb-admin ping --silent 2> /dev/null && break; sleep 1; done

# Least-privilege application user (only this database).
mariadb -uroot <<SQL
CREATE DATABASE IF NOT EXISTS syscom_growthhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'growthhub'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER 'growthhub'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON syscom_growthhub.* TO 'growthhub'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# App configuration from the environment (debug off, proxy-aware client IPs).
php -r '
$c = [
    "app" => ["base_url" => getenv("APP_URL"), "debug" => false, "secret" => getenv("APP_SECRET"), "trust_proxy" => true],
    "db" => ["host" => "127.0.0.1", "port" => 3306, "name" => "syscom_growthhub", "user" => "growthhub", "pass" => getenv("DB_PASS")],
    "audit" => ["allow_self" => false],
    "ai" => ["api_key" => (string) getenv("OPENROUTER_API_KEY")],
];
file_put_contents("config/config.local.php", "<?php\n// Generated at container start. Do not commit.\nreturn " . var_export($c, true) . ";\n");
'
chown root:www-data config/config.local.php && chmod 640 config/config.local.php

php database/install.php > /dev/null
if [ -n "${ADMIN_PASSWORD:-}" ]; then
    php database/create-admin.php "${ADMIN_NAME:-Site Admin}" "${ADMIN_EMAIL:-admin@syscom.local}" "${ADMIN_PASSWORD}" admin > /dev/null
fi
echo "GrowthHub ready at ${APP_URL} (port ${PORT})"

exec apache2-foreground
