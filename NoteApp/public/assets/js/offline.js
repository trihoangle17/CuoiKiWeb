/**
 * offline.js
 * Tích hợp cơ sở dữ liệu cục bộ IndexedDB với cơ sở dữ liệu trực tuyến MySQL (PWA Offline):
 * - ObjectStore "notes"  : Lưu bản sao cục bộ các ghi chú của người dùng để xem/tìm kiếm khi mất mạng.
 * - ObjectStore "outbox" : Hàng đợi lưu các thay đổi thực hiện lúc ngoại tuyến, tự động đẩy lên
 *                          MySQL qua /sync/push ngay khi kết nối Internet được khôi phục.
 */

(function () {
    'use strict';

    const DB_NAME = 'noteapp_offline_db';
    const DB_VERSION = 1;
    let dbPromise = null;
    let isSyncing = false;

    function getCsrfToken() {
        if (window.csrfToken) return window.csrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function openDb() {
        if (dbPromise) {
            return dbPromise;
        }

        dbPromise = new Promise(function (resolve, reject) {
            if (!('indexedDB' in window)) {
                reject(new Error('Trình duyệt không hỗ trợ IndexedDB'));
                return;
            }

            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = function (event) {
                const db = event.target.result;
                if (!db.objectStoreNames.contains('notes')) {
                    db.createObjectStore('notes', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('outbox')) {
                    db.createObjectStore('outbox', { keyPath: 'seq', autoIncrement: true });
                }
            };

            request.onsuccess = function (event) {
                resolve(event.target.result);
            };

            request.onerror = function (event) {
                reject(event.target.error);
            };
        });

        return dbPromise;
    }

    /**
     * Lưu một danh sách ghi chú tải từ MySQL vào IndexedDB.
     */
    async function replaceAllNotes(notes) {
        try {
            const db = await openDb();
            const tx = db.transaction('notes', 'readwrite');
            const store = tx.objectStore('notes');
            store.clear();
            (notes || []).forEach(function (note) {
                store.put(note);
            });
            return new Promise(function (res) { tx.oncomplete = res; });
        } catch (e) {
            console.warn('IndexedDB replaceAllNotes lỗi:', e);
        }
    }

    /**
     * Lưu hoặc cập nhật 1 ghi chú cục bộ trong IndexedDB.
     */
    async function saveLocalNote(note) {
        try {
            const db = await openDb();
            const tx = db.transaction('notes', 'readwrite');
            const store = tx.objectStore('notes');

            const existingReq = store.get(Number(note.id) || note.id);
            existingReq.onsuccess = function () {
                const merged = Object.assign(
                    {
                        is_pinned: 0,
                        is_locked: 0,
                        is_shared: false,
                        labels: [],
                        time_ago: 'Đã lưu ngoại tuyến',
                    },
                    existingReq.result || {},
                    note
                );
                store.put(merged);
            };

            return new Promise(function (res) { tx.oncomplete = res; });
        } catch (e) {
            console.warn('IndexedDB saveLocalNote lỗi:', e);
        }
    }

    /**
     * Xóa 1 ghi chú khỏi IndexedDB cục bộ.
     */
    async function removeLocalNote(noteId) {
        try {
            const db = await openDb();
            const tx = db.transaction('notes', 'readwrite');
            tx.objectStore('notes').delete(Number(noteId) || noteId);
            return new Promise(function (res) { tx.oncomplete = res; });
        } catch (e) {
            console.warn('IndexedDB removeLocalNote lỗi:', e);
        }
    }

    /**
     * Lấy toàn bộ ghi chú từ IndexedDB (có hỗ trợ lọc theo từ khóa và nhãn khi mất mạng).
     */
    async function getLocalNotes(keyword, labelId) {
        try {
            const db = await openDb();
            const tx = db.transaction('notes', 'readonly');
            const req = tx.objectStore('notes').getAll();

            const all = await new Promise(function (resolve) {
                req.onsuccess = function () { resolve(req.result || []); };
                req.onerror = function () { resolve([]); };
            });

            const q = (keyword || '').trim().toLowerCase();
            const lid = Number(labelId || 0);

            const filtered = all.filter(function (n) {
                if (lid > 0) {
                    const hasLabel = (n.labels || []).some(function (l) { return Number(l.id) === lid; });
                    if (!hasLabel) return false;
                }
                if (q !== '') {
                    const matchTitle = (n.title || '').toLowerCase().includes(q);
                    const matchContent = !n.is_locked && (n.content || '').toLowerCase().includes(q);
                    return matchTitle || matchContent;
                }
                return true;
            });

            filtered.sort(function (a, b) {
                if (Number(b.is_pinned || 0) !== Number(a.is_pinned || 0)) {
                    return Number(b.is_pinned || 0) - Number(a.is_pinned || 0);
                }
                return String(b.updated_at || '').localeCompare(String(a.updated_at || ''));
            });

            return filtered;
        } catch (e) {
            return [];
        }
    }

    /**
     * Lấy chi tiết 1 ghi chú từ IndexedDB khi mở /notes/edit/{id} lúc mất mạng.
     */
    async function getLocalNoteById(noteId) {
        try {
            const db = await openDb();
            const tx = db.transaction('notes', 'readonly');
            const store = tx.objectStore('notes');
            const key = isNaN(Number(noteId)) ? noteId : Number(noteId);
            const req = store.get(key);

            return await new Promise(function (resolve) {
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { resolve(null); };
            });
        } catch (e) {
            return null;
        }
    }

    /**
     * Đưa một thao tác ngoại tuyến (create, update, delete, toggle_pin) vào hàng đợi outbox.
     */
    async function queueOperation(operation) {
        try {
            const db = await openDb();
            const tx = db.transaction('outbox', 'readwrite');
            tx.objectStore('outbox').add(Object.assign({ queued_at: new Date().toISOString() }, operation));
            return new Promise(function (res) { tx.oncomplete = res; });
        } catch (e) {
            console.warn('IndexedDB queueOperation lỗi:', e);
        }
    }

    /**
     * Đồng bộ hai chiều:
     * 1. Đẩy các thao tác đang chờ trong outbox lên MySQL (/sync/push).
     * 2. Tải danh sách ghi chú mới nhất từ MySQL về IndexedDB (/sync/pull).
     */
    async function syncWithServer() {
        if (!navigator.onLine || isSyncing) {
            return;
        }
        isSyncing = true;

        try {
            const db = await openDb();
            const readTx = db.transaction('outbox', 'readonly');
            const pendingReq = readTx.objectStore('outbox').getAll();
            const pendingOps = await new Promise(function (resolve) {
                pendingReq.onsuccess = function () { resolve(pendingReq.result || []); };
                pendingReq.onerror = function () { resolve([]); };
            });

            if (pendingOps.length > 0) {
                const pushRes = await fetch('/sync/push', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: 'operations=' + encodeURIComponent(JSON.stringify(pendingOps)) +
                          '&csrf_token=' + encodeURIComponent(getCsrfToken()),
                });

                if (pushRes.ok) {
                    const pushData = await pushRes.json();
                    const clearTx = db.transaction('outbox', 'readwrite');
                    clearTx.objectStore('outbox').clear();
                    await new Promise(function (r) { clearTx.oncomplete = r; });

                    // Nếu đang mở một ghi chú tạo lúc offline (ID tạm local_...), cập nhật sang ID thật từ MySQL
                    if (pushData.id_map) {
                        const noteIdInput = document.getElementById('noteId');
                        if (noteIdInput && pushData.id_map[noteIdInput.value]) {
                            const realId = pushData.id_map[noteIdInput.value];
                            noteIdInput.value = realId;
                            window.history.replaceState(null, '', '/notes/edit/' + realId);
                        }
                    }

                    window.showToast?.('success', 'Đã đồng bộ dữ liệu ngoại tuyến lên máy chủ!');
                    document.dispatchEvent(new CustomEvent('noteapp:synced'));
                }
            }

            // Tải bản sao mới nhất từ MySQL về IndexedDB
            const pullRes = await fetch('/sync/pull', {
                headers: { 'Accept': 'application/json' },
            });
            if (pullRes.ok) {
                const pullData = await pullRes.json();
                if (Array.isArray(pullData.notes)) {
                    await replaceAllNotes(pullData.notes);
                }
            }
        } catch (e) {
            // Đang mất mạng hoặc chưa đăng nhập: giữ nguyên dữ liệu cục bộ
        } finally {
            isSyncing = false;
        }
    }

    // Tự động đồng bộ khi vừa tải trang (nếu có mạng) và ngay khi có mạng trở lại
    document.addEventListener('DOMContentLoaded', function () {
        if (navigator.onLine) {
            syncWithServer();
        }
    });

    window.addEventListener('online', function () {
        syncWithServer();
    });

    window.NoteAppOffline = {
        saveNote: saveLocalNote,
        removeNote: removeLocalNote,
        getNotes: getLocalNotes,
        getNoteById: getLocalNoteById,
        queueOperation: queueOperation,
        syncNow: syncWithServer,
    };
})();
