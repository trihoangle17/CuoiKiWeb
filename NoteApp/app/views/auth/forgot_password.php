<?php
/**
 * views/auth/forgot_password.php
 * Giao diện yêu cầu khôi phục mật khẩu qua liên kết hoặc mã OTP gửi về email.
 */
$sent = $sent ?? false;
$errors = $errors ?? [];
$submittedEmail = $submittedEmail ?? '';
?>
<h1 class="h4 text-center fw-bold mb-2">Quên mật khẩu</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2" role="alert">
        <ul class="mb-0 ps-3 small">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($sent): ?>
    <div class="alert alert-success small" role="alert">
        <i class="bi bi-envelope-check-fill me-1"></i>
        Nếu địa chỉ email tồn tại trong hệ thống, chúng tôi đã gửi <strong>liên kết đặt lại mật khẩu</strong> cùng <strong>mã OTP 6 chữ số</strong> đến hộp thư của bạn.
    </div>

    <form method="post" action="/reset-password" class="mt-3" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="step" value="verify_otp">
        <input type="hidden" name="email" value="<?= e($submittedEmail) ?>">

        <div class="mb-3">
            <label for="otp" class="form-label fw-medium">Nhập mã OTP 6 chữ số từ email</label>
            <input type="text" class="form-control text-center fw-bold fs-5 letter-spacing-otp" id="otp" name="otp"
                   required autofocus inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code">
            <div class="form-text small">Hoặc bạn có thể bấm trực tiếp vào liên kết trong email để tới màn hình đặt mật khẩu mới.</div>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-semibold">
            <i class="bi bi-shield-check me-1"></i>Xác thực mã OTP
        </button>
    </form>
<?php else: ?>
    <p class="text-muted text-center small mb-4">
        Nhập email đã đăng ký, hệ thống sẽ gửi liên kết khôi phục và mã OTP 6 số cho bạn.
    </p>

    <form method="post" action="/forgot-password" novalidate>
        <?= csrfField() ?>

        <div class="mb-3">
            <label for="email" class="form-label fw-medium">Địa chỉ Email đã đăng ký</label>
            <input type="email" class="form-control" id="email" name="email" required autofocus
                   placeholder="name@example.com" value="<?= e($submittedEmail) ?>">
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-semibold">
            <i class="bi bi-send me-1"></i>Gửi yêu cầu khôi phục
        </button>
    </form>
<?php endif; ?>

<p class="text-center mt-4 mb-0 small">
    <a href="/login" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Quay lại đăng nhập</a>
</p>
