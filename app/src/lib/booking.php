<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Nghiệp vụ đặt vé: giữ ghế, thanh toán giả lập, hủy, hết hạn
 * ------------------------------------------------------------------ */

final class BookingError extends RuntimeException
{
}

/** Chuyển các đơn "đang giữ" quá hạn sang expired và trả ghế về trạng thái trống. */
function expire_stale_holds(): void
{
    $pdo = db();
    $pdo->exec("UPDATE bookings SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()");
    $pdo->exec("UPDATE booking_seats SET is_active = NULL
                WHERE is_active = 1
                  AND booking_id IN (SELECT id FROM bookings WHERE status IN ('expired', 'cancelled'))");
}

function find_showtime(int $id): ?array
{
    return db_one(
        "SELECT st.*, m.title, m.kind, m.genre, m.duration_min, m.age_rating, m.poster_color,
                r.name AS room_name, r.room_type, r.row_count, r.seats_per_row,
                (st.start_time > NOW()) AS is_upcoming
         FROM showtimes st
         JOIN movies m ON m.id = st.movie_id
         JOIN rooms r  ON r.id = st.room_id
         WHERE st.id = ?",
        [$id]
    );
}

/** Sơ đồ ghế của một suất: mỗi ghế kèm trạng thái free / held / sold. */
function seat_map(int $showtimeId): array
{
    $rows = db_all(
        "SELECT s.id, s.row_label, s.seat_number, s.seat_type,
                CASE
                  WHEN b.status = 'paid'    THEN 'sold'
                  WHEN b.status = 'pending' THEN 'held'
                  ELSE 'free'
                END AS state
         FROM showtimes st
         JOIN seats s ON s.room_id = st.room_id
         LEFT JOIN booking_seats bs ON bs.seat_id = s.id AND bs.showtime_id = st.id AND bs.is_active = 1
         LEFT JOIN bookings b ON b.id = bs.booking_id
         WHERE st.id = ?
         ORDER BY s.row_label, s.seat_number",
        [$showtimeId]
    );
    $map = [];
    foreach ($rows as $r) {
        $map[$r['row_label']][] = $r;
    }
    return $map;
}

function seat_price(array $showtime, string $seatType): int
{
    return (int) $showtime['base_price'] + ($seatType === 'vip' ? (int) $showtime['vip_surcharge'] : 0);
}

function random_code(int $length, string $prefix = ''): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // bỏ O, 0, I, 1 cho dễ đọc
    $code = $prefix;
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

/**
 * Giữ ghế cho người dùng trong N phút. Toàn bộ trong một transaction:
 * nếu có ghế đã bị người khác giữ, chỉ mục UNIQUE báo lỗi 1062 và mọi thứ được hoàn tác.
 */
function create_hold(int $userId, int $showtimeId, array $seatIds): array
{
    $seatIds = array_values(array_unique(array_filter(array_map('intval', $seatIds), fn ($v) => $v > 0)));
    $max = (int) config('max_seats');
    if (count($seatIds) === 0) {
        throw new BookingError('Bạn chưa chọn ghế nào.');
    }
    if (count($seatIds) > $max) {
        throw new BookingError("Mỗi đơn chỉ được chọn tối đa {$max} ghế.");
    }

    expire_stale_holds();

    $st = find_showtime($showtimeId);
    if (!$st || !$st['is_active'] || !$st['is_upcoming']) {
        throw new BookingError('Suất chiếu không tồn tại hoặc đã bắt đầu.');
    }

    $existing = db_one(
        "SELECT booking_code FROM bookings
         WHERE user_id = ? AND showtime_id = ? AND status = 'pending' AND expires_at > NOW()",
        [$userId, $showtimeId]
    );
    if ($existing) {
        throw new BookingError('Bạn đang có một đơn giữ ghế chưa thanh toán cho suất này (mã ' . $existing['booking_code'] . '). Hãy thanh toán hoặc hủy đơn đó trước.');
    }

    $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
    $seats = db_all(
        "SELECT id, row_label, seat_number, seat_type FROM seats WHERE room_id = ? AND id IN ($placeholders)
         ORDER BY row_label, seat_number",
        array_merge([(int) $st['room_id']], $seatIds)
    );
    if (count($seats) !== count($seatIds)) {
        throw new BookingError('Có ghế không thuộc phòng chiếu này.');
    }

    $total = 0;
    foreach ($seats as &$s) {
        $s['price'] = seat_price($st, $s['seat_type']);
        $total += $s['price'];
    }
    unset($s);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $code = random_code(8, 'CB');
        db_exec(
            "INSERT INTO bookings (booking_code, user_id, showtime_id, seat_count, total_amount, status, expires_at)
             VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL ? MINUTE))",
            [$code, $userId, $showtimeId, count($seats), $total, (int) config('hold_minutes')]
        );
        $bookingId = (int) $pdo->lastInsertId();

        $values = [];
        $params = [];
        foreach ($seats as $s) {
            $values[] = '(?, ?, ?, ?, 1)';
            array_push($params, $bookingId, $showtimeId, (int) $s['id'], $s['price']);
        }
        db_exec(
            'INSERT INTO booking_seats (booking_id, showtime_id, seat_id, price, is_active) VALUES ' . implode(',', $values),
            $params
        );
        $pdo->commit();
    } catch (PDOException $ex) {
        $pdo->rollBack();
        if ((int) ($ex->errorInfo[1] ?? 0) === 1062) {
            app_log('seat_conflict', ['showtime_id' => $showtimeId, 'seats' => $seatIds], 'warning');
            throw new BookingError('Một số ghế bạn chọn vừa được người khác giữ. Vui lòng chọn ghế khác.');
        }
        throw $ex;
    }

    $labels = array_map(fn ($s) => $s['row_label'] . $s['seat_number'], $seats);
    app_log('booking_created', [
        'booking_code' => $code,
        'showtime_id'  => $showtimeId,
        'seats'        => implode(',', $labels),
        'amount'       => $total,
    ]);
    return ['code' => $code, 'total' => $total, 'seats' => $labels];
}

