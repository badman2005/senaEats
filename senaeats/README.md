# senaEats: setup on a LAMP stack (antiX / Debian)

1. Install: `sudo apt install apache2 mariadb-server php php-mysql php-mbstring libapache2-mod-php`
2. Copy this folder to `/var/www/html/senaeats` (or run `php -S localhost:8000` inside it for a quick test).
3. Create the database and a dedicated user:
   ```
   sudo mysql < schema.sql
   sudo mysql -e "CREATE USER 'senaeats'@'localhost' IDENTIFIED BY 'a-strong-password'; GRANT SELECT,INSERT,UPDATE,DELETE ON senaeats.* TO 'senaeats'@'localhost';"
   ```
4. Set credentials (Apache: `SetEnv SE_DB_PASS a-strong-password` in the vhost; or edit defaults in `db.php`).
5. Load demo data: `SE_DB_PASS=a-strong-password php seed.php`
6. Open `http://localhost/senaeats/`. Demo logins (password `senaeats123`): customer@, vendor@, courier@, admin@ `senaeats.test`.

## Try the full flow
Customer orders (pin on map) -> vendor accepts/cooks/marks ready -> courier takes delivery (share GPS) ->
customer reads the 4-digit code on the tracking page -> courier enters it -> escrow becomes `released`.

## Notes
- Escrow is simulated: `escrow_transactions.status` goes held -> released (delivery code verified) or refunded (vendor rejects). Plug a real mobile-money API into `place_order` and `settle()` in `api.php`.
- Code entry is limited to 5 wrong attempts per order.
- Production: serve over HTTPS and add `session.cookie_secure=1`. Leaflet and map tiles load from the internet; self-host them for offline use.
- Menu pricing, delivery fee (K10 + K2/km) and platform fee (10%) are computed server-side only.

## Update v2 (portal, profiles, favourites, payouts)
Existing install: `sudo mysql < migration_v2.sql`, then `chmod 775 uploads` and make it writable by the web user (`sudo chown www-data uploads`).
Fresh install: run `schema.sql`, then `migration_v2.sql`.
- `index.php` portal and catalog (cart persists in the browser), `profile.php?type=restaurant|courier&id=N`, `dashboard.php` (customer).
- Vendors and couriers see "Your funds": escrow held vs unlocked. `withdraw` only draws on escrow released by the 4-digit code, under a row lock.
- Reviews are only allowed on delivered orders, one per order.
