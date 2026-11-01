<?php
/**
 * views/notes/index.php
 * Trang danh sách ghi chú cá nhân: mặc định dạng lưới (Grid), chuyển đổi linh hoạt
 * sang dạng danh sách (List), tìm kiếm live 300ms, lọc theo nhãn, ghim và xóa có xác nhận.
 */
$notes = $notes ?? [];
$activeLabel = $activeLabel ?? null;
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-2">
        <h1 class="h4 fw-bold mb-0"><?= e($pageTitle ?? 'Ghi chú của tôi') ?></h1>
        <span id="notesCountBadge" class="badge rounded-pill bg-body-secondary text-body-secondary fw-normal fs-6" title="Tổng số ghi chú"><?= count($notes) ?></span>
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
        <button type="button" class="btn btn-outline-secondary btn-icon" id="btnOpenHelpModal" data-bs-toggle="modal" data-bs-target="#shortcutsHelpModal" title="Phím tắt & Trợ giúp (?)" aria-label="Xem phím tắt và trợ giúp">
            <i class="bi bi-question-circle" aria-hidden="true"></i>
        </button>
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

<!-- Trạng thái lỗi mạng khi không có dữ liệu (có nút Thử lại và thông điệp an tâm) -->
<div id="notesErrorState" class="alert alert-danger d-none d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-wifi-off fs-5 text-danger" aria-hidden="true"></i>
        <span id="notesErrorMessage">Không thể kết nối đến máy chủ. Dữ liệu ngoại tuyến của bạn vẫn an toàn trên thiết bị.</span>
    </div>
    <button type="button" class="btn btn-outline-danger btn-sm px-3" id="btnRetryNotes">
        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Thử lại
    </button>
</div>

<!-- Thông báo số kết quả cho trình đọc màn hình (cập nhật bởi notes.js) -->
<div id="notesResultStatus" class="visually-hidden" role="status" aria-live="polite"></div>

<!-- Vùng hiển thị danh sách ghi chú (mặc định dạng lưới) -->
<div id="notesContainer" class="notes-grid" tabindex="-1" data-view-mode="grid" data-label-id="<?= (int) ($activeLabelId ?? 0) ?>">
    <?php include __DIR__ . '/_note_list.php'; ?>
</div>

<!-- Trạng thái rỗng (phân biệt giữa chưa có ghi chú nào và không tìm thấy do tìm kiếm/lọc) -->
<div id="notesEmptyState" class="empty-state text-center text-muted py-5 <?= empty($notes) ? '' : 'd-none' ?>">
    <i id="notesEmptyIcon" class="bi <?= $activeLabel ? 'bi-search' : 'bi-journal-plus' ?> display-4 d-block mb-3 text-secondary" aria-hidden="true"></i>
    <h2 id="notesEmptyTitle" class="h6 fw-semibold text-body">
        <?= $activeLabel ? 'Không tìm thấy ghi chú phù hợp' : 'Chưa có ghi chú nào' ?>
    </h2>
    <p id="notesEmptyDesc" class="small mb-3">
        <?= $activeLabel ? 'Không có ghi chú nào trong nhãn này. Thử bỏ lọc nhãn hoặc tìm kiếm với từ khóa khác.' : 'Bắt đầu tạo ghi chú đầu tiên của bạn để lưu trữ ý tưởng.' ?>
    </p>
    <div id="notesEmptyActions">
        <?php if ($activeLabel): ?>
            <a href="/notes" class="btn btn-outline-secondary btn-sm px-3 me-2">
                <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Xem tất cả ghi chú
            </a>
            <a href="/notes/create" class="btn btn-primary btn-sm px-3">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tạo ghi chú mới
            </a>
        <?php else: ?>
            <a href="/notes/create" class="btn btn-primary btn-sm px-3">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tạo ghi chú ngay
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Hộp thoại xác nhận xóa ghi chú (kèm ô mật khẩu nếu ghi chú bị khóa) -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true" aria-labelledby="deleteConfirmModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5 text-danger" id="deleteConfirmModalTitle">
                    <i class="bi bi-trash3 me-2"></i>Xác nhận xóa ghi chú
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Bạn có chắc chắn muốn xóa ghi chú này không? Hành động này không thể hoàn tác.</p>
                <div id="deleteLockPasswordWrap" class="mt-3 d-none">
                    <label for="deleteLockPasswordInput" class="form-label small fw-semibold">
                        <i class="bi bi-lock-fill text-warning-emphasis me-1"></i>Ghi chú đang được khóa mật khẩu. Nhập mật khẩu để xóa:
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
                    <h3 class="modal-title h5" id="lockPasswordModalTitle">
                        <i class="bi bi-shield-lock-fill text-warning-emphasis me-2"></i>Ghi chú được bảo vệ bằng mật khẩu
                    </h3>
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

<!-- Hộp thoại Hướng dẫn phím tắt & Mẹo sử dụng -->
<div class="modal fade" id="shortcutsHelpModal" tabindex="-1" aria-hidden="true" aria-labelledby="shortcutsHelpModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5" id="shortcutsHelpModalTitle">
                    <i class="bi bi-keyboard text-primary me-2" aria-hidden="true"></i>Phím tắt & Mẹo sử dụng
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <h4 class="h6 small text-uppercase fw-bold text-muted mb-2">Phím tắt thao tác nhanh</h4>
                <div class="list-group list-group-flush mb-3">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="small">Tìm kiếm ghi chú</span>
                        <span><kbd class="bg-body-secondary text-body border">Ctrl + K</kbd> hoặc <kbd class="bg-body-secondary text-body border">/</kbd></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="small">Xóa tìm kiếm / Thoát ô nhập</span>
                        <kbd class="bg-body-secondary text-body border">Esc</kbd>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="small">Tạo ghi chú mới</span>
                        <kbd class="bg-body-secondary text-body border">Alt + N</kbd>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="small">Mở hướng dẫn này</span>
                        <kbd class="bg-body-secondary text-body border">?</kbd>
                    </div>
                </div>

                <h4 class="h6 small text-uppercase fw-bold text-muted mb-2">Mẹo tiện ích</h4>
                <ul class="small text-muted mb-0 ps-3">
                    <li class="mb-1"><strong>Tự động lưu:</strong> Mọi ghi chú tự động lưu khi soạn thảo, không lo mất nội dung.</li>
                    <li class="mb-1"><strong>Làm việc ngoại tuyến:</strong> Khi mất mạng, bạn vẫn xem và tìm kiếm được các ghi chú đã nạp.</li>
                    <li><strong>Bảo mật:</strong> Đặt mật khẩu riêng cho từng ghi chú để bảo vệ dữ liệu nhạy cảm.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-sm px-3" data-bs-dismiss="modal">Đã hiểu</button>
            </div>
        </div>
    </div>
</div>

<?php $extraScripts = ['/assets/js/notes.js']; ?>
