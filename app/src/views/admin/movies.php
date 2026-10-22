<?php
/** Quản lý phim/sự kiện. Biến: $movies, $errors, $old */
$title = 'Phim và sự kiện';
require __DIR__ . '/_nav.php';
$v = fn (string $k, string $d = '') => e($old[$k] ?? $d);
$err = fn (string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
?>
<div class="admin-cols">
  <section class="card">
    <h2>Danh sách</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Tên</th><th>Loại</th><th>Thể loại</th><th class="num">Phút</th><th class="num">Suất sắp tới</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($movies as $m): ?>
          <tr>
            <td><a href="/movie/<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a></td>
            <td><?= e(kind_label($m['kind'])) ?></td>
            <td><?= e($m['genre']) ?></td>
            <td class="num"><?= (int) $m['duration_min'] ?></td>
            <td class="num"><?= (int) $m['upcoming'] ?></td>
            <td><?= $m['is_active'] ? '<span class="badge badge-paid">Đang bán</span>' : '<span class="badge badge-expired">Tạm ẩn</span>' ?></td>
            <td>
              <form method="post" action="/admin/movies/<?= (int) $m['id'] ?>/toggle" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm" type="submit"><?= $m['is_active'] ? 'Ẩn' : 'Mở bán' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <h2>Thêm phim / sự kiện</h2>
    <form method="post" action="/admin/movies" class="form" novalidate>
      <?= csrf_field() ?>
      <label>Tên <input name="title" value="<?= $v('title') ?>" required><?= $err('title') ?></label>
      <label>Loại
        <select name="kind">
          <option value="movie" <?= ($old['kind'] ?? '') === 'movie' ? 'selected' : '' ?>>Phim</option>
          <option value="event" <?= ($old['kind'] ?? '') === 'event' ? 'selected' : '' ?>>Sự kiện</option>
        </select><?= $err('kind') ?>
      </label>
      <label>Thể loại <input name="genre" value="<?= $v('genre') ?>" placeholder="Hành động, Âm nhạc..."><?= $err('genre') ?></label>
      <div class="form-row">
        <label>Thời lượng (phút) <input type="number" name="duration_min" min="30" max="300" value="<?= $v('duration_min', '110') ?>"><?= $err('duration_min') ?></label>
        <label>Độ tuổi
          <select name="age_rating">
            <?php foreach (['P', 'K', 'T13', 'T16', 'T18'] as $a): ?>
              <option <?= ($old['age_rating'] ?? 'P') === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
          </select><?= $err('age_rating') ?>
        </label>
        <label>Màu poster <input type="color" name="poster_color" value="<?= $v('poster_color', '#7c3aed') ?>"></label>
      </div>
      <label>Mô tả <textarea name="description" rows="3"><?= $v('description') ?></textarea><?= $err('description') ?></label>
      <button type="submit" class="btn btn-primary">Lưu</button>
    </form>
  </section>
</div>
