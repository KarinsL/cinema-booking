<?php
/** Cổng thanh toán mô phỏng. Biến: $p (giao dịch), $b (đơn) */
$title = 'Cổng thanh toán mô phỏng';
?>
<div class="gateway">
  <div class="gateway-banner">MÔI TRƯỜNG MÔ PHỎNG · không trừ tiền thật</div>
  <div class="gateway-body">
    <p class="eyebrow">Cổng thanh toán thử nghiệm của <?= e(config('app_name')) ?></p>
    <h1><?= e(money($p['amount'])) ?></h1>
    <dl class="info-list">
      <dt>Mã giao dịch</dt><dd class="mono"><?= e($p['transaction_ref']) ?></dd>
      <dt>Đơn hàng</dt><dd class="mono"><?= e($p['booking_code']) ?></dd>
      <dt>Phương thức</dt><dd><?= e(method_label($p['method'])) ?></dd>
      <dt>Nội dung</dt><dd><?= e($b['title']) ?>, ghế <?= e($b['seat_labels']) ?></dd>
    </dl>
    <p class="muted small">Chọn kết quả để mô phỏng phản hồi của cổng thanh toán. Website sẽ cập nhật trạng thái đơn tương ứng.</p>
    <div class="gateway-actions">
      <form method="post" action="/gateway/<?= e($p['transaction_ref']) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="result" value="success">
        <button type="submit" class="btn btn-success btn-block">Xác nhận thanh toán thành công</button>
      </form>
      <form method="post" action="/gateway/<?= e($p['transaction_ref']) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="result" value="fail">
        <button type="submit" class="btn btn-danger btn-block">Mô phỏng giao dịch thất bại</button>
      </form>
    </div>
  </div>
</div>
