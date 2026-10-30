<?php
/**
 * config.php
 * Nạp biến môi trường từ file .env và định nghĩa các hằng số dùng chung
 * cho toàn bộ ứng dụng NoteApp.
 */

declare(strict_types=1);

// Thiết lập múi giờ thống nhất với MySQL (+07:00) để token/OTP không lệch giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// ------------------------------------------------------------
// Đọc file .env (parser thuần PHP, không phụ thuộc thư viện ngoài)
// ------------------------------------------------------------
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        // Bỏ dấu nháy bao quanh giá trị nếu có
        $value = trim($value, "\"'");

        // Ưu tiên biến môi trường hệ thống (ví dụ truyền từ docker-compose)
        if ($name !== '' && getenv($name) === false) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

/**
 * Lấy giá trị biến môi trường, có giá trị mặc định.
 */
function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    if (is_string($value)) {
        $lower = strtolower($value);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
    }
    return $value;
}

// ------------------------------------------------------------
// Cấu hình ứng dụng
// ------------------------------------------------------------
define('APP_ENV', (string) env('APP_ENV', 'production'));
define('APP_DEBUG', (bool) env('APP_DEBUG', false));
define('APP_URL', (string) env('APP_URL', ''));
define('APP_KEY', (string) env('APP_KEY', 'noteapp_default_secret_key_change_in_env'));
define('APP_ROOT', __DIR__);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

// ------------------------------------------------------------
// Cấu hình cơ sở dữ liệu
// ------------------------------------------------------------
define('DB_HOST', (string) env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (string) env('DB_PORT', '3306'));
define('DB_NAME', (string) env('DB_NAME', 'noteapp'));
define('DB_USER', (string) env('DB_USER', 'root'));
define('DB_PASS', (string) env('DB_PASS', ''));

// ------------------------------------------------------------
// Cấu hình email SMTP
// ------------------------------------------------------------
define('MAIL_HOST', (string) env('MAIL_HOST', ''));
define('MAIL_PORT', (int) env('MAIL_PORT', 587));
define('MAIL_USERNAME', (string) env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', (string) env('MAIL_PASSWORD', ''));
define('MAIL_ENCRYPTION', (string) env('MAIL_ENCRYPTION', 'tls'));
define('MAIL_FROM_ADDRESS', (string) env('MAIL_FROM_ADDRESS', 'no-reply@noteapp.local'));
define('MAIL_FROM_NAME', (string) env('MAIL_FROM_NAME', 'NoteApp'));

// ------------------------------------------------------------
// Cấu hình WebSocket
// ------------------------------------------------------------
define('WS_HOST', (string) env('WS_HOST', '0.0.0.0'));
define('WS_PORT', (int) env('WS_PORT', 8081));

// ------------------------------------------------------------
// Cấu hình AI / LLM
// ------------------------------------------------------------
define('LLM_API_KEY', (string) env('LLM_API_KEY', ''));
define('LLM_API_URL', (string) env('LLM_API_URL', 'https://api.openai.com/v1/chat/completions'));
define('LLM_MODEL', (string) env('LLM_MODEL', 'gpt-4o-mini'));

// ------------------------------------------------------------
// Cấu hình bảo mật & phiên làm việc
// ------------------------------------------------------------
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 120));
define('RATE_LIMIT_LOGIN_MAX_ATTEMPTS', (int) env('RATE_LIMIT_LOGIN_MAX_ATTEMPTS', 5));
define('RATE_LIMIT_LOGIN_LOCK_MINUTES', (int) env('RATE_LIMIT_LOGIN_LOCK_MINUTES', 15));

// ------------------------------------------------------------
// Đường dẫn lưu trữ file tải lên (dùng URL tương đối, không hardcode host/port)
// ------------------------------------------------------------
define('UPLOAD_PATH', APP_ROOT . '/public/uploads');
define('UPLOAD_URL', '/uploads');
define('AVATAR_DEFAULT', '/assets/images/default-avatar.svg');

// Giới hạn upload file đính kèm (15MB, hỗ trợ ảnh, video, tài liệu phổ biến)
define('UPLOAD_MAX_SIZE', 15 * 1024 * 1024);
define('UPLOAD_ALLOWED_MIME', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'video/mp4',
    'video/webm',
    'video/ogg',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/zip',
    'application/x-zip-compressed',
    'text/plain',
    'text/csv',
]);

// Giới hạn upload avatar (2MB)
define('AVATAR_MAX_SIZE', 2 * 1024 * 1024);
define('AVATAR_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp']);
