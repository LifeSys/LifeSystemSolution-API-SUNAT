#!/bin/sh
set -e

echo "==> Optimizando autoload..."
composer dump-autoload --optimize --no-dev 2>/dev/null || true

echo "==> Limpiando cache previo..."
php artisan config:clear
php artisan cache:clear 2>/dev/null || true

echo "==> Ejecutando migraciones..."
php artisan migrate --force 2>&1

echo "==> Cacheando config y rutas..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Creando symlink storage..."
php artisan storage:link 2>/dev/null || true

echo "==> Iniciando worker de colas en segundo plano..."
php artisan queue:work --tries=20 --timeout=90 --sleep=3 --max-time=3600 &
QUEUE_PID=$!

# Si el worker muere (crash, OOM, etc.), reinícialo automáticamente en vez de
# dejar el contenedor sin nadie procesando la cola.
(
  while true; do
    wait "$QUEUE_PID" || true
    echo "==> Worker de colas caído, reiniciando en 5s..."
    sleep 5
    php artisan queue:work --tries=20 --timeout=90 --sleep=3 --max-time=3600 &
    QUEUE_PID=$!
  done
) &

echo "==> Iniciando servidor..."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"