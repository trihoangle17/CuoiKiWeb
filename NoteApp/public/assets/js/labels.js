/**
 * labels.js
 * Quản lý nhãn ghi chú qua AJAX trong Modal:
 * - Liệt kê toàn bộ nhãn
 * - Thêm nhãn mới
 * - Đổi tên nhãn ngay tại chỗ (inline form, không dùng prompt thô)
 * - Xóa nhãn an toàn (xóa nhãn không xóa ghi chú; đổi tên tự động cập nhật trên ghi chú)
 */

(function () {
    'use strict';

    const btnManageLabels = document.getElementById('btnManageLabels');
    const modalEl = document.getElementById('labelManageModal');
    const listEl = document.getElementById('labelManageList');
    const createForm = document.getElementById('labelCreateForm');
    const nameInput = document.getElementById('labelNameInput');
    const errorEl = document.getElementById('labelFormError');
    const itemTemplate = document.getElementById('labelItemTemplate');

    if (!btnManageLabels || !modalEl) {
        return;
    }

    let modal = null;
    let needsReload = false;

    function csrf() {
        if (window.csrfToken) return window.csrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showError(message) {
        errorEl.textContent = message;
        errorEl.classList.remove('d-none');
    }

    function clearError() {
        errorEl.classList.add('d-none');
        errorEl.textContent = '';
    }

    function loadLabels() {
        listEl.innerHTML = '<li class="list-group-item text-center text-muted small py-3">Đang tải danh sách nhãn...</li>';
        fetch('/labels', { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) { renderList(data.labels || []); })
            .catch(function () { showError('Không tải được danh sách nhãn. Vui lòng kiểm tra kết nối mạng.'); });
    }

    function renderList(labels) {
        listEl.innerHTML = '';

        if (labels.length === 0) {
            const emptyLi = document.createElement('li');
            emptyLi.className = 'list-group-item text-center text-muted small py-3';
            emptyLi.textContent = 'Chưa có nhãn nào. Hãy thêm nhãn đầu tiên ở ô bên trên.';
            listEl.appendChild(emptyLi);
            return;
        }

        labels.forEach(function (label) {
            const node = itemTemplate.content.cloneNode(true);
            const li = node.querySelector('li');
            li.dataset.labelId = label.id;
            li.querySelector('.label-item-name').textContent = label.name;
            li.querySelector('.label-rename-input').value = label.name;
            listEl.appendChild(li);
        });
    }

    btnManageLabels.addEventListener('click', function () {
        clearError();
        loadLabels();
        if (window.bootstrap) {
            modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    });

    createForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        clearError();

        const name = nameInput.value.trim();
        if (!name) return;

        fetch('/labels/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'name=' + encodeURIComponent(name) + '&csrf_token=' + encodeURIComponent(csrf()),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    showError(data.error);
                    return;
                }
                nameInput.value = '';
                loadLabels();
                window.showToast?.('success', 'Đã thêm nhãn "' + data.name + '".');
                needsReload = true;
            })
            .catch(function () { showError('Lỗi kết nối mạng khi thêm nhãn.'); });
    });

    listEl?.addEventListener('click', function (e) {
        const li = e.target.closest('li[data-label-id]');
        if (!li) return;
        const labelId = li.dataset.labelId;
        const viewRow = li.querySelector('.label-view-row');
        const editRow = li.querySelector('.label-edit-row');
        const renameInput = li.querySelector('.label-rename-input');

        // Bật chế độ sửa tên ngay trên dòng
        if (e.target.closest('.btn-rename-label')) {
            clearError();
            viewRow.classList.add('d-none');
            editRow.classList.remove('d-none');
            editRow.classList.add('d-flex');
            renameInput.focus();
            renameInput.select();
            return;
        }

        // Hủy sửa tên
        if (e.target.closest('.btn-cancel-rename')) {
            editRow.classList.add('d-none');
            editRow.classList.remove('d-flex');
            viewRow.classList.remove('d-none');
            renameInput.value = li.querySelector('.label-item-name').textContent;
            return;
        }

        // Xóa nhãn
        if (e.target.closest('.btn-delete-label')) {
            const labelName = li.querySelector('.label-item-name').textContent;
            if (!window.confirm('Xóa nhãn "' + labelName + '"? Các ghi chú gắn nhãn này vẫn được giữ nguyên.')) {
                return;
            }

            fetch('/labels/delete/' + labelId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                },
                body: 'csrf_token=' + encodeURIComponent(csrf()),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        loadLabels();
                        window.showToast?.('success', 'Đã xóa nhãn "' + labelName + '".');
                        needsReload = true;
                    } else {
                        showError(data.error || 'Không thể xóa nhãn.');
                    }
                })
                .catch(function () { showError('Lỗi kết nối mạng khi xóa nhãn.'); });
        }
    });

    listEl?.addEventListener('submit', function (e) {
        const form = e.target.closest('.label-edit-row');
        if (!form) return;
        e.preventDefault();
        clearError();

        const li = form.closest('li[data-label-id]');
        const labelId = li.dataset.labelId;
        const newName = form.querySelector('.label-rename-input').value.trim();

        if (!newName) {
            showError('Tên nhãn không được để trống.');
            return;
        }

        fetch('/labels/rename/' + labelId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'name=' + encodeURIComponent(newName) + '&csrf_token=' + encodeURIComponent(csrf()),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    showError(data.error);
                    return;
                }
                loadLabels();
                window.showToast?.('success', 'Đã đổi tên nhãn thành "' + data.name + '".');
                needsReload = true;
            })
            .catch(function () { showError('Lỗi kết nối mạng khi đổi tên nhãn.'); });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (needsReload) {
            window.location.reload();
        }
    });
})();
