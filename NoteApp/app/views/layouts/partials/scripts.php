    <!-- Bootstrap 5.3 JS Bundle -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

    <!-- Các module JavaScript dùng chung của NoteApp -->
    <script src="/assets/js/toast.js"></script>
    <script src="/assets/js/offline.js"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/labels.js"></script>

    <?php if (!empty($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?= e($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        // Đăng ký Service Worker phục vụ PWA Offline
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function (err) {
                    console.warn('Không thể đăng ký Service Worker:', err);
                });
            });
        }
    </script>
