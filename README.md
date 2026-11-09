# CMI SKU Gen API

Laravel 12 API (PHP 8.3) backed by Microsoft SQL Server.

## Endpoint

`POST /skugen` with a JSON array body:

```json
[
  {
    "collection": "BCBG",
    "model": "X1",
    "color": "BLK",
    "size": "52",
    "release_date": "2026-12-01",
    "mode": "G",
    "debug_flag": 0,
    "source": "app"
  }
]
```

Flow (`app/Http/Controllers/SKUGenController.php`):

1. `TRUNCATE TABLE cvo_tmp_sku_gen`
2. For each object: `EXEC cvo_cmi_sku_generate_2026_sp collection, model, color, size, release_date, mode, debug_flag, source` (parameterized)
3. Returns `SELECT * FROM cvo_tmp_sku_gen WHERE collection <> 'coll' ORDER BY severity, model, part_no` as JSON

Runs are serialized with a cache lock (the temp table is shared); a request that waits longer
than `SKUGEN_LOCK_WAIT_SECONDS` gets HTTP 409.

## Configuration (`.env`)

```
DB_CONNECTION=sqlsrv
DB_HOST=
DB_PORT=1433
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
DB_ENCRYPT=yes
DB_TRUST_SERVER_CERTIFICATE=true
```

## Ubuntu (AWS) server setup

```bash
# PHP 8.3 + extensions
sudo apt install php8.3-fpm php8.3-cli php8.3-dev php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip unixodbc-dev

# Microsoft ODBC Driver 18 (see learn.microsoft.com "Install the ODBC driver for Linux"), then:
sudo ACCEPT_EULA=Y apt install msodbcsql18
sudo pecl install sqlsrv pdo_sqlsrv
echo "extension=sqlsrv.so"     | sudo tee /etc/php/8.3/mods-available/sqlsrv.ini
echo "extension=pdo_sqlsrv.so" | sudo tee /etc/php/8.3/mods-available/pdo_sqlsrv.ini
sudo phpenmod sqlsrv pdo_sqlsrv && sudo systemctl restart php8.3-fpm

# App
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate   # then fill DB_* values
php artisan config:cache && php artisan route:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

Point the nginx/Apache document root at `public/`.
