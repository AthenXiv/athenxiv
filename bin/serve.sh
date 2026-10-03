#!/usr/bin/env sh
# Athenaeum — start the local development server (Linux / macOS).
#   bin/serve.sh          -> http://127.0.0.1:8080
#   bin/serve.sh 9000     -> http://127.0.0.1:9000

set -e
PORT="${1:-8080}"
cd "$(dirname "$0")/.."

exec php \
  -d extension=pdo_sqlite \
  -d extension=pdo_mysql \
  -d extension=zip \
  -d extension=gd \
  -d extension=fileinfo \
  -d upload_max_filesize=32M \
  -d post_max_size=40M \
  -d max_execution_time=120 \
  -S "127.0.0.1:${PORT}" -t public public/router.php
