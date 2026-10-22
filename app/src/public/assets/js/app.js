/* CineTicket: chọn ghế, đếm ngược giữ ghế, in vé, hộp xác nhận.
   Không dùng inline script để tương thích Content-Security-Policy script-src 'self'. */
(function () {
  'use strict';

  function money(n) {
    return n.toLocaleString('vi-VN') + ' đ';
  }

  /* ---------- Chọn ghế ---------- */
  var form = document.getElementById('seat-form');
  if (form) {
    var max = parseInt(form.dataset.max || '8', 10);
    var base = parseInt(form.dataset.base || '0', 10);
    var vip = parseInt(form.dataset.vip || '0', 10);
    var selected = new Map(); // id -> {label, type}
    var btn = document.getElementById('hold-btn');
    var inputs = document.getElementById('seat-inputs');

    var render = function () {
      var labels = [];
      var total = 0;
      inputs.innerHTML = '';
      selected.forEach(function (seat, id) {
        labels.push(seat.label);
        total += base + (seat.type === 'vip' ? vip : 0);
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'seat_ids[]';
        input.value = id;
        inputs.appendChild(input);
      });
      document.getElementById('sel-seats').textContent = labels.length ? labels.join(', ') : 'Chưa chọn';
      document.getElementById('sel-count').textContent = String(labels.length);
      document.getElementById('sel-total').textContent = money(total);
      if (btn) {
        btn.disabled = labels.length === 0;
      }
    };

    form.addEventListener('click', function (ev) {
      var seat = ev.target.closest('button.seat');
      if (!seat || seat.disabled) {
        return;
      }
      var id = seat.dataset.id;
      if (selected.has(id)) {
        selected.delete(id);
        seat.classList.remove('is-selected');
        seat.setAttribute('aria-pressed', 'false');
      } else {
        if (selected.size >= max) {
          window.alert('Mỗi đơn chỉ chọn tối đa ' + max + ' ghế.');
          return;
        }
        selected.set(id, { label: seat.dataset.label, type: seat.dataset.type });
        seat.classList.add('is-selected');
        seat.setAttribute('aria-pressed', 'true');
      }
      render();
    });

    form.addEventListener('submit', function (ev) {
      if (selected.size === 0) {
        ev.preventDefault();
      } else if (btn) {
        btn.disabled = true;
        btn.textContent = 'Đang giữ ghế...';
      }
    });
    render();
  }

  /* ---------- Đếm ngược thời gian giữ ghế ---------- */
  document.querySelectorAll('[data-countdown]').forEach(function (box) {
    var left = parseInt(box.dataset.countdown, 10);
    var out = box.querySelector('.countdown-value');
    var tick = function () {
      if (left <= 0) {
        box.classList.add('is-expired');
        box.textContent = 'Đã hết thời gian giữ ghế. Trang sẽ tải lại...';
        var pay = document.getElementById('pay-form');
        if (pay) {
          pay.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        }
        setTimeout(function () { window.location.reload(); }, 2500);
        return;
      }
      var m = Math.floor(left / 60);
      var s = left % 60;
      out.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
      left -= 1;
      setTimeout(tick, 1000);
    };
    tick();
  });

  /* ---------- In vé ---------- */
  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });

  /* ---------- Xác nhận trước khi gửi form ---------- */
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.dataset.confirm)) {
        ev.preventDefault();
      }
    });
  });
})();
