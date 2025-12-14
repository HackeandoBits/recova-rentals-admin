#!/bin/bash
set -e  # Exit on error

# 1. Copiar configuración de Nginx (Sobreescribimos la default)
cp /home/site/wwwroot/nginx.conf /etc/nginx/sites-available/default
service nginx reload

# 2. Limpiar caché de Laravel (sin regenerar para evitar problemas con Filament)
php /home/site/wwwroot/artisan config:clear
php /home/site/wwwroot/artisan route:clear
php /home/site/wwwroot/artisan view:clear
php /home/site/wwwroot/artisan cache:clear

# 3. Enlace simbólico para imágenes (Hotspots)
php /home/site/wwwroot/artisan storage:link
