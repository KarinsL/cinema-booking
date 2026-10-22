# Đề 11: Hệ thống đặt vé xem phim / sự kiện

Bài thực hành môn **Triển khai và Quản trị Hệ thống Phần mềm**.

| Thông tin | |
|---|---|
| Sinh viên | Nguyễn Phạm Phương Lan |
| MSSV | DTC245200624 |
| Lớp | CNTT-K23L |
| Giảng viên hướng dẫn | Vũ Văn Diện |

Website đặt vé CineTicket (PHP 8.3 + Apache) cho phép xem lịch chiếu phim và sự kiện, chọn ghế trên sơ đồ, giữ ghế 10 phút, thanh toán qua cổng giả lập và nhận vé điện tử. Toàn bộ hệ thống chạy bằng Docker Compose.

## 1. Thành phần hệ thống

| Service | Image | Vai trò | Truy cập |
|---|---|---|---|
| `nginx` | nginxinc/nginx-unprivileged:1.30-alpine | Reverse proxy, HTTPS, security headers, Basic Auth, giới hạn tốc độ | cổng 80, 443 của máy |
| `app` | build từ `app/Dockerfile` (php:8.3-apache) | Website đặt vé, endpoint `/health` và `/metrics` nội bộ | https://cinema.local |
| `db` | mysql:8.4 | CSDL `cinema_db` | chỉ trong mạng Docker |
| `phpmyadmin` | phpmyadmin:5.2.3-apache | Quản lý CSDL | https://pma.cinema.local |
| `prometheus` | prom/prometheus:v3.13.4 | Thu thập metrics, luật cảnh báo | https://prometheus.cinema.local |
| `grafana` | grafana/grafana:13.2.4 | Dashboard giám sát | https://grafana.cinema.local |
| `cadvisor` | ghcr.io/google/cadvisor:v0.60.6 | Metrics từng container | nội bộ |
| `node-exporter` | prom/node-exporter:v1.10.2 | Metrics máy chủ Ubuntu | nội bộ |
| `mysqld-exporter` | prom/mysqld-exporter:v0.18.0 | Metrics MySQL | nội bộ |
| `nginx-exporter` | nginx/nginx-prometheus-exporter:1.5.1 | Metrics Nginx (stub_status) | nội bộ |
| `loki` | grafana/loki:3.6.11 | Lưu và truy vấn log (LogQL) | qua Grafana |
| `promtail` | grafana/promtail:3.6.11 | Thu log stdout/stderr của mọi container | nội bộ |

## 2. Kiến trúc

```text
 Trình duyệt (Windows/Ubuntu)
        │  HTTPS: cinema.local và các tên miền con *.cinema.local
        ▼
 ┌──────────────── máy Ubuntu: chỉ mở cổng 80, 443 ────────────────┐
 │  nginx (HTTPS, headers, Basic Auth, rate limit)                  │
 │    ├── app:8080 (PHP+Apache) ──► db:3306 (MySQL) ◄── phpmyadmin │
 │    ├── phpmyadmin:80                                             │
 │    ├── grafana:3000 ──► prometheus:9090 ──► exporter, cAdvisor   │
 │    └── prometheus:9090                                           │
 │  promtail ──(Docker API)──► log mọi container ──► loki:3100      │
 │  grafana ──► loki (LogQL)                                        │
 └──────────────────────────────────────────────────────────────────┘
```

Mạng Docker được tách theo vai trò:

| Mạng | internal | Thành viên |
|---|---|---|
| `edge` | không | nginx, grafana (cần Internet để import dashboard) |
| `frontend` | có | nginx, app, phpmyadmin, grafana, prometheus |
| `backend` | có | db, app, phpmyadmin, mysqld-exporter |
| `monitoring` | có | prometheus, grafana, loki, promtail, cadvisor, các exporter, nginx |

## 3. Yêu cầu

