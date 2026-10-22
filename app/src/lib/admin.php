<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Trang quản trị (chỉ role = admin)
 * ------------------------------------------------------------------ */

function admin_dashboard(): never
{
    require_admin();
    expire_stale_holds();
    $kpi = db_one(
        "SELECT
            (SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status = 'paid') AS revenue_total,
            (SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status = 'paid' AND DATE(paid_at) = CURDATE()) AS revenue_today,
            (SELECT COUNT(*) FROM booking_seats bs JOIN bookings b ON b.id = bs.booking_id
               WHERE b.status = 'paid' AND bs.is_active = 1) AS tickets_sold,
            (SELECT COUNT(*) FROM bookings WHERE status = 'pending') AS pending_count,
            (SELECT COUNT(*) FROM showtimes WHERE start_time > NOW() AND is_active = 1) AS upcoming_showtimes,
            (SELECT COUNT(*) FROM users WHERE role = 'customer') AS customers"
    );
    $byStatus = db_all('SELECT status, COUNT(*) AS n FROM bookings GROUP BY status ORDER BY status');
    $topMovies = db_all(
        "SELECT m.title, m.kind,
                (SELECT COUNT(*) FROM booking_seats bs
                   JOIN bookings b   ON b.id = bs.booking_id
                   JOIN showtimes st ON st.id = b.showtime_id
                  WHERE st.movie_id = m.id AND b.status = 'paid' AND bs.is_active = 1) AS tickets
         FROM movies m
         ORDER BY tickets DESC, m.id
         LIMIT 6"
    );
    $recent = db_all(
        "SELECT b.booking_code, b.status, b.total_amount, b.created_at, u.full_name, m.title, st.start_time
         FROM bookings b JOIN users u ON u.id = b.user_id
         JOIN showtimes st ON st.id = b.showtime_id JOIN movies m ON m.id = st.movie_id
         ORDER BY b.created_at DESC LIMIT 8"
    );
    render('admin/dashboard', compact('kpi', 'byStatus', 'topMovies', 'recent') + ['tab' => 'dashboard']);
}

function admin_movies(): never
{
    require_admin();
    $movies = db_all(
        "SELECT m.*, (SELECT COUNT(*) FROM showtimes st WHERE st.movie_id = m.id AND st.start_time > NOW()) AS upcoming
         FROM movies m ORDER BY m.kind, m.id"
    );
    render('admin/movies', ['movies' => $movies, 'tab' => 'movies', 'errors' => [], 'old' => []]);
}

function admin_movie_create(): never
{
    $admin = require_admin();
    $old = [
        'title'        => post_str('title', 150),
        'kind'         => post_str('kind', 10),
        'genre'        => post_str('genre', 80),
        'duration_min' => post_str('duration_min', 4),
        'age_rating'   => post_str('age_rating', 10),
        'description'  => post_str('description', 2000),
        'poster_color' => post_str('poster_color', 7),
    ];
    $errors = [];
    if (mb_strlen($old['title']) < 2) {
        $errors['title'] = 'Nhập tên phim/sự kiện.';
    }
    if (!in_array($old['kind'], ['movie', 'event'], true)) {
        $errors['kind'] = 'Chọn loại.';
    }
    if ($old['genre'] === '') {
        $errors['genre'] = 'Nhập thể loại.';
    }
    $duration = (int) $old['duration_min'];
    if ($duration < 30 || $duration > 300) {
        $errors['duration_min'] = 'Thời lượng từ 30 đến 300 phút.';
    }
    if (!in_array($old['age_rating'], ['P', 'K', 'T13', 'T16', 'T18'], true)) {
        $errors['age_rating'] = 'Chọn phân loại độ tuổi.';
    }
    if (mb_strlen($old['description']) < 10) {
        $errors['description'] = 'Mô tả tối thiểu 10 ký tự.';
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $old['poster_color'])) {
        $old['poster_color'] = '#334155';
    }
    if ($errors) {
        $movies = db_all("SELECT m.*, 0 AS upcoming FROM movies m ORDER BY m.kind, m.id");
        render('admin/movies', ['movies' => $movies, 'tab' => 'movies', 'errors' => $errors, 'old' => $old], 422);
    }
    db_exec(
        'INSERT INTO movies (title, kind, genre, duration_min, age_rating, description, poster_color) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$old['title'], $old['kind'], $old['genre'], $duration, $old['age_rating'], $old['description'], $old['poster_color']]
    );
    app_log('admin_movie_created', ['title' => $old['title'], 'by' => $admin['email']]);
    flash('success', 'Đã thêm "' . $old['title'] . '". Vào mục Suất chiếu để mở lịch bán vé.');
    redirect('/admin/movies');
}

function admin_movie_toggle(string $id): never
{
    $admin = require_admin();
    db_exec('UPDATE movies SET is_active = 1 - is_active WHERE id = ?', [(int) $id]);
    app_log('admin_movie_toggled', ['movie_id' => (int) $id, 'by' => $admin['email']]);
    flash('success', 'Đã cập nhật trạng thái mở bán.');
    redirect('/admin/movies');
}

function admin_showtimes(): never
{
    require_admin();
    render('admin/showtimes', admin_showtime_data() + ['tab' => 'showtimes', 'errors' => [], 'old' => []]);
}

