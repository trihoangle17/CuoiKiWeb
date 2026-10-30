<?php
/**
 * partials/sidebar.php
 * Thanh điều hướng bên trái: Ghi chú của tôi, Được chia sẻ với tôi,
 * AI Hỏi - Đáp (RAG) và danh sách Nhãn (lọc & quản lý nhãn).
 */
if (empty($currentUser)) {
    return;
}

$labels = $labels ?? [];
$activeLabelId = $activeLabelId ?? null;
$unseenShareCount = $unseenShareCount ?? 0;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$isNotesHome = ($currentPath === '/' || $currentPath === '/notes' || str_starts_with($currentPath, '/notes/create') || str_starts_with($currentPath, '/notes/edit')) && !$activeLabelId;
$isSharedPage = str_starts_with($currentPath, '/shares');
$isAiPage = str_starts_with($currentPath, '/ai');
$isProfilePage = str_starts_with($currentPath, '/profile');
?>
<aside class="app-sidebar border-end" id="appSidebar" aria-label="Menu điều hướng chính">
    <div class="px-2 mb-3">
        <a href="/notes/create" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2 rounded-pill shadow-sm">
            <i class="bi bi-plus-lg"></i>
            <span>Tạo ghi chú mới</span>
        </a>
    </div>

    <nav class="sidebar-nav" aria-label="Danh mục chính">
        <a href="/notes" class="sidebar-link <?= $isNotesHome ? 'active' : '' ?>">
            <i class="bi bi-lightbulb"></i>
            <span>Ghi chú của tôi</span>
        </a>
        <a href="/shares/with-me" class="sidebar-link <?= $isSharedPage ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            <span class="flex-grow-1">Được chia sẻ với tôi</span>
            <?php if ($unseenShareCount > 0): ?>
                <span class="badge rounded-pill text-bg-danger"><?= (int) $unseenShareCount ?></span>
            <?php endif; ?>
        </a>
        <a href="/ai" class="sidebar-link <?= $isAiPage ? 'active' : '' ?>">
            <i class="bi bi-stars text-primary"></i>
            <span>AI Hỏi – Đáp</span>
        </a>
    </nav>

    <div class="sidebar-section">
        <div class="sidebar-section-title">
            <span>Nhãn ghi chú</span>
            <button type="button" class="btn btn-sm btn-icon" id="btnManageLabels" title="Quản lý nhãn (thêm, đổi tên, xóa)" aria-label="Quản lý nhãn">
                <i class="bi bi-pencil-square"></i>
            </button>
        </div>
        <nav class="sidebar-nav" id="labelList" aria-label="Lọc theo nhãn">
            <?php foreach ($labels as $label): ?>
                <a href="/notes/label/<?= (int) $label['id'] ?>"
                   class="sidebar-link <?= $activeLabelId === (int) $label['id'] ? 'active' : '' ?>">
                    <i class="bi bi-tag"></i>
                    <span class="text-truncate"><?= e($label['name']) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (empty($labels)): ?>
                <p class="text-muted small px-3 mb-0">Chưa có nhãn nào. Bấm biểu tượng cây bút để tạo nhãn.</p>
            <?php endif; ?>
        </nav>
    </div>

    <div class="sidebar-section mt-auto pt-3 border-top">
        <nav class="sidebar-nav" aria-label="Tài khoản">
            <a href="/profile" class="sidebar-link <?= $currentPath === '/profile' ? 'active' : '' ?>">
                <i class="bi bi-person"></i>
                <span>Hồ sơ cá nhân</span>
            </a>
            <a href="/profile/settings" class="sidebar-link <?= $currentPath === '/profile/settings' ? 'active' : '' ?>">
                <i class="bi bi-palette"></i>
                <span>Tùy chọn hiển thị</span>
            </a>
        </nav>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
