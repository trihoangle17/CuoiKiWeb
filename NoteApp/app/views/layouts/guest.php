<?php
/**
 * layouts/guest.php
 * Layout gọn gàng cho các trang xác thực chưa đăng nhập:
 * đăng nhập, đăng ký, quên mật khẩu, đặt lại mật khẩu.
 */
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'NoteApp';
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="light">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
    <title><?= e($pageTitle) ?> - NoteApp</title>
</head>
<body class="guest-body">

<main class="guest-wrapper">
    <div class="guest-card">
        <div class="guest-brand">
            <i class="bi bi-journal-richtext fs-2 text-primary"></i>
            <span>NoteApp</span>
        </div>

        <?php require __DIR__ . '/partials/flash.php'; ?>

        <?php require $viewFile; ?>
    </div>
</main>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
<script src="/assets/js/toast.js"></script>
</body>
</html>
