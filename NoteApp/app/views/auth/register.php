<?php
/**
 * views/auth/register.php
 * Giao diện đăng ký tài khoản mới: chỉ yêu cầu Email, Tên hiển thị và Mật khẩu nhập 2 lần.
 */
$errors = $errors ?? [];
$old = $old ?? ['email' => '', 'display_name' => ''];
?>
<h1 class="h4 text-center fw-bold mb-1">Tạo tài khoản mới</h1>
<p class="text-muted text-center small mb-4">Bắt đầu lưu trữ và đồng bộ ghi chú ở mọi nơi</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2" role="alert">
        <ul class="mb-0 ps-3 small">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="/register" novalidate>
    <?= csrfField() ?>

    <div class="mb-3">
        <label for="display_name" class="form-label fw-medium">Tên hiển thị</label>
        <input type="text" class="form-control" id="display_name" name="display_name" required autofocus
               placeholder="Ví dụ: Nguyễn Văn An" maxlength="100" value="<?= e($old['display_name']) ?>">
    </div>

    <div class="mb-3">
        <label for="email" class="form-label fw-medium">Địa chỉ Email</label>
        <input type="email" class="form-control" id="email" name="email" required
               placeholder="name@example.com" value="<?= e($old['email']) ?>">
    </div>

    <div class="mb-3">
        <label for="password" class="form-label fw-medium">Mật khẩu</label>
        <input type="password" class="form-control" id="password" name="password" required minlength="6"
               placeholder="Tối thiểu 6 ký tự">
    </div>

    <div class="mb-3">
        <label for="password_confirm" class="form-label fw-medium">Nhập lại mật khẩu</label>
        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="6"
               placeholder="Xác nhận lại mật khẩu">
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold mt-2">
        <i class="bi bi-person-plus me-1"></i>Đăng ký &amp; Bắt đầu ngay
    </button>
</form>

<p class="text-center mt-4 mb-0 small">
    Đã có tài khoản? <a href="/login" class="fw-semibold text-decoration-none">Đăng nhập</a>
</p>
