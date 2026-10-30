/**
 * toast.js
 * Hiển thị thông báo phản hồi kiểu Bootstrap Toast thân thiện, hỗ trợ Accessibility.
 */

(function () {
    'use strict';

    const ICONS = {
        success: 'bi-check-circle-fill text-success',
        error: 'bi-exclamation-octagon-fill text-danger',
        info: 'bi-info-circle-fill text-primary',
    };

    function showToast(type, message) {
        const container = document.getElementById('toastContainer');
        if (!container || !message) {
            return;
        }

        const iconClass = ICONS[type] || ICONS.info;
        const toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center border shadow-sm bg-body';
        toastEl.setAttribute('role', 'status');
        toastEl.setAttribute('aria-live', 'polite');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.innerHTML =
            '<div class="d-flex">' +
            '<div class="toast-body d-flex align-items-center gap-2">' +
            '<i class="bi ' + iconClass + ' fs-5"></i><span class="fw-medium"></span>' +
            '</div>' +
            '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Đóng thông báo"></button>' +
            '</div>';

        toastEl.querySelector('span').textContent = message;
        container.appendChild(toastEl);

        if (window.bootstrap) {
            const toast = new bootstrap.Toast(toastEl, { delay: 3800 });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', function () {
                toastEl.remove();
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-flash]').forEach(function (el) {
            showToast(el.getAttribute('data-flash'), el.getAttribute('data-message'));
        });
    });

    window.showToast = showToast;
})();
