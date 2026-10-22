<?php
/** Khung trang chung. Biến có sẵn: $content, $user, $flashes, $title (tùy chọn) */
$appName = config('app_name');
$pageTitle = isset($title) && $title !== '' ? $title . ' | ' . $appName : $appName . ' | Đặt vé xem phim và sự kiện';
$path = request_path();
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/app.css?v=3">
  <script src="/assets/js/app.js?v=3" defer></script>
</head>
<body>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="/" aria-label="Trang chủ <?= e($appName) ?>">
      <svg class="brand-mark" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v4a3 3 0 0 0 0 6v4H4v-4a3 3 0 0 0 0-6z"/></svg>
      <span><?= e($appName) ?></span>
    </a>
    <nav class="nav-links" aria-label="Điều hướng chính">
      <a href="/?kind=movie" class="<?= ($_GET['kind'] ?? '') === 'movie' ? 'active' : '' ?>">Phim</a>
      <a href="/?kind=event" class="<?= ($_GET['kind'] ?? '') === 'event' ? 'active' : '' ?>">Sự kiện</a>
      <?php if (!empty($user)): ?>
        <a href="/my-tickets" class="<?= $path === '/my-tickets' ? 'active' : '' ?>">Vé của tôi</a>
        <?php if ($user['role'] === 'admin'): ?>
          <a href="/admin" class="<?= str_starts_with($path, '/admin') ? 'active' : '' ?>">Quản trị</a>
        <?php endif; ?>
        <span class="nav-user" title="<?= e($user['email']) ?>"><?= e($user['full_name']) ?></span>
        <form method="post" action="/logout" class="inline-form">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-ghost btn-sm">Đăng xuất</button>
        </form>
      <?php else: ?>
        <a href="/login" class="<?= $path === '/login' ? 'active' : '' ?>">Đăng nhập</a>
        <a href="/register" class="btn btn-primary btn-sm">Đăng ký</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="container main">
  <?php foreach ($flashes ?? [] as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>

<footer class="site-footer">
  <div class="container footer-inner">
    <span><?= e($appName) ?> · Đề 11: Hệ thống đặt vé xem phim / sự kiện</span>
    <span>Thanh toán trên website là mô phỏng, không phát sinh giao dịch thật.</span>
  </div>
</footer>
</body>
</html>
