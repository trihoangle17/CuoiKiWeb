<?php
/**
 * layouts/app.php
 * Layout chính cho toàn bộ các trang đã đăng nhập.
 * Áp dụng đầy đủ tùy chọn hiển thị của người dùng: theme (light/dark),
 * font_size (small/medium/large) và note_color (màu thẻ ghi chú).
 */
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'NoteApp';
$currentUser = $currentUser ?? null;
$unseenShareCount = $unseenShareCount ?? 0;
$userTheme = $currentUser['theme'] ?? 'light';
$userFontSize = $currentUser['font_size'] ?? 'medium';
$userNoteColor = $currentUser['note_color'] ?? '#ffffff';
$hasCustomNoteColor = strtolower($userNoteColor) !== '#ffffff';
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="<?= e($userTheme) ?>">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
    <title><?= e($pageTitle) ?> - NoteApp</title>
</head>
<body class="app-body font-size-<?= e($userFontSize) ?> <?= $hasCustomNoteColor ? 'has-custom-note-color' : '' ?>"
      style="--user-note-color: <?= e($userNoteColor) ?>;">

<a href="#mainContent" class="visually-hidden-focusable skip-link">Chuyển đến nội dung chính</a>

<?php require __DIR__ . '/partials/navbar.php'; ?>

<div class="app-layout">
    <?php require __DIR__ . '/partials/sidebar.php'; ?>

    <main class="app-main" id="mainContent" tabindex="-1">
        <?php require __DIR__ . '/partials/flash.php'; ?>

        <?php if (!empty($currentUser) && empty($currentUser['is_active'])): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 activation-banner shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                <div class="flex-grow-1">
                    <strong>Tài khoản chưa được xác thực email.</strong>
                    Bạn vẫn có thể sử dụng đầy đủ mọi tính năng, nhưng vui lòng kiểm tra hộp thư <strong><?= e($currentUser['email']) ?></strong> và bấm vào liên kết kích hoạt để hoàn tất xác minh.
                </div>
            </div>
        <?php endif; ?>

        <?php if ($unseenShareCount > 0): ?>
            <div class="alert alert-info alert-dismissible fade show d-flex align-items-center justify-content-between gap-2 shadow-sm" role="alert">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-bell-fill fs-5 text-primary"></i>
                    <span>Bạn có <strong><?= (int) $unseenShareCount ?></strong> ghi chú mới vừa được chia sẻ với bạn!</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="/shares/with-me" class="btn btn-sm btn-primary">Xem ngay</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng thông báo"></button>
                </div>
            </div>
        <?php endif; ?>

        <?php require $viewFile; ?>
    </main>
</div>

<?php if (!empty($currentUser)): ?>
    <?php require __DIR__ . '/partials/label_modal.php'; ?>
<?php endif; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

<script>
    window.csrfToken = <?= json_encode(csrfToken()) ?>;
    window.WS_PORT = <?= (int) WS_PORT ?>;
</script>
<?php require __DIR__ . '/partials/scripts.php'; ?>
</body>
</html>
