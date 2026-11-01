<?php
/**
 * views/notes/_note_list.php
 * Thành phần hiển thị danh sách thẻ ghi chú (dùng chung cho cả Grid view và List view).
 * Luôn hiển thị rõ các biểu tượng trạng thái đặc biệt: Đã ghim, Đã khóa mật khẩu, Đã chia sẻ.
 */
foreach ($notes as $note):
    $isLocked = (int) ($note['is_locked'] ?? 0) === 1;
    $isPinned = (int) ($note['is_pinned'] ?? 0) === 1;
    $isShared = !empty($note['is_shared']);
    // Tên dùng cho nhãn trợ năng (tránh nhãn trống khi ghi chú không có tiêu đề)
    $noteTitleLabel = $note['title'] !== '' ? $note['title'] : '(Không có tiêu đề)';
?>
    <article class="note-card <?= $isPinned ? 'note-card-pinned' : '' ?>"
             data-note-id="<?= (int) $note['id'] ?>"
             data-locked="<?= $isLocked ? '1' : '0' ?>">
        <?php if ($isLocked): ?>
        <button type="button" class="note-card-link" data-locked="1" aria-haspopup="dialog">
        <?php else: ?>
        <a href="<?= '/notes/edit/' . (int) $note['id'] ?>" class="note-card-link">
        <?php endif; ?>
            <div class="note-card-header">
                <h2 class="note-card-title"><?= e($noteTitleLabel) ?></h2>
                <div class="note-card-icons" role="group" aria-label="Trạng thái ghi chú">
                    <?php if ($isPinned): ?>
                        <span class="status-badge text-primary" title="Đã ghim lên đầu">
                            <i class="bi bi-pin-angle-fill" aria-hidden="true"></i>
                            <span class="visually-hidden">Đã ghim</span>
                        </span>
                    <?php endif; ?>
                    <?php if ($isLocked): ?>
                        <span class="status-badge text-warning" title="Được bảo vệ bằng mật khẩu">
                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                            <span class="visually-hidden">Đã khóa mật khẩu</span>
                        </span>
                    <?php endif; ?>
                    <?php if ($isShared): ?>
                        <span class="status-badge text-success" title="Đang chia sẻ với người khác">
                            <i class="bi bi-people-fill" aria-hidden="true"></i>
                            <span class="visually-hidden">Đã chia sẻ</span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <p class="note-card-content <?= $isLocked ? 'fst-italic text-muted' : '' ?>">
                <?php if ($isLocked): ?>
                    <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Nội dung được bảo vệ bằng mật khẩu
                <?php else: ?>
                    <?= e(mb_substr((string) $note['content'], 0, 220, 'UTF-8')) ?>
                <?php endif; ?>
            </p>

            <?php if (!empty($note['labels'])): ?>
                <div class="note-card-labels">
                    <?php foreach ($note['labels'] as $lbl): ?>
                        <span class="badge rounded-pill note-label-badge"><?= e($lbl['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="note-card-footer text-muted small">
                <i class="bi bi-clock me-1" aria-hidden="true"></i><?= e($note['time_ago'] ?? timeAgo($note['updated_at'] ?? '')) ?>
            </div>
        <?= $isLocked ? '</button>' : '</a>' ?>

        <div class="note-card-actions">
            <button type="button"
                    class="btn btn-icon btn-sm btn-toggle-pin"
                    title="<?= $isPinned ? 'Bỏ ghim ghi chú' : 'Ghim ghi chú lên đầu' ?>"
                    aria-label="<?= $isPinned ? 'Bỏ ghim ghi chú' : 'Ghim ghi chú lên đầu' ?>: <?= e($noteTitleLabel) ?>">
                <i class="bi bi-pin<?= $isPinned ? '-fill text-primary' : '' ?>" aria-hidden="true"></i>
            </button>
            <button type="button"
                    class="btn btn-icon btn-sm text-danger btn-delete-note"
                    title="Xóa ghi chú"
                    aria-label="Xóa ghi chú: <?= e($noteTitleLabel) ?>">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
        </div>
    </article>
<?php endforeach; ?>
