#!/bin/bash
# =====================================================================
# 03-users.sh : Tạo tài khoản MySQL theo nguyên tắc đặc quyền tối thiểu
# Image mysql nạp (source) file .sh này trong lúc khởi tạo CSDL lần đầu,
# nên dùng được hàm docker_process_sql và biến môi trường của container.
# Mật khẩu lấy từ file .env (sinh bằng scripts/gen-secrets.sh).
# =====================================================================

: "${MYSQL_DATABASE:?Thiếu biến MYSQL_DATABASE}"
: "${APP_DB_USER:?Thiếu biến APP_DB_USER}"           "${APP_DB_PASSWORD:?Thiếu APP_DB_PASSWORD}"
: "${DBA_DB_USER:?Thiếu biến DBA_DB_USER}"           "${DBA_DB_PASSWORD:?Thiếu DBA_DB_PASSWORD}"
: "${REPORT_DB_USER:?Thiếu biến REPORT_DB_USER}"     "${REPORT_DB_PASSWORD:?Thiếu REPORT_DB_PASSWORD}"
: "${EXPORTER_DB_USER:?Thiếu biến EXPORTER_DB_USER}" "${EXPORTER_DB_PASSWORD:?Thiếu EXPORTER_DB_PASSWORD}"

# Thoát ký tự đặc biệt trong chuỗi SQL ('  và  \)
sql_esc() { local s=${1//\\/\\\\}; s=${s//\'/\'\'}; printf '%s' "$s"; }

DB="$MYSQL_DATABASE"

mysql_note "Cài chính sách mật khẩu và tạo tài khoản giới hạn quyền..."

docker_process_sql --database=mysql <<EOSQL
-- 1) Chính sách mật khẩu: tối thiểu 12 ký tự, có chữ hoa, chữ thường, số, ký tự đặc biệt
INSTALL COMPONENT 'file://component_validate_password';
SET PERSIST validate_password.policy = 'MEDIUM';
SET PERSIST validate_password.length = 12;

-- 2) Tài khoản ứng dụng web: chỉ SELECT/INSERT/UPDATE, không DELETE/DROP/ALTER
CREATE USER '$(sql_esc "$APP_DB_USER")'@'%'
  IDENTIFIED BY '$(sql_esc "$APP_DB_PASSWORD")'
  WITH MAX_USER_CONNECTIONS 50;
GRANT SELECT, INSERT, UPDATE ON \`${DB}\`.* TO '$(sql_esc "$APP_DB_USER")'@'%';

-- 3) Tài khoản quản trị CSDL (đăng nhập phpMyAdmin): toàn quyền nhưng CHỈ trên cinema_db,
--    khóa 1 ngày nếu sai mật khẩu 5 lần, bắt đổi mật khẩu sau 90 ngày
CREATE USER '$(sql_esc "$DBA_DB_USER")'@'%'
  IDENTIFIED BY '$(sql_esc "$DBA_DB_PASSWORD")'
  WITH MAX_USER_CONNECTIONS 10
  FAILED_LOGIN_ATTEMPTS 5 PASSWORD_LOCK_TIME 1
  PASSWORD EXPIRE INTERVAL 90 DAY;
GRANT ALL PRIVILEGES ON \`${DB}\`.* TO '$(sql_esc "$DBA_DB_USER")'@'%';

-- 4) Tài khoản báo cáo: chỉ đọc
CREATE USER '$(sql_esc "$REPORT_DB_USER")'@'%'
  IDENTIFIED BY '$(sql_esc "$REPORT_DB_PASSWORD")'
  WITH MAX_USER_CONNECTIONS 5
  FAILED_LOGIN_ATTEMPTS 5 PASSWORD_LOCK_TIME 1;
GRANT SELECT ON \`${DB}\`.* TO '$(sql_esc "$REPORT_DB_USER")'@'%';

-- 5) Tài khoản cho mysqld-exporter (Prometheus): chỉ đọc trạng thái máy chủ
CREATE USER '$(sql_esc "$EXPORTER_DB_USER")'@'%'
  IDENTIFIED BY '$(sql_esc "$EXPORTER_DB_PASSWORD")'
  WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT ON *.* TO '$(sql_esc "$EXPORTER_DB_USER")'@'%';
GRANT SELECT ON performance_schema.* TO '$(sql_esc "$EXPORTER_DB_USER")'@'%';
EOSQL

mysql_note "Đã tạo: ${APP_DB_USER}, ${DBA_DB_USER}, ${REPORT_DB_USER}, ${EXPORTER_DB_USER}. Root chỉ đăng nhập được từ trong container."
