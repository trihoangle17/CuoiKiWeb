<?php
/**
 * functions.php
 * Các hàm tiện ích dùng chung trong toàn bộ ứng dụng NoteApp.
 */

declare(strict_types=1);

/**
 * Chuyển chuỗi dạng "forgot-password" thành "forgotPassword" (camelCase)
 * để gọi đúng tên method trong controller.
 */
function camelCaseAction(string $segment): string
{
    $parts = explode('-', $segment);
    $first = array_shift($parts);
    $rest = array_map('ucfirst', $parts);
    return $first . implode('', $rest);
}

/**
 * Escape chuỗi đầu ra HTML để chống XSS.
 * Bắt buộc dùng hàm này ở mọi nơi hiển thị dữ liệu ra view.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Chuyển hướng trình duyệt đến URL tương đối và kết thúc script.
 */
function redirect(string $path): void
{
    // Đảm bảo chỉ redirect nội bộ (URL tương đối bắt đầu bằng /)
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

/**
 * Lấy đường dẫn tương đối hiện tại (dùng cho redirect sau khi đăng nhập).
 */
function currentUrl(): string
{
    return $_SERVER['REQUEST_URI'] ?? '/';
}

/**
 * Tự động xác định Base URL theo request thực tế (không hardcode host/port),
 * dùng khi cần tạo đường dẫn tuyệt đối gửi trong nội dung email.
 */
function baseUrl(): string
{
    if (!empty($_SERVER['HTTP_HOST'])) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $isHttps ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
    return rtrim(APP_URL, '/');
}

/**
 * Tạo token CSRF và lưu vào session nếu chưa có.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Trả về thẻ input hidden chứa CSRF token dùng trong form HTML.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/**
 * Kiểm tra CSRF token gửi lên có khớp với token trong session không.
 */
function verifyCsrfToken(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Bắt buộc kiểm tra CSRF token từ POST hoặc header X-CSRF-Token.
 */
function requireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!verifyCsrfToken($token)) {
        if (isAjaxRequest()) {
            jsonResponse(['error' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 419);
        }
        http_response_code(419);
        echo 'Phiên làm việc không hợp lệ hoặc đã hết hạn. Vui lòng tải lại trang.';
        exit;
    }
}

/**
 * Tạo chữ ký HMAC cho phép client tham gia phòng WebSocket cộng tác của một ghi chú.
 */
function createWsCollabToken(int $noteId, int $userId, string $displayName): array
{
    $exp = time() + 7200; // Hiệu lực 2 giờ
    $payload = $noteId . ':' . $userId . ':' . $exp . ':' . $displayName;
    $sig = hash_hmac('sha256', $payload, APP_KEY);

    return [
        'noteId' => $noteId,
        'userId' => $userId,
        'displayName' => $displayName,
        'exp' => $exp,
        'sig' => $sig,
    ];
}

/**
 * Xác thực chữ ký HMAC khi client kết nối vào WebSocket server.
 */
function verifyWsCollabToken(int $noteId, int $userId, string $displayName, int $exp, string $sig): bool
{
    if ($exp < time() || $sig === '') {
        return false;
    }
    $payload = $noteId . ':' . $userId . ':' . $exp . ':' . $displayName;
    $expected = hash_hmac('sha256', $payload, APP_KEY);
    return hash_equals($expected, $sig);
}

/**
 * Đặt flash message hiển thị một lần rồi tự xóa.
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/**
 * Lấy và xóa flash message theo loại (success, error, info).
 */
function getFlash(string $type): ?string
{
    if (empty($_SESSION['flash'][$type])) {
        return null;
    }
    $message = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);
    return $message;
}

/**
 * Trả về dữ liệu JSON chuẩn UTF-8 và kết thúc script.
 */
function jsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lấy HTTP method hiện tại (GET, POST, ...).
 */
function requestMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Kiểm tra request hiện tại có phải AJAX / Fetch JSON hay không.
 */
function isAjaxRequest(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return str_contains($accept, 'application/json') || strtolower($xhr) === 'xmlhttprequest';
}

/**
 * Định dạng dung lượng file dễ đọc (KB, MB).
 */
function formatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}

/**
 * Định dạng thời gian tương đối tiếng Việt ("Vừa xong", "5 phút trước"...).
 */
function timeAgo(?string $datetime): string
{
    if (empty($datetime)) {
        return '';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Vừa xong';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . ' phút trước';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . ' giờ trước';
    }
    if ($diff < 2592000) {
        return (int) floor($diff / 86400) . ' ngày trước';
    }

    return date('d/m/Y H:i', $timestamp);
}
