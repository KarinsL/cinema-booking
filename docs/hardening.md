# Các biện pháp hardening đã áp dụng

Kiểm tra tự động toàn bộ bằng `./scripts/check-security.sh` (in PASS/FAIL từng mục).

## 1. Container

| Biện pháp | Áp dụng cho | Vị trí cấu hình | Cách kiểm tra |
|---|---|---|---|
| Chạy bằng user thường (non-root) | app (www-data, uid 33), nginx (uid 101), db (mysql, uid 999), prometheus, exporter (nobody), grafana (472), loki (10001) | `app/Dockerfile` (`USER www-data`, Apache nghe 8080), image `nginx-unprivileged`, `user: mysql` trong compose | `docker top cinema-app -o pid,uid,user` |
| Root filesystem chỉ đọc | app, nginx, prometheus, exporter, promtail | `read_only: true` + `tmpfs` cho `/tmp`, `/run` | `docker inspect -f '{{.HostConfig.ReadonlyRootfs}}' cinema-app` |
| Bỏ toàn bộ Linux capabilities | mọi service dùng anchor `x-hardening` | `cap_drop: [ALL]` | `docker inspect -f '{{.HostConfig.CapDrop}}' cinema-nginx` |
| Chặn leo thang quyền | mọi service | `security_opt: no-new-privileges:true` | `docker inspect -f '{{.HostConfig.SecurityOpt}}' cinema-app` |
| Giới hạn RAM, CPU, số tiến trình | mọi service | `deploy.resources.limits` | `docker stats --no-stream` |
| Xoay vòng log | mọi service | `logging.options: max-size 10m, max-file 3` | `docker inspect -f '{{.HostConfig.LogConfig}}' cinema-app` |
| Mã nguồn thuộc root, chỉ đọc với www-data | app | `COPY --chown=root:root`, chmod 644/755 | `docker exec cinema-app touch /var/www/app/x` (bị từ chối) |

Ngoại lệ có chủ đích: `phpmyadmin` (Apache master cần root để chuyển sang www-data), `cadvisor` và `promtail` (cần đọc thông tin Docker của máy host). Ba service này không publish cổng, nằm trong mạng internal và vẫn bật `no-new-privileges`.

## 2. Mạng

| Biện pháp | Chi tiết |
|---|---|
| Chỉ Nginx mở cổng ra ngoài | Chỉ có `80:8080` và `443:8443`. MySQL, Grafana, Prometheus, Loki không publish cổng |
| Tách 4 mạng theo vai trò | `edge` (Internet), `frontend`, `backend`, `monitoring` |
| Mạng `internal: true` | `frontend`, `backend`, `monitoring` không có đường ra Internet; container `db`, `app` không gọi ra ngoài được |
| Nginx không thấy MySQL | `nginx` không ở mạng `backend`: `docker exec cinema-nginx nslookup db` báo không phân giải được |

## 3. Cơ sở dữ liệu

| Biện pháp | Chi tiết |
|---|---|
| Mật khẩu mạnh, sinh ngẫu nhiên | `scripts/gen-secrets.sh`: 22 ký tự, có hoa, thường, số, ký tự đặc biệt; `.env` quyền 600 và nằm trong `.gitignore` |
| Chính sách mật khẩu | component `validate_password`: policy MEDIUM, tối thiểu 12 ký tự (`db/init/03-users.sh`) |
| root chỉ đăng nhập trong container | `MYSQL_ROOT_HOST=localhost` |
| Đặc quyền tối thiểu | `cinema_app`: SELECT, INSERT, UPDATE (không DELETE/DROP/ALTER). `cinema_report`: chỉ SELECT. `cinema_exporter`: PROCESS, REPLICATION CLIENT. `cinema_dba`: toàn quyền nhưng chỉ trên `cinema_db` |
| Khóa tài khoản khi dò mật khẩu | `cinema_dba`, `cinema_report`: sai 5 lần khóa 1 ngày; `cinema_dba` phải đổi mật khẩu sau 90 ngày |
| Giới hạn kết nối | `MAX_USER_CONNECTIONS` cho từng tài khoản |
| Tắt LOAD DATA LOCAL | `--local-infile=OFF` |

Mở khóa tài khoản bị khóa: `docker compose exec db mysql -uroot -p -e "ALTER USER 'cinema_dba'@'%' ACCOUNT UNLOCK;"`

## 4. Nginx và ứng dụng web

| Biện pháp | Chi tiết |
|---|---|
| HTTPS | Chứng chỉ tự ký RSA 2048, chỉ TLS 1.2/1.3, HTTP tự chuyển 301 sang HTTPS |
| Security headers | HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, COOP (`nginx/snippets/security-headers.conf`) |
| Ẩn phiên bản | `server_tokens off`, ẩn `X-Powered-By`, Apache `ServerTokens Prod`, PHP `expose_php = Off` |
| Basic Auth | phpMyAdmin và Prometheus (`nginx/auth/.htpasswd`, băm APR1) |
| Giới hạn tốc độ | `/login`: 10 request/phút/IP (trả 429), toàn site 20 request/giây/IP |
| Chặn đường dẫn nhạy cảm | file ẩn (`.env`, `.git`) trả 403, `/metrics` và `/health` của app trả 404 khi đi qua Nginx |
| Bảo mật trong mã PHP | PDO prepared statement (chống SQL injection), `htmlspecialchars` (chống XSS), CSRF token cho mọi form POST, `password_hash` bcrypt, `session_regenerate_id` khi đăng nhập, cookie HttpOnly + Secure + SameSite, chặn open redirect |

## 5. Máy chủ Ubuntu (tùy chọn, theo Lab 9)

Docker tự quản lý chuỗi iptables của nó, nên KHÔNG chạy `iptables -F` hay `iptables -X` trên máy đang chạy Docker (sẽ xóa luật của Docker và làm mất kết nối tới container). Nếu muốn áp dụng tường lửa, chỉ thao tác trên chuỗi INPUT:

```bash
sudo iptables -F INPUT                                    # chỉ xóa luật cũ của chuỗi INPUT
sudo iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
sudo iptables -A INPUT -i lo -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 22 -j ACCEPT        # đổi thành cổng SSH đang dùng
sudo iptables -A INPUT -p tcp -m multiport --dports 80,443 -j ACCEPT
sudo iptables -A INPUT -p icmp --icmp-type echo-request -m limit --limit 1/s -j ACCEPT
sudo iptables -P INPUT DROP
sudo apt install -y iptables-persistent && sudo netfilter-persistent save
```

Lưu lượng vào container đi qua chuỗi FORWARD/DOCKER-USER do Docker quản lý nên website vẫn truy cập được. Cấu hình SSH và Fail2Ban làm theo Lab 9 (nhớ tạo SSH key trước khi tắt đăng nhập bằng mật khẩu).
