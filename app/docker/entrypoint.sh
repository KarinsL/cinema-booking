#!/bin/sh
# Khởi động container app: chạy bootstrap (tạo admin, lịch chiếu, dữ liệu mẫu) rồi mới chạy Apache.
# Bootstrap lỗi (vd. DB chưa sẵn sàng) thì vẫn chạy Apache để /health báo trạng thái.
set -e
php /var/www/app/bin/bootstrap.php || echo "[bootstrap] Lỗi, website vẫn khởi động" >&2
exec docker-php-entrypoint "$@"
