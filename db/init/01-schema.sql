-- =====================================================================
-- 01-schema.sql : Tạo cấu trúc CSDL cho hệ thống đặt vé xem phim / sự kiện
-- File chạy tự động MỘT LẦN khi volume db_data còn trống
-- (cơ chế /docker-entrypoint-initdb.d của image mysql).
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS cinema_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cinema_db;

-- Người dùng của website (khách hàng và quản trị viên)
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  phone         VARCHAR(20)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- Phim hoặc sự kiện (kind = movie | event)
CREATE TABLE movies (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(150) NOT NULL,
  kind         ENUM('movie','event') NOT NULL DEFAULT 'movie',
  genre        VARCHAR(80)  NOT NULL,
  duration_min SMALLINT UNSIGNED NOT NULL,
  age_rating   VARCHAR(10)  NOT NULL DEFAULT 'P',
  description  TEXT NOT NULL,
  poster_color CHAR(7) NOT NULL DEFAULT '#334155',
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Phòng chiếu / hội trường
CREATE TABLE rooms (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(50) NOT NULL,
  room_type     ENUM('cinema','hall') NOT NULL DEFAULT 'cinema',
  row_count     TINYINT UNSIGNED NOT NULL,
  seats_per_row TINYINT UNSIGNED NOT NULL
) ENGINE=InnoDB;

-- Ghế trong từng phòng
CREATE TABLE seats (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id     INT UNSIGNED NOT NULL,
  row_label   CHAR(1) NOT NULL,
  seat_number TINYINT UNSIGNED NOT NULL,
  seat_type   ENUM('standard','vip') NOT NULL DEFAULT 'standard',
  UNIQUE KEY uq_seat (room_id, row_label, seat_number),
  CONSTRAINT fk_seat_room FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB;

-- Suất chiếu: một phim/sự kiện tại một phòng vào một thời điểm
CREATE TABLE showtimes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  movie_id      INT UNSIGNED NOT NULL,
  room_id       INT UNSIGNED NOT NULL,
  start_time    DATETIME NOT NULL,
  base_price    INT UNSIGNED NOT NULL,
  vip_surcharge INT UNSIGNED NOT NULL DEFAULT 20000,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_room_time (room_id, start_time),
  KEY idx_movie_time (movie_id, start_time),
  CONSTRAINT fk_st_movie FOREIGN KEY (movie_id) REFERENCES movies(id),
  CONSTRAINT fk_st_room  FOREIGN KEY (room_id)  REFERENCES rooms(id)
) ENGINE=InnoDB;

-- Đơn đặt vé. pending = đang giữ ghế (hết hạn sau expires_at)
CREATE TABLE bookings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code CHAR(10) NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  showtime_id  INT UNSIGNED NOT NULL,
  seat_count   TINYINT UNSIGNED NOT NULL,
  total_amount INT UNSIGNED NOT NULL,
  status       ENUM('pending','paid','cancelled','expired') NOT NULL DEFAULT 'pending',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NOT NULL,
  paid_at      DATETIME NULL,
  UNIQUE KEY uq_booking_code (booking_code),
  KEY idx_booking_user (user_id),
  KEY idx_booking_status_exp (status, expires_at),
  CONSTRAINT fk_bk_user     FOREIGN KEY (user_id)     REFERENCES users(id),
  CONSTRAINT fk_bk_showtime FOREIGN KEY (showtime_id) REFERENCES showtimes(id)
) ENGINE=InnoDB;

-- Ghế thuộc từng đơn.
-- is_active = 1   : ghế đang được giữ hoặc đã bán
-- is_active = NULL: đơn đã hủy/hết hạn, ghế được trả lại
-- Chỉ mục UNIQUE (showtime_id, seat_id, is_active) chặn việc hai người cùng
-- giữ một ghế. MySQL cho phép nhiều giá trị NULL trong chỉ mục UNIQUE nên
-- các dòng đã hủy không chặn lần đặt sau.
CREATE TABLE booking_seats (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NOT NULL,
  showtime_id INT UNSIGNED NOT NULL,
  seat_id     INT UNSIGNED NOT NULL,
  price       INT UNSIGNED NOT NULL,
  is_active   TINYINT(1) NULL DEFAULT 1,
  UNIQUE KEY uq_showtime_seat_active (showtime_id, seat_id, is_active),
  KEY idx_bs_booking (booking_id),
  CONSTRAINT fk_bs_booking  FOREIGN KEY (booking_id)  REFERENCES bookings(id),
  CONSTRAINT fk_bs_showtime FOREIGN KEY (showtime_id) REFERENCES showtimes(id),
  CONSTRAINT fk_bs_seat     FOREIGN KEY (seat_id)     REFERENCES seats(id)
) ENGINE=InnoDB;

-- Giao dịch thanh toán (giả lập, không kết nối cổng thật)
CREATE TABLE payments (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id      INT UNSIGNED NOT NULL,
  method          ENUM('ewallet','qrcode','card') NOT NULL,
  amount          INT UNSIGNED NOT NULL,
  status          ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  transaction_ref VARCHAR(32) NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at    DATETIME NULL,
  UNIQUE KEY uq_payment_ref (transaction_ref),
  KEY idx_pay_booking (booking_id),
  CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB;
