#!/usr/bin/env bash
# senaEats offline setup / repair script. Safe to run many times.
# Usage:  ./setup.sh            (set up, then start the app)
#         SE_DB_PASS=secret ./setup.sh   (use your own database password)
# If it says "permission denied":  chmod +x setup.sh   or run:  bash setup.sh
set -e
cd "$(dirname "$0")"
DB=senaeats; DBUSER=senaeats
export SE_DB_PASS="${SE_DB_PASS:-change_me}"

echo "== 1. Start the database service =="
sudo service mariadb start 2>/dev/null || sudo service mysql start 2>/dev/null || true

echo "== 2. Create the database =="
sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

has_table() { sudo mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB' AND table_name='$1';"; }

echo "== 3. Create tables (only if missing) =="
if [ "$(has_table users)" = "0" ]; then sudo mysql "$DB" < schema.sql && echo "schema.sql applied"; else echo "schema already applied, skipping"; fi
if [ "$(has_table gallery_photos)" = "0" ]; then sudo mysql "$DB" < migration_v2.sql && echo "migration_v2.sql applied"; else echo "v2 migration already applied, skipping"; fi

echo "== 4. Create the app's database user =="
sudo mysql -e "
CREATE USER IF NOT EXISTS '$DBUSER'@'localhost' IDENTIFIED BY '$SE_DB_PASS';
ALTER USER '$DBUSER'@'localhost' IDENTIFIED BY '$SE_DB_PASS';
CREATE USER IF NOT EXISTS '$DBUSER'@'127.0.0.1' IDENTIFIED BY '$SE_DB_PASS';
ALTER USER '$DBUSER'@'127.0.0.1' IDENTIFIED BY '$SE_DB_PASS';
GRANT SELECT,INSERT,UPDATE,DELETE ON $DB.* TO '$DBUSER'@'localhost';
GRANT SELECT,INSERT,UPDATE,DELETE ON $DB.* TO '$DBUSER'@'127.0.0.1';
FLUSH PRIVILEGES;"

echo "== 5. Check the tables (expect 12) =="
sudo mysql "$DB" -e "SHOW TABLES;"

echo "== 6. Photo upload folder permissions =="
mkdir -p uploads
if getent group www-data >/dev/null; then sudo chown "$USER":www-data uploads; fi
chmod 775 uploads

echo "== 7. Demo accounts (password: senaeats123) =="
php seed.php

echo
echo "All set. Starting the app at http://localhost:8000  (Ctrl+C to stop)"
echo "Logins: customer@ / vendor@ / courier@ / admin@ senaeats.test"
exec php -S localhost:8000
