<?php
/**
 * views/notes/edit.php
 * Giao diện DUY NHẤT dùng chung cho cả Thêm ghi chú mới và Sửa ghi chú.
 * - Chỉ bắt buộc tiêu đề và nội dung; TỰ ĐỘNG LƯU (không có nút Save).
 * - Hỗ trợ đầy đủ: nhãn, đính kèm nhiều ảnh/video/file, ghim, khóa mật khẩu riêng,
 *   chia sẻ qua email, tóm tắt bằng AI (LLM) và đồng bộ thời gian thực qua WebSocket.
 */
$note = $note ?? ['id' => null, 'title' => '', 'content' => '', 'ai_summary' => null, 'is_pinned' => 0, 'is_locked' => 0];
$noteId = (int) ($note['id'] ?? 0);
$isOwner = $isOwner ?? true;
$canEdit = $canEdit ?? true;
$shareInfo = $shareInfo ?? null;
$wsCollabToken = $wsCollabToken ?? null;
$noteLabels = $noteLabels ?? [];
$attachments = $attachments ?? [];
$labels = $labels ?? [];
$isLocked = (int) ($note['is_locked'] ?? 0) === 1;
$isPinned = (int) ($note['is_pinned'] ?? 0) === 1;
?>

<!-- Thanh công cụ phía trên trình soạn thảo -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= $isOwner ? '/notes' : '/shares/with-me' ?>" class="btn btn-icon" title="Quay lại danh sách" aria-label="Quay lại danh sách">
            <i class="bi bi-arrow-left fs-5"></i>
        </a>
        <?php if ($canEdit): ?>
            <span class="badge rounded-pill text-bg-light border px-3 py-2 fw-normal text-secondary" id="autoSaveStatus" aria-live="polite">
                <?php if ($noteId > 0): ?>
                    <i class="bi bi-cloud-check text-success me-1"></i>Đã lưu tự động
                <?php else: ?>
                    <i class="bi bi-pencil me-1"></i>Nhập tiêu đề &amp; nội dung để tự động lưu
                <?php endif; ?>
            </span>
        <?php else: ?>
            <span class="badge rounded-pill text-bg-secondary px-3 py-2">
                <i class="bi bi-eye me-1"></i>Chế độ chỉ đọc
            </span>
        <?php endif; ?>
    </div>

    <div class="d-flex align-items-center gap-1 flex-wrap">
        <!-- Trạng thái người cùng chỉnh sửa thời gian thực (WebSocket) -->
        <div id="collabPresenceBar" class="d-none align-items-center gap-2 me-2 small badge rounded-pill text-bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-2">
            <span class="collab-dot"></span>
            <span id="collabUsersText">Đang kết nối realtime...</span>
        </div>

        <?php if ($isOwner): ?>
            <div id="ownerNoteActions" class="d-flex align-items-center gap-1 <?= $noteId > 0 ? '' : 'd-none' ?>">
                <button type="button" class="btn btn-icon" id="btnTogglePinEdit" title="<?= $isPinned ? 'Bỏ ghim' : 'Ghim ghi chú' ?>" aria-label="Ghim hoặc bỏ ghim">
                    <i class="bi bi-pin<?= $isPinned ? '-fill text-primary' : '' ?>"></i>
                </button>

                <div class="dropdown">
                    <button type="button" class="btn btn-icon" id="btnLockMenu" data-bs-toggle="dropdown" aria-expanded="false" title="Bảo vệ bằng mật khẩu" aria-label="Cài đặt mật khẩu khóa ghi chú">
                        <i class="bi bi-lock<?= $isLocked ? '-fill text-warning' : '' ?>" id="lockMenuIcon"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="btnLockMenu">
                        <?php if ($isLocked): ?>
                            <li>
                                <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#changeLockModal">
                                    <i class="bi bi-key me-2 text-primary"></i>Đổi mật khẩu khóa
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item py-2 text-danger" data-bs-toggle="modal" data-bs-target="#removeLockModal">
                                    <i class="bi bi-unlock me-2"></i>Tắt khóa mật khẩu
                                </button>
                            </li>
                        <?php else: ?>
                            <li>
                                <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#setLockModal">
                                    <i class="bi bi-shield-lock me-2 text-warning"></i>Đặt mật khẩu khóa ghi chú
                                </button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <a href="<?= $noteId > 0 ? '/shares/create/' . $noteId : '#' ?>" id="btnShareLink" class="btn btn-icon" title="Chia sẻ ghi chú qua email" aria-label="Chia sẻ ghi chú">
                    <i class="bi bi-person-plus"></i>
                </a>
            </div>
        <?php endif; ?>

        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 <?= $noteId > 0 ? '' : 'd-none' ?>" id="btnAiSummarize" title="Tóm tắt nội dung ghi chú bằng AI">
            <i class="bi bi-stars me-1"></i>AI Tóm tắt
        </button>

        <?php if ($isOwner): ?>
            <button type="button" class="btn btn-icon text-danger" id="btnDeleteNote" title="Xóa ghi chú" aria-label="Xóa ghi chú">
                <i class="bi bi-trash"></i>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Thông tin ghi chú được chia sẻ (nếu người xem không phải chủ sở hữu) -->
