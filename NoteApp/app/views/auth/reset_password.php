<?php
/**
 * views/auth/reset_password.php
 * Giao diện khôi phục mật khẩu 2 bước chuẩn:
 * - Khi chưa có token: Màn hình xác thực Email + mã OTP 6 chữ số (xác thực xong sẽ redirect sang ?token=xxx).
 * - Khi đã có token hợp lệ (?token=xxx từ link email hoặc sau khi xác thực OTP): Màn hình nhập mật khẩu mới 2 lần.
 */
$errors = $errors ?? [];
$token = $token ?? ($_GET['token'] ?? '');
$email = $email ?? ($_GET['email'] ?? '');
$tokenInvalid = $tokenInvalid ?? false;
?>
<h1 class="h4 text-center fw-bold mb-2">
    <?= $token !== '' ? 'Đặt lại mật khẩu mới' : 'Xác thực mã OTP' ?>
</h1>
<p class="text-muted text-center small mb-4">
    <?= $token !== ''
        ? 'Nhập mật khẩu mới cho tài khoản của bạn.'
        : 'Nhập mã OTP 6 chữ số đã gửi tới email để chuyển sang màn hình đặt mật khẩu mới.' ?>
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

<?php if ($tokenInvalid): ?>
    <div class="d-grid gap-2 mt-3">
        <a href="/forgot-password" class="btn btn-outline-primary">
            <i class="bi bi-arrow-repeat me-1"></i>Yêu cầu lại liên kết / mã OTP mới
        </a>
    </div>
<?php elseif ($token !== ''): ?>
    <form method="post" action="/reset-password" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="mb-3">
            <label for="password" class="form-label fw-medium">Mật khẩu mới</label>
            <input type="password" class="form-control" id="password" name="password" required autofocus minlength="6"
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
<?php else: ?>
    <form method="post" action="/reset-password" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="step" value="verify_otp">

        <div class="mb-3">
            <label for="email" class="form-label fw-medium">Địa chỉ Email</label>
            <input type="email" class="form-control" id="email" name="email" required
                   placeholder="name@example.com" value="<?= e($email) ?>">
        </div>

        <div class="mb-3">
            <label for="otp" class="form-label fw-medium">Mã OTP (6 chữ số trong email)</label>
            <input type="text" class="form-control text-center fw-bold fs-5 letter-spacing-otp" id="otp" name="otp"
                   required autofocus inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code">
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-semibold">
            <i class="bi bi-shield-check me-1"></i>Xác thực mã OTP
        </button>
    </form>
<?php endif; ?>

<p class="text-center mt-4 mb-0 small">
    <a href="/login" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Quay lại đăng nhập</a>
</p>
