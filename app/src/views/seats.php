<?php
/** Sơ đồ ghế. Biến: $st (suất chiếu), $map (ghế theo hàng), $counts, $maxSeats, $holdMin, $user */
$title = 'Chọn ghế: ' . $st['title'];
$isHall = $st['room_type'] === 'hall';
$canBook = $st['is_upcoming'] && !empty($user);
?>
<nav class="breadcrumb">
  <a href="/">Trang chủ</a> / <a href="/movie/<?= (int) $st['movie_id'] ?>"><?= e($st['title']) ?></a> / <span>Chọn ghế</span>
</nav>

<section class="booking-head">
  <?= poster($st, 'poster-sm') ?>
  <div>
    <h1><?= e($st['title']) ?></h1>
    <p class="meta">
      <?= e(fmt_date($st['start_time'])) ?> · <strong><?= e(fmt_time($st['start_time'])) ?></strong> ·
      <?= e($st['room_name']) ?> · <?= (int) $st['duration_min'] ?> phút
    </p>
    <p class="meta">
      Ghế thường <strong><?= e(money($st['base_price'])) ?></strong> ·
      Ghế VIP <strong><?= e(money((int) $st['base_price'] + (int) $st['vip_surcharge'])) ?></strong> ·
      Còn trống <?= (int) $counts['free'] ?>/<?= array_sum($counts) ?> ghế
    </p>
  </div>
</section>

<?php if (!$st['is_upcoming']): ?>
  <div class="alert alert-error">Suất này đã bắt đầu nên không bán vé nữa.</div>
<?php elseif (empty($user)): ?>
  <div class="alert alert-info">
    Bạn có thể xem sơ đồ ghế. Để giữ ghế, hãy
    <a href="/login?next=<?= e(rawurlencode('/showtime/' . (int) $st['id'])) ?>">đăng nhập</a> hoặc
    <a href="/register">tạo tài khoản</a>.
  </div>
<?php endif; ?>

<form method="post" action="/showtime/<?= (int) $st['id'] ?>/hold" id="seat-form" class="seat-layout"
      data-max="<?= (int) $maxSeats ?>" data-base="<?= (int) $st['base_price'] ?>" data-vip="<?= (int) $st['vip_surcharge'] ?>">
  <?= csrf_field() ?>
  <div class="seat-stage">
    <div class="screen"><?= $isHall ? 'SÂN KHẤU' : 'MÀN HÌNH' ?></div>
    <div class="seat-grid" role="group" aria-label="Sơ đồ ghế">
      <?php foreach ($map as $row => $seats): ?>
        <div class="seat-row">
          <span class="row-label"><?= e($row) ?></span>
          <?php foreach ($seats as $s):
              $label = $s['row_label'] . $s['seat_number'];
              $disabled = $s['state'] !== 'free' || !$canBook; ?>
            <button type="button"
                    class="seat seat-<?= e($s['seat_type']) ?> is-<?= e($s['state']) ?>"
                    data-id="<?= (int) $s['id'] ?>" data-label="<?= e($label) ?>" data-type="<?= e($s['seat_type']) ?>"
                    aria-pressed="false" <?= $disabled ? 'disabled' : '' ?>
                    aria-label="Ghế <?= e($label) ?>, <?= $s['seat_type'] === 'vip' ? 'VIP' : 'thường' ?>, <?= ['free' => 'còn trống', 'held' => 'đang được giữ', 'sold' => 'đã bán'][$s['state']] ?>">
              <?= (int) $s['seat_number'] ?>
            </button>
          <?php endforeach; ?>
          <span class="row-label"><?= e($row) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <ul class="legend">
      <li><span class="seat seat-standard is-free demo"></span> Ghế thường</li>
      <li><span class="seat seat-vip is-free demo"></span> Ghế VIP</li>
      <li><span class="seat is-selected demo"></span> Đang chọn</li>
      <li><span class="seat is-held demo"></span> Người khác đang giữ</li>
      <li><span class="seat is-sold demo"></span> Đã bán</li>
    </ul>
  </div>

  <aside class="summary-card" aria-live="polite">
    <h2>Đơn của bạn</h2>
    <p class="summary-line"><span>Ghế đã chọn</span><strong id="sel-seats">Chưa chọn</strong></p>
    <p class="summary-line"><span>Số lượng</span><strong id="sel-count">0</strong></p>
    <p class="summary-line total"><span>Tạm tính</span><strong id="sel-total">0 đ</strong></p>
    <div id="seat-inputs"></div>
    <?php if ($canBook): ?>
      <button type="submit" class="btn btn-primary btn-block" id="hold-btn" disabled>Giữ ghế và thanh toán</button>
      <p class="muted small">Tối đa <?= (int) $maxSeats ?> ghế mỗi đơn. Ghế được giữ <?= (int) $holdMin ?> phút.</p>
    <?php elseif (empty($user) && $st['is_upcoming']): ?>
      <a class="btn btn-primary btn-block" href="/login?next=<?= e(rawurlencode('/showtime/' . (int) $st['id'])) ?>">Đăng nhập để đặt vé</a>
    <?php endif; ?>
  </aside>
</form>