- Ubuntu Server 22.04 hoặc 24.04 (máy ảo 2 vCPU, RAM từ 4 GB, ổ đĩa trống từ 15 GB)
- Docker Engine 25 trở lên và Docker Compose v2 (`docker compose version`)
- Cổng 80 và 443 của máy đang trống (tắt Nginx cài trên máy từ Lab 1: `sudo systemctl disable --now nginx`)

## 4. Cài đặt và chạy

```bash
git clone git@github.com:DTC245200624/cinema-booking.git
cd cinema-booking

./scripts/gen-secrets.sh      # tạo .env với mật khẩu mạnh + file Basic Auth (in ra thông tin đăng nhập)
./scripts/gen-cert.sh         # tạo chứng chỉ SSL tự ký cho *.cinema.local

docker compose up -d --build  # build image app và chạy toàn bộ hệ thống
docker compose ps             # chờ các service ở trạng thái Up/healthy
```

Khai báo tên miền (thay `192.168.x.x` bằng IP máy Ubuntu, xem bằng `ip -4 addr`):

- Trên Ubuntu: thêm vào `/etc/hosts` dòng `127.0.0.1 cinema.local www.cinema.local pma.cinema.local grafana.cinema.local prometheus.cinema.local`
- Trên Windows: mở Notepad bằng quyền Administrator, sửa `C:\Windows\System32\drivers\etc\hosts`, thêm `192.168.x.x cinema.local www.cinema.local pma.cinema.local grafana.cinema.local prometheus.cinema.local`

Chứng chỉ tự ký nên trình duyệt sẽ cảnh báo lần đầu, chọn Advanced → Proceed.

## 5. Địa chỉ và tài khoản

Mật khẩu nằm trong file `.env` (xem bằng `cat .env`), không được commit lên GitHub.

| Địa chỉ | Đăng nhập |
|---|---|
| https://cinema.local | Khách: tự đăng ký. Admin: `APP_ADMIN_EMAIL` / `APP_ADMIN_PASSWORD` |
| https://pma.cinema.local | Basic Auth `TOOLS_BASIC_AUTH_USER`, sau đó MySQL `cinema_dba` / `DBA_DB_PASSWORD` |
| https://grafana.cinema.local | `GRAFANA_ADMIN_USER` / `GRAFANA_ADMIN_PASSWORD` |
| https://prometheus.cinema.local | Basic Auth `TOOLS_BASIC_AUTH_USER` / `TOOLS_BASIC_AUTH_PASSWORD` |

## 6. Kiểm tra từng phần

**Website và CSDL**

```bash
docker compose logs app | grep bootstrap       # tạo admin, lịch chiếu 7 ngày, đơn mẫu
docker compose exec db mysql -uroot -p cinema_db -e "SHOW TABLES; SELECT COUNT(*) FROM showtimes;"
```

Luồng demo: đăng ký → chọn suất → chọn ghế (thường/VIP) → giữ ghế 10 phút → chọn phương thức thanh toán giả lập → bấm "Xác nhận thanh toán thành công" → nhận vé. Trang `/admin` dùng tài khoản admin để xem doanh thu, thêm phim, thêm suất chiếu.

**Nginx reverse proxy + HTTPS**

```bash
curl -I http://cinema.local                      # 301 chuyển sang HTTPS
curl -kI https://cinema.local                    # 200 + Strict-Transport-Security, CSP, X-Frame-Options...
curl -k -o /dev/null -w "%{http_code}\n" https://pma.cinema.local   # 401 (cần Basic Auth)
```

**Prometheus + Grafana**

- Prometheus → Status → Target health: các job `prometheus`, `node-exporter`, `cadvisor`, `mysql`, `nginx`, `cinema-app`, `grafana` đều UP.
- Grafana đã có sẵn data source Prometheus và dashboard "Cinema Booking - Giám sát hệ thống" (container, Nginx, MySQL, máy chủ, nghiệp vụ). Có thể import thêm dashboard cộng đồng: 1860 (Node Exporter Full), 14282 (cAdvisor), 7362 (MySQL Overview), 12708 (NGINX exporter).
- Tạo lưu lượng mẫu để có số liệu: `./scripts/traffic.sh 5`

