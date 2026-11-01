<?php
/**
 * partials/navbar.php
 * Thanh điều hướng trên cùng: nút mở menu mobile, logo, ô tìm kiếm live
 * (không có nút Search), trạng thái mạng Offline/Online, đổi Sáng/Tối và menu tài khoản.
 */
$currentUser = $currentUser ?? null;
$avatarSrc = !empty($currentUser['avatar_path']) ? e($currentUser['avatar_path']) : AVATAR_DEFAULT;
?>
<header>
    <nav class="navbar navbar-expand app-navbar border-bottom px-2 px-md-3" aria-label="Thanh điều hướng chính">
        <div class="container-fluid gap-2">
            <?php if ($currentUser): ?>
            <button class="btn btn-icon d-lg-none" type="button" id="btnToggleSidebar" aria-label="Mở menu điều hướng" aria-controls="appSidebar" aria-expanded="false">
                <i class="bi bi-list fs-4"></i>
            </button>
            <?php endif; ?>

            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="/notes">
                <i class="bi bi-journal-richtext fs-4 text-primary"></i>
                <span class="d-none d-sm-inline">NoteApp</span>
            </a>

            <?php if ($currentUser): ?>
            <div class="flex-grow-1 mx-1 mx-md-4 app-search-wrap">
                <label for="noteSearchInput" class="visually-hidden">Tìm kiếm ghi chú theo tiêu đề hoặc nội dung</label>
                <div class="input-group search-input-group">
                    <span class="input-group-text bg-body-tertiary border-end-0">
                        <i class="bi bi-search text-muted" id="searchIcon"></i>
                        <span class="spinner-border spinner-border-sm text-primary d-none" id="searchSpinner" role="status" aria-hidden="true"></span>
                    </span>
                    <input
                        type="search"
                        id="noteSearchInput"
                        class="form-control bg-body-tertiary border-start-0"
                        placeholder="Tìm kiếm ghi chú... (Ctrl+K hoặc /)"
                        autocomplete="off">
                </div>
            </div>

            <div class="d-flex align-items-center gap-1 gap-sm-2">
                <span class="badge rounded-pill text-bg-warning d-none align-items-center gap-1 py-2 px-3" id="offlineBadge" title="Đang xem chế độ ngoại tuyến, tự đồng bộ khi có mạng">
                    <i class="bi bi-wifi-off"></i>
                    <span class="d-none d-md-inline">Ngoại tuyến</span>
                </span>

                <button class="btn btn-icon" type="button" id="btnThemeToggle" aria-label="Chuyển đổi giao diện sáng hoặc tối" title="Đổi giao diện Sáng / Tối">
                    <i class="bi <?= ($currentUser['theme'] ?? 'light') === 'dark' ? 'bi-sun-fill text-warning' : 'bi-moon-stars-fill' ?>" id="themeToggleIcon"></i>
                </button>

                <div class="dropdown">
                    <button class="btn btn-icon p-0 d-flex align-items-center" type="button" id="userMenuBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Mở menu tài khoản">
                        <img src="<?= $avatarSrc ?>" alt="Ảnh đại diện của <?= e($currentUser['display_name']) ?>" class="avatar-sm rounded-circle border">
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenuBtn">
                        <li class="px-3 py-2">
                            <div class="fw-semibold text-truncate" style="max-width: 200px;"><?= e($currentUser['display_name']) ?></div>
                            <div class="small text-muted text-truncate" style="max-width: 200px;"><?= e($currentUser['email']) ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2" href="/profile"><i class="bi bi-person-circle me-2"></i>Hồ sơ &amp; Đổi mật khẩu</a></li>
                        <li><a class="dropdown-item py-2" href="/profile/settings"><i class="bi bi-sliders me-2"></i>Tùy chọn hiển thị</a></li>
                        <li><a class="dropdown-item py-2" href="/shares/with-me"><i class="bi bi-people me-2"></i>Được chia sẻ với tôi</a></li>
                        <li><a class="dropdown-item py-2" href="/ai"><i class="bi bi-stars me-2 text-primary"></i>AI Hỏi – Đáp (RAG)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="/logout" method="post" class="m-0">
                                <?= csrfField() ?>
                                <button type="submit" class="dropdown-item py-2 text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </nav>
</header>
