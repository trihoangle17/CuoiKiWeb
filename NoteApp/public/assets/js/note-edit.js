/**
 * note-edit.js
 * Xử lý giao diện Tạo mới & Chỉnh sửa ghi chú dùng chung:
 * - Tự động lưu (Auto-save debounce 600ms, không có nút Save).
 * - Khi vừa tạo mới thành công, tự động mở khóa ngay các tính năng: Đính kèm tệp, Ghim, Khóa mật khẩu, Chia sẻ, AI Tóm tắt.
 * - Quản lý khóa mật khẩu qua Modal chuẩn (Đặt khóa nhập 2 lần; Đổi mật khẩu nhập cũ + mới 2 lần; Tắt khóa nhập lại mật khẩu hiện tại).
 * - Đính kèm nhiều Ảnh / Video / Tài liệu kèm xem trước trực quan.
 * - Tóm tắt nội dung bằng AI (LLM) và Tạo lại (Regenerate).
 * - Hỗ trợ xem & lưu tạm vào IndexedDB khi mất mạng (PWA Offline).
 */

(function () {
    'use strict';

    const form = document.getElementById('noteForm');
    if (!form) {
        return;
    }

    const canEdit = form.dataset.canEdit === '1';
    const titleInput = document.getElementById('noteTitle');
    const contentInput = document.getElementById('noteContent');
    const statusEl = document.getElementById('autoSaveStatus');
    const noteIdInput = document.getElementById('noteId');

    let saveDebounceTimer = null;
    let isSaving = false;

    function csrf() {
        if (window.csrfToken) return window.csrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function setStatus(html) {
        if (statusEl) {
            statusEl.innerHTML = html;
        }
    }

    // --- Phục hồi nội dung từ IndexedDB nếu mở /notes/edit/{id} khi mất mạng ---
    const pathMatch = window.location.pathname.match(/^\/notes\/edit\/([^/]+)/);
    if (!navigator.onLine && pathMatch && (!noteIdInput.value || noteIdInput.value === '0') && window.NoteAppOffline) {
        const offlineId = pathMatch[1];
        window.NoteAppOffline.getNoteById(offlineId).then(function (cachedNote) {
            if (cachedNote) {
                noteIdInput.value = cachedNote.id;
                form.dataset.noteId = cachedNote.id;
                titleInput.value = cachedNote.title || '';
                contentInput.value = cachedNote.content || '';
                setStatus('<i class="bi bi-wifi-off text-warning me-1"></i>Đang xem bản lưu ngoại tuyến');
            }
        });
    }

    // --- Tự động lưu (Auto-save) ---
    function scheduleAutoSave() {
        if (!canEdit) return;

        const title = titleInput.value.trim();
        const content = contentInput.value.trim();

        if (title === '' || content === '') {
            setStatus('<i class="bi bi-info-circle text-secondary me-1"></i>Cần nhập đủ tiêu đề &amp; nội dung để tự động lưu');
            clearTimeout(saveDebounceTimer);
            return;
        }

        setStatus('<i class="bi bi-arrow-repeat text-primary me-1"></i>Đang soạn...');
        clearTimeout(saveDebounceTimer);
        saveDebounceTimer = setTimeout(autoSave, 600);
    }

    function getSelectedLabelIds() {
        return Array.from(form.querySelectorAll('input[name="labels[]"]:checked')).map(function (cb) {
            return Number(cb.value);
        });
    }

    async function saveOfflineFallback(title, content) {
        if (!window.NoteAppOffline) return;

        let currentId = noteIdInput.value;
        const labelIds = getSelectedLabelIds();

        if (!currentId || currentId === '0') {
            currentId = 'local_' + Date.now();
            noteIdInput.value = currentId;
            form.dataset.noteId = currentId;
            await window.NoteAppOffline.queueOperation({
                action: 'create',
                temp_id: currentId,
                title: title,
                content: content,
                labels: labelIds,
            });
        } else {
            await window.NoteAppOffline.queueOperation({
                action: 'update',
                note_id: currentId,
                title: title,
                content: content,
                labels: labelIds,
            });
        }

        await window.NoteAppOffline.saveNote({
            id: isNaN(Number(currentId)) ? currentId : Number(currentId),
            title: title,
            content: content,
            updated_at: new Date().toISOString(),
            time_ago: 'Vừa lưu ngoại tuyến',
        });

        setStatus('<i class="bi bi-cloud-slash text-warning me-1"></i>Đã lưu ngoại tuyến (tự đồng bộ khi có mạng)');
    }

    function autoSave() {
        if (!canEdit) return;

        const title = titleInput.value.trim();
        const content = contentInput.value.trim();

        if (title === '' || content === '') {
            return;
        }

        if (!navigator.onLine) {
            saveOfflineFallback(title, content);
            return;
        }

        if (isSaving) {
            clearTimeout(saveDebounceTimer);
            saveDebounceTimer = setTimeout(autoSave, 350);
            return;
        }

        isSaving = true;
        setStatus('<span class="spinner-border spinner-border-sm text-primary me-1" role="status"></span>Đang lưu...');

        const labelParams = getSelectedLabelIds()
            .map(function (id) { return 'labels[]=' + encodeURIComponent(id); })
            .join('&');

        const currentId = (!noteIdInput.value || String(noteIdInput.value).startsWith('local_')) ? '0' : noteIdInput.value;

        const body = 'id=' + encodeURIComponent(currentId) +
            '&title=' + encodeURIComponent(title) +
            '&content=' + encodeURIComponent(content) +
            (labelParams ? '&' + labelParams : '') +
            '&csrf_token=' + encodeURIComponent(csrf());

        fetch('/notes/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: body,
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                isSaving = false;
                if (data.error) {
                    setStatus('<i class="bi bi-exclamation-circle text-danger me-1"></i>' + data.error);
                    return;
                }

                // Nếu vừa tạo ghi chú mới lần đầu: kích hoạt ngay tất cả các nút chức năng mà không cần tải lại trang
                if (currentId === '0' && data.id) {
                    noteIdInput.value = data.id;
                    form.dataset.noteId = data.id;
                    window.history.replaceState(null, '', '/notes/edit/' + data.id);

                    document.getElementById('ownerNoteActions')?.classList.remove('d-none');
                    document.getElementById('attachmentSection')?.classList.remove('d-none');
                    document.getElementById('btnAiSummarize')?.classList.remove('d-none');

                    const shareLink = document.getElementById('btnShareLink');
                    if (shareLink) {
                        shareLink.setAttribute('href', '/shares/create/' + data.id);
                    }

                    if (data.ws_token) {
                        window.wsCollabToken = data.ws_token;
                        document.dispatchEvent(new CustomEvent('noteapp:note-created', { detail: data.ws_token }));
                    }
                }

                setStatus('<i class="bi bi-cloud-check text-success me-1"></i>Đã lưu lúc ' + data.saved_at);

                if (form.dataset.isLocked !== '1' && window.NoteAppOffline) {
                    window.NoteAppOffline.saveNote({
                        id: Number(data.id),
                        title: title,
                        content: content,
                        updated_at: new Date().toISOString(),
                        time_ago: 'Vừa xong',
                    });
                }
            })
            .catch(function () {
                isSaving = false;
                saveOfflineFallback(title, content);
            });
    }

    if (canEdit) {
        titleInput?.addEventListener('input', function () {
            scheduleAutoSave();
            document.dispatchEvent(new CustomEvent('noteapp:local-edit'));
        });

        contentInput?.addEventListener('input', function () {
            scheduleAutoSave();
            document.dispatchEvent(new CustomEvent('noteapp:local-edit'));
        });

        form.querySelectorAll('input[name="labels[]"]').forEach(function (cb) {
            cb.addEventListener('change', scheduleAutoSave);
        });
    }

    // --- Ghim / bỏ ghim ghi chú ---
    document.getElementById('btnTogglePinEdit')?.addEventListener('click', function () {
        const noteId = noteIdInput.value;
        if (!noteId || noteId === '0') return;
        const btn = this;

        fetch('/notes/toggle-pin/' + encodeURIComponent(noteId), {
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
                    const icon = btn.querySelector('i');
                    if (icon) {
                        icon.className = data.is_pinned ? 'bi bi-pin-fill text-primary' : 'bi bi-pin';
                    }
                    window.showToast?.('success', data.is_pinned ? 'Đã ghim ghi chú lên đầu.' : 'Đã bỏ ghim ghi chú.');
                }
            });
    });

    // --- Xóa ghi chú (luôn hiển thị hộp thoại xác nhận) ---
    const deleteEditModalEl = document.getElementById('deleteEditNoteModal');
    document.getElementById('btnDeleteNote')?.addEventListener('click', function () {
        const noteId = noteIdInput.value;
        if (!noteId || noteId === '0') {
            window.location.href = '/notes';
            return;
        }
        document.getElementById('deleteEditError')?.classList.add('d-none');
        if (window.bootstrap && deleteEditModalEl) {
            bootstrap.Modal.getOrCreateInstance(deleteEditModalEl).show();
        }
    });

    document.getElementById('btnConfirmDeleteEdit')?.addEventListener('click', function () {
        const noteId = noteIdInput.value;
        const passInput = document.getElementById('deleteEditLockPass');
        const errEl = document.getElementById('deleteEditError');
        const lockPass = passInput ? passInput.value : '';

        if (form.dataset.isLocked === '1' && lockPass === '') {
            if (errEl) {
                errEl.textContent = 'Vui lòng nhập mật khẩu khóa ghi chú để xác nhận xóa.';
                errEl.classList.remove('d-none');
            }
            passInput?.focus();
            return;
        }

        const body = 'csrf_token=' + encodeURIComponent(csrf()) +
            (lockPass !== '' ? '&lock_password=' + encodeURIComponent(lockPass) : '');

        fetch('/notes/delete/' + encodeURIComponent(noteId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: body,
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.NoteAppOffline?.removeNote(noteId);
                    window.location.href = '/notes';
                } else if (errEl) {
                    errEl.textContent = data.error || 'Không thể xóa ghi chú.';
                    errEl.classList.remove('d-none');
                }
            });
    });

    // --- Khóa mật khẩu riêng: Đặt mới (nhập 2 lần) ---
    document.getElementById('setLockForm')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const noteId = noteIdInput.value;
        const p1 = document.getElementById('setLockPass1').value;
        const p2 = document.getElementById('setLockPass2').value;
        const errEl = document.getElementById('setLockError');
        errEl.classList.add('d-none');

        fetch('/notes/set-lock/' + encodeURIComponent(noteId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'password=' + encodeURIComponent(p1) +
                '&password_confirm=' + encodeURIComponent(p2) +
                '&csrf_token=' + encodeURIComponent(csrf()),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.NoteAppOffline?.removeNote(noteId);
                    window.showToast?.('success', 'Đã bật khóa mật khẩu cho ghi chú.');
                    setTimeout(function () { window.location.reload(); }, 500);
                } else {
                    errEl.textContent = data.error || 'Không thể đặt mật khẩu khóa.';
                    errEl.classList.remove('d-none');
                }
            });
    });

    // --- Khóa mật khẩu riêng: Đổi mật khẩu (nhập mật khẩu cũ + mới 2 lần) ---
    document.getElementById('changeLockForm')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const noteId = noteIdInput.value;
        const oldP = document.getElementById('oldLockPass').value;
        const newP1 = document.getElementById('newLockPass1').value;
        const newP2 = document.getElementById('newLockPass2').value;
        const errEl = document.getElementById('changeLockError');
        errEl.classList.add('d-none');

        fetch('/notes/change-lock-password/' + encodeURIComponent(noteId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'old_password=' + encodeURIComponent(oldP) +
                '&new_password=' + encodeURIComponent(newP1) +
                '&new_password_confirm=' + encodeURIComponent(newP2) +
                '&csrf_token=' + encodeURIComponent(csrf()),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    const modalEl = document.getElementById('changeLockModal');
                    if (window.bootstrap && modalEl) {
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    }
                    document.getElementById('changeLockForm').reset();
                    window.showToast?.('success', 'Đã đổi mật khẩu khóa ghi chú thành công.');
                } else {
                    errEl.textContent = data.error || 'Không thể đổi mật khẩu khóa.';
                    errEl.classList.remove('d-none');
                }
            });
    });

    // --- Khóa mật khẩu riêng: Tắt khóa (nhập lại mật khẩu hiện tại) ---
    document.getElementById('removeLockForm')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const noteId = noteIdInput.value;
        const pass = document.getElementById('removeLockPass').value;
        const errEl = document.getElementById('removeLockError');
        errEl.classList.add('d-none');

        fetch('/notes/remove-lock/' + encodeURIComponent(noteId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'password=' + encodeURIComponent(pass) + '&csrf_token=' + encodeURIComponent(csrf()),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.showToast?.('success', 'Đã tắt khóa mật khẩu ghi chú.');
                    setTimeout(function () { window.location.reload(); }, 500);
                } else {
                    errEl.textContent = data.error || 'Mật khẩu không chính xác.';
                    errEl.classList.remove('d-none');
                }
            });
    });

    // --- Tải lên & xóa nhiều Ảnh / Video / Tệp đính kèm ---
    const attachmentInput = document.getElementById('attachmentInput');
    const attachmentList = document.getElementById('attachmentList');
    const attachmentEmptyHint = document.getElementById('attachmentEmptyHint');
    const uploadProgress = document.getElementById('attachmentUploadProgress');

    document.getElementById('btnAddAttachment')?.addEventListener('click', function () {
        const noteId = noteIdInput.value;
        if (!noteId || noteId === '0' || String(noteId).startsWith('local_')) {
            window.showToast?.('info', 'Hãy nhập tiêu đề và nội dung để ghi chú tự động lưu trước khi đính kèm tệp.');
            return;
        }
        attachmentInput?.click();
    });

    attachmentInput?.addEventListener('change', function () {
        const noteId = noteIdInput.value;
        const files = Array.from(this.files || []);
        if (files.length === 0) return;

        uploadProgress?.classList.remove('d-none');
        let remaining = files.length;

        files.forEach(function (file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', csrf());

            fetch('/notes/upload-attachment/' + encodeURIComponent(noteId), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.error) {
                        window.showToast?.('error', file.name + ': ' + data.error);
                        return;
                    }
                    appendAttachmentDom(data);
                    attachmentEmptyHint?.classList.add('d-none');
                    window.showToast?.('success', 'Đã đính kèm "' + data.original_name + '".');
                })
                .catch(function () {
                    window.showToast?.('error', 'Không thể tải lên tệp: ' + file.name);
                })
                .finally(function () {
                    remaining--;
                    if (remaining <= 0) {
                        uploadProgress?.classList.add('d-none');
                    }
                });
        });

        this.value = '';
    });

    function appendAttachmentDom(data) {
        if (!attachmentList) return;

        const item = document.createElement('div');
        item.className = 'attachment-item';
        item.dataset.attachmentId = data.id;

        let previewHtml = '';
        if (data.mime_type.startsWith('image/')) {
            previewHtml = '<a href="' + data.url + '" target="_blank" rel="noopener"><img src="' + data.url + '" alt="" class="attachment-thumb"></a>';
        } else if (data.mime_type.startsWith('video/')) {
            previewHtml = '<video src="' + data.url + '" controls preload="metadata" class="attachment-video"></video>';
        } else {
            previewHtml = '<div class="attachment-file-icon"><i class="bi bi-file-earmark-text fs-2 text-primary"></i></div>';
        }

        item.innerHTML =
            previewHtml +
            '<div class="attachment-meta w-100">' +
            '<a href="' + data.url + '" target="_blank" rel="noopener" class="attachment-name small fw-medium"></a>' +
            '<div class="text-muted" style="font-size: 0.72rem;"></div>' +
            '</div>' +
            '<button type="button" class="btn btn-icon btn-sm btn-remove-attachment" title="Xóa tệp đính kèm"><i class="bi bi-x"></i></button>';

        const nameLink = item.querySelector('.attachment-name');
        nameLink.textContent = data.original_name;
        nameLink.setAttribute('download', data.original_name);
        nameLink.setAttribute('title', data.original_name);
        item.querySelector('.text-muted').textContent = data.file_size_formatted || '';

        attachmentList.appendChild(item);
    }

    attachmentList?.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-remove-attachment');
        if (!btn) return;

        const item = btn.closest('.attachment-item');
        const attachmentId = item.dataset.attachmentId;

        if (!window.confirm('Bạn muốn xóa tệp đính kèm này khỏi ghi chú?')) {
            return;
        }

        fetch('/notes/delete-attachment/' + encodeURIComponent(attachmentId), {
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
                    item.remove();
                    if (attachmentList.children.length === 0) {
                        attachmentEmptyHint?.classList.remove('d-none');
                    }
                    window.showToast?.('success', 'Đã xóa tệp đính kèm.');
                } else {
                    window.showToast?.('error', data.error || 'Không xóa được tệp.');
                }
            });
    });

    // --- AI Tóm tắt ghi chú (kèm chức năng Tạo lại) ---
    const aiSummaryBox = document.getElementById('aiSummaryBox');
    const aiSummaryContent = document.getElementById('aiSummaryContent');

    function requestAiSummary(regenerate) {
        const noteId = noteIdInput.value;
        if (!noteId || noteId === '0') {
            window.showToast?.('info', 'Vui lòng nhập nội dung để ghi chú được lưu trước khi tóm tắt.');
            return;
        }

        aiSummaryBox?.classList.remove('d-none');
        if (aiSummaryContent) {
            aiSummaryContent.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>Đang phân tích và tạo bản tóm tắt AI...';
        }

        const body = 'regenerate=' + (regenerate ? '1' : '0') +
            '&title=' + encodeURIComponent(titleInput.value.trim()) +
            '&content=' + encodeURIComponent(contentInput.value.trim()) +
            '&csrf_token=' + encodeURIComponent(csrf());

        fetch('/ai/summarize/' + encodeURIComponent(noteId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: body,
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    aiSummaryContent.textContent = data.error;
                    window.showToast?.('error', data.error);
                    return;
                }
                aiSummaryContent.textContent = data.summary || 'Không tạo được bản tóm tắt.';
                if (regenerate) {
                    window.showToast?.('success', 'Đã tạo lại bản tóm tắt mới.');
                }
            })
            .catch(function () {
                if (aiSummaryContent) {
                    aiSummaryContent.textContent = 'Không thể kết nối dịch vụ AI khi mất mạng.';
                }
            });
    }

    document.getElementById('btnAiSummarize')?.addEventListener('click', function () {
        requestAiSummary(false);
    });
    document.getElementById('btnAiRegenerate')?.addEventListener('click', function () {
        requestAiSummary(true);
    });
})();
