#!/bin/bash
set -e

echo "Avvio application..."

echo "Attendo PostgreSQL..."
until pg_isready -h postgres -U postgres > /dev/null 2>&1; do
    sleep 1
done
echo "PostgreSQL pronto"

echo "Attendo Redis..."
until redis-cli -h redis ping > /dev/null 2>&1; do
    sleep 1
done
echo "Redis pronto"

echo "Pulizia cache..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear

if [ ! -d "vendor" ]; then
    echo "Installo Composer packages..."
    composer install --no-interaction
fi

if [ ! -d "node_modules" ]; then
    echo "Installo npm packages..."
    npm install
fi

echo "Setup completato. Accedi a: http://localhost/admin"

exec "$@"
