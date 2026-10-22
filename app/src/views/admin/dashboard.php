<?php
/** Tổng quan quản trị. Biến: $kpi, $byStatus, $topMovies, $recent */
$title = 'Quản trị';
require __DIR__ . '/_nav.php';
$maxTickets = max(1, ...array_map(fn ($m) => (int) $m['tickets'], $topMovies ?: [['tickets' => 1]]));
?>
<div class="kpi-grid">
  <div class="kpi"><span>Doanh thu hôm nay</span><strong><?= e(money($kpi['revenue_today'])) ?></strong></div>
  <div class="kpi"><span>Tổng doanh thu</span><strong><?= e(money($kpi['revenue_total'])) ?></strong></div>
  <div class="kpi"><span>Vé đã bán</span><strong><?= (int) $kpi['tickets_sold'] ?></strong></div>
  <div class="kpi"><span>Đơn đang giữ ghế</span><strong><?= (int) $kpi['pending_count'] ?></strong></div>
  <div class="kpi"><span>Suất sắp chiếu</span><strong><?= (int) $kpi['upcoming_showtimes'] ?></strong></div>
  <div class="kpi"><span>Khách hàng</span><strong><?= (int) $kpi['customers'] ?></strong></div>
</div>

<div class="admin-cols">
  <section class="card">
    <h2>Vé bán theo phim / sự kiện</h2>
    <ul class="bars">
      <?php foreach ($topMovies as $m): ?>
        <li>
          <span class="bar-label"><?= e($m['title']) ?></span>
          <span class="bar"><span style="width: <?= round((int) $m['tickets'] / $maxTickets * 100) ?>%"></span></span>
          <span class="bar-value"><?= (int) $m['tickets'] ?> vé</span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="card">
    <h2>Đơn theo trạng thái</h2>
    <ul class="status-list">
      <?php foreach ($byStatus as $s): ?>
        <li><?= badge($s['status']) ?> <strong><?= (int) $s['n'] ?></strong></li>
      <?php endforeach; ?>
    </ul>
    <p class="muted small">Số liệu này cũng được xuất cho Prometheus qua endpoint nội bộ <span class="mono">/metrics</span>.</p>
  </section>
</div>

<section class="card">
  <h2>Đơn mới nhất</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Mã</th><th>Khách</th><th>Phim / sự kiện</th><th>Suất</th><th class="num">Tổng</th><th>Trạng thái</th><th>Tạo lúc</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="mono"><a href="/ticket/<?= e($r['booking_code']) ?>"><?= e($r['booking_code']) ?></a></td>
          <td><?= e($r['full_name']) ?></td>
          <td><?= e($r['title']) ?></td>
          <td><?= e(fmt_datetime($r['start_time'])) ?></td>
          <td class="num"><?= e(money($r['total_amount'])) ?></td>
          <td><?= badge($r['status']) ?></td>
          <td><?= e(fmt_datetime($r['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
