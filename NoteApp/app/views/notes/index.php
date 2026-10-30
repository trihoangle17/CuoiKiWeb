<?php
/**
 * views/notes/index.php
 * Trang danh sách ghi chú cá nhân: mặc định dạng lưới (Grid), chuyển đổi linh hoạt
 * sang dạng danh sách (List), tìm kiếm live 300ms, lọc theo nhãn, ghim và xóa có xác nhận.
 */
$notes = $notes ?? [];
$activeLabel = $activeLabel ?? null;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <h1 class="h4 fw-bold mb-0"><?= e($pageTitle ?? 'Ghi chú của tôi') ?></h1>
        <?php if ($activeLabel): ?>
            <a href="/notes" class="btn btn-sm btn-outline-secondary rounded-pill" title="Bỏ lọc nhãn">
                <i class="bi bi-x-lg me-1"></i>Bỏ lọc
            </a>
        <?php endif; ?>
    </div>

    <div class="d-flex align-items-center gap-2">
        <div class="btn-group" role="group" aria-label="Chuyển đổi kiểu hiển thị lưới hoặc danh sách">
            <button type="button" class="btn btn-outline-secondary active" id="btnViewGrid" title="Hiển thị dạng lưới" aria-label="Hiển thị dạng lưới" aria-pressed="true">
                <i class="bi bi-grid-3x3-gap-fill"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btnViewList" title="Hiển thị dạng danh sách" aria-label="Hiển thị dạng danh sách" aria-pressed="false">
                <i class="bi bi-view-stacked"></i>
            </button>
        </div>
        <a href="/notes/create" class="btn btn-primary d-flex align-items-center gap-1">
            <i class="bi bi-plus-lg"></i>
            <span>Ghi chú mới</span>
        </a>
    </div>
</div>

<!-- Trạng thái đang tải khi tìm kiếm live -->
<div id="notesLoadingState" class="text-center py-5 d-none" role="status" aria-live="polite">
    <div class="spinner-border text-primary mb-2" role="status"></div>
    <p class="text-muted small mb-0">Đang tải danh sách ghi chú...</p>
</div>

<!-- Trạng thái lỗi mạng khi không có dữ liệu -->
<div id="notesErrorState" class="alert alert-danger d-none" role="alert"></div>

<!-- Vùng hiển thị danh sách ghi chú (mặc định dạng lưới) -->
<div id="notesContainer" class="notes-grid" data-view-mode="grid" data-label-id="<?= (int) ($activeLabelId ?? 0) ?>">
    <?php include __DIR__ . '/_note_list.php'; ?>
</div>

<!-- Trạng thái rỗng -->
<div id="notesEmptyState" class="empty-state text-center text-muted py-5 <?= empty($notes) ? '' : 'd-none' ?>">
    <i class="bi bi-journal-plus display-4 d-block mb-3 text-secondary opacity-50"></i>
    <h2 class="h6 fw-semibold text-body">Chưa có ghi chú nào phù hợp</h2>
    <p class="small mb-3">Bấm nút "Ghi chú mới" để bắt đầu tạo ghi chú đầu tiên của bạn.</p>
    <a href="/notes/create" class="btn btn-primary btn-sm px-3"><i class="bi bi-plus-lg me-1"></i>Tạo ghi chú ngay</a>
</div>

<!-- Hộp thoại xác nhận xóa ghi chú (kèm ô mật khẩu nếu ghi chú bị khóa) -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true" aria-labelledby="deleteConfirmModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteConfirmModalTitle">
                    <i class="bi bi-trash3 me-2"></i>Xác nhận xóa ghi chú
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Bạn có chắc chắn muốn xóa ghi chú này không? Hành động này không thể hoàn tác.</p>
                <div id="deleteLockPasswordWrap" class="mt-3 d-none">
                    <label for="deleteLockPasswordInput" class="form-label small fw-semibold">
                        <i class="bi bi-lock-fill text-warning me-1"></i>Ghi chú đang được khóa mật khẩu. Nhập mật khẩu để xóa:
                    </label>
                    <input type="password" class="form-control" id="deleteLockPasswordInput" placeholder="Nhập mật khẩu khóa ghi chú">
                </div>
                <div class="text-danger small mt-2 d-none" id="deleteNoteError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete">
                    <i class="bi bi-trash me-1"></i>Xác nhận xóa
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hộp thoại nhập mật khẩu để mở xem/sửa ghi chú bị khóa -->
<div class="modal fade" id="lockPasswordModal" tabindex="-1" aria-hidden="true" aria-labelledby="lockPasswordModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="lockUnlockModalForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="lockPasswordModalTitle">
                        <i class="bi bi-shield-lock-fill text-warning me-2"></i>Ghi chú được bảo vệ bằng mật khẩu
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Vui lòng nhập mật khẩu riêng của ghi chú này để xem hoặc chỉnh sửa.</p>
                    <label for="lockPasswordInput" class="visually-hidden">Mật khẩu ghi chú</label>
                    <input type="password" class="form-control" id="lockPasswordInput" placeholder="Nhập mật khẩu khóa ghi chú..." required>
                    <div class="text-danger small mt-2 d-none" id="lockPasswordError" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="btnConfirmUnlock">
                        <i class="bi bi-unlock me-1"></i>Mở khóa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $extraScripts = ['/assets/js/notes.js']; ?>