function admin_showtime_data(): array
{
    $showtimes = db_all(
        "SELECT st.*, m.title, m.kind, r.name AS room_name,
                (SELECT COUNT(*) FROM seats s WHERE s.room_id = st.room_id) AS capacity,
                (SELECT COUNT(*) FROM booking_seats bs JOIN bookings b ON b.id = bs.booking_id
                   WHERE bs.showtime_id = st.id AND bs.is_active = 1 AND b.status = 'paid') AS sold,
                (SELECT COUNT(*) FROM booking_seats bs JOIN bookings b ON b.id = bs.booking_id
                   WHERE bs.showtime_id = st.id AND bs.is_active = 1 AND b.status = 'pending') AS held
         FROM showtimes st JOIN movies m ON m.id = st.movie_id JOIN rooms r ON r.id = st.room_id
         WHERE st.start_time > NOW()
         ORDER BY st.start_time
         LIMIT 120"
    );
    return [
        'showtimes' => $showtimes,
        'movies'    => db_all('SELECT id, title, kind FROM movies WHERE is_active = 1 ORDER BY kind, title'),
        'rooms'     => db_all('SELECT id, name FROM rooms ORDER BY id'),
    ];
}

function admin_showtime_create(): never
{
    $admin = require_admin();
    $old = [
        'movie_id'      => post_str('movie_id', 10),
        'room_id'       => post_str('room_id', 10),
        'start_time'    => post_str('start_time', 20),
        'base_price'    => post_str('base_price', 10),
        'vip_surcharge' => post_str('vip_surcharge', 10),
    ];
    $errors = [];
    if (!db_value('SELECT id FROM movies WHERE id = ? AND is_active = 1', [(int) $old['movie_id']])) {
        $errors['movie_id'] = 'Chọn phim/sự kiện đang mở bán.';
    }
    if (!db_value('SELECT id FROM rooms WHERE id = ?', [(int) $old['room_id']])) {
        $errors['room_id'] = 'Chọn phòng.';
    }
    $ts = strtotime(str_replace('T', ' ', $old['start_time']));
    if ($ts === false || $ts < time() + 600) {
        $errors['start_time'] = 'Giờ chiếu phải sau thời điểm hiện tại ít nhất 10 phút.';
    }
    $price = (int) $old['base_price'];
    $vip = (int) $old['vip_surcharge'];
    if ($price < 10000 || $price > 5000000) {
        $errors['base_price'] = 'Giá vé từ 10.000 đến 5.000.000 đ.';
    }
    if ($vip < 0 || $vip > 5000000) {
        $errors['vip_surcharge'] = 'Phụ thu VIP không hợp lệ.';
    }
    if (!$errors) {
        try {
            db_exec(
                'INSERT INTO showtimes (movie_id, room_id, start_time, base_price, vip_surcharge) VALUES (?, ?, ?, ?, ?)',
                [(int) $old['movie_id'], (int) $old['room_id'], date('Y-m-d H:i:00', $ts), $price, $vip]
            );
            app_log('admin_showtime_created', ['movie_id' => (int) $old['movie_id'], 'start' => date('c', $ts), 'by' => $admin['email']]);
            flash('success', 'Đã thêm suất chiếu ' . date('H:i d/m/Y', $ts) . '.');
            redirect('/admin/showtimes');
        } catch (PDOException $ex) {
            if ((int) ($ex->errorInfo[1] ?? 0) !== 1062) {
                throw $ex;
            }
            $errors['start_time'] = 'Phòng này đã có suất chiếu đúng giờ đó.';
        }
    }
    render('admin/showtimes', admin_showtime_data() + ['tab' => 'showtimes', 'errors' => $errors, 'old' => $old], 422);
}

function admin_showtime_generate(): never
{
    $admin = require_admin();
    $n = generate_showtimes(7);
    app_log('admin_showtimes_generated', ['created' => $n, 'by' => $admin['email']]);
    flash('success', $n > 0 ? "Đã tạo thêm $n suất chiếu cho 7 ngày tới." : 'Lịch 7 ngày tới đã đủ, không tạo thêm suất nào.');
    redirect('/admin/showtimes');
}

function admin_bookings(): never
{
    require_admin();
    expire_stale_holds();
    $status = query_str('status');
    $params = [];
    $where = '1 = 1';
    if (in_array($status, ['pending', 'paid', 'cancelled', 'expired'], true)) {
        $where = 'b.status = ?';
        $params[] = $status;
    } else {
        $status = '';
    }
    $bookings = db_all(
        "SELECT b.booking_code, b.status, b.seat_count, b.total_amount, b.created_at, b.paid_at,
                u.full_name, u.email, m.title, st.start_time, r.name AS room_name,
                (SELECT p.method FROM payments p WHERE p.booking_id = b.id AND p.status = 'success' LIMIT 1) AS method
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         JOIN showtimes st ON st.id = b.showtime_id
         JOIN movies m ON m.id = st.movie_id
         JOIN rooms r ON r.id = st.room_id
         WHERE $where
         ORDER BY b.created_at DESC
         LIMIT 100",
        $params
    );
    render('admin/bookings', ['bookings' => $bookings, 'status' => $status, 'tab' => 'bookings']);
}
