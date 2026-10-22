<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Trang công khai, đặt vé, thanh toán giả lập, tài khoản
 * ------------------------------------------------------------------ */

function page_home(): never
{
    $kind = query_str('kind');
    $kind = in_array($kind, ['movie', 'event'], true) ? $kind : '';
    $date = query_str('date');
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';

    $params = [];
    $where = 'm.is_active = 1';
    if ($kind !== '') {
        $where .= ' AND m.kind = ?';
        $params[] = $kind;
    }
    $movies = db_all("SELECT * FROM movies m WHERE $where ORDER BY m.kind, m.id", $params);

    // Suất chiếu sắp tới (theo ngày lọc hoặc 7 ngày tới) gom theo phim
    $stParams = [];
    $stWhere = 'st.is_active = 1 AND st.start_time > NOW()';
    if ($date !== '') {
        $stWhere .= ' AND DATE(st.start_time) = ?';
        $stParams[] = $date;
    } else {
        $stWhere .= ' AND st.start_time < DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
    }
    $rows = db_all(
        "SELECT st.id, st.movie_id, st.start_time, st.base_price, r.name AS room_name
         FROM showtimes st JOIN rooms r ON r.id = st.room_id
         WHERE $stWhere ORDER BY st.start_time",
        $stParams
    );
    $byMovie = [];
    foreach ($rows as $r) {
        $byMovie[$r['movie_id']][] = $r;
    }

    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $days[] = date('Y-m-d', strtotime("+$i day"));
    }
    render('home', compact('movies', 'byMovie', 'kind', 'date', 'days'));
}

function page_movie(string $id): never
{
    $movie = db_one('SELECT * FROM movies WHERE id = ? AND is_active = 1', [(int) $id]);
    if (!$movie) {
        abort(404, 'Phim hoặc sự kiện không tồn tại hoặc đã ngừng chiếu.');
    }
    $rows = db_all(
        "SELECT st.id, st.start_time, st.base_price, st.vip_surcharge, r.name AS room_name,
                (SELECT COUNT(*) FROM seats s WHERE s.room_id = st.room_id) AS capacity,
                (SELECT COUNT(*) FROM booking_seats bs WHERE bs.showtime_id = st.id AND bs.is_active = 1) AS taken
         FROM showtimes st JOIN rooms r ON r.id = st.room_id
         WHERE st.movie_id = ? AND st.is_active = 1 AND st.start_time > NOW()
         ORDER BY st.start_time",
        [(int) $id]
    );
    $byDate = [];
    foreach ($rows as $r) {
        $byDate[substr($r['start_time'], 0, 10)][] = $r;
    }
    render('movie', compact('movie', 'byDate'));
}

function page_seats(string $id): never
{
    expire_stale_holds();
    $st = find_showtime((int) $id);
    if (!$st || !$st['is_active']) {
        abort(404, 'Suất chiếu không tồn tại.');
    }
    $map = seat_map((int) $id);
    $counts = ['free' => 0, 'held' => 0, 'sold' => 0];
    foreach ($map as $row) {
        foreach ($row as $s) {
            $counts[$s['state']]++;
        }
    }
    render('seats', [
        'st'        => $st,
        'map'       => $map,
        'counts'    => $counts,
        'maxSeats'  => (int) config('max_seats'),
        'holdMin'   => (int) config('hold_minutes'),
    ]);
}

function action_hold(string $id): never
{
    $user = require_login();
    $seatIds = $_POST['seat_ids'] ?? [];
    if (!is_array($seatIds)) {
        $seatIds = [];
    }
    try {
        $hold = create_hold((int) $user['id'], (int) $id, $seatIds);
    } catch (BookingError $ex) {
        flash('error', $ex->getMessage());
        redirect('/showtime/' . (int) $id);
    }
    flash('success', 'Đã giữ ghế ' . implode(', ', $hold['seats']) . '. Vui lòng thanh toán trong ' . config('hold_minutes') . ' phút.');
    redirect('/checkout/' . $hold['code']);
}

