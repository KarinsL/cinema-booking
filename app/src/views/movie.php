<?php
/** Chi tiết phim/sự kiện + lịch chiếu theo ngày. Biến: $movie, $byDate */
$title = $movie['title'];
?>
<nav class="breadcrumb"><a href="/">Trang chủ</a> / <span><?= e($movie['title']) ?></span></nav>

<section class="movie-detail">
  <?= poster($movie, 'poster-lg') ?>
  <div>
    <p class="eyebrow"><?= e(kind_label($movie['kind'])) ?> · <?= e($movie['genre']) ?></p>
    <h1><?= e($movie['title']) ?></h1>
    <p class="meta">
      Thời lượng <?= (int) $movie['duration_min'] ?> phút ·
      Phân loại <span class="age-tag age-<?= e(strtolower($movie['age_rating'])) ?>"><?= e($movie['age_rating']) ?></span>
    </p>
    <p class="lead"><?= e($movie['description']) ?></p>
  </div>
</section>

<h2 class="section-title">Lịch <?= $movie['kind'] === 'event' ? 'diễn' : 'chiếu' ?></h2>
<?php if (!$byDate): ?>
  <p class="empty">Hiện chưa có suất nào. Quay lại sau nhé.</p>
<?php endif; ?>

<?php foreach ($byDate as $d => $list): ?>
  <div class="day-block">
    <h3><?= e(fmt_date($d)) ?></h3>
    <div class="showtime-list">
      <?php foreach ($list as $s): $left = (int) $s['capacity'] - (int) $s['taken']; ?>
        <a class="showtime-card <?= $left <= 0 ? 'is-full' : '' ?>" href="/showtime/<?= (int) $s['id'] ?>">
          <strong><?= e(fmt_time($s['start_time'])) ?></strong>
          <span><?= e($s['room_name']) ?></span>
          <span class="price">từ <?= e(money($s['base_price'])) ?></span>
          <span class="seats-left"><?= $left > 0 ? 'Còn ' . $left . ' ghế' : 'Hết ghế' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
