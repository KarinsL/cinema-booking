<?php /** Thanh điều hướng trang quản trị. Biến: $tab */ ?>
<div class="admin-head">
  <h1 class="page-title">Quản trị hệ thống</h1>
  <nav class="tabs" aria-label="Mục quản trị">
    <a class="tab <?= $tab === 'dashboard' ? 'active' : '' ?>" href="/admin">Tổng quan</a>
    <a class="tab <?= $tab === 'movies' ? 'active' : '' ?>" href="/admin/movies">Phim &amp; sự kiện</a>
    <a class="tab <?= $tab === 'showtimes' ? 'active' : '' ?>" href="/admin/showtimes">Suất chiếu</a>
    <a class="tab <?= $tab === 'bookings' ? 'active' : '' ?>" href="/admin/bookings">Đơn đặt vé</a>
  </nav>
</div>