<?php if (!$isOwner && $shareInfo): ?>
    <div class="alert alert-light border d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 px-3 mb-3">
        <div class="small">
            <i class="bi bi-people-fill text-primary me-1"></i>
            Được chia sẻ bởi <strong><?= e($shareInfo['owner_name']) ?></strong> (<?= e($shareInfo['owner_email']) ?>)
            • <?= e(timeAgo($shareInfo['created_at'] ?? '')) ?>
        </div>
        <span class="badge <?= $canEdit ? 'text-bg-success' : 'text-bg-secondary' ?>">
            <?= $canEdit ? 'Quyền chỉnh sửa (Đồng bộ Realtime)' : 'Quyền chỉ đọc' ?>
        </span>
    </div>
<?php endif; ?>

<!-- Khung soạn thảo chính -->
<div class="note-editor-surface card shadow-sm border mb-4">
    <div class="card-body p-3 p-md-4">
        <form id="noteForm" data-note-id="<?= $noteId ?>" data-can-edit="<?= $canEdit ? '1' : '0' ?>" data-is-locked="<?= $isLocked ? '1' : '0' ?>">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="noteId" value="<?= $noteId ?>">

            <div class="mb-2">
                <label for="noteTitle" class="visually-hidden">Tiêu đề ghi chú (bắt buộc)</label>
                <input type="text"
                       class="form-control form-control-lg border-0 px-0 fw-bold bg-transparent"
                       id="noteTitle"
                       name="title"
                       placeholder="Tiêu đề ghi chú..."
                       value="<?= e($note['title']) ?>"
                       maxlength="255"
                       autocomplete="off"
                       <?= $canEdit ? '' : 'readonly' ?>>
            </div>

            <hr class="my-2 opacity-25">

            <div class="mb-3">
                <label for="noteContent" class="visually-hidden">Nội dung ghi chú (bắt buộc)</label>
                <textarea class="form-control border-0 px-0 bg-transparent note-textarea"
                          id="noteContent"
                          name="content"
                          rows="12"
                          placeholder="Nhập nội dung ghi chú của bạn tại đây (tự động lưu ngay khi gõ)..."
                          <?= $canEdit ? '' : 'readonly' ?>><?= e($note['content']) ?></textarea>
            </div>

            <?php if ($isOwner && !empty($labels)): ?>
                <div class="pt-3 border-top">
                    <div class="small text-muted fw-medium mb-2"><i class="bi bi-tags me-1"></i>Gắn nhãn phân loại:</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($labels as $lbl): ?>
                            <label class="label-chip">
                                <input type="checkbox" name="labels[]" value="<?= (int) $lbl['id'] ?>"
                                       <?= in_array((int) $lbl['id'], $noteLabels, true) ? 'checked' : '' ?>>
                                <span><?= e($lbl['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Hộp kết quả AI Tóm tắt -->
<section class="card border-primary-subtle bg-primary-subtle bg-opacity-10 mb-4 <?= !empty($note['ai_summary']) ? '' : 'd-none' ?>" id="aiSummaryBox" aria-label="Bản tóm tắt AI">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <h2 class="h6 fw-bold text-primary mb-0">
                <i class="bi bi-stars me-1"></i>Tóm tắt nội dung bằng AI
            </h2>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="btnAiRegenerate">
                <i class="bi bi-arrow-clockwise me-1"></i>Tạo lại bản tóm tắt
            </button>
        </div>
        <div class="ai-summary-text small" id="aiSummaryContent"><?= e($note['ai_summary'] ?? '') ?></div>
    </div>
</section>

<!-- Khu vực đính kèm nhiều ảnh / video / tệp tài liệu -->
<section class="card shadow-sm mb-4 <?= ($noteId > 0 || !empty($attachments)) ? '' : 'd-none' ?>" id="attachmentSection" aria-label="Tệp đính kèm">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h6 fw-bold mb-0"><i class="bi bi-paperclip me-1 text-primary"></i>Ảnh, Video &amp; Tệp đính kèm</h2>
                <small class="text-muted">Hỗ trợ đính kèm nhiều ảnh, video (MP4/WebM) và tài liệu (PDF, Word, Excel, TXT, ZIP - tối đa 15MB/tệp)</small>
            </div>
            <?php if ($canEdit): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddAttachment">
                    <i class="bi bi-cloud-upload me-1"></i>Thêm tệp / ảnh / video
                </button>
                <input type="file" id="attachmentInput" class="d-none" multiple
                       accept="image/*,video/mp4,video/webm,video/ogg,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip">
            <?php endif; ?>
        </div>

        <div id="attachmentUploadProgress" class="small text-primary mb-2 d-none">
            <span class="spinner-border spinner-border-sm me-1" role="status"></span>Đang tải tệp lên máy chủ...
        </div>

        <div class="attachment-list" id="attachmentList">
            <?php foreach ($attachments as $att):
                $attUrl = UPLOAD_URL . '/' . $att['stored_name'];
                $mime = (string) $att['mime_type'];
            ?>
                <div class="attachment-item" data-attachment-id="<?= (int) $att['id'] ?>">
                    <?php if (str_starts_with($mime, 'image/')): ?>
                        <a href="<?= e($attUrl) ?>" target="_blank" rel="noopener">
                            <img src="<?= e($attUrl) ?>" alt="<?= e($att['original_name']) ?>" class="attachment-thumb">
                        </a>
                    <?php elseif (str_starts_with($mime, 'video/')): ?>
                        <video src="<?= e($attUrl) ?>" controls preload="metadata" class="attachment-video"></video>
                    <?php else: ?>
                        <div class="attachment-file-icon">
                            <i class="bi bi-file-earmark-text fs-2 text-primary"></i>
                        </div>
                    <?php endif; ?>

                    <div class="attachment-meta w-100">
                        <a href="<?= e($attUrl) ?>" target="_blank" rel="noopener" download="<?= e($att['original_name']) ?>" class="attachment-name small fw-medium" title="<?= e($att['original_name']) ?>">
                            <?= e($att['original_name']) ?>
                        </a>
                        <div class="text-muted" style="font-size: 0.72rem;"><?= e(formatBytes((int) $att['file_size'])) ?></div>
                    </div>

                    <?php if ($canEdit): ?>
                        <button type="button" class="btn btn-icon btn-sm btn-remove-attachment" title="Xóa tệp đính kèm" aria-label="Xóa tệp đính kèm">
                            <i class="bi bi-x"></i>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-muted small mb-0 <?= empty($attachments) ? '' : 'd-none' ?>" id="attachmentEmptyHint">
            Chưa có tệp đính kèm nào trong ghi chú này.
        </p>
    </div>
</section>

<?php if ($isOwner): ?>
<!-- Modal 1: Đặt mật khẩu khóa ghi chú (nhập 2 lần) -->
<div class="modal fade" id="setLockModal" tabindex="-1" aria-hidden="true" aria-labelledby="setLockModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="setLockForm">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="setLockModalTitle"><i class="bi bi-shield-lock text-warning-emphasis me-2"></i>Đặt mật khẩu khóa ghi chú</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Mỗi ghi chú sử dụng một mật khẩu bảo vệ riêng. Khi bật khóa, mọi thao tác xem, sửa hoặc xóa đều yêu cầu nhập mật khẩu này.</p>
                    <div class="mb-3">
                        <label for="setLockPass1" class="form-label small fw-medium">Mật khẩu khóa mới (tối thiểu 4 ký tự)</label>
                        <input type="password" class="form-control" id="setLockPass1" minlength="4" required>
                    </div>
                    <div class="mb-2">
                        <label for="setLockPass2" class="form-label small fw-medium">Nhập lại mật khẩu khóa</label>
                        <input type="password" class="form-control" id="setLockPass2" minlength="4" required>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="setLockError" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Bật khóa mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Đổi mật khẩu khóa ghi chú (nhập mật khẩu cũ + mật khẩu mới 2 lần) -->
<div class="modal fade" id="changeLockModal" tabindex="-1" aria-hidden="true" aria-labelledby="changeLockModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="changeLockForm">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="changeLockModalTitle"><i class="bi bi-key text-primary me-2"></i>Đổi mật khẩu khóa ghi chú</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="oldLockPass" class="form-label small fw-medium">Mật khẩu khóa hiện tại</label>
                        <input type="password" class="form-control" id="oldLockPass" required>
                    </div>
                    <div class="mb-3">
                        <label for="newLockPass1" class="form-label small fw-medium">Mật khẩu khóa mới</label>
                        <input type="password" class="form-control" id="newLockPass1" minlength="4" required>
                    </div>
                    <div class="mb-2">
                        <label for="newLockPass2" class="form-label small fw-medium">Nhập lại mật khẩu khóa mới</label>
                        <input type="password" class="form-control" id="newLockPass2" minlength="4" required>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="changeLockError" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Cập nhật mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Tắt khóa mật khẩu (nhập lại mật khẩu hiện tại để xác nhận) -->
<div class="modal fade" id="removeLockModal" tabindex="-1" aria-hidden="true" aria-labelledby="removeLockModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="removeLockForm">
                <div class="modal-header">
                    <h3 class="modal-title h5 text-danger" id="removeLockModalTitle"><i class="bi bi-unlock me-2"></i>Tắt bảo vệ mật khẩu</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Nhập lại mật khẩu khóa hiện tại của ghi chú này để xác nhận gỡ bỏ bảo vệ mật khẩu.</p>
                    <div class="mb-2">
                        <label for="removeLockPass" class="form-label small fw-medium">Mật khẩu khóa hiện tại</label>
                        <input type="password" class="form-control" id="removeLockPass" required>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="removeLockError" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xác nhận tắt khóa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Xác nhận xóa ghi chú đang mở -->
<div class="modal fade" id="deleteEditNoteModal" tabindex="-1" aria-hidden="true" aria-labelledby="deleteEditNoteModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5 text-danger" id="deleteEditNoteModalTitle"><i class="bi bi-trash3 me-2"></i>Xác nhận xóa ghi chú</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Bạn có chắc chắn muốn xóa ghi chú này không? Dữ liệu sau khi xóa không thể khôi phục.</p>
                <?php if ($isLocked): ?>
                    <div class="mt-3">
                        <label for="deleteEditLockPass" class="form-label small fw-semibold">Nhập mật khẩu khóa ghi chú để xác nhận xóa:</label>
                        <input type="password" class="form-control" id="deleteEditLockPass" placeholder="Mật khẩu khóa ghi chú">
                    </div>
                <?php endif; ?>
                <div class="text-danger small mt-2 d-none" id="deleteEditError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDeleteEdit">Xóa vĩnh viễn</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    window.wsCollabToken = <?= json_encode($wsCollabToken) ?>;
</script>
<?php $extraScripts = ['/assets/js/note-edit.js', '/assets/js/collab.js']; ?>
