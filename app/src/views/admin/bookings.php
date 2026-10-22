<?php
/** Danh sách đơn. Biến: $bookings, $status */
$title = 'Đơn đặt vé';
require __DIR__ . '/_nav.php';
?>
<div class="filters">
  <div class="tabs">
    <?php foreach (['' => 'Tất cả', 'paid' => 'Đã thanh toán', 'pending' => 'Đang giữ', 'cancelled' => 'Đã hủy', 'expired' => 'Hết hạn'] as $k => $label): ?>
      <a class="tab <?= $status === $k ? 'active' : '' ?>" href="/admin/bookings<?= $k ? '?status=' . e($k) : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<section class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Mã</th><th>Khách</th><th>Phim / sự kiện</th><th>Suất</th><th class="num">Ghế</th><th class="num">Tổng</th><th>Thanh toán</th><th>Trạng thái</th><th>Tạo lúc</th></tr></thead>
      <tbody>
      <?php if (!$bookings): ?>
        <tr><td colspan="9" class="muted">Không có đơn nào.</td></tr>
      <?php endif; ?>
      <?php foreach ($bookings as $b): ?>
        <tr>
          <td class="mono"><a href="/ticket/<?= e($b['booking_code']) ?>"><?= e($b['booking_code']) ?></a></td>
          <td><?= e($b['full_name']) ?><br><small class="muted"><?= e($b['email']) ?></small></td>
          <td><?= e($b['title']) ?></td>
          <td><?= e(fmt_datetime($b['start_time'])) ?><br><small class="muted"><?= e($b['room_name']) ?></small></td>
          <td class="num"><?= (int) $b['seat_count'] ?></td>
          <td class="num"><?= e(money($b['total_amount'])) ?></td>
          <td><?= $b['method'] ? e(method_label($b['method'])) : '<span class="muted">-</span>' ?></td>
          <td><?= badge($b['status']) ?></td>
          <td><?= e(fmt_datetime($b['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
