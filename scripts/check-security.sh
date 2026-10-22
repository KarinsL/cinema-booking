#!/usr/bin/env bash
# =====================================================================
# Kiểm tra các biện pháp hardening của hệ thống, in PASS/FAIL từng mục.
# Chạy trên máy Ubuntu, trong thư mục dự án, khi toàn bộ container đang chạy:
#     ./scripts/check-security.sh
# =====================================================================
set -uo pipefail
cd "$(dirname "$0")/.."

PASS=0; FAIL=0; NOTE=0
ok()   { printf '  \e[32m[PASS]\e[0m %s\n' "$1"; PASS=$((PASS + 1)); }
bad()  { printf '  \e[31m[FAIL]\e[0m %s\n' "$1"; FAIL=$((FAIL + 1)); }
note() { printf '  \e[33m[NOTE]\e[0m %s\n' "$1"; NOTE=$((NOTE + 1)); }
env_get() { grep -E "^$1=" .env | head -n1 | cut -d= -f2-; }
running() { docker ps --format '{{.Names}}' | grep -qx "$1"; }
curl_site() { curl --noproxy '*' -sk --resolve "$1:443:127.0.0.1" "${@:2}"; }

ROOT_PW=$(env_get MYSQL_ROOT_PASSWORD)
APP_USER=$(env_get APP_DB_USER); APP_PW=$(env_get APP_DB_PASSWORD)
DB=$(env_get MYSQL_DATABASE)
mysql_root() { docker exec -e MYSQL_PWD="$ROOT_PW" cinema-db mysql -uroot -N -B -e "$1" 2>/dev/null; }

echo "== 1. Container chạy bằng user thường (non-root) =="
for c in cinema-app cinema-nginx cinema-db cinema-prometheus cinema-grafana cinema-loki \
         cinema-node-exporter cinema-mysqld-exporter cinema-nginx-exporter; do
  running "$c" || { bad "$c chưa chạy"; continue; }
  uids=$(docker top "$c" -o pid,uid 2>/dev/null | awk 'NR > 1 {print $2}' | sort -u | tr '\n' ' ')
  if [[ -n "$uids" && ! " $uids " =~ " 0 " ]]; then ok "$c: tiến trình chạy với uid $uids"; else bad "$c: có tiến trình root (uid $uids)"; fi
done
note "cinema-phpmyadmin (Apache master), cinema-cadvisor, cinema-promtail chạy root vì cần đọc thông tin Docker/hệ thống; bù lại: không publish cổng, mạng internal, no-new-privileges"

echo "== 2. Root filesystem chỉ đọc =="
for c in cinema-app cinema-nginx cinema-prometheus cinema-node-exporter cinema-mysqld-exporter cinema-nginx-exporter cinema-promtail; do
  [[ "$(docker inspect -f '{{.HostConfig.ReadonlyRootfs}}' "$c" 2>/dev/null)" == "true" ]] && ok "$c read_only" || bad "$c không read_only"
done
if docker exec cinema-app sh -c 'touch /var/www/app/hack.php' 2>/dev/null; then bad "ghi được vào mã nguồn app"; else ok "không ghi được file vào /var/www/app (mã nguồn chỉ đọc)"; fi

echo "== 3. Bỏ Linux capabilities và chặn leo thang quyền =="
for c in cinema-app cinema-nginx cinema-db cinema-prometheus cinema-grafana cinema-loki cinema-promtail; do
  caps=$(docker inspect -f '{{.HostConfig.CapDrop}}' "$c" 2>/dev/null)
  sec=$(docker inspect -f '{{.HostConfig.SecurityOpt}}' "$c" 2>/dev/null)
  [[ "$caps" == *ALL* && "$sec" == *no-new-privileges* ]] && ok "$c cap_drop=ALL, no-new-privileges" || bad "$c caps=$caps sec=$sec"
done

echo "== 4. Giới hạn tài nguyên (RAM) =="
for c in cinema-db cinema-app cinema-nginx cinema-grafana cinema-prometheus cinema-loki; do
  mem=$(docker inspect -f '{{.HostConfig.Memory}}' "$c" 2>/dev/null)
  [[ "${mem:-0}" -gt 0 ]] && ok "$c giới hạn $((mem / 1024 / 1024)) MB" || bad "$c không giới hạn RAM"
done

echo "== 5. Cô lập mạng (network isolation) =="
published=$(docker ps --filter "label=com.docker.compose.project=cinema" --format '{{.Names}} {{.Ports}}' | grep -- '->' | awk '{print $1}' | sort -u | tr '\n' ' ')
[[ "$published" == "cinema-nginx " ]] && ok "chỉ cinema-nginx mở cổng ra máy host (80, 443)" || bad "các container mở cổng: $published"
for n in frontend backend monitoring; do
  [[ "$(docker network inspect -f '{{.Internal}}' "cinema_$n" 2>/dev/null)" == "true" ]] && ok "mạng cinema_$n là internal (không ra Internet)" || bad "mạng cinema_$n không internal"
