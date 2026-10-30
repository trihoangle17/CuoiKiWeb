-- ============================================================
-- NoteApp - Cấu trúc cơ sở dữ liệu (MySQL / MariaDB)
-- Đồ án môn Web Programming & Applications (503073)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS noteapp
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE noteapp;

-- ------------------------------------------------------------
-- Bảng users: tài khoản người dùng
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL COMMENT 'NULL = dùng avatar mặc định',
    is_active TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Đã kích hoạt email hay chưa',
    activation_token VARCHAR(100) DEFAULT NULL,
    activation_token_expires_at DATETIME DEFAULT NULL,
    -- Tùy chọn hiển thị của người dùng
    font_size VARCHAR(10) NOT NULL DEFAULT 'medium' COMMENT 'small|medium|large',
    note_color VARCHAR(20) NOT NULL DEFAULT '#ffffff',
    theme VARCHAR(10) NOT NULL DEFAULT 'light' COMMENT 'light|dark',
    -- Chống brute-force đăng nhập (rate-limit)
    failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Bảng password_resets: quên mật khẩu qua link hoặc OTP
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(100) NOT NULL COMMENT 'Token dùng cho link đặt lại mật khẩu',
    otp_code VARCHAR(10) DEFAULT NULL COMMENT 'Mã OTP 6 chữ số',
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_resets_token (token),
    INDEX idx_password_resets_otp (user_id, otp_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Bảng labels: nhãn ghi chú, mỗi user quản lý nhãn riêng
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS labels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_labels_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_labels_user_name (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Bảng notes: ghi chú của người dùng
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'Chủ sở hữu ghi chú',
    title VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    ai_summary TEXT DEFAULT NULL COMMENT 'Bản tóm tắt AI gần nhất (có thể tạo lại)',
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    pinned_at DATETIME DEFAULT NULL COMMENT 'Thời điểm ghim, dùng để sắp xếp nhiều ghi chú ghim',
    -- Khóa mật khẩu riêng từng ghi chú
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    lock_password_hash VARCHAR(255) DEFAULT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Xóa mềm',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notes_user_updated (user_id, updated_at),
    FULLTEXT INDEX ft_notes_title_content (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Bảng note_labels: quan hệ nhiều-nhiều giữa notes và labels
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS note_labels (
    note_id INT UNSIGNED NOT NULL,
    label_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (note_id, label_id),
    CONSTRAINT fk_note_labels_note FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    CONSTRAINT fk_note_labels_label FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Bảng attachments: file đính kèm (ảnh/video/file) của ghi chú
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    note_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL COMMENT 'Tên file ngẫu nhiên lưu trên server',
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL COMMENT 'Kích thước tính bằng byte',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attachments_note FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Bảng note_shares: chia sẻ ghi chú qua email người dùng đã đăng ký
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS note_shares (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    note_id INT UNSIGNED NOT NULL,
    shared_with_user_id INT UNSIGNED NOT NULL,
    permission ENUM('view', 'edit') NOT NULL DEFAULT 'view',
    shared_by_user_id INT UNSIGNED NOT NULL,
    is_seen TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = thông báo mới cho người nhận khi đăng nhập',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_shares_note FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    CONSTRAINT fk_shares_shared_with FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_shares_shared_by FOREIGN KEY (shared_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_shares_note_user (note_id, shared_with_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DỮ LIỆU DEMO (SEED DATA)
-- Mật khẩu của tất cả tài khoản demo: Demo@123
-- Mật khẩu khóa của ghi chú bị khóa (ID 4): 1234
-- ============================================================

INSERT INTO users (id, email, password_hash, display_name, is_active, activation_token, activation_token_expires_at, font_size, note_color, theme)
VALUES
    (1, 'demo@noteapp.local', '$2y$10$iU4Ik8DfSr0ubktXco9nG.nlfegMEJ5sXJabxqkCn4EneA/DpMyBq', 'Nguyễn Văn An (Demo)', 1, NULL, NULL, 'medium', '#ffffff', 'light'),
    (2, 'friend@noteapp.local', '$2y$10$iU4Ik8DfSr0ubktXco9nG.nlfegMEJ5sXJabxqkCn4EneA/DpMyBq', 'Trần Thị Bình (Cộng tác)', 1, NULL, NULL, 'medium', '#fff9db', 'light'),
    (3, 'unverified@noteapp.local', '$2y$10$iU4Ik8DfSr0ubktXco9nG.nlfegMEJ5sXJabxqkCn4EneA/DpMyBq', 'Lê Hoàng Nam (Chưa kích hoạt)', 0, 'demo_activation_token_123456', DATE_ADD(NOW(), INTERVAL 7 DAY), 'medium', '#ffffff', 'light')
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO labels (id, user_id, name) VALUES
    (1, 1, 'Công việc'),
    (2, 1, 'Cá nhân'),
    (3, 1, 'Học tập'),
    (4, 2, 'Dự án nhóm')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO notes (id, user_id, title, content, is_pinned, pinned_at, is_locked, lock_password_hash) VALUES
    (1, 1, 'Chào mừng đến với NoteApp', 'Đây là ứng dụng quản lý ghi chú phục vụ học tập và công việc:\n- Tự động lưu ngay khi gõ (không cần bấm nút Save).\n- Ghim nhiều ghi chú quan trọng theo thứ tự thời gian ghim.\n- Gắn nhãn, đính kèm hình ảnh, video, tài liệu.\n- Chia sẻ ghi chú qua email và cộng tác chỉnh sửa thời gian thực bằng WebSocket.\n- Tóm tắt nội dung và Hỏi - Đáp thông minh (RAG) bằng AI.', 1, DATE_SUB(NOW(), INTERVAL 10 MINUTE), 0, NULL),
    (2, 1, 'Kế hoạch đồ án môn Web Programming', 'Lịch trình thực hiện đồ án học kỳ I:\n1. Tuần 1: Thiết kế cơ sở dữ liệu MySQL và kiến trúc MVC thuần bằng PHP.\n2. Tuần 2: Xây dựng phân hệ Tài khoản (bcrypt, CSRF, kích hoạt email, OTP quên mật khẩu) và Ghi chú cơ bản.\n3. Tuần 3: Phát triển tính năng Khóa mật khẩu riêng từng ghi chú, Chia sẻ và đồng bộ WebSocket thời gian thực.\n4. Tuần 4: Tích hợp AI Tóm tắt, AI Hỏi - Đáp (RAG), PWA Offline (Service Worker + IndexedDB) và đóng gói Docker Compose.\nHọp nhóm định kỳ vào 14h00 chiều Thứ Sáu hàng tuần tại phòng B204.', 1, NOW(), 0, NULL),
    (3, 1, 'Ghi chú ôn tập Bảo mật Web & REST API', 'Các nguyên tắc bảo mật quan trọng đã áp dụng trong hệ thống:\n- Mật khẩu lưu bằng thuật toán bcrypt (password_hash & password_verify).\n- Mọi truy vấn CSDL đều sử dụng PDO Prepared Statements với ATTR_EMULATE_PREPARES = false để chống SQL Injection.\n- Escape toàn bộ dữ liệu đầu ra bằng htmlspecialchars() chống XSS.\n- Kiểm tra CSRF token trên mọi request thay đổi dữ liệu.\n- Xác thực MIME thực tế của file tải lên bằng finfo và đổi tên file ngẫu nhiên.', 0, NULL, 0, NULL),
    (4, 1, 'Thông tin tài khoản ngân hàng & mã PIN cá nhân', 'Ghi chú bảo mật cá nhân:\n- Ngân hàng Vietcombank chi nhánh Quận 7.\n- Mã két sắt lưu trữ tài liệu ở nhà: 8899.\n- Mật khẩu mở khóa ghi chú demo này là: 1234.', 0, NULL, 1, '$2y$10$KazZqtWLwfwp7FhL1XXMw.kZ8ZOlzpIkBqrKyhrix5OFOVLHe.Tf.'),
    (5, 2, 'Biên bản họp nhóm thiết kế giao diện UI/UX', 'Nội dung thống nhất từ buổi họp với Bình:\n- Giao diện hỗ trợ đầy đủ chế độ Sáng (Light) và Tối (Dark), cỡ chữ Nhỏ / Vừa / Lớn và màu nền thẻ ghi chú.\n- Tối ưu responsive trên cả điện thoại, máy tính bảng và màn hình desktop.\n- Ghi chú này đã được Bình chia sẻ quyền Chỉnh sửa (Edit) cho An để hai người cùng sửa trực tiếp qua WebSocket.', 1, NOW(), 0, NULL),
    (6, 2, 'Tài liệu tham khảo cấu hình Docker Compose', 'Danh sách các container trong hệ thống:\n- app: PHP 8.2 + Apache (cổng 8080).\n- db: MySQL 8.0 tự động nạp database/schema.sql khi khởi chạy lần đầu.\n- websocket: PHP WebSocket Server lắng nghe cổng 8081 phục vụ đồng bộ thời gian thực.\nGhi chú này được chia sẻ ở chế độ Chỉ đọc (Read-only).', 0, NULL, 0, NULL)
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO note_labels (note_id, label_id) VALUES
    (1, 2),
    (2, 1),
    (2, 3),
    (3, 3),
    (4, 2),
    (5, 4)
ON DUPLICATE KEY UPDATE label_id = VALUES(label_id);

INSERT INTO note_shares (id, note_id, shared_with_user_id, permission, shared_by_user_id, is_seen) VALUES
    (1, 2, 2, 'edit', 1, 0),
    (2, 5, 1, 'edit', 2, 0),
    (3, 6, 1, 'view', 2, 0)
ON DUPLICATE KEY UPDATE permission = VALUES(permission);
