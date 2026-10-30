<?php
/**
 * partials/flash.php
 * Hien thi thong bao flash (success/error/info) dang toast tu dong tat.
 */
$flashSuccess = getFlash('success');
$flashError = getFlash('error');
$flashInfo = getFlash('info');
?>
<?php if ($flashSuccess): ?>
    <div class="d-none" data-flash="success" data-message="<?= e($flashSuccess) ?>"></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="d-none" data-flash="error" data-message="<?= e($flashError) ?>"></div>
<?php endif; ?>
<?php if ($flashInfo): ?>
    <div class="d-none" data-flash="info" data-message="<?= e($flashInfo) ?>"></div>
<?php endif; ?>