done
if docker exec cinema-nginx nslookup db >/dev/null 2>&1; then bad "nginx nhìn thấy db"; else ok "nginx KHÔNG truy cập được db (khác mạng)"; fi
if docker exec cinema-app getent hosts db >/dev/null 2>&1; then ok "app truy cập được db qua mạng backend"; else bad "app không thấy db"; fi
if docker exec cinema-app curl -s -m 5 -o /dev/null https://example.com 2>/dev/null; then bad "app ra được Internet"; else ok "app không ra được Internet"; fi
if ss -tln | grep -qE ':(3306|3000|9090|3100|8081)\s'; then bad "máy host đang mở cổng DB/Grafana/Prometheus/Loki"; else ok "máy host không mở cổng 3306/3000/9090/3100"; fi

echo "== 6. MySQL: mật khẩu mạnh, giới hạn quyền =="
roots=$(mysql_root "SELECT host FROM mysql.user WHERE user='root'" | tr '\n' ' ')
[[ "$roots" == "localhost " ]] && ok "root chỉ đăng nhập từ localhost (trong container)" || bad "root host: $roots"
pol=$(mysql_root "SELECT @@validate_password.policy, @@validate_password.length" | tr '\t' ' ')
[[ "$pol" == "MEDIUM 12" ]] && ok "validate_password: policy MEDIUM, tối thiểu 12 ký tự" || bad "validate_password: '$pol'"
grants=$(mysql_root "SHOW GRANTS FOR '$APP_USER'@'%'" | tr '\n' ' ')
if [[ "$grants" == *"SELECT, INSERT, UPDATE ON"* && "$grants" != *DELETE* && "$grants" != *DROP* ]]; then ok "$APP_USER chỉ có SELECT, INSERT, UPDATE"; else bad "quyền $APP_USER: $grants"; fi
if docker exec -e MYSQL_PWD="$APP_PW" cinema-db mysql -h127.0.0.1 -u"$APP_USER" "$DB" -e 'DROP TABLE payments' >/dev/null 2>&1; then
  bad "$APP_USER xóa được bảng!"; else ok "$APP_USER bị từ chối lệnh DROP TABLE"; fi
if [[ "$(stat -c %a .env)" == "600" ]]; then ok ".env chỉ chủ sở hữu đọc được (600)"; else bad ".env có quyền $(stat -c %a .env)"; fi
if git check-ignore -q .env 2>/dev/null; then ok ".env nằm trong .gitignore (không lên GitHub)"; else bad ".env không bị git bỏ qua"; fi

echo "== 7. Nginx: HTTPS, security headers, chặn truy cập trái phép =="
code=$(curl --noproxy '*' -s -o /dev/null -w '%{http_code}' -H 'Host: cinema.local' http://127.0.0.1/)
[[ "$code" == "301" ]] && ok "HTTP tự chuyển sang HTTPS (301)" || bad "HTTP trả về $code"
headers=$(curl_site cinema.local -I https://cinema.local/)
for h in Strict-Transport-Security X-Frame-Options X-Content-Type-Options Content-Security-Policy Referrer-Policy Permissions-Policy; do
  grep -qi "^$h:" <<<"$headers" && ok "có header $h" || bad "thiếu header $h"
done
grep -qiE '^server: nginx\s*$' <<<"$headers" && ok "ẩn phiên bản Nginx (server_tokens off)" || bad "header Server lộ phiên bản"
grep -qi '^x-powered-by' <<<"$headers" && bad "lộ X-Powered-By" || ok "không lộ X-Powered-By"
code=$(curl_site pma.cinema.local -o /dev/null -w '%{http_code}' https://pma.cinema.local/)
[[ "$code" == "401" ]] && ok "phpMyAdmin yêu cầu Basic Auth (401)" || bad "phpMyAdmin trả $code khi chưa đăng nhập"
code=$(curl_site prometheus.cinema.local -o /dev/null -w '%{http_code}' https://prometheus.cinema.local/)
[[ "$code" == "401" ]] && ok "Prometheus yêu cầu Basic Auth (401)" || bad "Prometheus trả $code khi chưa đăng nhập"
code=$(curl_site cinema.local -o /dev/null -w '%{http_code}' https://cinema.local/metrics)
[[ "$code" == "404" ]] && ok "/metrics của app không lộ ra ngoài" || bad "/metrics trả $code"
codes=""; for i in $(seq 1 15); do codes+=$(curl_site cinema.local -o /dev/null -w '%{http_code} ' https://cinema.local/login); done
[[ "$codes" == *429* ]] && ok "giới hạn tốc độ /login hoạt động (có mã 429)" || bad "không thấy 429: $codes"

echo
echo "KẾT QUẢ: $PASS PASS, $FAIL FAIL, $NOTE ghi chú"
[[ $FAIL -eq 0 ]]