**Loki + LogQL**

Grafana → Explore → chọn data source Loki, hoặc mở dashboard "Cinema Booking - Log tập trung (Loki)". Một số truy vấn (xem đầy đủ trong `docs/logql-queries.md`):

```logql
{service="nginx"}                                                        # toàn bộ log truy cập Nginx
{service="nginx"} | json | status >= 400                                 # request lỗi 4xx/5xx
sum by (status) (count_over_time({service="nginx"} | json | __error__="" [5m]))   # số request theo mã HTTP
{service="app"} | json | event="payment_success"                         # các giao dịch thanh toán thành công
sum(count_over_time({service="app"} |= "login_failed" [5m]))             # đăng nhập sai trong 5 phút
{service="db"} |= "Access denied"                                        # đăng nhập MySQL thất bại
```

**Hardening**

```bash
./scripts/check-security.sh      # kiểm tra tự động, in PASS/FAIL từng biện pháp
```

Chi tiết từng biện pháp và vị trí cấu hình: `docs/hardening.md`.

## 7. Cấu trúc thư mục

```text
.
├── app/                     # website đặt vé (Dockerfile, cấu hình Apache/PHP, mã nguồn src/)
├── db/init/                 # 01-schema.sql, 02-seed.sql, 03-users.sh (chạy khi khởi tạo CSDL lần đầu)
├── nginx/                   # nginx.conf, conf.d/ (từng tên miền), snippets/, certs/, auth/
├── monitoring/              # prometheus.yml, rules/alerts.yml, grafana provisioning + dashboards
├── logging/                 # loki-config.yml, promtail-config.yml
├── docs/                    # tài liệu LogQL, hardening
├── scripts/                 # gen-secrets.sh, gen-cert.sh, traffic.sh, check-security.sh
├── docker-compose.yml
└── .env.example             # mẫu biến môi trường (file .env thật không commit)
```

## 8. Lệnh thường dùng

```bash
docker compose ps                         # trạng thái
docker compose logs -f nginx app          # xem log
docker compose restart nginx              # nạp lại cấu hình Nginx
docker compose down                       # dừng (giữ dữ liệu)
docker compose down -v                    # dừng và XÓA dữ liệu (CSDL khởi tạo lại từ đầu)
```

## 9. Xử lý sự cố

| Hiện tượng | Cách xử lý |
|---|---|
| `bind: address already in use` cổng 80/443 | Tắt dịch vụ đang chiếm cổng: `sudo systemctl disable --now nginx` (Lab 1) hoặc dừng Nginx Proxy Manager (Lab 5) |
| Nginx báo `cannot load certificate key ... Permission denied` | Chạy lại `./scripts/gen-cert.sh` (đặt quyền đọc cho user nginx, gid 101) |
| `Access denied` khi app kết nối DB sau khi đổi `.env` | Mật khẩu chỉ áp dụng lúc khởi tạo CSDL lần đầu: `docker compose down -v` rồi `up -d` lại |
| Trang báo 502 Bad Gateway | Service phía sau chưa chạy xong: `docker compose ps`, `docker compose logs <service>` |
| Target `cadvisor` không có số liệu container | Kiểm tra `ls /run/containerd/containerd.sock`; Docker cài bằng snap không có file này, nên cài Docker theo hướng dẫn apt |
| Promtail báo `client version ... is too old` | Docker Engine 29.0 đến 29.2: thêm `"min-api-version": "1.24"` vào `/etc/docker/daemon.json` rồi `sudo systemctl restart docker` |

## 10. Lịch sử phát triển

| Commit | Nội dung |
|---|---|
| 1 | Website đặt vé + MySQL + phpMyAdmin + Nginx reverse proxy HTTPS |
| 2 | Giám sát Prometheus + Grafana (cAdvisor, node-exporter, mysqld-exporter, nginx-exporter) |
| 3 | Log tập trung Loki + Promtail, truy vấn LogQL |
| 4 | Hardening: non-root, read-only, cap_drop, tách mạng internal, giới hạn tài nguyên |
