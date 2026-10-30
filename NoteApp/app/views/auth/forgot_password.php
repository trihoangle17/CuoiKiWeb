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
    <div class="alert alert-success" role="alert">
        <i class="bi bi-envelope-check-fill me-1"></i>
        Nếu địa chỉ email tồn tại trong hệ thống, chúng tôi đã gửi <strong>liên kết đặt lại mật khẩu</strong> cùng <strong>mã OTP 6 chữ số</strong> đến hộp thư của bạn.
    </div>
    <div class="d-grid gap-2 mt-3">
        <a href="/reset-password?email=<?= urlencode($submittedEmail) ?>" class="btn btn-primary">
            <i class="bi bi-key me-1"></i>Nhập mã OTP để đặt lại mật khẩu
        </a>
    </div>
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
