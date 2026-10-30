<?php
/**
 * views/notes/unlock.php
 * Màn hình xác thực mật khẩu khi truy cập trực tiếp vào một ghi chú đang khóa mật khẩu.
 */
?>
<div class="d-flex justify-content-center py-5">
    <div class="card shadow-sm" style="max-width: 420px; width: 100%;">
        <div class="card-body p-4 text-center">
            <div class="mb-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning" style="width: 64px; height: 64px;">
                    <i class="bi bi-shield-lock-fill fs-2"></i>
                </span>
            </div>
            <h1 class="h5 fw-bold mb-2">Ghi chú được bảo vệ bằng mật khẩu</h1>
            <p class="text-muted small mb-4">Vui lòng nhập mật khẩu riêng của ghi chú này để tiếp tục xem hoặc chỉnh sửa.</p>

            <form id="unlockForm">
                <?= csrfField() ?>
                <input type="hidden" name="note_id" value="<?= (int) $noteId ?>">
                <div class="mb-3 text-start">
                    <label for="unlockPasswordField" class="visually-hidden">Mật khẩu ghi chú</label>
                    <input type="password" class="form-control" id="unlockPasswordField" name="password" placeholder="Nhập mật khẩu ghi chú..." autofocus required>
                </div>
                <div class="alert alert-danger py-2 small mb-3 d-none" id="unlockError" role="alert"></div>
                <div class="d-flex gap-2">
                    <a href="/notes" class="btn btn-outline-secondary w-50">Quay lại</a>
                    <button type="submit" class="btn btn-primary w-50">
                        <i class="bi bi-unlock me-1"></i>Mở khóa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $extraScripts = ['/assets/js/note-unlock.js']; ?>
