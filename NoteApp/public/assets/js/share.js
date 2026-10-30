/**
 * share.js
 * Quản lý chia sẻ ghi chú dành cho chủ sở hữu (views/shared/manage.php):
 * - Kiểm tra nhanh email đã đăng ký tài khoản hay chưa.
 * - Chia sẻ cho một hoặc nhiều người nhận cùng lúc với quyền Chỉ đọc / Chỉnh sửa.
 * - Cập nhật quyền hoặc thu hồi quyền truy cập ngay lập tức qua AJAX.
 */

(function () {
    'use strict';

    const form = document.getElementById('shareSendForm');
    if (!form) {
        return;
    }

    const noteId = form.dataset.noteId;
    const emailsInput = document.getElementById('shareEmailsInput');
    const permissionSelect = document.getElementById('sharePermissionSelect');
    const btnVerify = document.getElementById('btnVerifyEmail');
    const verifyFeedback = document.getElementById('emailVerifyFeedback');
    const resultAlert = document.getElementById('shareResultAlert');
    const recipientsList = document.getElementById('recipientsList');
    const statusBadge = document.getElementById('shareStatusBadge');
    const btnSubmit = document.getElementById('btnSubmitShare');

    function csrf() {
        if (window.csrfToken) return window.csrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // --- Kiểm tra nhanh các email đã nhập ---
    btnVerify?.addEventListener('click', async function () {
        const raw = emailsInput.value.trim();
        if (!raw) {
            verifyFeedback.innerHTML = '<span class="text-danger">Vui lòng nhập ít nhất một email để kiểm tra.</span>';
            return;
        }

        const emails = raw.split(/[\s,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        verifyFeedback.innerHTML = '<span class="text-muted">Đang kiểm tra tài khoản...</span>';

        const messages = [];
        for (const email of emails) {
            try {
                const res = await fetch('/shares/check-email?email=' + encodeURIComponent(email), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.valid) {
                    messages.push('<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>' + escapeHtml(email) + ' (' + escapeHtml(data.display_name) + ')</span>');
                } else {
                    messages.push('<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>' + escapeHtml(email) + ': ' + escapeHtml(data.message) + '</span>');
                }
            } catch (e) {
                messages.push('<span class="text-danger">Không thể kiểm tra ' + escapeHtml(email) + '</span>');
            }
        }

        verifyFeedback.innerHTML = messages.join('<br>');
    });

    // --- Gửi chia sẻ cho nhiều người nhận ---
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const emails = emailsInput.value.trim();
        const permission = permissionSelect.value;

        if (!emails) return;

        btnSubmit.disabled = true;
        resultAlert.className = 'mt-3 d-none';
        verifyFeedback.innerHTML = '';

        fetch('/shares/send/' + encodeURIComponent(noteId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'emails=' + encodeURIComponent(emails) +
                '&permission=' + encodeURIComponent(permission) +
                '&csrf_token=' + encodeURIComponent(csrf()),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                btnSubmit.disabled = false;

                if (data.error) {
                    resultAlert.className = 'alert alert-danger py-2 small mt-3';
                    resultAlert.textContent = data.error;
                    return;
                }

                const parts = [];
                if (data.success && data.success.length > 0) {
                    const okEmails = data.success.map(function (u) { return u.email; }).join(', ');
                    parts.push('<div><i class="bi bi-check-circle-fill text-success me-1"></i>Đã chia sẻ thành công tới: <strong>' + escapeHtml(okEmails) + '</strong></div>');
                    emailsInput.value = '';
                }
                if (data.failed && data.failed.length > 0) {
                    parts.push('<div class="mt-1 text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Không thể chia sẻ: ' + escapeHtml(data.failed.join('; ')) + '</div>');
                }

                resultAlert.className = 'alert ' + ((data.failed && data.failed.length > 0) ? 'alert-warning' : 'alert-success') + ' py-2 small mt-3';
                resultAlert.innerHTML = parts.join('');

                if (Array.isArray(data.shares)) {
                    renderRecipients(data.shares);
                }
            })
            .catch(function () {
                btnSubmit.disabled = false;
                resultAlert.className = 'alert alert-danger py-2 small mt-3';
                resultAlert.textContent = 'Lỗi kết nối mạng khi gửi chia sẻ.';
            });
    });

    function renderRecipients(shares) {
        if (statusBadge) {
            statusBadge.className = 'badge rounded-pill ' + (shares.length > 0 ? 'text-bg-success' : 'text-bg-secondary');
            statusBadge.textContent = shares.length > 0 ? 'Đang chia sẻ với ' + shares.length + ' người' : 'Chưa chia sẻ';
        }

        if (shares.length === 0) {
            recipientsList.innerHTML = '<div class="text-center text-muted py-4 small">Ghi chú này hiện chỉ mình bạn có quyền truy cập.</div>';
            return;
        }

        recipientsList.innerHTML = shares.map(function (s) {
            return (
                '<div class="list-group-item px-0 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2" data-share-id="' + s.id + '">' +
                '<div class="min-w-0">' +
                '<div class="fw-semibold text-truncate">' + escapeHtml(s.display_name) + '</div>' +
                '<div class="small text-muted text-truncate">' + escapeHtml(s.email) + '</div>' +
                '</div>' +
                '<div class="d-flex align-items-center gap-2">' +
                '<select class="form-select form-select-sm share-permission-select" style="width: auto;" aria-label="Đổi quyền chia sẻ">' +
                '<option value="view" ' + (s.permission === 'view' ? 'selected' : '') + '>Chỉ đọc</option>' +
                '<option value="edit" ' + (s.permission === 'edit' ? 'selected' : '') + '>Chỉnh sửa</option>' +
                '</select>' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-revoke-share"><i class="bi bi-x-circle me-1"></i>Thu hồi</button>' +
                '</div></div>'
            );
        }).join('');
    }

    // --- Thay đổi quyền (view <-> edit) hoặc Thu hồi ---
    recipientsList?.addEventListener('change', function (e) {
        const select = e.target.closest('.share-permission-select');
        if (!select) return;

        const row = select.closest('[data-share-id]');
        const shareId = row.dataset.shareId;

        fetch('/shares/update-permission/' + encodeURIComponent(shareId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'permission=' + encodeURIComponent(select.value) + '&csrf_token=' + encodeURIComponent(csrf()),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.showToast?.('success', 'Đã cập nhật quyền truy cập.');
                } else {
                    window.showToast?.('error', data.error || 'Không thể cập nhật quyền.');
                }
            });
    });

    recipientsList?.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-revoke-share');
        if (!btn) return;

        const row = btn.closest('[data-share-id]');
        const shareId = row.dataset.shareId;

        if (!window.confirm('Bạn muốn thu hồi quyền truy cập ghi chú của người nhận này?')) {
            return;
        }

        fetch('/shares/revoke/' + encodeURIComponent(shareId), {
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
                    row.remove();
                    const remaining = recipientsList.querySelectorAll('[data-share-id]').length;
                    if (remaining === 0) {
                        renderRecipients([]);
                    } else if (statusBadge) {
                        statusBadge.textContent = 'Đang chia sẻ với ' + remaining + ' người';
                    }
                    window.showToast?.('success', 'Đã thu hồi quyền chia sẻ.');
                } else {
                    window.showToast?.('error', data.error || 'Không thể thu hồi.');
                }
            });
    });
})();
