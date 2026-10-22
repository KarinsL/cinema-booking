<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Sinh lịch chiếu mẫu và dữ liệu đặt vé mẫu
 * ------------------------------------------------------------------ */

/**
 * Tạo suất chiếu cho $days ngày tới, xoay vòng các phim/sự kiện đang mở bán.
 * Dùng INSERT IGNORE: suất trùng (cùng phòng, cùng giờ) sẽ bị bỏ qua,
 * nên chạy lại nhiều lần không sinh dữ liệu trùng.
 */
function generate_showtimes(int $days = 7): int
{
    $movies = db_all("SELECT id FROM movies WHERE is_active = 1 AND kind = 'movie' ORDER BY id");
    $events = db_all("SELECT id FROM movies WHERE is_active = 1 AND kind = 'event' ORDER BY id");
    $rooms = db_all('SELECT id, room_type FROM rooms ORDER BY id');

    // Khung giờ cố định cho từng phòng chiếu
    $cinemaSlots = [
        ['09:00', 60000], ['11:30', 70000], ['14:00', 75000],
        ['16:30', 75000], ['19:30', 90000], ['22:00', 80000],
    ];
    $created = 0;
    $sql = 'INSERT IGNORE INTO showtimes (movie_id, room_id, start_time, base_price, vip_surcharge) VALUES (?, ?, ?, ?, ?)';

    for ($d = 0; $d < $days; $d++) {
        $date = date('Y-m-d', strtotime("+{$d} day"));
        $cinemaIndex = 0;
        foreach ($rooms as $room) {
            if ($room['room_type'] === 'cinema') {
                if (!$movies) {
                    continue;
                }
                foreach ($cinemaSlots as $i => [$time, $price]) {
                    // Mỗi phòng xoay vòng một cặp phim khác nhau
                    $movie = $movies[($cinemaIndex * 2 + $i % 2) % count($movies)];
                    $extra = $cinemaIndex === 1 ? 15000 : 0; // phòng 3D đắt hơn
                    $created += db_exec($sql, [(int) $movie['id'], (int) $room['id'], "$date $time:00", $price + $extra, 20000]);
                }
                $cinemaIndex++;
            } elseif ($events && $d > 0) {
                // Hội trường: mỗi ngày một sự kiện lúc 19:30, xen kẽ các sự kiện
                $event = $events[$d % count($events)];
                $created += db_exec($sql, [(int) $event['id'], (int) $room['id'], "$date 19:30:00", 250000, 150000]);
            }
        }
    }
    return $created;
}

/** Tạo vài đơn đã thanh toán / đã hủy để trang quản trị và Grafana có số liệu ngay khi demo. */
function seed_demo_bookings(): int
{
    if ((int) db_value('SELECT COUNT(*) FROM bookings') > 0) {
        return 0;
    }
    $email = 'khachmau@cinema.local';
    $uid = db_value('SELECT id FROM users WHERE email = ?', [$email]);
    if (!$uid) {
        db_exec(
            "INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')",
            ['Khách mẫu (dữ liệu demo)', $email, '0900000000', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]
        );
        $uid = db()->lastInsertId();
    }
    $uid = (int) $uid;

    $showtimes = db_all(
        "SELECT st.id, st.base_price, st.vip_surcharge, st.room_id
         FROM showtimes st WHERE st.start_time > NOW() ORDER BY st.start_time LIMIT 12"
    );
    $made = 0;
    $methods = ['ewallet', 'qrcode', 'card'];
    foreach ($showtimes as $i => $st) {
        $want = 2 + ($i % 3);
        $free = db_all(
            "SELECT s.id, s.seat_type FROM seats s
             WHERE s.room_id = ? AND s.id NOT IN (
                 SELECT seat_id FROM booking_seats WHERE showtime_id = ? AND is_active = 1)
             ORDER BY RAND() LIMIT $want",
            [(int) $st['room_id'], (int) $st['id']]
        );
        if (!$free) {
            continue;
        }
        $status = ($i % 5 === 4) ? 'cancelled' : 'paid';
        $total = 0;
        foreach ($free as $s) {
            $total += seat_price($st, $s['seat_type']);
        }
        $code = random_code(8, 'CB');
        db_exec(
            "INSERT INTO bookings (booking_code, user_id, showtime_id, seat_count, total_amount, status, created_at, expires_at, paid_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? MINUTE, NOW() - INTERVAL ? MINUTE, ?)",
            [$code, $uid, (int) $st['id'], count($free), $total, $status, 60 + $i * 7, 50 + $i * 7,
             $status === 'paid' ? date('Y-m-d H:i:s', time() - (55 + $i * 7) * 60) : null]
        );
        $bid = (int) db()->lastInsertId();
        foreach ($free as $s) {
            db_exec(
                'INSERT INTO booking_seats (booking_id, showtime_id, seat_id, price, is_active) VALUES (?, ?, ?, ?, ?)',
                [$bid, (int) $st['id'], (int) $s['id'], seat_price($st, $s['seat_type']), $status === 'paid' ? 1 : null]
            );
        }
        if ($status === 'paid') {
            db_exec(
                "INSERT INTO payments (booking_id, method, amount, status, transaction_ref, completed_at)
                 VALUES (?, ?, ?, 'success', ?, NOW())",
                [$bid, $methods[$i % 3], $total, 'PAYDEMO' . random_code(10)]
            );
        }
        $made++;
    }
    return $made;
}
