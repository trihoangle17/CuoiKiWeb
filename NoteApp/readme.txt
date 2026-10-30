================================================================================
503073 - WEB PROGRAMMING & APPLICATIONS (SEMESTER I / 2026-2027)
FINAL PROJECT: NOTE MANAGEMENT APPLICATION (NoteApp)
================================================================================

1. TỔNG QUAN KIẾN TRÚC & CÔNG NGHỆ
--------------------------------------------------------------------------------
- Backend  : PHP 8.x thuần theo kiến trúc MVC, PDO + MySQL (Prepared Statements).
- Frontend : HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3 & Bootstrap Icons.
- Realtime : WebSocket Server thuần PHP chuẩn RFC 6455 (websocket/server.php).
- PWA      : Service Worker (public/sw.js) + IndexedDB (public/assets/js/offline.js)
             hỗ trợ xem/ghi chú khi mất mạng và tự đồng bộ khi có mạng trở lại.
- Bảo mật  : Bcrypt (password_hash), CSRF Token trên toàn bộ thao tác thay đổi dữ liệu,
             escape đầu ra HTML chống XSS, PDO Prepared Statements chống SQL Injection,
             kiểm tra MIME thực tế (finfo) & đổi tên file ngẫu nhiên khi upload,
             session_regenerate_id chống Session Fixation, Rate-limit đăng nhập.
- URL      : Chạy trực tiếp tại gốc http://localhost:PORT/ (không nằm trong thư mục con),
             sử dụng hoàn toàn đường dẫn tương đối, không hardcode host/port.

2. TÀI KHOẢN DEMO CÓ SẴN DỮ LIỆU ĐỂ ĐÁNH GIÁ (SEED DATA)
--------------------------------------------------------------------------------
Toàn bộ dữ liệu mẫu được nạp tự động từ tệp `database/schema.sql`:

[Tài khoản 1 - Chính, đã kích hoạt, có sẵn ghi chú ghim, nhãn, chia sẻ & ghi chú khóa]
- Email    : demo@noteapp.local
- Mật khẩu : Demo@123
- Mật khẩu khóa của ghi chú bảo mật ("Thông tin tài khoản ngân hàng..."): 1234

[Tài khoản 2 - Dùng kiểm thử Chia sẻ & Cộng tác chỉnh sửa Realtime qua WebSocket]
- Email    : friend@noteapp.local
- Mật khẩu : Demo@123

[Tài khoản 3 - Chưa kích hoạt email, dùng kiểm thử Banner cảnh báo chưa xác thực]
- Email    : unverified@noteapp.local
- Mật khẩu : Demo@123
  (Có thể kiểm thử kích hoạt ngay bằng cách mở đường dẫn:
   /activate?token=demo_activation_token_123456)

3. HƯỚNG DẪN CHẠY DỰ ÁN (2 CÁCH)
--------------------------------------------------------------------------------

CÁCH 1: CHẠY BẰNG DOCKER COMPOSE (KHUYẾN NGHỊ - TỰ ĐỘNG HOÀN TOÀN)
------------------------------------------------------------------
Yêu cầu: Đã cài đặt Docker Desktop (hoặc Docker Engine + Docker Compose).

Bước 1: (Tùy chọn) Mở tệp `.env` để điền `LLM_API_KEY` (OpenAI / Groq / Gemini)
        và thông tin SMTP Gmail nếu muốn gửi email thật ra Internet.
Bước 2: Mở Terminal / PowerShell tại thư mục gốc dự án (`NoteApp/`) và chạy:

        docker-compose up -d --build

Bước 3: Đợi khoảng 10-15 giây để MySQL khởi tạo bảng & dữ liệu mẫu từ `schema.sql`,
        sau đó truy cập trình duyệt tại:

        http://localhost:8080/

        - Web Application : http://localhost:8080/
        - WebSocket Server: ws://localhost:8081 (tự động chạy ngầm)
        - MySQL Host Port : localhost:3307 (user: root / pass: rootpassword)

