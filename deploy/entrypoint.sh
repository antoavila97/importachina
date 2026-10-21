#!/bin/sh
# Arranque del contenedor en Render.
# Equivale a lo que hace Procfile en Railway: migrar, sembrar, optimizar y servir.
set -eu

# TiDB Cloud (y otros MySQL gestionados) exigen TLS. Si no se definio un CA
# explicito, se usa el bundle publico que ya trae la imagen Debian.
if [ -z "${MYSQL_ATTR_SSL_CA:-}" ] && [ -f /etc/ssl/certs/ca-certificates.crt ]; then
    export MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
fi

echo "==> Limpiando cache de configuracion"
php artisan config:clear

echo "==> Verificando que exista la base ${DB_DATABASE:-?}"
# Laravel no crea la base; si el proveedor solo entrega un esquema de sistema
# (TiDB entrega "sys" o "test"), se crea el que usa la app.
php -r '
$host = getenv("DB_HOST");
$port = getenv("DB_PORT") ?: "3306";
$user = getenv("DB_USERNAME");
$pass = getenv("DB_PASSWORD");
$name = getenv("DB_DATABASE");
$ca   = getenv("MYSQL_ATTR_SSL_CA");

if (!$host || !$user || !$name) {
    exit(0);
}

$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 20];
if ($ca) {
    $opts[PDO::MYSQL_ATTR_SSL_CA] = $ca;
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, $opts);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name`");
    echo "base \"$name\" lista\n";
} catch (Throwable $e) {
    echo "aviso: no se pudo crear la base: " . $e->getMessage() . "\n";
}
' || true

echo "==> Migrando la base y sembrando roles + usuarios"
php artisan migrate --force --seed

echo "==> Sembrando catalogo (8 productos) y ventas de demo"
# Idempotente: si ya hay pedidos cargados no genera nada de nuevo.
php artisan db:seed --class=DemoSalesSeeder --force || true

echo "==> Dejando las contrasenas que documenta el proyecto"
# DatabaseSeeder crea los usuarios con "password"; el proyecto documenta otra.
# Si falla, no se cae el arranque: igual se puede entrar con "password".
php artisan tinker --execute='App\Models\User::whereIn("email", ["admin@importachina.com", "vendedor@importachina.com", "cliente@importachina.com"])->update(["password" => bcrypt("ImportaChina#2026")]);' || true

echo "==> Optimizando (config, rutas, vistas)"
php artisan optimize

echo "==> Sirviendo en 0.0.0.0:${PORT:-8000}"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
