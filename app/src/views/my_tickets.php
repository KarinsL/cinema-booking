<?php
/** Danh sách đơn của người dùng. Biến: $bookings */
$title = 'Vé của tôi';
?>
<h1 class="page-title">Vé của tôi</h1>
<?php if (!$bookings): ?>
  <p class="empty">Bạn chưa đặt vé nào. <a href="/">Chọn phim ngay</a>.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Mã đơn</th><th>Phim / sự kiện</th><th>Suất</th><th>Phòng</th><th class="num">Ghế</th><th class="num">Tổng tiền</th><th>Trạng thái</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($bookings as $b): ?>
        <tr>
          <td class="mono"><?= e($b['booking_code']) ?></td>
          <td><?= e($b['title']) ?></td>
          <td><?= e(fmt_datetime($b['start_time'])) ?></td>
          <td><?= e($b['room_name']) ?></td>
          <td class="num"><?= (int) $b['seat_count'] ?></td>
          <td class="num"><?= e(money($b['total_amount'])) ?></td>
          <td><?= badge($b['status']) ?></td>
          <td>
            <?php if ($b['status'] === 'paid'): ?>
              <a href="/ticket/<?= e($b['booking_code']) ?>">Xem vé</a>
            <?php elseif ($b['status'] === 'pending'): ?>
              <a href="/checkout/<?= e($b['booking_code']) ?>">Thanh toán</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
