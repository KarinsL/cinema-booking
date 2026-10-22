<?php
declare(strict_types=1);

/**
 * Chạy một lần mỗi khi container app khởi động (xem docker/entrypoint.sh):
 *  1. Chờ MySQL sẵn sàng (tối đa ~60 giây)
 *  2. Tạo tài khoản admin của website từ biến môi trường nếu chưa có
 *  3. Bổ sung suất chiếu cho 7 ngày tới khi lịch sắp hết
 *  4. Tạo vài đơn mẫu (APP_SEED_DEMO=true) để dashboard có số liệu
 */

require __DIR__ . '/../lib/core.php';
require __DIR__ . '/../lib/booking.php';
require __DIR__ . '/../lib/schedule.php';

function out(string $msg): void
{
    fwrite(STDOUT, '[bootstrap] ' . $msg . PHP_EOL);
}

$connected = false;
for ($i = 1; $i <= 30; $i++) {
    try {
        db()->query('SELECT 1');
        $connected = true;
        break;
    } catch (PDOException $e) {
        out("Chưa kết nối được MySQL (lần $i/30): " . $e->getMessage());
        sleep(2);
    }
}
if (!$connected) {
    out('Bỏ qua bootstrap vì không kết nối được CSDL. Website vẫn khởi động, /health sẽ báo lỗi DB.');
    exit(1);
}

$admin = config('admin');
if ($admin['password'] === '') {
    out('APP_ADMIN_PASSWORD trống nên không tạo tài khoản admin.');
} elseif (!db_value('SELECT id FROM users WHERE email = ?', [$admin['email']])) {
    db_exec(
        "INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'admin')",
        [$admin['name'], $admin['email'], password_hash($admin['password'], PASSWORD_DEFAULT)]
    );
    out('Đã tạo tài khoản admin: ' . $admin['email']);
}

$upcoming = (int) db_value('SELECT COUNT(*) FROM showtimes WHERE start_time > NOW()');
if ($upcoming < 20) {
    $n = generate_showtimes(7);
    out("Đã thêm $n suất chiếu cho 7 ngày tới.");
}

if (config('seed_demo')) {
    $n = seed_demo_bookings();
    if ($n > 0) {
        out("Đã tạo $n đơn đặt vé mẫu.");
    }
}

out('Hoàn tất.');
