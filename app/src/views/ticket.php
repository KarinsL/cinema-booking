<?php
/** Vé điện tử. Biến: $b (đơn), $payment */
$title = 'Vé ' . $b['booking_code'];
?>
<nav class="breadcrumb no-print"><a href="/">Trang chủ</a> / <a href="/my-tickets">Vé của tôi</a> / <span><?= e($b['booking_code']) ?></span></nav>

<?php if ($b['status'] !== 'paid'): ?>
  <div class="alert alert-info">Đơn chưa thanh toán. <a href="/checkout/<?= e($b['booking_code']) ?>">Mở trang thanh toán</a></div>
<?php endif; ?>

<article class="ticket">
  <div class="ticket-main" style="--poster:<?= e($b['poster_color']) ?>">
    <p class="eyebrow"><?= e(kind_label($b['kind'])) ?> · <?= e($b['age_rating']) ?></p>
    <h1><?= e($b['title']) ?></h1>
    <div class="ticket-grid">
      <div><span>Ngày</span><strong><?= e(date('d/m/Y', strtotime($b['start_time']))) ?></strong></div>
      <div><span>Giờ</span><strong><?= e(fmt_time($b['start_time'])) ?></strong></div>
      <div><span>Phòng</span><strong><?= e($b['room_name']) ?></strong></div>
      <div><span>Ghế</span><strong><?= e($b['seat_labels']) ?></strong></div>
    </div>
    <p class="muted small">Khách hàng: <?= e($b['full_name']) ?> · <?= e($b['email']) ?></p>
  </div>
  <div class="ticket-stub">
    <span>Mã vé</span>
    <strong class="ticket-code"><?= e($b['booking_code']) ?></strong>
    <?= badge($b['status']) ?>
    <span class="ticket-total"><?= e(money($b['total_amount'])) ?></span>
    <?php if ($payment): ?>
      <small><?= e(method_label($payment['method'])) ?><br><?= e(fmt_datetime($payment['completed_at'])) ?></small>
    <?php endif; ?>
  </div>
</article>

<div class="actions no-print">
  <button type="button" class="btn btn-ghost" data-print>In vé</button>
  <a class="btn btn-primary" href="/">Đặt thêm vé</a>
</div>
