<?php
/**
 * views/profile/settings.php
 * Màn hình tùy chọn cá nhân hóa của người dùng:
 * cỡ chữ ghi chú (Nhỏ / Vừa / Lớn), màu thẻ ghi chú và giao diện Sáng / Tối.
 */
$presetColors = [
    '#ffffff' => 'Mặc định',
    '#fff9db' => 'Vàng nhạt',
    '#e6fcf5' => 'Xanh ngọc',
    '#e7f5ff' => 'Xanh dương',
    '#fff0f6' => 'Hồng phấn',
    '#f3f0ff' => 'Tím oải hương',
    '#f4fce3' => 'Xanh cốm',
];
$currentColor = strtolower($currentUser['note_color'] ?? '#ffffff');
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h4 fw-bold mb-0"><i class="bi bi-palette me-2 text-primary"></i>Tùy chọn hiển thị</h1>
    <a href="/profile" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-person me-1"></i>Quay lại hồ sơ
    </a>
</div>

<div class="card shadow-sm" style="max-width: 560px;">
    <div class="card-body p-4">
        <form method="post" action="/profile/settings">
            <?= csrfField() ?>

            <div class="mb-4">
                <label for="font_size" class="form-label fw-semibold">Cỡ chữ hiển thị ghi chú</label>
                <select class="form-select" id="font_size" name="font_size">
                    <option value="small" <?= $currentUser['font_size'] === 'small' ? 'selected' : '' ?>>Nhỏ (Small)</option>
                    <option value="medium" <?= $currentUser['font_size'] === 'medium' ? 'selected' : '' ?>>Vừa - Mặc định (Medium)</option>
                    <option value="large" <?= $currentUser['font_size'] === 'large' ? 'selected' : '' ?>>Lớn - Dễ đọc (Large)</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="note_color" class="form-label fw-semibold d-block">Màu nền thẻ ghi chú</label>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <?php foreach ($presetColors as $hex => $label): ?>
                        <button type="button"
                                class="color-swatch-btn <?= $currentColor === $hex ? 'active' : '' ?>"
                                style="background-color: <?= e($hex) ?>;"
                                data-color="<?= e($hex) ?>"
                                title="<?= e($label) ?>"
                                aria-label="Chọn màu <?= e($label) ?>"></button>
                    <?php endforeach; ?>
                    <input type="color" class="form-control form-control-color ms-1" id="note_color" name="note_color"
                           value="<?= e($currentColor) ?>" title="Tùy chọn màu bất kỳ">
                </div>
                <div class="small text-muted">Chọn nhanh bảng màu pastel dễ đọc hoặc tự pha màu theo sở thích.</div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold d-block">Chế độ giao diện</label>
                <div class="btn-group w-100" role="group" aria-label="Chọn chế độ sáng hoặc tối">
                    <input type="radio" class="btn-check" name="theme" id="themeLight" value="light" autocomplete="off"
                           <?= $currentUser['theme'] === 'light' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-secondary py-2" for="themeLight">
                        <i class="bi bi-sun-fill text-warning me-1"></i>Giao diện Sáng (Light)
                    </label>

                    <input type="radio" class="btn-check" name="theme" id="themeDark" value="dark" autocomplete="off"
                           <?= $currentUser['theme'] === 'dark' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-secondary py-2" for="themeDark">
                        <i class="bi bi-moon-stars-fill text-primary me-1"></i>Giao diện Tối (Dark)
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-semibold">
                <i class="bi bi-check2-circle me-1"></i>Lưu tùy chọn hiển thị
            </button>
        </form>
    </div>
</div>

<script>
    document.querySelectorAll('.color-swatch-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const hex = this.getAttribute('data-color');
            const input = document.getElementById('note_color');
            if (input && hex) {
                input.value = hex;
            }
            document.querySelectorAll('.color-swatch-btn').forEach(function (b) { b.classList.remove('active'); });
            this.classList.add('active');
        });
    });
</script>
