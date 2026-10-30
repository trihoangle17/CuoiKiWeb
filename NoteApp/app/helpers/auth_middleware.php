<?php
/**
 * auth_middleware.php
 * Cac ham kiem tra xac thuc, dung trong router va controller.
 */

declare(strict_types=1);

/**
 * Kiem tra nguoi dung da dang nhap hay chua.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Lay id nguoi dung dang dang nhap, null neu chua dang nhap.
 */
function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Bat buoc nguoi dung phai dang nhap, neu chua thi redirect ve /login.
 * Luu lai URL dang truy cap de sau khi dang nhap co the quay lai (tuy chon).
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = currentUrl();
        redirect('/login');
    }
}

/**
 * Dang nhap nguoi dung: luu session va chong co dinh session (session fixation)
 * bang cach tao lai session id moi.
 */
function loginUser(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['login_time'] = time();
}

/**
 * Dang xuat nguoi dung: xoa toan bo session va tao session moi rong.
 */
function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    session_start();
    session_regenerate_id(true);
}
