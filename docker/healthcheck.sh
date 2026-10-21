#!/bin/sh
set -eu
SCRIPT_FILENAME=/var/www/html/public/index.php \
SCRIPT_NAME=/index.php \
REQUEST_METHOD=GET \
REQUEST_URI=/api/health \
SERVER_NAME=localhost \
SERVER_PORT=8080 \
cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q '"ok":true'
