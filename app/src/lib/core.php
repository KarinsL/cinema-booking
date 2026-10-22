<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Hàm lõi: cấu hình, kết nối CSDL, session, log, render giao diện
 * ------------------------------------------------------------------ */

const APP_ROOT = __DIR__ . '/..';

function config(?string $key = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config.php';
        date_default_timezone_set($config['timezone']);
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

/** Kết nối PDO dùng chung trong một request (prepared statement thật, báo lỗi bằng exception). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
        ]);
    }
    return $pdo;
}

function db_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_value(string $sql, array $params = []): mixed
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/* ---------------------------- Request ---------------------------- */

function is_https(): bool
{
    // Nginx kết thúc TLS rồi chuyển tiếp HTTP nội bộ, báo giao thức gốc qua X-Forwarded-Proto
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    return $proto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

function client_ip(): string
{
    // App chỉ nhận request từ Nginx trong mạng nội bộ Docker nên tin header X-Real-IP
    $ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '-';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '-';
}

function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);
    return $path === '/' ? '/' : rtrim($path, '/');
}

function post_str(string $key, int $maxLen = 255): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $maxLen);
}

function query_str(string $key, int $maxLen = 100): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $maxLen) : '';
}

/* ---------------------------- Session ---------------------------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        app_log('csrf_rejected', ['path' => request_path()], 'warning');
        abort(403, 'Phiên làm việc đã hết hạn hoặc yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }
}

/* ----------------------------- Auth ------------------------------ */

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $user = db_one('SELECT id, full_name, email, phone, role FROM users WHERE id = ?', [(int) $_SESSION['uid']]);
        }
    }
    return $user;
}

function require_login(): array
{
    $u = current_user();
    if ($u === null) {
        flash('info', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('/login?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        app_log('admin_forbidden', ['email' => $u['email'], 'path' => request_path()], 'warning');
        abort(403, 'Bạn không có quyền truy cập trang quản trị.');
    }
    return $u;
}

function safe_next(string $next): string
{
    // Chỉ cho chuyển hướng về đường dẫn nội bộ, chặn open redirect (//evil.com, https://...)
    if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')) {
        return '/';
    }
    return $next;
}

/* ------------------------------ Log ------------------------------ */

/**
 * Ghi log nghiệp vụ dạng JSON ra stderr của container.
 * Docker thu stderr, Promtail đọc qua Docker API rồi đẩy lên Loki,
 * nên có thể lọc bằng LogQL:  {service="app"} | json | event="payment_success"
 */
function app_log(string $event, array $context = [], string $level = 'info'): void
{
    $record = [
        'ts'      => date('c'),
        'level'   => $level,
        'service' => 'cinema-app',
        'event'   => $event,
        'ip'      => client_ip(),
        'user_id' => $_SESSION['uid'] ?? null,
    ] + $context;
    file_put_contents('php://stderr', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

/* ---------------------------- Response --------------------------- */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to, int $code = 303): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function render(string $view, array $data = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $data['flashes'] = session_status() === PHP_SESSION_ACTIVE ? take_flashes() : [];
    if (!array_key_exists('user', $data)) {
        try {
            $data['user'] = session_status() === PHP_SESSION_ACTIVE ? current_user() : null;
        } catch (Throwable $e) {
            $data['user'] = null; // DB lỗi vẫn hiển thị được trang báo lỗi
        }
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/views/layout.php';
    exit;
}

function abort(int $status, string $message = ''): never
{
    $titles = [
        403 => 'Không có quyền truy cập',
        404 => 'Không tìm thấy trang',
        405 => 'Phương thức không được hỗ trợ',
        500 => 'Lỗi hệ thống',
        503 => 'Hệ thống tạm thời gián đoạn',
    ];
    render('error', [
        'title'   => $titles[$status] ?? 'Có lỗi xảy ra',
        'status'  => $status,
        'message' => $message !== '' ? $message : ($titles[$status] ?? ''),
    ], $status);
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------------------------- Định dạng --------------------------- */

function money(int|string|null $amount): string
{
    return number_format((int) $amount, 0, ',', '.') . ' đ';
}

function vn_weekday(string $date): string
{
    $names = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    return $names[(int) date('w', strtotime($date))];
}

function fmt_date(string $datetime): string
{
    return vn_weekday($datetime) . ', ' . date('d/m/Y', strtotime($datetime));
}

function fmt_time(string $datetime): string
{
    return date('H:i', strtotime($datetime));
}

function fmt_datetime(?string $datetime): string
{
    return $datetime ? date('H:i d/m/Y', strtotime($datetime)) : '';
}

function initials(string $title): string
{
    $words = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', '', $title) ?? '') ?: [];
    $letters = '';
    foreach (array_slice($words, 0, 3) as $w) {
        $letters .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $letters;
}

function status_label(string $status): string
{
    return [
        'pending'   => 'Đang giữ ghế',
        'paid'      => 'Đã thanh toán',
        'cancelled' => 'Đã hủy',
        'expired'   => 'Hết hạn',
        'success'   => 'Thành công',
        'failed'    => 'Thất bại',
    ][$status] ?? $status;
}

function method_label(string $method): string
{
    return [
        'ewallet' => 'Ví điện tử (giả lập)',
        'qrcode'  => 'Chuyển khoản QR (giả lập)',
        'card'    => 'Thẻ ngân hàng (giả lập)',
    ][$method] ?? $method;
}

function kind_label(string $kind): string
{
    return $kind === 'event' ? 'Sự kiện' : 'Phim';
}

/** "Poster" vẽ bằng CSS (không dùng ảnh ngoài): màu nền riêng + chữ viết tắt tên phim. */
function poster(array $m, string $class = ''): string
{
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($m['poster_color'] ?? '')) ? $m['poster_color'] : '#334155';
    return '<div class="poster ' . e($class) . '" style="--poster:' . e($color) . '">'
        . '<span class="poster-kind">' . e(kind_label($m['kind'])) . '</span>'
        . '<span class="poster-initials">' . e(initials($m['title'])) . '</span>'
        . '<span class="poster-age age-' . e(strtolower($m['age_rating'])) . '">' . e($m['age_rating']) . '</span>'
        . '</div>';
}

function badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(status_label($status)) . '</span>';
}
