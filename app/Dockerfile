# =====================================================================
# Image cho website đặt vé: PHP 8.3 + Apache (giống Lab 2), chạy NON-ROOT
# =====================================================================
FROM php:8.3-apache

# 1) Extension kết nối MySQL bằng PDO
RUN docker-php-ext-install pdo_mysql

# 2) Cấu hình PHP cho môi trường production + tùy chỉnh bảo mật
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php-app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

# 3) Apache nghe cổng 8080 (>1024) để chạy được bằng user www-data,
#    ẩn phiên bản server, tắt module không dùng, giới hạn số tiến trình
COPY docker/ports.conf /etc/apache2/ports.conf
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache-hardening.conf /etc/apache2/conf-available/zz-hardening.conf
COPY docker/mpm-prefork.conf /etc/apache2/mods-available/mpm_prefork.conf
RUN a2enconf zz-hardening \
 && a2dismod -f autoindex status \
 && rm -rf /var/www/html/*

# 4) Mã nguồn thuộc root, www-data chỉ có quyền đọc (không sửa được code khi bị tấn công)
WORKDIR /var/www/app
COPY --chown=root:root src/ /var/www/app/
RUN find /var/www/app -type d -exec chmod 755 {} \; \
 && find /var/www/app -type f -exec chmod 644 {} \;

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod 755 /usr/local/bin/app-entrypoint

# 5) Bỏ quyền root: mọi tiến trình trong container chạy bằng www-data (uid 33)
USER www-data
EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 \
  CMD curl -fsS http://127.0.0.1:8080/health || exit 1

ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
