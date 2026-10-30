<?php
/**
 * views/shared/manage.php
 * Giao diện quản lý chia sẻ ghi chú dành cho chủ sở hữu:
 * - Nhập 1 hoặc nhiều email người dùng đã đăng ký (có xác thực email hợp lệ).
 * - Chọn quyền Chỉ đọc (view) hoặc Chỉnh sửa cộng tác realtime (edit).
 * - Xem danh sách tất cả người nhận hiện tại, thay đổi quyền hoặc thu hồi bất kỳ lúc nào.
 */
$note = $note ?? [];
$shares = $shares ?? [];
$noteId = (int) ($note['id'] ?? 0);
?>
<div class="mx-auto" style="max-width: 760px;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-2">
            <a href="/notes/edit/<?= $noteId ?>" class="btn btn-icon" title="Quay lại ghi chú" aria-label="Quay lại ghi chú">
                <i class="bi bi-arrow-left fs-5"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0">Chia sẻ ghi chú</h1>
                <div class="small text-muted text-truncate" style="max-width: 480px;">
                    Ghi chú: <strong><?= e($note['title'] !== '' ? $note['title'] : '(Không có tiêu đề)') ?></strong>
                </div>
            </div>
        </div>
        <span class="badge rounded-pill <?= !empty($shares) ? 'text-bg-success' : 'text-bg-secondary' ?>" id="shareStatusBadge">
            <?= !empty($shares) ? 'Đang chia sẻ với ' . count($shares) . ' người' : 'Chưa chia sẻ' ?>
        </span>
    </div>

    <!-- Form thêm người nhận mới -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-person-plus-fill text-primary me-2"></i>Mời người nhận qua Email</h2>
            <form id="shareSendForm" data-note-id="<?= $noteId ?>">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="shareEmailsInput" class="form-label small fw-medium">
                        Địa chỉ email người nhận (nhập nhiều email cách nhau bằng dấu phẩy <code>,</code> hoặc khoảng trắng)
                    </label>
                    <div class="input-group">
                        <input type="text"
                               class="form-control"
                               id="shareEmailsInput"
                               name="emails"
                               placeholder="Ví dụ: friend@noteapp.local, user2@example.com"
                               required
                               autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" id="btnVerifyEmail" title="Kiểm tra nhanh email đã đăng ký chưa">
                            <i class="bi bi-check2-circle me-1"></i>Kiểm tra
                        </button>
                    </div>
                    <div id="emailVerifyFeedback" class="form-text small mt-1"></div>
                </div>

                <div class="row g-3 align-items-end">
                    <div class="col-12 col-sm-7">
                        <label for="sharePermissionSelect" class="form-label small fw-medium">Quyền truy cập</label>
                        <select class="form-select" id="sharePermissionSelect" name="permission">
                            <option value="view">Chỉ đọc (Read-only)</option>
                            <option value="edit">Chỉnh sửa – Cộng tác thời gian thực (Realtime WebSocket)</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-5">
                        <button type="submit" class="btn btn-primary w-100 fw-semibold" id="btnSubmitShare">
                            <i class="bi bi-send me-1"></i>Chia sẻ &amp; Gửi thông báo
                        </button>
                    </div>
                </div>
            </form>

            <div id="shareResultAlert" class="mt-3 d-none"></div>
        </div>
    </div>

    <!-- Danh sách người nhận hiện tại -->
    <div class="card shadow-sm">
        <div class="card-body p-3 p-md-4">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-people text-primary me-2"></i>Danh sách người đang được chia sẻ</h2>

            <div id="recipientsList" class="list-group list-group-flush">
                <?php if (empty($shares)): ?>
                    <div class="text-center text-muted py-4 small" id="recipientsEmpty">
                        Ghi chú này hiện chỉ mình bạn có quyền truy cập.
                    </div>
                <?php else: ?>
                    <?php foreach ($shares as $s): ?>
                        <div class="list-group-item px-0 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2"
                             data-share-id="<?= (int) $s['id'] ?>">
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate"><?= e($s['display_name']) ?></div>
                                <div class="small text-muted text-truncate"><?= e($s['email']) ?> • Đã chia sẻ <?= e(timeAgo($s['created_at'])) ?></div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <select class="form-select form-select-sm share-permission-select" style="width: auto;" aria-label="Đổi quyền chia sẻ">
                                    <option value="view" <?= $s['permission'] === 'view' ? 'selected' : '' ?>>Chỉ đọc</option>
                                    <option value="edit" <?= $s['permission'] === 'edit' ? 'selected' : '' ?>>Chỉnh sửa</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-revoke-share" title="Thu hồi quyền truy cập">
                                    <i class="bi bi-x-circle me-1"></i>Thu hồi
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php $extraScripts = ['/assets/js/share.js']; ?>
