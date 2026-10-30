# ============================================================
# Dockerfile - Môi trường PHP 8.2 + Apache cho NoteApp
# Cấu hình DocumentRoot trỏ thẳng vào /var/www/html/public
# để ứng dụng chạy trực tiếp ở http://localhost:PORT/
# ============================================================

FROM php:8.2-apache

# Cài đặt các PHP extensions cần thiết: PDO MySQL, Sockets, Mbstring, Fileinfo
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        curl \
    && docker-php-ext-install pdo pdo_mysql sockets zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Cấu hình Apache DocumentRoot trỏ trực tiếp vào thư mục public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf "<Directory /var/www/html/public>\n    Options -Indexes +FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n" > /etc/apache2/conf-available/noteapp.conf \
    && a2enconf noteapp

# Tăng giới hạn dung lượng upload file cho PHP (20MB)
RUN printf "upload_max_filesize = 20M\npost_max_size = 25M\ndate.timezone = Asia/Ho_Chi_Minh\n" > /usr/local/etc/php/conf.d/noteapp.ini

WORKDIR /var/www/html

COPY . /var/www/html

# Cấp quyền ghi thư mục uploads cho tiến trình Apache (www-data)
RUN mkdir -p /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html/public/uploads \
    && chmod -R 775 /var/www/html/public/uploads

EXPOSE 80
