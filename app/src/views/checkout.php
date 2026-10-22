<?php
/** Trang thanh toán. Biến: $b (đơn), $lastPayment */
$title = 'Thanh toán đơn ' . $b['booking_code'];
$active = $b['status'] === 'pending' && (int) $b['seconds_left'] > 0;
?>
<nav class="breadcrumb"><a href="/">Trang chủ</a> / <a href="/my-tickets">Vé của tôi</a> / <span><?= e($b['booking_code']) ?></span></nav>

<div class="checkout">
  <section class="card">
    <div class="card-head">
      <h1>Xác nhận đơn <span class="mono"><?= e($b['booking_code']) ?></span></h1>
      <?= badge($b['status']) ?>
    </div>
    <dl class="info-list">
      <dt><?= e(kind_label($b['kind'])) ?></dt><dd><?= e($b['title']) ?></dd>
      <dt>Suất</dt><dd><?= e(fmt_time($b['start_time'])) ?>, <?= e(fmt_date($b['start_time'])) ?></dd>
      <dt>Phòng</dt><dd><?= e($b['room_name']) ?></dd>
      <dt>Ghế</dt><dd><strong><?= e($b['seat_labels']) ?></strong> (<?= (int) $b['seat_count'] ?> ghế)</dd>
    </dl>
    <table class="table compact">
      <thead><tr><th>Ghế</th><th>Loại</th><th class="num">Giá</th></tr></thead>
      <tbody>
      <?php foreach ($b['seats'] as $s): ?>
        <tr><td><?= e($s['row_label'] . $s['seat_number']) ?></td><td><?= $s['seat_type'] === 'vip' ? 'VIP' : 'Thường' ?></td><td class="num"><?= e(money($s['price'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><th colspan="2">Tổng cộng</th><th class="num"><?= e(money($b['total_amount'])) ?></th></tr></tfoot>
    </table>
  </section>

  <section class="card">
    <?php if ($active): ?>
      <div class="countdown" data-countdown="<?= (int) $b['seconds_left'] ?>">
        Ghế được giữ cho bạn trong <strong class="countdown-value">--:--</strong>
      </div>
      <?php if ($lastPayment && $lastPayment['status'] === 'failed'): ?>
        <div class="alert alert-error">Lần thanh toán trước (<?= e(method_label($lastPayment['method'])) ?>) không thành công.</div>
      <?php endif; ?>
      <form method="post" action="/checkout/<?= e($b['booking_code']) ?>/pay" class="pay-form" id="pay-form">
        <?= csrf_field() ?>
        <fieldset>
          <legend>Chọn phương thức thanh toán</legend>
          <?php foreach ([
              'ewallet' => ['VÍ', 'Mô phỏng thanh toán qua ví điện tử'],
              'qrcode'  => ['QR', 'Mô phỏng quét mã QR của ngân hàng'],
              'card'    => ['THẺ', 'Mô phỏng thẻ ATM/quốc tế, không cần nhập số thẻ'],
          ] as $m => [$logo, $hint]): ?>
            <label class="pay-option">
              <input type="radio" name="method" value="<?= e($m) ?>" <?= $m === 'ewallet' ? 'checked' : '' ?>>
              <span class="pay-logo pay-<?= e($m) ?>"><?= e($logo) ?></span>
              <span><strong><?= e(method_label($m)) ?></strong><small><?= e($hint) ?></small></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <p class="notice">Đây là cổng thanh toán mô phỏng phục vụ bài thực hành. Không nhập và không lưu thông tin thẻ thật.</p>
        <button type="submit" class="btn btn-primary btn-block">Thanh toán <?= e(money($b['total_amount'])) ?></button>
      </form>
      <form method="post" action="/booking/<?= e($b['booking_code']) ?>/cancel" data-confirm="Hủy đơn và trả lại ghế?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost btn-block">Hủy đơn, trả ghế</button>
      </form>
    <?php else: ?>
      <div class="alert alert-error">
        Đơn này <?= $b['status'] === 'cancelled' ? 'đã bị hủy' : 'đã hết thời gian giữ ghế' ?>, ghế đã được trả lại cho người khác.
      </div>
      <a class="btn btn-primary btn-block" href="/showtime/<?= (int) $b['showtime_id'] ?>">Chọn ghế lại</a>
    <?php endif; ?>
  </section>
</div>
