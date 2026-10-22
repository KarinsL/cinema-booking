#!/usr/bin/env bash
# =====================================================================
# Tạo chứng chỉ SSL tự ký (giống Lab 1 bài 2) có SAN cho các tên miền của hệ thống
# =====================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

DOMAIN=cinema.local
DIR=nginx/certs
SAN="DNS:cinema.local,DNS:www.cinema.local,DNS:pma.cinema.local,DNS:grafana.cinema.local,DNS:prometheus.cinema.local"

mkdir -p "$DIR"
rm -f "$DIR/$DOMAIN.key" "$DIR/$DOMAIN.crt"

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout "$DIR/$DOMAIN.key" \
  -out "$DIR/$DOMAIN.crt" \
  -subj "/C=VN/ST=ThaiNguyen/O=CinemaBooking/CN=$DOMAIN" \
  -addext "subjectAltName=$SAN" \
  -addext "extendedKeyUsage=serverAuth"

chmod 644 "$DIR/$DOMAIN.crt"
chmod 640 "$DIR/$DOMAIN.key"
# Private key: chỉ chủ file và group 101 (user nginx trong container) đọc được
if sudo -n true 2>/dev/null || sudo -v; then
  sudo chgrp 101 "$DIR/$DOMAIN.key"
else
  chmod 644 "$DIR/$DOMAIN.key"
  echo "[!] Không dùng được sudo, đặt quyền 644 cho private key"
fi

echo "[+] Đã tạo chứng chỉ:"
openssl x509 -in "$DIR/$DOMAIN.crt" -noout -subject -dates
openssl x509 -in "$DIR/$DOMAIN.crt" -noout -ext subjectAltName
ls -l "$DIR"
