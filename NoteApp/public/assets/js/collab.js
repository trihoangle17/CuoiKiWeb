/**
 * collab.js
 * Đồng bộ thời gian thực (Realtime Collaboration) bằng WebSocket cho nhiều người
 * cùng chỉnh sửa một ghi chú (không hardcode host, tự động nhận diện hostname hiện tại).
 */

(function () {
    'use strict';

    const titleInput = document.getElementById('noteTitle');
    const contentInput = document.getElementById('noteContent');
    const presenceBar = document.getElementById('collabPresenceBar');
    const presenceText = document.getElementById('collabUsersText');
    const statusEl = document.getElementById('autoSaveStatus');

    if (!titleInput || !contentInput) {
        return;
    }

    let socket = null;
    let reconnectTimer = null;
    let broadcastTimer = null;
    let isApplyingRemote = false;

    function buildWsUrl() {
        const isSecure = window.location.protocol === 'https:';
        const protocol = isSecure ? 'wss:' : 'ws:';
        const host = window.location.hostname;
        const wsPort = window.WS_PORT || 8081;

        // Nếu deploy online qua HTTPS reverse-proxy ở đường dẫn /ws thì dùng đường dẫn tương đối,
        // còn môi trường Docker / Local HTTP thì kết nối tới cùng hostname ở cổng WS_PORT
        if (isSecure && window.location.port === '') {
            return protocol + '//' + window.location.host + '/ws';
        }
        return protocol + '//' + host + ':' + wsPort;
    }

    function connectWebSocket(tokenObj) {
        if (!tokenObj || !tokenObj.noteId || !navigator.onLine) {
            return;
        }

        if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) {
            return;
        }

        try {
            socket = new WebSocket(buildWsUrl());
        } catch (err) {
            return;
        }

        socket.addEventListener('open', function () {
            socket.send(JSON.stringify({
                type: 'join',
                noteId: tokenObj.noteId,
                userId: tokenObj.userId,
                displayName: tokenObj.displayName,
                exp: tokenObj.exp,
                sig: tokenObj.sig,
            }));
        });

        socket.addEventListener('message', function (event) {
            let msg;
            try {
                msg = JSON.parse(event.data);
            } catch (e) {
                return;
            }

            if (msg.type === 'presence') {
                updatePresenceUi(msg.users || []);
            } else if (msg.type === 'edit' && Number(msg.senderSocketId) !== Number(msg.selfSocketId || -1)) {
                applyRemoteChanges(msg);
            }
        });

        socket.addEventListener('close', function () {
            presenceBar?.classList.add('d-none');
            presenceBar?.classList.remove('d-inline-flex');

            clearTimeout(reconnectTimer);
            reconnectTimer = setTimeout(function () {
                if (window.wsCollabToken && navigator.onLine) {
                    connectWebSocket(window.wsCollabToken);
                }
            }, 4000);
        });

        socket.addEventListener('error', function () {
            // Đóng êm để kích hoạt thử lại sau 4s
            try { socket.close(); } catch (e) {}
        });
    }

    function updatePresenceUi(users) {
        if (!presenceBar || !presenceText) return;

        if (!users || users.length === 0) {
            presenceBar.classList.add('d-none');
            presenceBar.classList.remove('d-inline-flex');
            return;
        }

        presenceBar.classList.remove('d-none');
        presenceBar.classList.add('d-inline-flex');

        if (users.length === 1) {
            presenceText.textContent = 'Trực tuyến (sẵn sàng cộng tác)';
        } else {
            const names = users.map(function (u) { return u.displayName; }).join(', ');
            presenceText.textContent = users.length + ' người đang cùng mở: ' + names;
        }
    }

    /**
     * Cập nhật nội dung từ người cộng tác khác mà vẫn giữ nguyên vị trí con trỏ gõ chữ.
     */
    function applyRemoteChanges(msg) {
        isApplyingRemote = true;

        if (typeof msg.title === 'string' && titleInput.value !== msg.title) {
            const tStart = titleInput.selectionStart;
            const tEnd = titleInput.selectionEnd;
            titleInput.value = msg.title;
            if (document.activeElement === titleInput && tStart !== null) {
                titleInput.setSelectionRange(Math.min(tStart, msg.title.length), Math.min(tEnd, msg.title.length));
            }
        }

        if (typeof msg.content === 'string' && contentInput.value !== msg.content) {
            const cStart = contentInput.selectionStart;
            const cEnd = contentInput.selectionEnd;
            const delta = msg.content.length - contentInput.value.length;
            contentInput.value = msg.content;

            if (document.activeElement === contentInput && cStart !== null) {
                const newPos = Math.max(0, Math.min(msg.content.length, cStart + (delta > 0 ? 0 : delta)));
                contentInput.setSelectionRange(newPos, newPos);
            }
        }

        if (statusEl && msg.editorName) {
            statusEl.innerHTML = '<i class="bi bi-lightning-charge-fill text-primary me-1"></i>Đồng bộ trực tiếp từ ' + msg.editorName;
        }

        isApplyingRemote = false;
    }

    function broadcastLocalEdit() {
        if (isApplyingRemote || !socket || socket.readyState !== WebSocket.OPEN) {
            return;
        }

        clearTimeout(broadcastTimer);
        broadcastTimer = setTimeout(function () {
            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({
                    type: 'edit',
                    title: titleInput.value,
                    content: contentInput.value,
                }));
            }
        }, 120);
    }

    document.addEventListener('noteapp:local-edit', broadcastLocalEdit);

    document.addEventListener('noteapp:note-created', function (e) {
        if (e.detail) {
            connectWebSocket(e.detail);
        }
    });

    if (window.wsCollabToken) {
        connectWebSocket(window.wsCollabToken);
    }
})();
