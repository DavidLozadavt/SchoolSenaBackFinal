#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
# El ":" inicial conserva /etc/php/*/cli/conf.d (pdo, mysql, zip) y suma este overlay.
export PHP_INI_SCAN_DIR=":${DIR}/php-conf.d"
php -r 'fwrite(STDERR, "PHP uploads: upload_max_filesize=".ini_get("upload_max_filesize")." post_max_size=".ini_get("post_max_size")." tmp=".(ini_get("upload_tmp_dir") ?: sys_get_temp_dir()).PHP_EOL);'
exec php artisan serve --host=127.0.0.1 --port=8000 "$@"