Để dừng hệ thống: `docker-compose down`


CÁCH 2: CHẠY TRỰC TIẾP TRÊN MÁY CỤC BỘ (XAMPP / WAMP / PHP CLI)
------------------------------------------------------------------
Yêu cầu: PHP >= 8.0 (bật sẵn pdo_mysql, fileinfo, curl, mbstring) và MySQL / MariaDB.

Bước 1: Tạo cơ sở dữ liệu & nạp dữ liệu mẫu:
        - Mở phpMyAdmin hoặc MySQL CLI và import tệp `database/schema.sql`.
Bước 2: Kiểm tra tệp `.env`:
        - Chỉnh `DB_HOST`, `DB_PORT`, `DB_NAME=noteapp`, `DB_USER`, `DB_PASS` khớp với
          cấu hình MySQL trên máy (mặc định XAMPP là `DB_USER=root`, `DB_PASS=`).
        - (Lưu ý kiểm thử): Nếu tắt dịch vụ MySQL đi và tải lại trang web, hệ thống
          sẽ chỉ hiển thị trang "Yêu cầu kết nối cơ sở dữ liệu".
Bước 3: Khởi chạy Web Server tại gốc `http://localhost:PORT/` (không đặt trong thư mục con):
        - Dùng XAMPP: đặt mã nguồn trực tiếp trong `htdocs` (hoặc trỏ DocumentRoot của
          Apache VirtualHost vào thư mục `NoteApp/public`), bật `mod_rewrite`.
        - Hoặc dùng PHP Built-in Server (chạy tại thư mục gốc `NoteApp`):
          php -S localhost:8080 -t public public/index.php
          (Với Apache / Docker thì `.htaccess` đã tự xử lý định tuyến).
Bước 4: Khởi chạy máy chủ WebSocket để dùng tính năng cộng tác thời gian thực:
        Mở một cửa sổ Terminal thứ hai tại thư mục `NoteApp/` và chạy:

        php websocket/server.php

4. GHI CHÚ KIỂM THỬ NHANH CÁC TÍNH NĂNG NÂNG CAO
--------------------------------------------------------------------------------
- Tự động lưu (Auto-save): Vào "Ghi chú mới", nhập Tiêu đề & Nội dung, dừng gõ 0.6s
  hệ thống tự động lưu ngay (không có nút Save).
- Khóa mật khẩu riêng từng ghi chú: Bấm biểu tượng ổ khóa trên ghi chú để Đặt khóa
  (nhập 2 lần), Đổi mật khẩu (nhập mật khẩu cũ + mới 2 lần), Tắt khóa (nhập mật khẩu
  hiện tại). Khi ra danh sách, mọi thao tác Xem / Sửa / Xóa đều yêu cầu mật khẩu.
- Cộng tác Realtime (WebSocket): Đăng nhập `demo@noteapp.local` ở trình duyệt 1 và
  `friend@noteapp.local` ở trình duyệt ẩn danh (Incognito), cùng mở ghi chú chung
  "Kế hoạch đồ án môn Web Programming" hoặc "Biên bản họp nhóm thiết kế giao diện UI/UX"
  để quan sát nội dung đồng bộ trực tiếp theo thời gian thực khi gõ.
- AI Tóm tắt & AI Hỏi-Đáp (RAG): Điền `LLM_API_KEY` vào `.env` (hoặc trải nghiệm ngay
  với chế độ dự phòng thông minh tích hợp sẵn). Trong AI Hỏi-Đáp (`/ai`), đặt câu hỏi
  tự nhiên và bấm trực tiếp vào thẻ ghi chú tham chiếu bên dưới câu trả lời.
- PWA Offline: Mở ứng dụng khi có mạng để Service Worker & IndexedDB lưu đồng bộ, sau đó
  chuyển DevTools -> Network sang "Offline": vẫn xem, tìm kiếm và soạn ghi chú bình thường;
  khi bật "Online" lại, dữ liệu tự động đồng bộ lên MySQL.
================================================================================