/** Lấy đơn kèm thông tin suất chiếu, phim, phòng và danh sách ghế. */
function find_booking(string $code): ?array
{
    $b = db_one(
        "SELECT b.*, TIMESTAMPDIFF(SECOND, NOW(), b.expires_at) AS seconds_left,
                u.full_name, u.email,
                st.start_time, st.room_id, m.title, m.kind, m.genre, m.duration_min, m.age_rating, m.poster_color,
                r.name AS room_name
         FROM bookings b
         JOIN users u     ON u.id = b.user_id
         JOIN showtimes st ON st.id = b.showtime_id
         JOIN movies m    ON m.id = st.movie_id
         JOIN rooms r     ON r.id = st.room_id
         WHERE b.booking_code = ?",
        [$code]
    );
    if (!$b) {
        return null;
    }
    $b['seats'] = db_all(
        "SELECT s.row_label, s.seat_number, s.seat_type, bs.price
         FROM booking_seats bs JOIN seats s ON s.id = bs.seat_id
         WHERE bs.booking_id = ?
         ORDER BY s.row_label, s.seat_number",
        [(int) $b['id']]
    );
    $b['seat_labels'] = implode(', ', array_map(fn ($s) => $s['row_label'] . $s['seat_number'], $b['seats']));
    return $b;
}

/** Chỉ chủ đơn hoặc admin được xem/thao tác. */
function load_owned_booking(string $code, array $user): array
{
    if (!preg_match('/^CB[A-Z0-9]{8}$/', $code)) {
        abort(404, 'Mã đặt vé không hợp lệ.');
    }
    expire_stale_holds();
    $b = find_booking($code);
    if (!$b || ((int) $b['user_id'] !== (int) $user['id'] && $user['role'] !== 'admin')) {
        abort(404, 'Không tìm thấy đơn đặt vé.');
    }
    return $b;
}

