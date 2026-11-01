<?php
/**
 * partials/label_modal.php
 * Hộp thoại quản lý nhãn ghi chú: liệt kê, thêm mới, sửa tên ngay tại chỗ (inline)
 * và xác nhận xóa (xóa nhãn không xóa ghi chú).
 */
?>
<div class="modal fade" id="labelManageModal" tabindex="-1" aria-hidden="true" aria-labelledby="labelManageModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5" id="labelManageModalTitle">
                    <i class="bi bi-tags me-2 text-primary"></i>Quản lý nhãn
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <form id="labelCreateForm" class="d-flex gap-2 mb-3">
                    <label for="labelNameInput" class="visually-hidden">Tên nhãn mới</label>
                    <input type="text" class="form-control" id="labelNameInput" placeholder="Nhập tên nhãn mới..." maxlength="50" required>
                    <button type="submit" class="btn btn-primary px-3 flex-shrink-0" title="Thêm nhãn">
                        <i class="bi bi-plus-lg"></i> Thêm
                    </button>
                </form>
                <div class="alert alert-danger py-2 small mb-3 d-none" id="labelFormError" role="alert"></div>

                <ul class="list-group" id="labelManageList">
                    <!-- Được render động bởi labels.js -->
                </ul>
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>Đổi tên nhãn sẽ tự động cập nhật trên mọi ghi chú. Xóa nhãn không làm mất ghi chú của bạn.
                </p>
            </div>
        </div>
    </div>
</div>

<template id="labelItemTemplate">
    <li class="list-group-item d-flex align-items-center justify-content-between gap-2 py-2">
        <div class="label-view-row d-flex align-items-center justify-content-between gap-2 w-100">
            <span class="d-flex align-items-center gap-2 flex-grow-1 text-truncate">
                <i class="bi bi-tag text-secondary"></i>
                <span class="label-item-name text-truncate"></span>
            </span>
            <div class="d-flex gap-1 flex-shrink-0">
                <button type="button" class="btn btn-icon btn-sm btn-rename-label" title="Đổi tên nhãn" aria-label="Đổi tên nhãn">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-icon btn-sm text-danger btn-delete-label" title="Xóa nhãn" aria-label="Xóa nhãn">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <form class="label-edit-row d-none align-items-center gap-2 w-100">
            <input type="text" class="form-control form-control-sm label-rename-input" maxlength="50" required>
            <button type="submit" class="btn btn-sm btn-success px-2" title="Lưu tên mới"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary px-2 btn-cancel-rename" title="Hủy"><i class="bi bi-x-lg"></i></button>
        </form>
    </li>
</template>
