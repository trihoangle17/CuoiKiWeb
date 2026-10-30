/**
 * app.js
 * Xử lý tương tác chung trên toàn bộ ứng dụng:
 * - Đóng/mở menu trượt (Sidebar) trên màn hình di động & máy tính bảng.
 * - Chuyển đổi nhanh giao diện Sáng / Tối (lưu đồng bộ vào CSDL).
 * - Hiển thị huy hiệu cảnh báo khi mất mạng (Offline) và khi có mạng lại (Online).
 */

(function () {
    'use strict';

    function getCsrfToken() {
        if (window.csrfToken) return window.csrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // --- Điều khiển Sidebar trên màn hình nhỏ ---
    const btnToggleSidebar = document.getElementById('btnToggleSidebar');
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('show');
        overlay?.classList.remove('show');
        btnToggleSidebar?.setAttribute('aria-expanded', 'false');
    }

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('show');
        overlay?.classList.add('show');
        btnToggleSidebar?.setAttribute('aria-expanded', 'true');
    }

    btnToggleSidebar?.addEventListener('click', function () {
        if (sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay?.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar?.classList.contains('show')) {
            closeSidebar();
        }
    });

    // --- Chuyển đổi giao diện Sáng / Tối ---
    const btnThemeToggle = document.getElementById('btnThemeToggle');
    const themeIcon = document.getElementById('themeToggleIcon');

    function updateThemeIcon(theme) {
        if (!themeIcon) return;
        themeIcon.className = theme === 'dark' ? 'bi bi-sun-fill text-warning' : 'bi bi-moon-stars-fill';
    }

    btnThemeToggle?.addEventListener('click', function () {
        const html = document.documentElement;
        const current = html.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';

        html.setAttribute('data-bs-theme', next);
        updateThemeIcon(next);
        localStorage.setItem('noteapp_theme', next);

        fetch('/profile/update-theme', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'theme=' + encodeURIComponent(next) + '&csrf_token=' + encodeURIComponent(getCsrfToken()),
        }).catch(function () {
            // Đang ngoại tuyến: giữ trạng thái trên trình duyệt
        });
    });

    // --- Huy hiệu trạng thái mạng (Offline / Online) ---
    const offlineBadge = document.getElementById('offlineBadge');

    function updateNetworkBadge() {
        if (!offlineBadge) return;
        if (navigator.onLine) {
            offlineBadge.classList.add('d-none');
            offlineBadge.classList.remove('d-inline-flex');
        } else {
            offlineBadge.classList.remove('d-none');
            offlineBadge.classList.add('d-inline-flex');
        }
    }

    window.addEventListener('online', updateNetworkBadge);
    window.addEventListener('offline', function () {
        updateNetworkBadge();
        window.showToast?.('info', 'Mất kết nối mạng. Bạn vẫn có thể xem và ghi chú ngoại tuyến.');
    });

    updateNetworkBadge();
})();
