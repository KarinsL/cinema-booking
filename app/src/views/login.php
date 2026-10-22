<?php
/** Đăng nhập. Biến: $next, $email */
$title = 'Đăng nhập';
?>
<div class="auth-card card">
  <h1>Đăng nhập</h1>
  <form method="post" action="/login" class="form" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label>Email
      <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
    </label>
    <label>Mật khẩu
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
  </form>
  <p class="muted small">Chưa có tài khoản? <a href="/register">Đăng ký</a></p>
</div>
