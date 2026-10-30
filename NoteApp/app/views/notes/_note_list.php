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
?>
    <article class="note-card <?= $isPinned ? 'note-card-pinned' : '' ?>"
             data-note-id="<?= (int) $note['id'] ?>"
             data-locked="<?= $isLocked ? '1' : '0' ?>">
        <a href="<?= $isLocked ? '#' : '/notes/edit/' . (int) $note['id'] ?>"
           class="note-card-link"
           <?= $isLocked ? 'data-locked="1"' : '' ?>>
            <div class="note-card-header">
                <h2 class="note-card-title"><?= e($note['title'] !== '' ? $note['title'] : '(Không có tiêu đề)') ?></h2>
                <div class="note-card-icons" aria-label="Trạng thái ghi chú">
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
                    <i class="bi bi-shield-lock me-1"></i>Nội dung được bảo vệ bằng mật khẩu
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
                <i class="bi bi-clock me-1"></i><?= e($note['time_ago'] ?? timeAgo($note['updated_at'] ?? '')) ?>
            </div>
        </a>

        <div class="note-card-actions">
            <button type="button"
                    class="btn btn-icon btn-sm btn-toggle-pin"
                    title="<?= $isPinned ? 'Bỏ ghim ghi chú' : 'Ghim ghi chú lên đầu' ?>"
                    aria-label="<?= $isPinned ? 'Bỏ ghim ghi chú' : 'Ghim ghi chú lên đầu' ?>">
                <i class="bi bi-pin<?= $isPinned ? '-fill text-primary' : '' ?>"></i>
            </button>
            <button type="button"
                    class="btn btn-icon btn-sm text-danger btn-delete-note"
                    title="Xóa ghi chú"
                    aria-label="Xóa ghi chú">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </article>
<?php endforeach; ?>
