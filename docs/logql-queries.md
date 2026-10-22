# Truy vấn log bằng LogQL (Loki)

Promtail đọc stdout/stderr của mọi container thuộc project `cinema` qua Docker API và gắn các nhãn:

| Nhãn | Ý nghĩa | Ví dụ |
|---|---|---|
| `project` | tên project Docker Compose | `cinema` |
| `service` | tên service trong docker-compose.yml | `nginx`, `app`, `db` |
| `container` | tên container | `cinema-nginx` |
| `stream` | luồng log | `stdout`, `stderr` |
| `status` | mã HTTP (chỉ log Nginx) | `200`, `404` |
| `level` | mức log nghiệp vụ (chỉ log app) | `info`, `warning`, `error` |

Log Nginx và log nghiệp vụ của website đều ở dạng JSON nên dùng được bộ phân tích `| json`.

Mở Grafana → Explore → chọn data source **Loki** → chuyển sang chế độ Code → dán truy vấn → Run query. Trước khi demo nên chạy `./scripts/traffic.sh 5` để có dữ liệu.

## 1. Lọc log theo nhãn

```logql
{service="nginx"}
```
Toàn bộ log truy cập qua Nginx, mỗi dòng là một request (thời gian, IP, URL, mã trạng thái, thời gian xử lý).

```logql
{container=~"cinema-app|cinema-db"}
```
Log của website và MySQL cùng lúc (giống truy vấn `{container=~"wordpress|mysql"}` ở Lab 8).

## 2. Lọc theo nội dung

```logql
{service="db"} |= "Access denied"
```
Các lần đăng nhập MySQL thất bại (MySQL chạy với `--log-error-verbosity=3` nên ghi cả thông báo này). Thử bằng cách đăng nhập phpMyAdmin với mật khẩu sai.

```logql
{project="cinema"} |~ "(?i)error|failed|denied"
```
Mọi dòng có chữ error/failed/denied (không phân biệt hoa thường) trên toàn hệ thống.

## 3. Phân tích JSON và lọc theo trường

```logql
{service="nginx"} | json | status >= 400
```
Chỉ các request lỗi (404 trang không tồn tại, 401 sai mật khẩu, 403 bị chặn, 429 vượt giới hạn tốc độ).

```logql
{service="app"} | json | event="payment_success"
```
Các giao dịch thanh toán giả lập thành công, kèm mã đơn, phương thức, số tiền.

```logql
{service="app"} | json | event="login_failed" | line_format "{{.ts}} IP={{.ip}} email={{.email}}"
```
Danh sách đăng nhập sai với IP và email, định dạng lại cho dễ đọc. Dùng để phát hiện dò mật khẩu.

## 4. Truy vấn dạng số liệu (vẽ biểu đồ)

```logql
sum by (status) (count_over_time({service="nginx"} | json | __error__="" [5m]))
```
Số request theo mã trạng thái HTTP trong mỗi khung 5 phút.

```logql
sum(count_over_time({service="app"} |= "login_failed" [5m]))
```
Số lần đăng nhập sai trong 5 phút, tăng đột biến là dấu hiệu tấn công brute force.

```logql
topk(5, sum by (remote_addr) (count_over_time({service="nginx"} | json | __error__="" [15m])))
```
5 địa chỉ IP gửi nhiều request nhất trong 15 phút.

```logql
sum by (service) (count_over_time({project="cinema"} [15m]))
```
Số dòng log của từng service, cho biết service nào đang ghi log nhiều nhất.

Các truy vấn trên đã được dựng sẵn thành dashboard **Cinema Booking - Log tập trung (Loki)** trong Grafana.
