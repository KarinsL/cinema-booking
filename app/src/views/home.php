<?php
/** Trang chủ: danh sách phim/sự kiện + suất chiếu sắp tới. Biến: $movies, $byMovie, $kind, $date, $days */
$title = $kind === 'event' ? 'Sự kiện' : ($kind === 'movie' ? 'Phim đang chiếu' : '');
$qs = fn (array $extra) => '/?' . http_build_query(array_filter(array_merge(['kind' => $kind, 'date' => $date], $extra), fn ($v) => $v !== ''));
?>
<section class="hero">
  <div>
    <p class="eyebrow">Rạp chiếu và hội trường sự kiện</p>
    <h1>Chọn suất, chọn ghế, nhận vé trong 2 phút</h1>
    <p class="lead">Xem sơ đồ ghế theo thời gian thực, ghế được giữ <?= (int) config('hold_minutes') ?> phút trong lúc bạn thanh toán.</p>
  </div>
  <div class="hero-stats" aria-label="Số liệu nhanh">
    <div><strong><?= count(array_filter($movies, fn ($m) => $m['kind'] === 'movie')) ?></strong><span>phim</span></div>
    <div><strong><?= count(array_filter($movies, fn ($m) => $m['kind'] === 'event')) ?></strong><span>sự kiện</span></div>
    <div><strong><?= array_sum(array_map('count', $byMovie)) ?></strong><span>suất sắp tới</span></div>
  </div>
</section>

<div class="filters">
  <div class="tabs" role="tablist" aria-label="Loại">
    <a role="tab" class="tab <?= $kind === '' ? 'active' : '' ?>" href="<?= e($qs(['kind' => ''])) ?>">Tất cả</a>
    <a role="tab" class="tab <?= $kind === 'movie' ? 'active' : '' ?>" href="<?= e($qs(['kind' => 'movie'])) ?>">Phim</a>
    <a role="tab" class="tab <?= $kind === 'event' ? 'active' : '' ?>" href="<?= e($qs(['kind' => 'event'])) ?>">Sự kiện</a>
  </div>
  <div class="date-pills" aria-label="Ngày chiếu">
    <a class="pill <?= $date === '' ? 'active' : '' ?>" href="<?= e($qs(['date' => ''])) ?>">7 ngày tới</a>
    <?php foreach ($days as $i => $d): ?>
      <a class="pill <?= $date === $d ? 'active' : '' ?>" href="<?= e($qs(['date' => $d])) ?>">
        <span><?= $i === 0 ? 'Hôm nay' : e(vn_weekday($d)) ?></span><b><?= e(date('d/m', strtotime($d))) ?></b>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!$movies): ?>
  <p class="empty">Chưa có phim hoặc sự kiện nào đang mở bán.</p>
<?php endif; ?>

<div class="movie-grid">
  <?php foreach ($movies as $m): $sts = $byMovie[$m['id']] ?? []; ?>
    <article class="movie-card">
      <a href="/movie/<?= (int) $m['id'] ?>" class="poster-link" aria-label="Xem chi tiết <?= e($m['title']) ?>">
        <?= poster($m) ?>
      </a>
      <div class="movie-body">
        <h2 class="movie-title"><a href="/movie/<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a></h2>
        <p class="meta"><?= e($m['genre']) ?> · <?= (int) $m['duration_min'] ?> phút</p>
        <?php if ($sts): ?>
          <div class="time-chips">
            <?php foreach (array_slice($sts, 0, 6) as $s): ?>
              <a class="chip" href="/showtime/<?= (int) $s['id'] ?>" title="<?= e($s['room_name'] . ' · từ ' . money($s['base_price'])) ?>">
                <small><?= e(date('d/m', strtotime($s['start_time']))) ?></small><?= e(fmt_time($s['start_time'])) ?>
              </a>
            <?php endforeach; ?>
            <?php if (count($sts) > 6): ?>
              <a class="chip chip-more" href="/movie/<?= (int) $m['id'] ?>">+<?= count($sts) - 6 ?> suất</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <p class="muted small">Chưa có suất chiếu trong khoảng ngày đã chọn.</p>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
