<?php
declare(strict_types=1);

/**
 * Cấu hình ứng dụng. Mọi thông tin nhạy cảm (mật khẩu DB, tài khoản admin)
 * được đọc từ biến môi trường do docker-compose truyền vào, không ghi cứng trong mã.
 */

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

return [
    'app_name'     => env('APP_NAME', 'CineTicket'),
    'timezone'     => env('TZ', 'Asia/Ho_Chi_Minh'),
    'hold_minutes' => (int) env('APP_HOLD_MINUTES', '10'),   // thời gian giữ ghế chờ thanh toán
    'max_seats'    => (int) env('APP_MAX_SEATS', '8'),       // số ghế tối đa mỗi đơn
    'db' => [
        'host'     => env('DB_HOST', 'db'),
        'port'     => (int) env('DB_PORT', '3306'),
        'name'     => env('DB_NAME', 'cinema_db'),
        'user'     => env('DB_USER', 'cinema_app'),
        'password' => env('DB_PASSWORD', ''),
    ],
    'admin' => [
        'email'    => env('APP_ADMIN_EMAIL', 'admin@cinema.local'),
        'password' => env('APP_ADMIN_PASSWORD', ''),
        'name'     => env('APP_ADMIN_NAME', 'Quản trị viên'),
    ],
    'seed_demo' => env('APP_SEED_DEMO', 'true') === 'true',
];
