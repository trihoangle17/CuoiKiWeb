/**
 * notes.js
 * Xử lý trang danh sách ghi chú:
 * - Chuyển đổi mượt mà giữa dạng Lưới (Grid - mặc định) và Danh sách (List).
 * - Tìm kiếm live với độ trễ debounce 300ms (không có nút Search), giữ bộ lọc nhãn,
 *   và tự động tìm kiếm trên IndexedDB cục bộ khi mất mạng (PWA Offline).
 * - Ghim / bỏ ghim ghi chú.
 * - Mở khóa mật khẩu trước khi xem/sửa VÀ yêu cầu cả mật khẩu khóa + xác nhận trước khi xóa.
 */

(function () {
    'use strict';

    const container = document.getElementById('notesContainer');
    if (!container) {
        return;
    }

    const emptyState = document.getElementById('notesEmptyState');
    const loadingState = document.getElementById('notesLoadingState');
    const errorState = document.getElementById('notesErrorState');
    const searchInput = document.getElementById('noteSearchInput');
    const searchIcon = document.getElementById('searchIcon');
    const searchSpinner = document.getElementById('searchSpinner');
    const btnViewGrid = document.getElementById('btnViewGrid');
    const btnViewList = document.getElementById('btnViewList');
    const deleteModalEl = document.getElementById('deleteConfirmModal');
    const lockModalEl = document.getElementById('lockPasswordModal');
    const lockUnlockModalForm = document.getElementById('lockUnlockModalForm');

    const activeLabelId = Number(container.dataset.labelId || 0);

    let deleteModal = null;
    let lockModal = null;
    if (window.bootstrap) {
        if (deleteModalEl) deleteModal = bootstrap.Modal.getOrCreateInstance(deleteModalEl);
        if (lockModalEl) lockModal = bootstrap.Modal.getOrCreateInstance(lockModalEl);
    }

    let pendingDeleteId = null;
    let pendingDeleteIsLocked = false;
    let pendingLockedNoteId = null;
    let pendingLockedAction = 'open';
    let searchDebounceTimer = null;

    function csrf() {
        if (window.csrfToken) return window.csrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // --- Chuyển đổi hiển thị Lưới (Grid) / Danh sách (List) ---
    function setViewMode(mode) {
        const validMode = mode === 'list' ? 'list' : 'grid';
        container.dataset.viewMode = validMode;
        container.classList.toggle('notes-grid', validMode === 'grid');
        container.classList.toggle('notes-list', validMode === 'list');
        btnViewGrid?.classList.toggle('active', validMode === 'grid');
        btnViewList?.classList.toggle('active', validMode === 'list');
        btnViewGrid?.setAttribute('aria-pressed', validMode === 'grid' ? 'true' : 'false');
        btnViewList?.setAttribute('aria-pressed', validMode === 'list' ? 'true' : 'false');
        localStorage.setItem('noteapp_view_mode', validMode);
    }

    const savedMode = localStorage.getItem('noteapp_view_mode');
    if (savedMode === 'list' || savedMode === 'grid') {
        setViewMode(savedMode);
    }

    btnViewGrid?.addEventListener('click', function () { setViewMode('grid'); });
    btnViewList?.addEventListener('click', function () { setViewMode('list'); });

    // --- Tìm kiếm Live (Debounce 300ms) ---
    searchInput?.addEventListener('input', function () {
        clearTimeout(searchDebounceTimer);
        const keyword = this.value;

        searchDebounceTimer = setTimeout(function () {
            fetchAndRenderNotes(keyword);
        }, 300);
    });

    function setSearchLoading(loading) {
        if (searchIcon && searchSpinner) {
            searchIcon.classList.toggle('d-none', loading);
            searchSpinner.classList.toggle('d-none', !loading);
        }
    }

    async function fetchAndRenderNotes(keyword) {
        errorState?.classList.add('d-none');
        setSearchLoading(true);

        // Nếu đang ngoại tuyến (Offline), đọc trực tiếp từ IndexedDB
        if (!navigator.onLine && window.NoteAppOffline) {
            const localNotes = await window.NoteAppOffline.getNotes(keyword, activeLabelId);
            setSearchLoading(false);
            renderNotes(localNotes);
            return;
        }

        const url = '/notes/search?q=' + encodeURIComponent(keyword || '') +
            (activeLabelId > 0 ? '&label_id=' + activeLabelId : '');

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                setSearchLoading(false);
                renderNotes(data.notes || []);
            })
            .catch(async function () {
                setSearchLoading(false);
                // Rơi về dữ liệu cục bộ IndexedDB khi kết nối mạng bị gián đoạn
                if (window.NoteAppOffline) {
                    const localNotes = await window.NoteAppOffline.getNotes(keyword, activeLabelId);
                    renderNotes(localNotes);
                } else if (errorState) {
                    errorState.classList.remove('d-none');
                }
            });
    }

    // Khi vừa mở trang ở chế độ Offline (do Service Worker trả về App Shell), nạp từ IndexedDB
    if (!navigator.onLine && window.NoteAppOffline) {
        window.NoteAppOffline.getNotes('', activeLabelId).then(function (localNotes) {
            if (localNotes && localNotes.length > 0) {
                renderNotes(localNotes);
            }
        });
    }

    document.addEventListener('noteapp:synced', function () {
        fetchAndRenderNotes(searchInput ? searchInput.value : '');
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        // Escape thêm dấu nháy để an toàn khi chèn vào thuộc tính HTML (aria-label, title...)
        return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Vùng thông báo kết quả cho trình đọc màn hình (số ghi chú sau tìm kiếm / ghim / xóa)
    const resultStatus = document.getElementById('notesResultStatus');
    // Ghi nhớ phần tử cần trả focus sau khi danh sách được vẽ lại (innerHTML làm mất focus)
    let pendingFocus = null;

    function restoreFocusAfterRender() {
        if (!pendingFocus) return;
        const target = pendingFocus;
        pendingFocus = null;

        let el = null;
        if (target.noteId) {
            el = container.querySelector('.note-card[data-note-id="' + target.noteId + '"] .' + target.selector);
        }
        (el || container).focus({ preventScroll: true });
    }

    function renderNotes(notes) {
        loadingState?.classList.add('d-none');

        const notesCountBadge = document.getElementById('notesCountBadge');
        if (notesCountBadge) {
            notesCountBadge.textContent = notes ? notes.length : 0;
        }

        const emptyIcon = document.getElementById('notesEmptyIcon');
        const emptyTitle = document.getElementById('notesEmptyTitle');
        const emptyDesc = document.getElementById('notesEmptyDesc');
        const emptyActions = document.getElementById('notesEmptyActions');

        const isSearching = Boolean(searchInput && searchInput.value.trim().length > 0);
        const hasLabelFilter = activeLabelId > 0;
        const isFiltered = isSearching || hasLabelFilter;

        if (!notes || notes.length === 0) {
            container.innerHTML = '';
            if (emptyState) {
                emptyState.classList.remove('d-none');
                if (isFiltered) {
                    if (emptyIcon) emptyIcon.className = 'bi bi-search display-4 d-block mb-3 text-secondary';
                    if (emptyTitle) emptyTitle.textContent = 'Không tìm thấy ghi chú phù hợp';
                    if (emptyDesc) {
                        emptyDesc.textContent = isSearching
                            ? 'Không có kết quả khớp với từ khóa tìm kiếm. Thử tìm với từ khóa khác hoặc xóa bộ lọc.'
                            : 'Không có ghi chú nào trong nhãn này.';
                    }
                    if (emptyActions) {
                        let resetBtnHtml = '';
                        if (isSearching) {
                            resetBtnHtml = '<button type="button" class="btn btn-outline-secondary btn-sm px-3 me-2" id="btnEmptyClearSearch"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>Xóa tìm kiếm</button>';
                        } else if (hasLabelFilter) {
                            resetBtnHtml = '<a href="/notes" class="btn btn-outline-secondary btn-sm px-3 me-2"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>Xem tất cả ghi chú</a>';
                        }
                        emptyActions.innerHTML = resetBtnHtml + '<a href="/notes/create" class="btn btn-primary btn-sm px-3"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tạo ghi chú mới</a>';
                        const btnClearSearch = document.getElementById('btnEmptyClearSearch');
                        if (btnClearSearch) {
                            btnClearSearch.addEventListener('click', function () {
                                if (searchInput) {
                                    searchInput.value = '';
                                    fetchAndRenderNotes('');
                                    searchInput.focus();
                                }
                            });
                        }
                    }
                } else {
                    if (emptyIcon) emptyIcon.className = 'bi bi-journal-plus display-4 d-block mb-3 text-secondary';
                    if (emptyTitle) emptyTitle.textContent = 'Chưa có ghi chú nào';
                    if (emptyDesc) emptyDesc.textContent = 'Bắt đầu tạo ghi chú đầu tiên của bạn để lưu trữ ý tưởng.';
                    if (emptyActions) {
                        emptyActions.innerHTML = '<a href="/notes/create" class="btn btn-primary btn-sm px-3"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tạo ghi chú ngay</a>';
                    }
                }
            }
            if (resultStatus) {
                resultStatus.textContent = isFiltered
                    ? 'Không tìm thấy ghi chú nào phù hợp.'
                    : 'Chưa có ghi chú nào.';
            }
            restoreFocusAfterRender();
            return;
        }

        emptyState?.classList.add('d-none');
        container.innerHTML = notes.map(renderNoteCard).join('');
        if (resultStatus) {
            resultStatus.textContent = isFiltered
                ? `Tìm thấy ${notes.length} ghi chú phù hợp.`
                : `${notes.length} ghi chú.`;
        }
        restoreFocusAfterRender();
    }

    function renderNoteCard(note) {
        const isLocked = Number(note.is_locked) === 1;
        const isPinned = Number(note.is_pinned) === 1;
        const isShared = Boolean(note.is_shared);

        const title = escapeHtml(note.title || '(Không có tiêu đề)');
        const contentHtml = isLocked
            ? '<i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Nội dung được bảo vệ bằng mật khẩu'
            : escapeHtml((note.content || '').slice(0, 220));

        // Mẫu này PHẢI giữ đồng bộ với views/notes/_note_list.php (cùng ngữ nghĩa, cùng nhãn trợ năng)
        const icons = [
            isPinned ? '<span class="status-badge text-primary" title="Đã ghim lên đầu"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i><span class="visually-hidden">Đã ghim</span></span>' : '',
            isLocked ? '<span class="status-badge text-warning" title="Được bảo vệ bằng mật khẩu"><i class="bi bi-lock-fill" aria-hidden="true"></i><span class="visually-hidden">Đã khóa mật khẩu</span></span>' : '',
            isShared ? '<span class="status-badge text-success" title="Đang chia sẻ với người khác"><i class="bi bi-people-fill" aria-hidden="true"></i><span class="visually-hidden">Đã chia sẻ</span></span>' : '',
        ].join('');

        const labelsHtml = (note.labels || [])
            .map(function (l) { return '<span class="badge rounded-pill note-label-badge">' + escapeHtml(l.name) + '</span>'; })
            .join('');

        const timeText = escapeHtml(note.time_ago || '');

        // Ghi chú khóa là <button> (mở hộp thoại mật khẩu), ghi chú thường là <a> dẫn tới trang sửa
        const linkOpen = isLocked
            ? '<button type="button" class="note-card-link" data-locked="1" aria-haspopup="dialog">'
            : '<a href="/notes/edit/' + encodeURIComponent(note.id) + '" class="note-card-link">';
        const linkClose = isLocked ? '</button>' : '</a>';

        return (
            '<article class="note-card ' + (isPinned ? 'note-card-pinned' : '') + '" data-note-id="' + escapeHtml(String(note.id)) + '" data-locked="' + (isLocked ? '1' : '0') + '">' +
            linkOpen +
            '<div class="note-card-header"><h2 class="note-card-title">' + title + '</h2>' +
            '<div class="note-card-icons" role="group" aria-label="Trạng thái ghi chú">' + icons + '</div></div>' +
            '<p class="note-card-content ' + (isLocked ? 'fst-italic text-muted' : '') + '">' + contentHtml + '</p>' +
            (labelsHtml ? '<div class="note-card-labels">' + labelsHtml + '</div>' : '') +
            '<div class="note-card-footer text-muted small"><i class="bi bi-clock me-1" aria-hidden="true"></i>' + timeText + '</div>' +
            linkClose +
            '<div class="note-card-actions">' +
            '<button type="button" class="btn btn-icon btn-sm btn-toggle-pin" title="' + (isPinned ? 'Bỏ ghim ghi chú' : 'Ghim ghi chú lên đầu') + '" aria-label="' + (isPinned ? 'Bỏ ghim ghi chú' : 'Ghim ghi chú lên đầu') + ': ' + title + '"><i class="bi bi-pin' + (isPinned ? '-fill text-primary' : '') + '" aria-hidden="true"></i></button>' +
            '<button type="button" class="btn btn-icon btn-sm text-danger btn-delete-note" title="Xóa ghi chú" aria-label="Xóa ghi chú: ' + title + '"><i class="bi bi-trash" aria-hidden="true"></i></button>' +
            '</div></article>'
        );
    }

    function openLockPrompt(noteId, action) {
        pendingLockedNoteId = noteId;
        pendingLockedAction = action;
        const input = document.getElementById('lockPasswordInput');
        const errEl = document.getElementById('lockPasswordError');
        if (input) input.value = '';
        if (errEl) errEl.classList.add('d-none');
        lockModal?.show();
        setTimeout(function () { input?.focus(); }, 250);
    }

    // --- Xử lý sự kiện trên thẻ ghi chú: Mở khóa xem/sửa, Ghim, Xóa ---
    container.addEventListener('click', function (e) {
        const pinBtn = e.target.closest('.btn-toggle-pin');
        if (pinBtn) {
            e.preventDefault();
            const card = pinBtn.closest('.note-card');
            if (card.dataset.locked === '1') {
                openLockPrompt(card.dataset.noteId, 'pin');
            } else {
                togglePin(card.dataset.noteId, '');
            }
            return;
        }

        const deleteBtn = e.target.closest('.btn-delete-note');
        if (deleteBtn) {
            e.preventDefault();
            const card = deleteBtn.closest('.note-card');
            pendingDeleteId = card.dataset.noteId;
            pendingDeleteIsLocked = card.dataset.locked === '1';

            const lockWrap = document.getElementById('deleteLockPasswordWrap');
            const lockInput = document.getElementById('deleteLockPasswordInput');
            const errEl = document.getElementById('deleteNoteError');

            if (lockWrap) lockWrap.classList.toggle('d-none', !pendingDeleteIsLocked);
            if (lockInput) lockInput.value = '';
            if (errEl) errEl.classList.add('d-none');

            deleteModal?.show();
            return;
        }

        const link = e.target.closest('.note-card-link');
        if (link && link.dataset.locked === '1') {
            e.preventDefault();
            const card = link.closest('.note-card');
            openLockPrompt(card.dataset.noteId, 'open');
        }
    });

    function togglePin(noteId, lockPassword, onError) {
        const body = 'csrf_token=' + encodeURIComponent(csrf()) +
            (lockPassword ? '&lock_password=' + encodeURIComponent(lockPassword) : '');

        fetch('/notes/toggle-pin/' + encodeURIComponent(noteId), {
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
                    lockModal?.hide();
                    pendingFocus = { noteId: String(noteId), selector: 'btn-toggle-pin' };
                    window.showToast?.('success', data.is_pinned ? 'Đã ghim ghi chú lên đầu.' : 'Đã bỏ ghim ghi chú.');
                    fetchAndRenderNotes(searchInput ? searchInput.value : '');
                } else if (typeof onError === 'function') {
                    onError(data.error || 'Mật khẩu khóa không chính xác.');
                } else {
                    window.showToast?.('error', data.error || 'Không thực hiện được thao tác.');
                }
            })
            .catch(function () {
                if (typeof onError === 'function') {
                    onError('Không thể thực hiện khi đang mất kết nối mạng.');
                } else {
                    window.showToast?.('error', 'Không thể ghim khi đang mất kết nối mạng.');
                }
            });
    }

    // --- Xác nhận xóa ghi chú (yêu cầu mật khẩu nếu đang khóa) ---
    document.getElementById('btnConfirmDelete')?.addEventListener('click', function () {
        if (!pendingDeleteId) return;

        const errEl = document.getElementById('deleteNoteError');
        const lockPassInput = document.getElementById('deleteLockPasswordInput');
        const lockPassword = lockPassInput ? lockPassInput.value : '';

        if (pendingDeleteIsLocked && lockPassword === '') {
            if (errEl) {
                errEl.textContent = 'Vui lòng nhập mật khẩu khóa của ghi chú để xóa.';
                errEl.classList.remove('d-none');
            }
            lockPassInput?.focus();
            return;
        }

        if (!navigator.onLine && !pendingDeleteIsLocked && window.NoteAppOffline) {
            window.NoteAppOffline.removeNote(pendingDeleteId);
            window.NoteAppOffline.queueOperation({ action: 'delete', note_id: pendingDeleteId });
            deleteModal?.hide();
            window.showToast?.('info', 'Đã xóa ghi chú ngoại tuyến. Sẽ đồng bộ khi có mạng.');
            fetchAndRenderNotes(searchInput ? searchInput.value : '');
            return;
        }

        const body = 'csrf_token=' + encodeURIComponent(csrf()) +
            (pendingDeleteIsLocked ? '&lock_password=' + encodeURIComponent(lockPassword) : '');

        fetch('/notes/delete/' + encodeURIComponent(pendingDeleteId), {
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
                    deleteModal?.hide();
                    window.NoteAppOffline?.removeNote(pendingDeleteId);
                    pendingFocus = { noteId: null };
                    window.showToast?.('success', 'Đã xóa ghi chú thành công.');
                    fetchAndRenderNotes(searchInput ? searchInput.value : '');
                } else if (errEl) {
                    errEl.textContent = data.error || 'Không thể xóa ghi chú.';
                    errEl.classList.remove('d-none');
                }
            })
            .catch(function () {
                if (errEl) {
                    errEl.textContent = 'Lỗi kết nối mạng. Vui lòng thử lại.';
                    errEl.classList.remove('d-none');
                }
            });
    });

    // --- Xác nhận mở khóa để xem/sửa hoặc ghim ghi chú bị khóa ---
    lockUnlockModalForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!pendingLockedNoteId) return;

        const password = document.getElementById('lockPasswordInput').value;
        const errorEl = document.getElementById('lockPasswordError');

        if (pendingLockedAction === 'pin') {
            togglePin(pendingLockedNoteId, password, function (msg) {
                errorEl.textContent = msg;
                errorEl.classList.remove('d-none');
            });
            return;
        }

        fetch('/notes/unlock/' + encodeURIComponent(pendingLockedNoteId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'csrf_token=' + encodeURIComponent(csrf()) + '&password=' + encodeURIComponent(password),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorEl.textContent = data.error || 'Mật khẩu khóa không chính xác.';
                    errorEl.classList.remove('d-none');
                }
            })
            .catch(function () {
                errorEl.textContent = 'Không thể mở khóa khi mất kết nối mạng.';
                errorEl.classList.remove('d-none');
            });
    });

    // --- Nút thử lại khi kết nối mạng lỗi ---
    const btnRetryNotes = document.getElementById('btnRetryNotes');
    btnRetryNotes?.addEventListener('click', function () {
        errorState?.classList.add('d-none');
        fetchAndRenderNotes(searchInput ? searchInput.value : '');
    });

    // --- Phím tắt thao tác nhanh toàn cục (Operate Mode Accelerators) ---
    document.addEventListener('keydown', function (e) {
        const target = e.target;
        const isEditing = target && (
            target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable
        );

        // Phím Esc: Nếu đang trong ô tìm kiếm hoặc có từ khóa -> Xóa tìm kiếm và blur
        if (e.key === 'Escape') {
            if (searchInput && (document.activeElement === searchInput || searchInput.value.trim().length > 0)) {
                if (searchInput.value.trim().length > 0) {
                    searchInput.value = '';
                    fetchAndRenderNotes('');
                }
                searchInput.blur();
                e.preventDefault();
            }
            return;
        }

        // Đang gõ văn bản trong input/textarea thì không kích hoạt các phím tắt chữ/số
        if (isEditing) {
            return;
        }

        // Ctrl+K hoặc / : Nhảy nhanh vào ô tìm kiếm
        if (((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') || (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey)) {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
            return;
        }

        // Alt+N : Đi đến trang tạo ghi chú mới
        if (e.altKey && e.key.toLowerCase() === 'n') {
            e.preventDefault();
            window.location.href = '/notes/create';
            return;
        }

        // Phím ? : Mở bảng trợ giúp phím tắt
        if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            const helpModalEl = document.getElementById('shortcutsHelpModal');
            if (helpModalEl && window.bootstrap) {
                e.preventDefault();
                bootstrap.Modal.getOrCreateInstance(helpModalEl).show();
            }
            return;
        }
    });
})();
