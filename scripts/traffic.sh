#!/usr/bin/env bash
# =====================================================================
# Tạo lưu lượng mẫu để Grafana có số liệu và Loki có log khi demo:
#  - xem trang chủ, trang phim, sơ đồ ghế
#  - vài đường dẫn không tồn tại (404) và đăng nhập sai (401)
#  - đăng ký khách mới -> giữ ghế -> thanh toán giả lập thành công
# Cách dùng:  ./scripts/traffic.sh [số_đơn_đặt_vé]      (mặc định 3)
# =====================================================================
set -uo pipefail

HOST=cinema.local
BOOKINGS=${1:-3}
BASE="https://$HOST"
JAR=$(mktemp)
trap 'rm -f "$JAR"' EXIT

req() { # req METHOD PATH [curl args...]  -> in mã HTTP
  local m=$1 p=$2; shift 2
  curl --noproxy '*' -sk --resolve "$HOST:443:127.0.0.1" -b "$JAR" -c "$JAR" \
       -o /dev/null -w '%{http_code}' -X "$m" "$BASE$p" "$@"
}
page() { # page PATH -> in nội dung HTML
  curl --noproxy '*' -sk --resolve "$HOST:443:127.0.0.1" -b "$JAR" -c "$JAR" "$BASE$1"
}
csrf() { page "$1" | grep -o 'name="_csrf" value="[^"]*"' | head -n1 | sed 's/.*value="//; s/"$//'; }
location() { # location METHOD PATH [args] -> in header Location
  curl --noproxy '*' -sk --resolve "$HOST:443:127.0.0.1" -b "$JAR" -c "$JAR" \
       -o /dev/null -w '%{redirect_url}' -X "$1" "$BASE$2" "${@:3}"
}

echo "[1] Duyệt website"
for p in / "/?kind=movie" "/?kind=event" /movie/1 /movie/2 /movie/5 /login /register; do
  printf '  %-16s %s\n' "$p" "$(req GET "$p")"; sleep 0.2
done

echo "[2] Đường dẫn không tồn tại (404) và file nhạy cảm (403)"
for p in /wp-admin /phpinfo.php /.env /.git/config /admin/../../etc/passwd; do
  printf '  %-24s %s\n' "$p" "$(req GET "$p")"; sleep 0.2
done

echo "[3] Đăng nhập sai 3 lần (sinh log login_failed)"
for i in 1 2 3; do
  tok=$(csrf /login)
  printf '  lần %s: %s\n' "$i" "$(req POST /login --data-urlencode "_csrf=$tok" \
        --data-urlencode "email=attacker$i@example.com" --data-urlencode "password=sai-mat-khau-$i")"
  sleep 1
done

echo "[4] Đặt vé và thanh toán giả lập ($BOOKINGS đơn)"
for n in $(seq 1 "$BOOKINGS"); do
  rm -f "$JAR"
  email="khach$(date +%s)$n@example.com"
  tok=$(csrf /register)
  code=$(req POST /register --data-urlencode "_csrf=$tok" --data-urlencode "full_name=Khách Demo $n" \
         --data-urlencode "email=$email" --data-urlencode "password=Demo12345" --data-urlencode "password_confirm=Demo12345")
  # chọn ngẫu nhiên một suất chiếu trên trang chủ
  sid=$(page / | grep -o 'href="/showtime/[0-9]*"' | sed 's/[^0-9]//g' | shuf -n1)
  html=$(page "/showtime/$sid" | tr '\n' ' ')
  seat=$(grep -o 'is-free"[^>]*data-id="[0-9]*"' <<<"$html" | grep -v disabled | grep -o 'data-id="[0-9]*"' | sed 's/[^0-9]//g' | shuf -n1)
  tok=$(grep -o 'name="_csrf" value="[^"]*"' <<<"$html" | head -n1 | sed 's/.*value="//; s/"$//')
  if [[ -z "$sid" || -z "$seat" ]]; then echo "  không tìm thấy suất/ghế trống"; continue; fi
  checkout=$(location POST "/showtime/$sid/hold" --data-urlencode "_csrf=$tok" --data-urlencode "seat_ids[]=$seat")
  bcode=${checkout##*/}
  tok=$(csrf "/checkout/$bcode")
  gateway=$(location POST "/checkout/$bcode/pay" --data-urlencode "_csrf=$tok" --data-urlencode "method=ewallet")
  ref=${gateway##*/}
  tok=$(csrf "/gateway/$ref")
  ticket=$(location POST "/gateway/$ref" --data-urlencode "_csrf=$tok" --data-urlencode "result=success")
  printf '  đơn %s: đăng ký=%s suất=%s ghế=%s -> %s\n' "$n" "$code" "$sid" "$seat" "${ticket#"$BASE"}"
  sleep 1
done
echo "Xong. Mở Grafana/Loki để xem số liệu và log vừa sinh ra."
