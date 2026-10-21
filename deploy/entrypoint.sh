#!/bin/sh
# Arranque del contenedor en Render.
# Equivale a lo que hace Procfile en Railway: migrar, sembrar, optimizar y servir.
set -eu

echo "==> Limpiando cache de configuracion"
php artisan config:clear

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
