#!/usr/bin/env bash
# Exit on error
set -o errexit

echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

echo "Installing Node dependencies..."
npm install

echo "Building frontend assets..."
npm run build

echo "Caching configuration..."
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force

echo "Seeding database..."
php artisan db:seed --force
