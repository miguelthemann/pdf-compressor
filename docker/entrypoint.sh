#!/bin/sh
set -e
# Volume montado em /var/www/html/uploads (ex.: Portainer) pode ficar com dono root;
# o Apache serve como www-data e precisa de RW em temp/ e compressed/.
if [ -d /var/www/html/uploads ]; then
    chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true
    chmod -R ug+rwX /var/www/html/uploads 2>/dev/null || true
fi
exec /usr/local/bin/docker-php-entrypoint "$@"
