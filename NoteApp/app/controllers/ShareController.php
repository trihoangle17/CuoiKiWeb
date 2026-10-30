<?php
/**
 * ShareController.php
 * Xử lý chia sẻ ghi chú qua email: xác thực email thuộc người dùng đã đăng ký,
 * hỗ trợ chọn nhiều người nhận cùng lúc, phân quyền chỉ-đọc (view) hoặc chỉnh-sửa (edit),
 * gửi thông báo email + thông báo trong ứng dụng, cho phép chủ ghi chú sửa quyền hoặc thu hồi bất kỳ lúc nào.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/Share.php';
require_once __DIR__ . '/../models/Label.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/Mailer.php';

class ShareController
{
    private Note $noteModel;
    private Share $shareModel;
    private Label $labelModel;
    private User $userModel;

    public function __construct()
    {
        $this->noteModel = new Note();
        $this->shareModel = new Share();
        $this->labelModel = new Label();
        $this->userModel = new User();
    }

    /**
     * Chuyển hướng mặc định /shares về /shares/with-me.
     */
    public function index(): void
    {
        redirect('/shares/with-me');
    }

    /**
     * Mục "Được chia sẻ với tôi": hiển thị danh sách ghi chú người khác chia sẻ,
     * kèm thông tin người chia sẻ, thời điểm chia sẻ và quyền (chỉ-đọc / chỉnh-sửa).
     */
    public function withMe(): void
    {
        $userId = currentUserId();
        // Reset mở khóa ghi chú khi ra danh sách để ghi chú khóa luôn hỏi lại mật khẩu
        unset($_SESSION['unlocked_notes']);

        // Đánh dấu đã xem thông báo chia sẻ mới
        $this->shareModel->markAllSeen($userId);

        $sharedNotes = $this->shareModel->getSharedWithMe($userId);
        foreach ($sharedNotes as &$sn) {
            $sn['is_locked'] = (int) $sn['is_locked'];
            $sn['is_pinned'] = (int) $sn['is_pinned'];
            unset($sn['lock_password_hash']);
            if ($sn['is_locked'] === 1) {
                $sn['content'] = '';
            }
        }
        unset($sn);

        $currentUser = $this->userModel->findById($userId);
        $labels = $this->labelModel->getAllByUser($userId);
        $unseenShareCount = 0;

        $pageTitle = 'Được chia sẻ với tôi';
        $viewFile = __DIR__ . '/../views/shared/with_me.php';
        require __DIR__ . '/../views/layouts/app.php';
    }

    /**
     * Màn hình quản lý chia sẻ của một ghi chú dành cho chủ sở hữu:
     * xem danh sách người nhận + quyền, thêm nhiều người nhận mới, đổi quyền hoặc thu hồi.
     */
    public function create(string $noteId): void
    {
        $userId = currentUserId();
        $nid = (int) $noteId;

        $note = $this->noteModel->findById($nid);
        if (!$note || !$this->noteModel->isOwner($nid, $userId)) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }

        // Nếu ghi chú đang khóa mật khẩu và chưa mở khóa trong phiên thì yêu cầu mở khóa trước
        if ((int) $note['is_locked'] === 1 && !isset($_SESSION['unlocked_notes'][$nid])) {
            redirect('/notes/edit/' . $nid);
        }

        $shares = $this->shareModel->getSharesForNote($nid);
        $currentUser = $this->userModel->findById($userId);
        $labels = $this->labelModel->getAllByUser($userId);
        $unseenShareCount = $this->shareModel->countUnseenShares($userId);

        $pageTitle = 'Chia sẻ ghi chú';
        $viewFile = __DIR__ . '/../views/shared/manage.php';
        require __DIR__ . '/../views/layouts/app.php';
    }

    /**
     * API kiểm tra nhanh một địa chỉ email có thuộc tài khoản đã đăng ký hay không (AJAX).
     */
    public function checkEmail(): void
    {
        $email = trim($_GET['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['valid' => false, 'message' => 'Định dạng email không hợp lệ.']);
        }

        $owner = $this->userModel->findById(currentUserId());
        if ($owner && strcasecmp($email, $owner['email']) === 0) {
            jsonResponse(['valid' => false, 'message' => 'Không thể chia sẻ ghi chú cho chính bạn.']);
        }

        $recipient = $this->userModel->findByEmail($email);
        if (!$recipient) {
            jsonResponse(['valid' => false, 'message' => 'Email này chưa đăng ký tài khoản trên hệ thống.']);
        }

        jsonResponse([
            'valid' => true,
            'email' => $recipient['email'],
            'display_name' => $recipient['display_name'],
        ]);
    }

    /**
     * Xử lý gửi chia sẻ cho một hoặc nhiều người nhận qua email (AJAX).
     */
    public function send(string $noteId): void
    {
        requireCsrf();
        $userId = currentUserId();
        $nid = (int) $noteId;

        if (!$this->noteModel->isOwner($nid, $userId)) {
            jsonResponse(['error' => 'Bạn không có quyền chia sẻ ghi chú này.'], 403);
        }

        $emailsRaw = trim($_POST['emails'] ?? '');
        $permission = $_POST['permission'] ?? 'view';

        if (!in_array($permission, ['view', 'edit'], true)) {
            jsonResponse(['error' => 'Quyền chia sẻ không hợp lệ.'], 422);
        }
        if ($emailsRaw === '') {
            jsonResponse(['error' => 'Vui lòng nhập ít nhất một địa chỉ email người nhận.'], 422);
        }

        // Hỗ trợ phân tách nhiều email bằng dấu phẩy, chấm phẩy hoặc xuống dòng
        $rawList = preg_split('/[\s,;]+/', $emailsRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $emails = array_values(array_unique(array_map('trim', $rawList)));

        $note = $this->noteModel->findById($nid);
        $owner = $this->userModel->findById($userId);

        $success = [];
        $failed = [];

        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed[] = $email . ' (định dạng email không hợp lệ)';
                continue;
            }

            if (strcasecmp($email, (string) $owner['email']) === 0) {
                $failed[] = $email . ' (không thể chia sẻ cho chính bạn)';
                continue;
            }

            $recipient = $this->userModel->findByEmail($email);
            if (!$recipient) {
                $failed[] = $email . ' (chưa đăng ký tài khoản NoteApp)';
                continue;
            }

            $this->shareModel->shareWithUser($nid, (int) $recipient['id'], $permission, $userId);
            $this->sendShareNotificationEmail($recipient, $owner, $note, $permission);
            $success[] = [
                'email' => $recipient['email'],
                'display_name' => $recipient['display_name'],
                'permission' => $permission,
            ];
        }

        $updatedShares = $this->shareModel->getSharesForNote($nid);

        jsonResponse([
            'success' => $success,
            'failed' => $failed,
            'shares' => $updatedShares,
        ]);
    }

    /**
     * Thay đổi quyền truy cập (view / edit) của một người nhận bất kỳ lúc nào (AJAX).
     */
    public function updatePermission(string $shareId): void
    {
        requireCsrf();
        $userId = currentUserId();
        $sid = (int) $shareId;

        $share = $this->shareModel->findById($sid);
        if (!$share || !$this->noteModel->isOwner((int) $share['note_id'], $userId)) {
            jsonResponse(['error' => 'Không có quyền thay đổi cài đặt chia sẻ này.'], 403);
        }

        $permission = $_POST['permission'] ?? 'view';
        if (!in_array($permission, ['view', 'edit'], true)) {
            jsonResponse(['error' => 'Quyền chia sẻ không hợp lệ.'], 422);
        }

        $this->shareModel->updatePermission($sid, $permission);
        jsonResponse(['success' => true, 'permission' => $permission]);
    }

    /**
     * Thu hồi hoàn toàn quyền truy cập của một người nhận (AJAX).
     */
    public function revoke(string $shareId): void
    {
        requireCsrf();
        $userId = currentUserId();
        $sid = (int) $shareId;

        $share = $this->shareModel->findById($sid);
        if (!$share || !$this->noteModel->isOwner((int) $share['note_id'], $userId)) {
            jsonResponse(['error' => 'Không có quyền thu hồi chia sẻ này.'], 403);
        }

        $this->shareModel->revoke($sid);
        jsonResponse(['success' => true]);
    }

    /**
     * Gửi email thông báo cho người nhận khi được chia sẻ ghi chú.
     */
    private function sendShareNotificationEmail(array $recipient, array $owner, array $note, string $permission): void
    {
        $permissionText = $permission === 'edit' ? 'Chỉnh sửa (cộng tác thời gian thực)' : 'Chỉ đọc';
        $noteUrl = baseUrl() . '/notes/edit/' . (int) $note['id'];

        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#222;">'
            . '<h2>Thông báo chia sẻ ghi chú mới</h2>'
            . '<p>Xin chào <strong>' . e($recipient['display_name']) . '</strong>,</p>'
            . '<p><strong>' . e($owner['display_name']) . '</strong> (' . e($owner['email']) . ') vừa chia sẻ một ghi chú với bạn trên NoteApp:</p>'
            . '<blockquote style="border-left:4px solid #4a6cf7;padding-left:12px;margin:12px 0;">'
            . '<strong>' . e($note['title'] !== '' ? $note['title'] : '(Không có tiêu đề)') . '</strong><br>'
            . 'Quyền truy cập: <strong>' . $permissionText . '</strong>'
            . '</blockquote>'
            . '<p><a href="' . e($noteUrl) . '" style="display:inline-block;padding:10px 20px;background:#4a6cf7;color:#fff;text-decoration:none;border-radius:6px;">Mở ghi chú ngay</a></p>'
            . '</div>';

        $mailer = new Mailer();
        $mailer->send($recipient['email'], $recipient['display_name'], 'Bạn được chia sẻ một ghi chú trên NoteApp', $html);
    }
}
