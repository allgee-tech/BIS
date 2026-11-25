#!/bin/sh
set -eu

port="${PORT:-8080}"
case "$port" in
    ''|*[!0-9]*)
        echo 'PORT must be a numeric TCP port.' >&2
        exit 1
        ;;
esac

sed -i -E "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \\*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

private_storage_path="${C2C_PRIVATE_STORAGE_PATH:-}"
if [ -n "$private_storage_path" ]; then
    mkdir -p "$private_storage_path"
    chown www-data:www-data "$private_storage_path"
    chmod 750 "$private_storage_path"
fi

exec apache2-foreground
