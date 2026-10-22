<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * /health  : Docker healthcheck kiểm tra app + kết nối DB
 * /metrics : chỉ số nghiệp vụ dạng Prometheus (Prometheus gọi nội bộ)
 * ------------------------------------------------------------------ */

function page_health(): never
{
    $dbOk = true;
    try {
        db()->query('SELECT 1');
    } catch (Throwable $e) {
        $dbOk = false;
    }
    json_response(['status' => $dbOk ? 'ok' : 'degraded', 'db' => $dbOk ? 'up' : 'down', 'time' => date('c')], $dbOk ? 200 : 503);
}

function page_metrics(): never
{
    // Request đi qua Nginx luôn có X-Real-IP/X-Forwarded-For. Prometheus gọi thẳng app:8080 trong
    // mạng Docker nên không có các header này. Chặn để người ngoài không đọc được số liệu kinh doanh.
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_X_REAL_IP'])) {
        http_response_code(404);
        exit;
    }
    $started = microtime(true);
    $lines = [];
    $metric = function (string $name, string $type, string $help, array $samples) use (&$lines): void {
        $lines[] = "# HELP $name $help";
        $lines[] = "# TYPE $name $type";
        foreach ($samples as [$labels, $value]) {
            $l = '';
            if ($labels) {
                $parts = [];
                foreach ($labels as $k => $v) {
                    $parts[] = $k . '="' . addcslashes((string) $v, "\\\"\n") . '"';
                }
                $l = '{' . implode(',', $parts) . '}';
            }
            $lines[] = $name . $l . ' ' . (is_float($value) ? sprintf('%.6F', $value) : (string) $value);
        }
    };

    $dbUp = 1;
    try {
        expire_stale_holds();

        $byStatus = ['pending' => 0, 'paid' => 0, 'cancelled' => 0, 'expired' => 0];
        foreach (db_all('SELECT status, COUNT(*) AS n FROM bookings GROUP BY status') as $r) {
            $byStatus[$r['status']] = (int) $r['n'];
        }
        $metric('cinema_bookings', 'gauge', 'So don dat ve theo trang thai', array_map(
            fn ($s, $n) => [['status' => $s], $n], array_keys($byStatus), $byStatus
        ));

        $sold = (int) db_value("SELECT COUNT(*) FROM booking_seats bs JOIN bookings b ON b.id = bs.booking_id
                                 WHERE b.status = 'paid' AND bs.is_active = 1");
        $metric('cinema_tickets_sold_total', 'counter', 'Tong so ve (ghe) da ban', [[[], $sold]]);

        $revenue = (int) db_value("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status = 'paid'");
        $metric('cinema_revenue_vnd_total', 'counter', 'Tong doanh thu da thanh toan (VND)', [[[], $revenue]]);

        $payments = [];
        foreach (db_all('SELECT method, status, COUNT(*) AS n FROM payments GROUP BY method, status') as $r) {
            $payments[] = [['method' => $r['method'], 'status' => $r['status']], (int) $r['n']];
        }
        $metric('cinema_payments', 'gauge', 'So giao dich thanh toan gia lap theo phuong thuc va ket qua', $payments);

        $seats = db_one(
            "SELECT
               (SELECT COUNT(*) FROM showtimes st JOIN seats s ON s.room_id = st.room_id
                  WHERE st.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)) AS capacity,
               (SELECT COUNT(*) FROM booking_seats bs JOIN showtimes st ON st.id = bs.showtime_id JOIN bookings b ON b.id = bs.booking_id
                  WHERE bs.is_active = 1 AND b.status = 'paid' AND st.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)) AS sold,
               (SELECT COUNT(*) FROM booking_seats bs JOIN showtimes st ON st.id = bs.showtime_id JOIN bookings b ON b.id = bs.booking_id
                  WHERE bs.is_active = 1 AND b.status = 'pending' AND st.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)) AS held"
        );
        $cap = (int) $seats['capacity'];
        $metric('cinema_upcoming_seats', 'gauge', 'Ghe cua cac suat trong 7 ngay toi theo trang thai', [
            [['state' => 'sold'], (int) $seats['sold']],
            [['state' => 'held'], (int) $seats['held']],
            [['state' => 'free'], max(0, $cap - (int) $seats['sold'] - (int) $seats['held'])],
        ]);

        $metric('cinema_showtimes_upcoming', 'gauge', 'So suat chieu chua dien ra', [[[], (int) db_value(
            'SELECT COUNT(*) FROM showtimes WHERE start_time > NOW() AND is_active = 1'
        )]]);

        $movies = [];
        foreach (db_all('SELECT kind, COUNT(*) AS n FROM movies WHERE is_active = 1 GROUP BY kind') as $r) {
            $movies[] = [['kind' => $r['kind']], (int) $r['n']];
        }
        $metric('cinema_titles_active', 'gauge', 'So phim/su kien dang mo ban', $movies);

        $users = [];
        foreach (db_all('SELECT role, COUNT(*) AS n FROM users GROUP BY role') as $r) {
            $users[] = [['role' => $r['role']], (int) $r['n']];
        }
        $metric('cinema_users', 'gauge', 'So tai khoan theo vai tro', $users);
    } catch (Throwable $e) {
        $dbUp = 0;
    }

    $metric('cinema_db_up', 'gauge', '1 neu app ket noi duoc MySQL', [[[], $dbUp]]);
    $metric('cinema_metrics_scrape_duration_seconds', 'gauge', 'Thoi gian tinh toan metrics', [[[], microtime(true) - $started]]);

    http_response_code(200);
    header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
    header('Cache-Control: no-store');
    echo implode("\n", $lines), "\n";
    exit;
}
