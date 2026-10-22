<?php
/** Trang lỗi. Biến: $status, $message */
?>
<section class="error-page">
  <p class="error-code"><?= (int) $status ?></p>
  <h1><?= e($title) ?></h1>
  <p class="lead"><?= e($message) ?></p>
  <a class="btn btn-primary" href="/">Về trang chủ</a>
</section>
