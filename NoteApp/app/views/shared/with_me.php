<?php
/**
 * views/shared/with_me.php
 * Mục "Được chia sẻ với tôi": hiển thị đầy đủ các ghi chú mà người khác chia sẻ,
 * chỉ rõ người chia sẻ, thời điểm chia sẻ, quyền truy cập (Chỉ đọc / Chỉnh sửa realtime)
 * và các biểu tượng trạng thái.
 */
$sharedNotes = $sharedNotes ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Được chia sẻ với tôi</h1>
        <p class="text-muted small mb-0">Các ghi chú từ người dùng khác đã chia sẻ qua email của bạn</p>
    </div>

    <div class="btn-group" role="group" aria-label="Chuyển đổi hiển thị lưới hoặc danh sách">
        <button type="button" class="btn btn-outline-secondary active" id="btnSharedGrid" title="Xem dạng lưới" aria-pressed="true">
            <i class="bi bi-grid-3x3-gap-fill"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary" id="btnSharedList" title="Xem dạng danh sách" aria-pressed="false">
            <i class="bi bi-view-stacked"></i>
        </button>
    </div>
</div>

<?php if (empty($sharedNotes)): ?>
    <div class="empty-state text-center text-muted py-5">
        <i class="bi bi-inbox display-4 d-block mb-3 opacity-50"></i>
        <h2 class="h6 fw-semibold text-body">Chưa có ghi chú nào được chia sẻ với bạn</h2>
        <p class="small mb-0">Khi một người dùng khác chia sẻ ghi chú tới địa chỉ email của bạn, ghi chú sẽ xuất hiện tại đây.</p>
    </div>
<?php else: ?>
    <div id="sharedNotesContainer" class="notes-grid">
        <?php foreach ($sharedNotes as $sn):
            $isLocked = (int) ($sn['is_locked'] ?? 0) === 1;
            $isEditable = ($sn['permission'] ?? 'view') === 'edit';
        ?>
            <article class="note-card shared-note-card" data-note-id="<?= (int) $sn['id'] ?>" data-locked="<?= $isLocked ? '1' : '0' ?>">
                <a href="<?= $isLocked ? '#' : '/notes/edit/' . (int) $sn['id'] ?>"
                   class="note-card-link pb-3"
                   <?= $isLocked ? 'data-locked="1"' : '' ?>>

                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <span class="badge rounded-pill <?= $isEditable ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?php if ($isEditable): ?>
                                <i class="bi bi-pencil-square me-1"></i>Có thể chỉnh sửa (Realtime)
                            <?php else: ?>
                                <i class="bi bi-eye me-1"></i>Chỉ đọc
                            <?php endif; ?>
                        </span>

                        <div class="note-card-icons">
                            <?php if ($isLocked): ?>
                                <i class="bi bi-lock-fill text-warning" title="Được khóa mật khẩu"></i>
                            <?php endif; ?>
                            <i class="bi bi-people-fill text-success" title="Ghi chú được chia sẻ"></i>
                        </div>
                    </div>

                    <h2 class="note-card-title"><?= e($sn['title'] !== '' ? $sn['title'] : '(Không có tiêu đề)') ?></h2>

                    <p class="note-card-content <?= $isLocked ? 'fst-italic text-muted' : '' ?>">
                        <?php if ($isLocked): ?>
                            <i class="bi bi-shield-lock me-1"></i>Nội dung được bảo vệ bằng mật khẩu
                        <?php else: ?>
                            <?= e(mb_substr((string) $sn['content'], 0, 200, 'UTF-8')) ?>
                        <?php endif; ?>
                    </p>

                    <div class="pt-2 mt-2 border-top small text-muted">
                        <div class="text-truncate" title="<?= e($sn['shared_by_email']) ?>">
                            <i class="bi bi-person-check me-1"></i>Chia sẻ bởi: <strong class="text-body"><?= e($sn['shared_by_name']) ?></strong> (<?= e($sn['shared_by_email']) ?>)
                        </div>
                        <div class="mt-1">
                            <i class="bi bi-calendar-event me-1"></i>Thời điểm chia sẻ: <?= e(date('d/m/Y H:i', strtotime($sn['shared_at']))) ?> (<?= e(timeAgo($sn['shared_at'])) ?>)
                        </div>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal nhập mật khẩu nếu ghi chú được chia sẻ có bật khóa mật khẩu -->
<div class="modal fade" id="sharedLockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="sharedUnlockForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Nhập mật khẩu ghi chú</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <input type="password" class="form-control" id="sharedLockPasswordInput" placeholder="Mật khẩu khóa ghi chú..." required>
                    <div class="text-danger small mt-2 d-none" id="sharedLockError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Mở khóa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        const container = document.getElementById('sharedNotesContainer');
        const btnGrid = document.getElementById('btnSharedGrid');
        const btnList = document.getElementById('btnSharedList');
        const modalEl = document.getElementById('sharedLockModal');
        let pendingNoteId = null;

        function setMode(mode) {
            if (!container) return;
            container.classList.toggle('notes-grid', mode === 'grid');
            container.classList.toggle('notes-list', mode === 'list');
            btnGrid?.classList.toggle('active', mode === 'grid');
            btnList?.classList.toggle('active', mode === 'list');
        }
        btnGrid?.addEventListener('click', function () { setMode('grid'); });
        btnList?.addEventListener('click', function () { setMode('list'); });

        container?.addEventListener('click', function (e) {
            const link = e.target.closest('.note-card-link[data-locked="1"]');
            if (!link) return;
            e.preventDefault();
            pendingNoteId = link.closest('.note-card').dataset.noteId;
            document.getElementById('sharedLockPasswordInput').value = '';
            document.getElementById('sharedLockError').classList.add('d-none');
            if (window.bootstrap && modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });

        document.getElementById('sharedUnlockForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!pendingNoteId) return;
            const pass = document.getElementById('sharedLockPasswordInput').value;
            const errEl = document.getElementById('sharedLockError');

            fetch('/notes/unlock/' + pendingNoteId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'password=' + encodeURIComponent(pass) + '&csrf_token=' + encodeURIComponent(window.csrfToken || ''),
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) {
                        window.location.href = d.redirect;
                    } else {
                        errEl.textContent = d.error || 'Mật khẩu không đúng.';
                        errEl.classList.remove('d-none');
                    }
                });
        });
    })();
</script>
