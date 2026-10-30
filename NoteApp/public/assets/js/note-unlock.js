/**
 * note-unlock.js
 * Xử lý xác thực mật khẩu khóa của ghi chú trên màn hình unlock.php.
 */

(function () {
    'use strict';

    const form = document.getElementById('unlockForm');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const noteId = form.querySelector('[name="note_id"]').value;
        const password = form.querySelector('[name="password"]').value;
        const csrfToken = form.querySelector('[name="csrf_token"]').value;
        const errorEl = document.getElementById('unlockError');

        errorEl.classList.add('d-none');

        fetch('/notes/unlock/' + encodeURIComponent(noteId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'password=' + encodeURIComponent(password) + '&csrf_token=' + encodeURIComponent(csrfToken),
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
                errorEl.textContent = 'Lỗi kết nối mạng. Vui lòng thử lại.';
                errorEl.classList.remove('d-none');
            });
    });
})();