function start_payment(array $booking, string $method): string
{
    if (!in_array($method, ['ewallet', 'qrcode', 'card'], true)) {
        throw new BookingError('Vui lòng chọn phương thức thanh toán.');
    }
    if ($booking['status'] !== 'pending' || (int) $booking['seconds_left'] <= 0) {
        throw new BookingError('Đơn đã hết thời gian giữ ghế hoặc không còn ở trạng thái chờ thanh toán.');
    }
    $ref = 'PAY' . date('ymdHis') . random_code(6);
    db_exec(
        "INSERT INTO payments (booking_id, method, amount, status, transaction_ref) VALUES (?, ?, ?, 'pending', ?)",
        [(int) $booking['id'], $method, (int) $booking['total_amount'], $ref]
    );
    app_log('payment_started', ['booking_code' => $booking['booking_code'], 'ref' => $ref, 'method' => $method, 'amount' => (int) $booking['total_amount']]);
    return $ref;
}

function find_payment(string $ref): ?array
{
    return db_one(
        "SELECT p.*, b.booking_code, b.user_id, b.status AS booking_status
         FROM payments p JOIN bookings b ON b.id = p.booking_id
         WHERE p.transaction_ref = ?",
        [$ref]
    );
}

/**
 * Cổng thanh toán giả lập trả kết quả. Khóa dòng (SELECT ... FOR UPDATE) để
 * hai lần bấm liên tiếp không xử lý một giao dịch hai lần.
 * Trả về: paid | failed | expired | done (đã xử lý trước đó)
 */
function complete_payment(string $ref, bool $success): string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $p = db_one('SELECT * FROM payments WHERE transaction_ref = ? FOR UPDATE', [$ref]);
        if (!$p) {
            throw new BookingError('Không tìm thấy giao dịch.');
        }
        $b = db_one(
            'SELECT id, booking_code, status, (expires_at < NOW()) AS is_expired FROM bookings WHERE id = ? FOR UPDATE',
            [(int) $p['booking_id']]
        );
        if ($p['status'] !== 'pending') {
            $pdo->commit();
            return 'done';
        }
        if (!$success) {
            db_exec("UPDATE payments SET status = 'failed', completed_at = NOW() WHERE id = ?", [(int) $p['id']]);
            $pdo->commit();
            app_log('payment_failed', ['booking_code' => $b['booking_code'], 'ref' => $ref, 'reason' => 'declined'], 'warning');
            return 'failed';
        }
        if ($b['status'] !== 'pending' || (int) $b['is_expired'] === 1) {
            db_exec("UPDATE payments SET status = 'failed', completed_at = NOW() WHERE id = ?", [(int) $p['id']]);
            db_exec("UPDATE bookings SET status = 'expired' WHERE id = ? AND status = 'pending'", [(int) $b['id']]);
            db_exec('UPDATE booking_seats SET is_active = NULL WHERE booking_id = ? AND is_active = 1', [(int) $b['id']]);
            $pdo->commit();
            app_log('payment_failed', ['booking_code' => $b['booking_code'], 'ref' => $ref, 'reason' => 'hold_expired'], 'warning');
            return 'expired';
        }
        db_exec("UPDATE payments SET status = 'success', completed_at = NOW() WHERE id = ?", [(int) $p['id']]);
        db_exec("UPDATE bookings SET status = 'paid', paid_at = NOW() WHERE id = ?", [(int) $b['id']]);
        $pdo->commit();
        app_log('payment_success', ['booking_code' => $b['booking_code'], 'ref' => $ref, 'method' => $p['method'], 'amount' => (int) $p['amount']]);
        return 'paid';
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function cancel_booking(array $booking): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    $n = db_exec("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status = 'pending'", [(int) $booking['id']]);
    if ($n > 0) {
        // Chỉ trả ghế khi đơn thật sự chuyển sang "đã hủy" (đơn đã thanh toán thì giữ nguyên)
        db_exec('UPDATE booking_seats SET is_active = NULL WHERE booking_id = ? AND is_active = 1', [(int) $booking['id']]);
    }
    $pdo->commit();
    if ($n > 0) {
        app_log('booking_cancelled', ['booking_code' => $booking['booking_code']]);
    }
    return $n > 0;
}
