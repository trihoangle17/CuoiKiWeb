<?php
/**
 * ProfileController.php
 * Xử lý hồ sơ cá nhân (xem/sửa tên hiển thị + ảnh đại diện),
 * đổi mật khẩu an toàn và tùy chọn hiển thị của người dùng.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Label.php';

class ProfileController
{
    private User $userModel;
    private Label $labelModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->labelModel = new Label();
    }

    /**
     * Trang hồ sơ cá nhân: xem/sửa tên hiển thị, avatar và đổi mật khẩu.
     */
    public function index(): void
    {
        $userId = currentUserId();
        $currentUser = $this->userModel->findById($userId);
        $labels = $this->labelModel->getAllByUser($userId);
        $errors = [];

        if (requestMethod() === 'POST' && ($_POST['form'] ?? '') === 'profile') {
            requireCsrf();
            $errors = $this->handleUpdateProfile($userId, $currentUser);
            $currentUser = $this->userModel->findById($userId);
        } elseif (requestMethod() === 'POST' && ($_POST['form'] ?? '') === 'password') {
            requireCsrf();
            $errors = $this->handleChangePassword($userId, $currentUser);
        }

        $pageTitle = 'Hồ sơ cá nhân';
        $viewFile = __DIR__ . '/../views/profile/index.php';
        require __DIR__ . '/../views/layouts/app.php';
    }

    /**
     * Trang tùy chọn người dùng: cỡ chữ, màu ghi chú mặc định, giao diện sáng/tối.
     */
    public function settings(): void
    {
        $userId = currentUserId();
        $currentUser = $this->userModel->findById($userId);
        $labels = $this->labelModel->getAllByUser($userId);
        $errors = [];

        if (requestMethod() === 'POST') {
            requireCsrf();

            $fontSize = $_POST['font_size'] ?? 'medium';
            $noteColor = trim($_POST['note_color'] ?? '#ffffff');
            $theme = $_POST['theme'] ?? 'light';

            if (!in_array($fontSize, ['small', 'medium', 'large'], true)) {
                $fontSize = 'medium';
            }
            if (!in_array($theme, ['light', 'dark'], true)) {
                $theme = 'light';
            }
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $noteColor)) {
                $noteColor = '#ffffff';
            }

            $this->userModel->updatePreferences($userId, $fontSize, strtolower($noteColor), $theme);
            $currentUser = $this->userModel->findById($userId);
            setFlash('success', 'Đã lưu tùy chọn hiển thị của bạn.');
        }

        $pageTitle = 'Tùy chọn hiển thị';
        $viewFile = __DIR__ . '/../views/profile/settings.php';
        require __DIR__ . '/../views/layouts/app.php';
    }

    /**
     * Cập nhật nhanh chế độ Sáng / Tối từ nút chuyển đổi trên thanh điều hướng (AJAX).
     */
    public function updateTheme(): void
    {
        requireCsrf();
        $theme = $_POST['theme'] ?? 'light';

        if (!in_array($theme, ['light', 'dark'], true)) {
            jsonResponse(['error' => 'Chế độ giao diện không hợp lệ.'], 422);
        }

        $this->userModel->updateTheme(currentUserId(), $theme);
        jsonResponse(['success' => true, 'theme' => $theme]);
    }

    /**
     * Cập nhật tên hiển thị và ảnh đại diện (có kiểm tra định dạng thực tế & dung lượng).
     */
    private function handleUpdateProfile(int $userId, array $currentUser): array
    {
        $errors = [];
        $displayName = trim($_POST['display_name'] ?? '');

        if ($displayName === '') {
            $errors[] = 'Vui lòng nhập tên hiển thị.';
        } elseif (mb_strlen($displayName, 'UTF-8') > 100) {
            $errors[] = 'Tên hiển thị tối đa 100 ký tự.';
        }

        $avatarPath = null;

        if (!empty($_FILES['avatar']['name'])) {
            $avatarPath = $this->handleAvatarUpload($_FILES['avatar']);
            if ($avatarPath === null) {
                $errors[] = 'Ảnh đại diện không hợp lệ (chỉ chấp nhận JPG, PNG, WEBP tối đa 2MB).';
            }
        }

        if (empty($errors)) {
            // Xóa file avatar cũ trên đĩa nếu đổi sang avatar mới
            if ($avatarPath !== null && !empty($currentUser['avatar_path'])) {
                $oldFile = APP_ROOT . '/public/' . ltrim((string) $currentUser['avatar_path'], '/');
                if (is_file($oldFile) && str_contains($oldFile, 'avatar_')) {
                    @unlink($oldFile);
                }
            }
            $this->userModel->updateProfile($userId, $displayName, $avatarPath);
            setFlash('success', 'Đã cập nhật hồ sơ cá nhân thành công.');
        }

        return $errors;
    }

    /**
     * Kiểm tra MIME thực tế bằng finfo, kích thước <= 2MB và đổi tên file ngẫu nhiên.
     */
    private function handleAvatarUpload(array $file): ?string
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if ($file['size'] <= 0 || $file['size'] > AVATAR_MAX_SIZE) {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMimeType = (string) $finfo->file($file['tmp_name']);

        if (!in_array($realMimeType, AVATAR_ALLOWED_MIME, true)) {
            return null;
        }

        // Xác thực thêm đây thực sự là file hình ảnh hợp lệ
        if (@getimagesize($file['tmp_name']) === false) {
            return null;
        }

        $extensionMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $extension = $extensionMap[$realMimeType] ?? 'jpg';
        $storedName = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = UPLOAD_PATH . '/' . $storedName;

        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return null;
        }

        return UPLOAD_URL . '/' . $storedName;
    }

    /**
     * Đổi mật khẩu: yêu cầu mật khẩu hiện tại chính xác + mật khẩu mới nhập 2 lần.
     * Xử lý session an toàn bằng session_regenerate_id(true).
     */
    private function handleChangePassword(int $userId, array $currentUser): array
    {
        $errors = [];
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        if (!password_verify($currentPassword, $currentUser['password_hash'])) {
            $errors[] = 'Mật khẩu hiện tại không chính xác.';
        }
        if (strlen($newPassword) < 6) {
            $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        }
        if ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'Mật khẩu mới xác nhận không khớp.';
        }

        if (empty($errors)) {
            $this->userModel->updatePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT));

            // Tạo lại session ID mới để chống chiếm quyền phiên cũ, giữ trạng thái đăng nhập an toàn
            session_regenerate_id(true);
            setFlash('success', 'Đổi mật khẩu thành công! Phiên đăng nhập đã được làm mới an toàn.');
            redirect('/profile');
        }

        return $errors;
    }
}
