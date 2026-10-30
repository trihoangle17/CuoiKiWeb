<?php
/**
 * views/auth/reset_password.php
 * Giao diện đặt lại mật khẩu mới:
 * - Nếu truy cập từ liên kết email (?token=xxx): chỉ cần nhập mật khẩu mới 2 lần.
 * - Nếu không có token: nhập Email + mã OTP 6 chữ số + mật khẩu mới 2 lần.
 */
$errors = $errors ?? [];
$token = $token ?? ($_GET['token'] ?? '');
$email = $email ?? ($_GET['email'] ?? '');
?>
<h1 class="h4 text-center fw-bold mb-2">Đặt lại mật khẩu mới</h1>
<p class="text-muted text-center small mb-4">
    <?= $token !== '' ? 'Nhập mật khẩu mới cho tài khoản của bạn.' : 'Xác thực bằng mã OTP 6 chữ số đã gửi tới email của bạn.' ?>
</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2" role="alert">
        <ul class="mb-0 ps-3 small">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="/reset-password" novalidate>
    <?= csrfField() ?>

    <?php if ($token !== ''): ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
    <?php else: ?>
        <div class="mb-3">
            <label for="email" class="form-label fw-medium">Địa chỉ Email</label>
            <input type="email" class="form-control" id="email" name="email" required
                   placeholder="name@example.com" value="<?= e($email) ?>">
        </div>

        <div class="mb-3">
            <label for="otp" class="form-label fw-medium">Mã OTP (6 chữ số trong email)</label>
            <input type="text" class="form-control text-center fw-bold fs-5 letter-spacing-otp" id="otp" name="otp"
                   required inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code">
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <label for="password" class="form-label fw-medium">Mật khẩu mới</label>
        <input type="password" class="form-control" id="password" name="password" required minlength="6"
               placeholder="Tối thiểu 6 ký tự">
    </div>

    <div class="mb-3">
        <label for="password_confirm" class="form-label fw-medium">Nhập lại mật khẩu mới</label>
        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="6"
               placeholder="Xác nhận mật khẩu mới">
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold">
        <i class="bi bi-check2-circle me-1"></i>Lưu mật khẩu mới
    </button>
</form>

<p class="text-center mt-4 mb-0 small">
    <a href="/login" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Quay lại đăng nhập</a>
</p>
