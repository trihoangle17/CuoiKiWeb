<?php
/**
 * views/auth/login.php
 * Giao diện đăng nhập tài khoản.
 */
$errors = $errors ?? [];
?>
<h1 class="h4 text-center fw-bold mb-1">Đăng nhập</h1>
<p class="text-muted text-center small mb-4">Đăng nhập để truy cập kho ghi chú cá nhân của bạn</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2" role="alert">
        <ul class="mb-0 ps-3 small">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="/login" novalidate>
    <?= csrfField() ?>

    <div class="mb-3">
        <label for="email" class="form-label fw-medium">Địa chỉ Email</label>
        <input type="email" class="form-control" id="email" name="email" required autofocus
               placeholder="name@example.com" value="<?= e($_POST['email'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <label for="password" class="form-label fw-medium mb-1">Mật khẩu</label>
            <a href="/forgot-password" class="small text-decoration-none">Quên mật khẩu?</a>
        </div>
        <input type="password" class="form-control" id="password" name="password" required placeholder="Nhập mật khẩu">
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold mt-2">
        <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
    </button>
</form>

<p class="text-center mt-4 mb-0 small">
    Chưa có tài khoản? <a href="/register" class="fw-semibold text-decoration-none">Đăng ký ngay</a>
</p>
