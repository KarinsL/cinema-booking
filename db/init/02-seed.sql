-- =====================================================================
-- 02-seed.sql : Dữ liệu mẫu (phòng, ghế, phim, sự kiện)
-- Suất chiếu cho 7 ngày tới do ứng dụng tự tạo khi khởi động
-- (app/src/bin/bootstrap.php) để ngày giờ luôn tính từ lúc bạn chạy hệ thống.
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';
USE cinema_db;

INSERT INTO rooms (id, name, room_type, row_count, seats_per_row) VALUES
  (1, 'Phòng 1 (2D)',   'cinema', 8, 12),
  (2, 'Phòng 2 (3D)',   'cinema', 7, 10),
  (3, 'Hội trường A',   'hall',  10, 14);

-- Sinh ghế tự động: hàng A..J, số ghế 1..14.
-- Phòng chiếu: 2 hàng cuối là ghế VIP. Hội trường: 2 hàng đầu là ghế VIP.
INSERT INTO seats (room_id, row_label, seat_number, seat_type)
SELECT r.id,
       SUBSTRING('ABCDEFGHIJ', rw.n, 1),
       sn.n,
       CASE
         WHEN r.room_type = 'cinema' AND rw.n > r.row_count - 2 THEN 'vip'
         WHEN r.room_type = 'hall'   AND rw.n <= 2              THEN 'vip'
         ELSE 'standard'
       END
FROM rooms r
JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
      UNION ALL SELECT 9 UNION ALL SELECT 10) rw ON rw.n <= r.row_count
JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
      UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
      UNION ALL SELECT 13 UNION ALL SELECT 14) sn ON sn.n <= r.seats_per_row
ORDER BY r.id, rw.n, sn.n;

-- Tên phim/sự kiện là dữ liệu hư cấu dùng cho bài thực hành
INSERT INTO movies (id, title, kind, genre, duration_min, age_rating, description, poster_color) VALUES
  (1, 'Hành Tinh Cát Đỏ', 'movie', 'Khoa học viễn tưởng', 128, 'T13',
   'Phi hành đoàn Bình Minh 7 hạ cánh khẩn cấp xuống một hành tinh phủ cát đỏ và phát hiện tín hiệu lạ phát ra từ lòng đất.',
   '#b45309'),
  (2, 'Mắt Bão', 'movie', 'Hành động', 112, 'T16',
   'Một nhóm cứu hộ có 48 giờ để đưa người dân vùng ven biển ra khỏi tâm cơn bão mạnh nhất trong 30 năm.',
   '#1d4ed8'),
  (3, 'Ngôi Nhà Trên Đồi Sương', 'movie', 'Kinh dị', 98, 'T18',
   'Ba người bạn thuê một căn nhà gỗ trên đồi chè để quay phim tài liệu, rồi những cuộn băng cũ bắt đầu tự phát.',
   '#4c1d95'),
  (4, 'Chú Mèo Phi Hành Gia', 'movie', 'Hoạt hình', 95, 'P',
   'Mèo Mướp lẻn vào tàu vũ trụ của trạm khí tượng và vô tình trở thành phi hành gia nhỏ tuổi nhất.',
   '#0f766e'),
  (5, 'Đêm Nhạc Acoustic: Phố Cũ', 'event', 'Âm nhạc', 150, 'P',
   'Đêm nhạc mộc với ban nhạc sinh viên, chơi lại các ca khúc về phố phường Hà Nội những năm 90.',
   '#be185d'),
  (6, 'Hội Thảo: AI Và Nghề Lập Trình', 'event', 'Hội thảo', 120, 'P',
   'Hai kỹ sư phần mềm chia sẻ cách họ dùng công cụ AI trong dự án thật, có phần hỏi đáp với sinh viên.',
   '#0369a1');
