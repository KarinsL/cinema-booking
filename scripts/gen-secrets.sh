#!/usr/bin/env bash
# =====================================================================
# Sinh file .env (mật khẩu mạnh ngẫu nhiên) và file nginx/auth/.htpasswd
# Cách dùng:  ./scripts/gen-secrets.sh          (tạo mới khi chưa có .env)
#             ./scripts/gen-secrets.sh --force  (tạo lại, chỉ dùng khi đã xóa volume DB)
# =====================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ -f .env && "${1:-}" != "--force" ]]; then
  echo "[!] Đã có file .env nên không tạo lại (tránh lệch mật khẩu với CSDL đã khởi tạo)."
  echo "    Chỉ tạo lại .htpasswd từ .env hiện có."
else
  [[ -f .env.example ]] || { echo "Không thấy .env.example"; exit 1; }

  # Mật khẩu 22 ký tự: 20 ký tự chữ/số ngẫu nhiên + ký tự đặc biệt @ và -,
  # luôn có chữ hoa, chữ thường, số (đạt chính sách validate_password MEDIUM của MySQL)
  gen_pass() {
    local raw p
    while true; do
      raw="$(openssl rand -base64 64)"
      raw="${raw//[^A-Za-z0-9]/}"
      p="${raw:0:8}@${raw:8:6}-${raw:14:6}"
      if [[ ${#p} -eq 22 && "$p" =~ [[:upper:]] && "$p" =~ [[:lower:]] && "$p" =~ [[:digit:]] ]]; then
        printf '%s' "$p"
        return
      fi
    done
  }

  umask 077
  : > .env
  while IFS= read -r line || [[ -n "$line" ]]; do
    if [[ "$line" =~ ^([A-Z0-9_]+)=CHANGE_ME$ ]]; then
      printf '%s=%s\n' "${BASH_REMATCH[1]}" "$(gen_pass)" >> .env
    else
      printf '%s\n' "$line" >> .env
    fi
  done < .env.example
  chmod 600 .env
  echo "[+] Đã tạo .env (quyền 600, chỉ user $(whoami) đọc được)"
fi

get_env() { grep -E "^$1=" .env | head -n1 | cut -d= -f2-; }

# ---- File mật khẩu Basic Auth cho Nginx (băm APR1-MD5, không lưu mật khẩu gốc) ----
mkdir -p nginx/auth
user="$(get_env TOOLS_BASIC_AUTH_USER)"
pass="$(get_env TOOLS_BASIC_AUTH_PASSWORD)"
printf '%s:%s\n' "$user" "$(openssl passwd -apr1 "$pass")" > nginx/auth/.htpasswd
chmod 640 nginx/auth/.htpasswd
# Nginx trong container chạy bằng uid/gid 101 nên cần quyền đọc qua group 101
if sudo -n true 2>/dev/null || sudo -v; then
  sudo chgrp 101 nginx/auth/.htpasswd
else
  chmod 644 nginx/auth/.htpasswd
  echo "[!] Không dùng được sudo, đặt quyền 644 cho .htpasswd"
fi
echo "[+] Đã tạo nginx/auth/.htpasswd cho user: $user"

cat <<EOF

================ THÔNG TIN ĐĂNG NHẬP (lưu lại, không commit) ================
Website admin   : https://cinema.local/login
                  $(get_env APP_ADMIN_EMAIL) / $(get_env APP_ADMIN_PASSWORD)
Basic Auth      : (hiện khi mở pma.cinema.local và prometheus.cinema.local)
                  $(get_env TOOLS_BASIC_AUTH_USER) / $(get_env TOOLS_BASIC_AUTH_PASSWORD)
phpMyAdmin      : https://pma.cinema.local
                  $(get_env DBA_DB_USER) / $(get_env DBA_DB_PASSWORD)   (quản trị cinema_db)
                  $(get_env APP_DB_USER) / $(get_env APP_DB_PASSWORD)   (quyền của web app)
Grafana         : https://grafana.cinema.local
                  $(get_env GRAFANA_ADMIN_USER) / $(get_env GRAFANA_ADMIN_PASSWORD)
MySQL root      : chỉ đăng nhập trong container:  docker compose exec db mysql -uroot -p
                  mật khẩu: $(get_env MYSQL_ROOT_PASSWORD)
=============================================================================
Xem lại bất cứ lúc nào bằng:  cat .env
EOF
