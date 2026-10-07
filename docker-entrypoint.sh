#!/bin/sh
set -e

echo "[StudentFlow] initializing MariaDB"
mkdir -p /run/mysqld
chown -R mysql:mysql /run/mysqld

if [ ! -d /var/lib/mysql/mysql ]; then
    echo "[StudentFlow] creating fresh MariaDB data directory"
    if command -v mariadb-install-db >/dev/null 2>&1; then
        mariadb-install-db --user=mysql --datadir=/var/lib/mysql --auth-root-authentication-method=normal >/dev/null
    else
        mysql_install_db --user=mysql --datadir=/var/lib/mysql >/dev/null
    fi
fi

mysqld --user=mysql --datadir=/var/lib/mysql --socket=/run/mysqld/mysqld.sock &

MYSQL_PID=$!

echo "[StudentFlow] waiting for MariaDB"
i=0
until mysqladmin --user=root ping >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -gt 45 ]; then
        echo "[StudentFlow] MariaDB failed to start"
        exit 1
    fi
    sleep 1
done

if ! mysql --user=root -e 'USE `studentflow`' >/dev/null 2>&1; then
    echo "[StudentFlow] importing schema"
    mysql --user=root < /var/www/html/database/studentflow.sql
    mysql --user=root < /var/www/html/database/github_migration.sql
    mysql --user=root < /var/www/html/database/features_migration.sql
    if [ -f /var/www/html/database/demo_data.sql ]; then
        mysql --user=root < /var/www/html/database/demo_data.sql
    fi
else
    echo "[StudentFlow] schema already present, skipping import"
fi

echo "[StudentFlow] creating app database user"
mysql --user=root <<'SQL'
CREATE USER IF NOT EXISTS 'studentflow'@'%' IDENTIFIED BY 'studentflow';
GRANT ALL PRIVILEGES ON `studentflow`.* TO 'studentflow'@'%';
CREATE USER IF NOT EXISTS 'studentflow'@'localhost' IDENTIFIED BY 'studentflow';
GRANT ALL PRIVILEGES ON `studentflow`.* TO 'studentflow'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "[StudentFlow] starting Apache"
exec "$@"