function page_checkout(string $code): never
{
    $user = require_login();
    $b = load_owned_booking($code, $user);
    if ($b['status'] === 'paid') {
        redirect('/ticket/' . $code);
    }
    $lastPayment = db_one('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [(int) $b['id']]);
    render('checkout', ['b' => $b, 'lastPayment' => $lastPayment]);
}

function action_pay(string $code): never
{
    $user = require_login();
    $b = load_owned_booking($code, $user);
    try {
        $ref = start_payment($b, post_str('method', 10));
    } catch (BookingError $ex) {
        flash('error', $ex->getMessage());
        redirect('/checkout/' . $code);
    }
    redirect('/gateway/' . $ref);
}

function load_gateway_payment(string $ref, array $user): array
{
    $p = find_payment($ref);
    if (!$p || ((int) $p['user_id'] !== (int) $user['id'] && $user['role'] !== 'admin')) {
        abort(404, 'Không tìm thấy giao dịch.');
    }
    return $p;
}

function page_gateway(string $ref): never
{
    $user = require_login();
    $p = load_gateway_payment($ref, $user);
    if ($p['status'] !== 'pending') {
        redirect($p['status'] === 'success' ? '/ticket/' . $p['booking_code'] : '/checkout/' . $p['booking_code']);
    }
    $b = find_booking($p['booking_code']);
    render('gateway', ['p' => $p, 'b' => $b]);
}

function action_gateway(string $ref): never
{
    $user = require_login();
    $p = load_gateway_payment($ref, $user);
    $result = complete_payment($ref, post_str('result', 10) === 'success');
    switch ($result) {
        case 'paid':
            flash('success', 'Thanh toán thành công. Chúc bạn xem phim vui vẻ!');
            redirect('/ticket/' . $p['booking_code']);
        case 'failed':
            flash('error', 'Giao dịch thất bại (mô phỏng). Ghế vẫn được giữ, bạn có thể thanh toán lại.');
            redirect('/checkout/' . $p['booking_code']);
        case 'expired':
            flash('error', 'Đơn đã hết thời gian giữ ghế nên giao dịch bị hủy. Vui lòng đặt lại.');
            redirect('/checkout/' . $p['booking_code']);
        default:
            redirect('/ticket/' . $p['booking_code']);
    }
}

function action_cancel(string $code): never
{
    $user = require_login();
    $b = load_owned_booking($code, $user);
    if (cancel_booking($b)) {
        flash('success', 'Đã hủy đơn ' . $code . ' và trả lại ghế.');
    } else {
        flash('error', 'Chỉ hủy được đơn đang giữ ghế (chưa thanh toán).');
    }
    redirect('/my-tickets');
}

function page_ticket(string $code): never
{
    $user = require_login();
    $b = load_owned_booking($code, $user);
    $payment = db_one("SELECT * FROM payments WHERE booking_id = ? AND status = 'success' ORDER BY id DESC LIMIT 1", [(int) $b['id']]);
    render('ticket', ['b' => $b, 'payment' => $payment]);
}

function page_my_tickets(): never
{
    $user = require_login();
    expire_stale_holds();
    $bookings = db_all(
        "SELECT b.booking_code, b.status, b.seat_count, b.total_amount, b.created_at, b.paid_at,
                st.start_time, m.title, m.kind, r.name AS room_name
         FROM bookings b
         JOIN showtimes st ON st.id = b.showtime_id
         JOIN movies m ON m.id = st.movie_id
         JOIN rooms r ON r.id = st.room_id
         WHERE b.user_id = ?
         ORDER BY b.created_at DESC
         LIMIT 50",
        [(int) $user['id']]
    );
    render('my_tickets', compact('bookings'));
}

/* ---------------------------- Tài khoản --------------------------- */

function page_login(): never
{
    if (current_user()) {
        redirect('/');
    }
    render('login', ['next' => safe_next(query_str('next', 200)), 'email' => '']);
}

function action_login(): never
{
    $email = mb_strtolower(post_str('email', 150));
    $password = $_POST['password'] ?? '';
    $next = safe_next(post_str('next', 200));
    $u = is_string($password) ? db_one('SELECT * FROM users WHERE email = ?', [$email]) : null;
    // Luôn chạy password_verify (kể cả khi email không tồn tại) để thời gian phản hồi như nhau
    $hash = $u['password_hash'] ?? '$2y$10$M4c1PCxM3sPKDqGkLg1Fp.YfXacEygbkp76Kcvhof887MSoPlkYJm';
    $ok = password_verify(is_string($password) ? $password : '', $hash) && $u !== null;

    if (!$ok) {
        app_log('login_failed', ['email' => $email], 'warning');
        usleep(300000); // làm chậm thử mật khẩu hàng loạt
        flash('error', 'Email hoặc mật khẩu không đúng.');
        render('login', ['next' => $next, 'email' => $email], 401);
    }
    session_regenerate_id(true); // chống session fixation
    $_SESSION['uid'] = (int) $u['id'];
    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $u['id']]);
    app_log('login_success', ['email' => $email, 'role' => $u['role']]);
    flash('success', 'Xin chào ' . $u['full_name'] . '!');
    redirect($u['role'] === 'admin' && $next === '/' ? '/admin' : $next);
}

function page_register(): never
{
    if (current_user()) {
        redirect('/');
    }
    render('register', ['old' => ['full_name' => '', 'email' => '', 'phone' => ''], 'errors' => []]);
}

function action_register(): never
{
    $old = [
        'full_name' => post_str('full_name', 100),
        'email'     => mb_strtolower(post_str('email', 150)),
        'phone'     => post_str('phone', 20),
    ];
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $password = is_string($password) ? $password : '';
    $errors = [];

    if (mb_strlen($old['full_name']) < 2) {
        $errors['full_name'] = 'Vui lòng nhập họ tên.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ.';
    } elseif (db_value('SELECT id FROM users WHERE email = ?', [$old['email']])) {
        $errors['email'] = 'Email này đã được đăng ký.';
    }
    if ($old['phone'] !== '' && !preg_match('/^0\d{9,10}$/', $old['phone'])) {
        $errors['phone'] = 'Số điện thoại gồm 10 hoặc 11 chữ số, bắt đầu bằng 0.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Mật khẩu tối thiểu 8 ký tự, có cả chữ và số.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Mật khẩu nhập lại không khớp.';
    }

    if ($errors) {
        render('register', compact('old', 'errors'), 422);
    }

    try {
        db_exec(
            "INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')",
            [$old['full_name'], $old['email'], $old['phone'] ?: null, password_hash($password, PASSWORD_DEFAULT)]
        );
    } catch (PDOException $ex) {
        if ((int) ($ex->errorInfo[1] ?? 0) !== 1062) {
            throw $ex;
        }
        render('register', ['old' => $old, 'errors' => ['email' => 'Email này đã được đăng ký.']], 422);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) db()->lastInsertId();
    app_log('user_registered', ['email' => $old['email']]);
    flash('success', 'Tạo tài khoản thành công. Chọn phim và đặt vé thôi!');
    redirect('/');
}

function action_logout(): never
{
    app_log('logout');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    start_session();
    flash('info', 'Bạn đã đăng xuất.');
    redirect('/');
}
