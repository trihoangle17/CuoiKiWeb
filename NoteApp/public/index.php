<?php
/**
 * public/index.php
 * Front Controller - Điểm vào duy nhất của toàn bộ ứng dụng NoteApp.
 * Hỗ trợ định tuyến tự động trên cả Apache (.htaccess), Docker và PHP Built-in Server.
 */

declare(strict_types=1);

// Nếu chạy bằng PHP Built-in Server (php -S localhost:8080 -t public public/index.php),
// phục vụ trực tiếp các tệp tĩnh tồn tại trong thư mục public/ (trừ file .php trong uploads)
if (PHP_SAPI === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $staticFile = __DIR__ . $reqPath;
    if ($reqPath !== '/' && is_file($staticFile)) {
        if (str_starts_with($reqPath, '/uploads/') && preg_match('/\.(php|phtml|phar)$/i', $reqPath)) {
            http_response_code(403);
            exit;
        }
        return false;
    }
}

require_once __DIR__ . '/../config.php';

// Cấu hình cookie phiên làm việc an toàn (HttpOnly, SameSite=Lax)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME * 60,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../app/models/Database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/auth_middleware.php';

// ------------------------------------------------------------
// Kiểm tra kết nối cơ sở dữ liệu đầu tiên:
// Nếu chưa kết nối được MySQL, CHỈ hiển thị trang "Yêu cầu kết nối cơ sở dữ liệu".
// ------------------------------------------------------------
if (!Database::isConnected()) {
    http_response_code(503);
    require_once __DIR__ . '/../app/views/errors/db_error.php';
    exit;
}

// ------------------------------------------------------------
// Phân tích đường dẫn URL tương đối thành [controller, action, ...params]
// ------------------------------------------------------------
if (isset($_GET['url'])) {
    $url = (string) $_GET['url'];
} else {
    $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $url = ltrim($rawPath, '/');
}

$url = trim($url, '/');
$segments = $url === '' ? [] : explode('/', $url);

$controllerSegment = $segments[0] ?? '';
$actionSegment = $segments[1] ?? '';
$params = array_slice($segments, 2);

// ------------------------------------------------------------
// Bảng ánh xạ Route -> Controller
// ------------------------------------------------------------
$routeMap = [
    ''                => ['NoteController', 'index'],
    'login'           => ['AuthController', 'login'],
    'register'        => ['AuthController', 'register'],
    'logout'          => ['AuthController', 'logout'],
    'activate'        => ['AuthController', 'activate'],
    'forgot-password' => ['AuthController', 'forgotPassword'],
    'reset-password'  => ['AuthController', 'resetPassword'],

    'notes'           => ['NoteController', 'index'],
    'profile'         => ['ProfileController', 'index'],
    'labels'          => ['LabelController', 'index'],
    'shares'          => ['ShareController', 'index'],
    'ai'              => ['AiController', 'index'],
    'sync'            => ['SyncController', 'index'],
];

$publicRoutes = ['login', 'register', 'activate', 'forgot-password', 'reset-password'];

if ($controllerSegment === '' || $controllerSegment === 'notes') {
    $controllerName = 'NoteController';
    $routeKey = $controllerSegment === '' ? '' : $controllerSegment;
} elseif (array_key_exists($controllerSegment, $routeMap)) {
    $controllerName = $routeMap[$controllerSegment][0];
    $routeKey = $controllerSegment;
} else {
    http_response_code(404);
    require_once __DIR__ . '/../app/views/errors/404.php';
    exit;
}

$isPublicRoute = in_array($routeKey, $publicRoutes, true);

// ------------------------------------------------------------
// Chưa đăng nhập thì lập tức chuyển hướng về /login
// ------------------------------------------------------------
if (!$isPublicRoute) {
    requireLogin();
}

$defaultActionMap = [
    ''                => 'index',
    'login'           => 'login',
    'register'        => 'register',
    'logout'          => 'logout',
    'activate'        => 'activate',
    'forgot-password' => 'forgotPassword',
    'reset-password'  => 'resetPassword',
    'notes'           => 'index',
    'profile'         => 'index',
    'labels'          => 'index',
    'shares'          => 'index',
    'ai'              => 'index',
    'sync'            => 'index',
];

$method = $actionSegment !== '' ? camelCaseAction($actionSegment) : $defaultActionMap[$routeKey];

$controllerFile = __DIR__ . '/../app/controllers/' . $controllerName . '.php';

if (!is_file($controllerFile)) {
    http_response_code(404);
    require_once __DIR__ . '/../app/views/errors/404.php';
    exit;
}

require_once $controllerFile;

if (!class_exists($controllerName)) {
    http_response_code(500);
    require_once __DIR__ . '/../app/views/errors/500.php';
    exit;
}

$controller = new $controllerName();

if (!method_exists($controller, $method)) {
    http_response_code(404);
    require_once __DIR__ . '/../app/views/errors/404.php';
    exit;
}

try {
    $controller->$method(...$params);
} catch (Throwable $e) {
    if (APP_DEBUG) {
        throw $e;
    }
    error_log('Lỗi ứng dụng NoteApp: ' . $e->getMessage());
    http_response_code(500);
    require_once __DIR__ . '/../app/views/errors/500.php';
    exit;
}
