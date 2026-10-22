<?php
declare(strict_types=1);

/**
 * Front controller: mọi request (trừ file tĩnh trong /assets) đều đi qua file này.
 * Apache chuyển về đây nhờ "FallbackResource /index.php" (xem docker/vhost.conf).
 */

// Khi chạy thử bằng PHP built-in server (php -S) thì để server tự trả file tĩnh
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    if (is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

$root = dirname(__DIR__);
require $root . '/lib/core.php';
require $root . '/lib/booking.php';
require $root . '/lib/schedule.php';
require $root . '/lib/handlers.php';
require $root . '/lib/admin.php';
require $root . '/lib/system.php';
require $root . '/lib/routes.php';

config(); // nạp cấu hình + múi giờ

set_exception_handler(function (Throwable $ex): void {
    app_log('unhandled_exception', [
        'type'    => get_class($ex),
        'message' => $ex->getMessage(),
        'file'    => basename($ex->getFile()) . ':' . $ex->getLine(),
        'path'    => $_SERVER['REQUEST_URI'] ?? '',
    ], 'error');
    if ($ex instanceof PDOException) {
        abort(503, 'Không kết nối được cơ sở dữ liệu. Vui lòng thử lại sau ít phút.');
    }
    abort(500, 'Đã có lỗi xảy ra. Mã lỗi đã được ghi vào log hệ thống.');
});

dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', request_path());
