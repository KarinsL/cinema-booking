<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Bảng định tuyến: [phương thức, mẫu đường dẫn, hàm xử lý]
 * ------------------------------------------------------------------ */

function routes(): array
{
    return [
        ['GET',  '#^/$#',                                'page_home'],
        ['GET',  '#^/movie/(\d+)$#',                     'page_movie'],
        ['GET',  '#^/showtime/(\d+)$#',                  'page_seats'],
        ['POST', '#^/showtime/(\d+)/hold$#',             'action_hold'],
        ['GET',  '#^/checkout/(CB[A-Z0-9]{8})$#',        'page_checkout'],
        ['POST', '#^/checkout/(CB[A-Z0-9]{8})/pay$#',    'action_pay'],
        ['POST', '#^/booking/(CB[A-Z0-9]{8})/cancel$#',  'action_cancel'],
        ['GET',  '#^/gateway/(PAY[A-Z0-9]{18})$#',       'page_gateway'],
        ['POST', '#^/gateway/(PAY[A-Z0-9]{18})$#',       'action_gateway'],
        ['GET',  '#^/ticket/(CB[A-Z0-9]{8})$#',          'page_ticket'],
        ['GET',  '#^/my-tickets$#',                      'page_my_tickets'],
        ['GET',  '#^/login$#',                           'page_login'],
        ['POST', '#^/login$#',                           'action_login'],
        ['GET',  '#^/register$#',                        'page_register'],
        ['POST', '#^/register$#',                        'action_register'],
        ['POST', '#^/logout$#',                          'action_logout'],
        ['GET',  '#^/admin$#',                           'admin_dashboard'],
        ['GET',  '#^/admin/movies$#',                    'admin_movies'],
        ['POST', '#^/admin/movies$#',                    'admin_movie_create'],
        ['POST', '#^/admin/movies/(\d+)/toggle$#',       'admin_movie_toggle'],
        ['GET',  '#^/admin/showtimes$#',                 'admin_showtimes'],
        ['POST', '#^/admin/showtimes$#',                 'admin_showtime_create'],
        ['POST', '#^/admin/showtimes/generate$#',        'admin_showtime_generate'],
        ['GET',  '#^/admin/bookings$#',                  'admin_bookings'],
    ];
}

function dispatch(string $method, string $path): never
{
    // Endpoint kỹ thuật: không mở session để không tạo file session cho mỗi lần healthcheck/scrape
    if ($path === '/health') {
        page_health();
    }
    if ($path === '/metrics') {
        page_metrics();
    }

    start_session();
    $allowed = [];
    foreach (routes() as [$m, $pattern, $handler]) {
        if (preg_match($pattern, $path, $matches)) {
            if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) {
                $allowed[] = $m;
                continue;
            }
            if ($method === 'POST') {
                verify_csrf();
            }
            array_shift($matches);
            $handler(...$matches);
            exit;
        }
    }
    if ($allowed) {
        header('Allow: ' . implode(', ', array_unique($allowed)));
        abort(405);
    }
    abort(404, 'Đường dẫn bạn truy cập không tồn tại.');
}
