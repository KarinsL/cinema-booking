<?php
/** Quản lý suất chiếu. Biến: $showtimes, $movies, $rooms, $errors, $old */
$title = 'Suất chiếu';
require __DIR__ . '/_nav.php';
$v = fn (string $k, string $d = '') => e($old[$k] ?? $d);
$err = fn (string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$defaultTime = date('Y-m-d\TH:i', strtotime('+1 day 19:00'));
?>
<div class="admin-cols">
  <section class="card">
    <div class="card-head">
      <h2>Suất sắp chiếu (<?= count($showtimes) ?>)</h2>
      <form method="post" action="/admin/showtimes/generate" class="inline-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost btn-sm">Tạo lịch mẫu 7 ngày tới</button>
      </form>
    </div>
    <div class="table-wrap tall">
      <table class="table">
        <thead><tr><th>Giờ</th><th>Phim / sự kiện</th><th>Phòng</th><th class="num">Giá</th><th class="num">Đã bán</th><th class="num">Đang giữ</th><th class="num">Sức chứa</th></tr></thead>
        <tbody>
        <?php foreach ($showtimes as $s): ?>
          <tr>
            <td><a href="/showtime/<?= (int) $s['id'] ?>"><?= e(fmt_datetime($s['start_time'])) ?></a></td>
            <td><?= e($s['title']) ?></td>
            <td><?= e($s['room_name']) ?></td>
            <td class="num"><?= e(money($s['base_price'])) ?></td>
            <td class="num"><?= (int) $s['sold'] ?></td>
            <td class="num"><?= (int) $s['held'] ?></td>
            <td class="num"><?= (int) $s['capacity'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <h2>Thêm suất chiếu</h2>
    <form method="post" action="/admin/showtimes" class="form" novalidate>
      <?= csrf_field() ?>
      <label>Phim / sự kiện
        <select name="movie_id">
          <?php foreach ($movies as $m): ?>
            <option value="<?= (int) $m['id'] ?>" <?= ($old['movie_id'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= e(kind_label($m['kind']) . ': ' . $m['title']) ?></option>
          <?php endforeach; ?>
        </select><?= $err('movie_id') ?>
      </label>
      <label>Phòng
        <select name="room_id">
          <?php foreach ($rooms as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= ($old['room_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
          <?php endforeach; ?>
        </select><?= $err('room_id') ?>
      </label>
      <label>Bắt đầu lúc <input type="datetime-local" name="start_time" value="<?= $v('start_time', $defaultTime) ?>"><?= $err('start_time') ?></label>
      <div class="form-row">
        <label>Giá vé thường (đ) <input type="number" name="base_price" step="1000" value="<?= $v('base_price', '80000') ?>"><?= $err('base_price') ?></label>
        <label>Phụ thu VIP (đ) <input type="number" name="vip_surcharge" step="1000" value="<?= $v('vip_surcharge', '20000') ?>"><?= $err('vip_surcharge') ?></label>
      </div>
      <button type="submit" class="btn btn-primary">Thêm suất</button>
    </form>
  </section>
</div>
