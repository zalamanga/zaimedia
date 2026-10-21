#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY is required. Run python scripts/setup-local.py before starting Compose.' >&2
    exit 1
fi

mkdir -p storage/app/private/books/pdf storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs public/uploads bootstrap/cache
php artisan migrate --force --no-interaction
php artisan db:seed --class=DatabaseSeeder --force --no-interaction
exec "$@"
