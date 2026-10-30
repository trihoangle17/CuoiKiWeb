<?php
/**
 * views/profile/index.php
 * Trang hồ sơ cá nhân: xem và cập nhật Tên hiển thị + Ảnh đại diện,
 * và chức năng Đổi mật khẩu an toàn.
 */
$errors = $errors ?? [];
$avatarSrc = !empty($currentUser['avatar_path']) ? e($currentUser['avatar_path']) : AVATAR_DEFAULT;
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h4 fw-bold mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>Hồ sơ cá nhân</h1>
    <a href="/profile/settings" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-sliders me-1"></i>Tùy chọn hiển thị
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Thông tin tài khoản &amp; Ảnh đại diện</h2>
                <form method="post" action="/profile" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="form" value="profile">

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <img src="<?= $avatarSrc ?>" alt="Ảnh đại diện hiện tại" id="avatarPreview"
                             class="rounded-circle border shadow-sm flex-shrink-0" width="80" height="80" style="object-fit:cover;">
                        <div class="flex-grow-1 min-w-0">
                            <label for="avatar" class="form-label small fw-medium mb-1">Tải lên ảnh đại diện mới</label>
                            <input type="file" class="form-control form-control-sm" id="avatar" name="avatar"
                                   accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small">Hỗ trợ JPG, PNG, WEBP (tối đa 2MB).</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="display_name" class="form-label fw-medium">Tên hiển thị</label>
                        <input type="text" class="form-control" id="display_name" name="display_name"
                               value="<?= e($currentUser['display_name']) ?>" maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Địa chỉ Email</label>
                        <input type="email" class="form-control" value="<?= e($currentUser['email']) ?>" disabled readonly>
                        <div class="form-text small">
                            Trạng thái:
                            <?php if (!empty($currentUser['is_active'])): ?>
                                <span class="text-success fw-semibold"><i class="bi bi-patch-check-fill"></i> Đã kích hoạt</span>
                            <?php else: ?>
                                <span class="text-warning fw-semibold"><i class="bi bi-exclamation-circle-fill"></i> Chưa kích hoạt email</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i>Lưu thông tin hồ sơ
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Đổi mật khẩu</h2>
                <form method="post" action="/profile">
                    <?= csrfField() ?>
                    <input type="hidden" name="form" value="password">

                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-medium">Mật khẩu hiện tại</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-medium">Mật khẩu mới</label>
                        <input type="password" class="form-control" id="new_password" name="new_password"
                               required minlength="6" placeholder="Tối thiểu 6 ký tự">
                    </div>

                    <div class="mb-3">
                        <label for="new_password_confirm" class="form-label fw-medium">Nhập lại mật khẩu mới</label>
                        <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm"
                               required minlength="6" placeholder="Xác nhận mật khẩu mới">
                    </div>

                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-shield-lock me-1"></i>Cập nhật mật khẩu
                    </button>
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>Hệ thống sẽ tự động làm mới định danh phiên làm việc (Session ID) để bảo vệ tài khoản sau khi đổi mật khẩu.
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('avatar')?.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function (ev) {
                const img = document.getElementById('avatarPreview');
                if (img) img.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
</script>
