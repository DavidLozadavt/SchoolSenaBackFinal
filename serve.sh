#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
export PHP_INI_SCAN_DIR=":${DIR}/php-conf.d"
exec php artisan serve --host=127.0.0.1 --port=8000 "$@"
