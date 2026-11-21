#!/bin/sh
cd -- "$(dirname -- "$0")" || exit 1
if ! command -v php >/dev/null 2>&1; then
  echo 'PHP belum tersedia. Pasang PHP 8.2+ dengan PDO SQLite terlebih dahulu.'
  exit 1
fi
echo 'Circlo: buka http://127.0.0.1:8000 di browser.'
echo 'Tekan Ctrl+C untuk menghentikan server.'
exec php -S 127.0.0.1:8000 -t public public/index.php
