/**
 * sw.js
 * Service Worker cho NoteApp (PWA Offline):
 * - Cài đặt bộ nhớ đệm (App Shell) cho các tài nguyên tĩnh và trang giao diện chính.
 * - Chiến lược Network-First (ưu tiên mạng, tự động rơi về Cache khi mất mạng) cho các trang HTML.
 * - Chiến lược Stale-While-Revalidate cho CSS, JS, Icon và CDN giúp tải tức thì cả khi Offline.
 */

const CACHE_NAME = 'noteapp-pwa-v1';

const APP_SHELL = [
    '/notes',
    '/notes/create',
    '/manifest.json',
    '/assets/css/style.css',
    '/assets/js/toast.js',
    '/assets/js/offline.js',
    '/assets/js/app.js',
    '/assets/js/labels.js',
    '/assets/js/notes.js',
    '/assets/js/note-edit.js',
    '/assets/js/collab.js',
    '/assets/images/default-avatar.svg',
    '/assets/images/favicon.svg',
    '/assets/images/icon-192.svg',
    '/assets/images/icon-512.svg'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return Promise.allSettled(
                APP_SHELL.map(function (url) {
                    return cache.add(url);
                })
            );
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) {
                    return key !== CACHE_NAME;
                }).map(function (key) {
                    return caches.delete(key);
                })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    const request = event.request;

    // Chỉ can thiệp các request GET
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Không cache các API trả dữ liệu động (đã được IndexedDB trong offline.js đảm nhiệm)
    if (
        url.pathname.startsWith('/sync') ||
        url.pathname.startsWith('/notes/search') ||
        url.pathname.startsWith('/labels') ||
        url.pathname.startsWith('/ai/') ||
        url.pathname.startsWith('/shares/check-email') ||
        url.pathname.startsWith('/logout')
    ) {
        return;
    }

    // Yêu cầu điều hướng trang HTML (Navigation): Network-First, fallback về Cache khi mất mạng
    if (request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then(function (networkResponse) {
                    if (networkResponse && networkResponse.status === 200) {
                        const clone = networkResponse.clone();
                        caches.open(CACHE_NAME).then(function (cache) {
                            cache.put(request, clone);
                        });
                    }
                    return networkResponse;
                })
                .catch(function () {
                    return caches.match(request).then(function (cached) {
                        if (cached) {
                            return cached;
                        }
                        // Nếu người dùng bấm mở /notes/edit/{id} khi đang offline mà trang đó chưa từng cache riêng,
                        // trả về khung giao diện chung /notes/create để offline.js nạp nội dung từ IndexedDB!
                        if (url.pathname.startsWith('/notes/edit/')) {
                            return caches.match('/notes/create');
                        }
                        return caches.match('/notes');
                    });
                })
        );
        return;
    }

    // Tài nguyên tĩnh (CSS, JS, Hình ảnh, Bootstrap CDN): Cache-First kèm cập nhật ngầm
    event.respondWith(
        caches.match(request).then(function (cached) {
            const fetchPromise = fetch(request)
                .then(function (networkResponse) {
                    if (networkResponse && (networkResponse.status === 200 || networkResponse.type === 'opaque')) {
                        const clone = networkResponse.clone();
                        caches.open(CACHE_NAME).then(function (cache) {
                            cache.put(request, clone);
                        });
                    }
                    return networkResponse;
                })
                .catch(function () {
                    return cached;
                });

            return cached || fetchPromise;
        })
    );
});
