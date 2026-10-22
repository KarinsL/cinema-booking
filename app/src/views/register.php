<?php
/** Đăng ký. Biến: $old, $errors */
$title = 'Tạo tài khoản';
$err = fn (string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
?>
<div class="auth-card card">
  <h1>Tạo tài khoản</h1>
  <form method="post" action="/register" class="form" novalidate>
    <?= csrf_field() ?>
    <label>Họ và tên
      <input type="text" name="full_name" value="<?= e($old['full_name']) ?>" autocomplete="name" required>
      <?= $err('full_name') ?>
    </label>
    <label>Email
      <input type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" required>
      <?= $err('email') ?>
    </label>
    <label>Số điện thoại (không bắt buộc)
      <input type="tel" name="phone" value="<?= e($old['phone']) ?>" autocomplete="tel">
      <?= $err('phone') ?>
    </label>
    <label>Mật khẩu
      <input type="password" name="password" autocomplete="new-password" required>
      <small class="muted">Tối thiểu 8 ký tự, có cả chữ và số.</small>
      <?= $err('password') ?>
    </label>
    <label>Nhập lại mật khẩu
      <input type="password" name="password_confirm" autocomplete="new-password" required>
      <?= $err('password_confirm') ?>
    </label>
    <button type="submit" class="btn btn-primary btn-block">Đăng ký</button>
  </form>
  <p class="muted small">Đã có tài khoản? <a href="/login">Đăng nhập</a></p>
</div>
