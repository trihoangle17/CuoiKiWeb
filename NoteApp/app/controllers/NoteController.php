<?php
/**
 * NoteController.php
 * Xử lý toàn bộ nghiệp vụ ghi chú: hiển thị lưới/danh sách, tìm kiếm live,
 * lọc theo nhãn, tạo & sửa chung một giao diện tự động lưu, ghim theo thời điểm,
 * khóa mật khẩu riêng từng ghi chú, đính kèm ảnh/video/file và chuẩn bị phiên cộng tác.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/Label.php';
require_once __DIR__ . '/../models/Attachment.php';
require_once __DIR__ . '/../models/Share.php';
require_once __DIR__ . '/../models/User.php';

class NoteController
{
    private Note $noteModel;
    private Label $labelModel;
    private Attachment $attachmentModel;
    private Share $shareModel;
    private User $userModel;

    public function __construct()
    {
        $this->noteModel = new Note();
        $this->labelModel = new Label();
        $this->attachmentModel = new Attachment();
        $this->shareModel = new Share();
        $this->userModel = new User();
    }

    /**
     * Trang danh sách ghi chú (mặc định dạng lưới, có nút chuyển sang danh sách).
     * Khi quay lại trang danh sách, khóa lại các ghi chú có mật khẩu để mọi thao tác
     * xem/sửa/xóa tiếp theo luôn yêu cầu nhập lại mật khẩu khóa.
     */
    public function index(?string $labelIdParam = null): void
    {
        $userId = currentUserId();
        unset($_SESSION['unlocked_notes']);

        $labelId = $labelIdParam !== null ? (int) $labelIdParam : null;
        $activeLabel = null;

        if ($labelId !== null) {
            if ($this->labelModel->isOwner($labelId, $userId)) {
                $activeLabel = $this->labelModel->findById($labelId);
            } else {
                $labelId = null;
            }
        }

        $notes = $this->noteModel->getAllByUser($userId, $labelId);
        $notes = $this->attachNoteMeta($notes);

        $labels = $this->labelModel->getAllByUser($userId);
        $currentUser = $this->userModel->findById($userId);
        $unseenShareCount = $this->shareModel->countUnseenShares($userId);

        $pageTitle = $activeLabel ? 'Nhãn: ' . $activeLabel['name'] : 'Ghi chú của tôi';
        $activeLabelId = $labelId;
        $viewFile = __DIR__ . '/../views/notes/index.php';
        require __DIR__ . '/../views/layouts/app.php';
    }

    /**
     * Lọc ghi chú theo nhãn: /notes/label/{id}
     */
    public function label(string $labelId): void
    {
        $this->index($labelId);
    }

    /**
     * Tìm kiếm live (AJAX, debounce 300ms) theo tiêu đề và nội dung,
     * đồng thời giữ nguyên bộ lọc nhãn nếu đang chọn nhãn.
     */
    public function search(): void
    {
        $userId = currentUserId();
        $keyword = trim($_GET['q'] ?? '');
        $labelId = isset($_GET['label_id']) && (int) $_GET['label_id'] > 0 ? (int) $_GET['label_id'] : null;

        if ($labelId !== null && !$this->labelModel->isOwner($labelId, $userId)) {
            $labelId = null;
        }

        $notes = $this->noteModel->getAllByUser($userId, $labelId, $keyword);
        $notes = $this->attachNoteMeta($notes);

        jsonResponse(['notes' => $notes]);
    }

    /**
     * Giao diện tạo ghi chú mới (dùng CHUNG view edit.php với chức năng sửa).
     */
    public function create(): void
    {
        $userId = currentUserId();

        $note = [
            'id' => null,
            'user_id' => $userId,
            'title' => '',
            'content' => '',
            'ai_summary' => null,
            'is_pinned' => 0,
            'is_locked' => 0,
        ];
        $isOwner = true;
        $canEdit = true;
        $shareInfo = null;
        $wsCollabToken = null;
        $noteLabels = [];
        $attachments = [];
        $labels = $this->labelModel->getAllByUser($userId);
        $currentUser = $this->userModel->findById($userId);
        $unseenShareCount = $this->shareModel->countUnseenShares($userId);

        $pageTitle = 'Ghi chú mới';
        $viewFile = __DIR__ . '/../views/notes/edit.php';
        require __DIR__ . '/../views/layouts/app.php';
    }

    /**
     * Giao diện xem / sửa ghi chú (dùng CHUNG view edit.php với tạo mới).
     * Kiểm tra chặt chẽ quyền sở hữu, quyền chia sẻ (view/edit) và khóa mật khẩu.
     */
    public function edit(string $id): void
    {
        $userId = currentUserId();
        $noteId = (int) $id;

        $note = $this->noteModel->findById($noteId);

        if (!$note || !$this->canAccessNote($noteId, $userId, 'view')) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }

        // Mọi thao tác xem/sửa trên ghi chú bị khóa đều phải xác thực mật khẩu khóa trước
        if ((int) $note['is_locked'] === 1 && !$this->isNoteUnlockedInSession($noteId)) {
            $this->renderUnlockForm($noteId);
            return;
        }

        $currentUser = $this->userModel->findById($userId);
        $isOwner = ((int) $note['user_id'] === $userId);
        $shareInfo = $isOwner ? null : $this->shareModel->getShareForRecipient($noteId, $userId);
        $canEdit = $isOwner || ($shareInfo !== null && $shareInfo['permission'] === 'edit');

        $noteLabels = $this->noteModel->getLabelIds($noteId);
        $noteLabelObjects = $this->noteModel->getLabelsForNote($noteId);
        $attachments = $this->attachmentModel->getByNoteId($noteId);
        $labels = $this->labelModel->getAllByUser($userId);
        $unseenShareCount = $this->shareModel->countUnseenShares($userId);

        // Cấp token WebSocket cho chủ sở hữu và người được chia sẻ quyền chỉnh sửa
        $wsCollabToken = null;
        if ($canEdit) {
            $wsCollabToken = createWsCollabToken($noteId, $userId, (string) $currentUser['display_name']);
        }

        $pageTitle = $canEdit ? 'Chỉnh sửa ghi chú' : 'Xem ghi chú được chia sẻ';
        $viewFile = __DIR__ . '/../views/notes/edit.php';
        require __DIR__ . '/../views/layouts/app.php';
    }

    /**
     * Tự động lưu ghi chú qua AJAX (dùng chung cho cả tạo mới và cập nhật).
     * Chỉ bắt buộc tiêu đề và nội dung, không có nút Save.
     */
    public function save(): void
    {
        requireCsrf();
        $userId = currentUserId();

        $noteId = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $labelIds = isset($_POST['labels']) && is_array($_POST['labels'])
            ? array_map('intval', $_POST['labels'])
            : [];

        // Chỉ bắt buộc 2 trường đầu vào: tiêu đề và nội dung
        if ($title === '' || $content === '') {
            jsonResponse(['error' => 'Vui lòng nhập đầy đủ tiêu đề và nội dung ghi chú.'], 422);
        }

        $isNew = false;

        if ($noteId > 0) {
            $existing = $this->noteModel->findById($noteId);
            if (!$existing || !$this->canAccessNote($noteId, $userId, 'edit')) {
                jsonResponse(['error' => 'Bạn không có quyền chỉnh sửa ghi chú này.'], 403);
            }
            if ((int) $existing['is_locked'] === 1 && !$this->isNoteUnlockedInSession($noteId)) {
                jsonResponse(['error' => 'Ghi chú đang khóa mật khẩu. Vui lòng mở khóa trước khi sửa.'], 403);
            }
            $this->noteModel->update($noteId, $title, $content);
        } else {
            $noteId = $this->noteModel->create($userId, $title, $content);
            $isNew = true;
        }

        // Chỉ chủ sở hữu quản lý nhãn cá nhân của ghi chú
        if ($this->noteModel->isOwner($noteId, $userId)) {
            $this->noteModel->syncLabels($noteId, $labelIds, $userId);
        }

        $currentUser = $this->userModel->findById($userId);
        $wsToken = $isNew
            ? createWsCollabToken($noteId, $userId, (string) ($currentUser['display_name'] ?? 'User'))
            : null;

        jsonResponse([
            'id' => $noteId,
            'is_new' => $isNew,
            'title' => $title,
            'content' => $content,
            'saved_at' => date('H:i:s'),
            'ws_token' => $wsToken,
        ]);
    }

    /**
     * Xóa ghi chú (xóa mềm). Nếu ghi chú đang bật khóa mật khẩu, bắt buộc
     * xác thực mật khẩu khóa của ghi chú trước khi xóa.
     */
    public function delete(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $noteId = (int) $id;

        if (!$this->noteModel->isOwner($noteId, $userId)) {
            jsonResponse(['error' => 'Bạn không có quyền xóa ghi chú này.'], 403);
        }

        $note = $this->noteModel->findById($noteId);
        if (!$note) {
            jsonResponse(['error' => 'Ghi chú không tồn tại.'], 404);
        }

        if ((int) $note['is_locked'] === 1) {
            $lockPassword = $_POST['lock_password'] ?? '';
            if ($lockPassword !== '') {
                if (!$this->noteModel->verifyLockPassword($noteId, $lockPassword)) {
                    jsonResponse(['error' => 'Mật khẩu khóa ghi chú không chính xác.'], 422);
                }
            } elseif (!$this->isNoteUnlockedInSession($noteId)) {
                jsonResponse([
                    'error' => 'Vui lòng nhập mật khẩu khóa của ghi chú để xác nhận xóa.',
                    'require_lock_password' => true,
                ], 403);
            }
        }

        $this->noteModel->softDelete($noteId);
        $this->clearNoteUnlockedInSession($noteId);

        jsonResponse(['success' => true]);
    }

    /**
     * Ghim hoặc bỏ ghim ghi chú (chỉ chủ sở hữu mới ghim trên danh sách của mình).
     * Nếu ghi chú đang bật khóa mật khẩu, bắt buộc xác thực mật khẩu khóa trước khi thao tác.
     */
    public function togglePin(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $noteId = (int) $id;

        if (!$this->noteModel->isOwner($noteId, $userId)) {
            jsonResponse(['error' => 'Không có quyền ghim ghi chú này.'], 403);
        }

        $note = $this->noteModel->findById($noteId);
        if (!$note) {
            jsonResponse(['error' => 'Không tìm thấy ghi chú.'], 404);
        }

        if ((int) $note['is_locked'] === 1) {
            $lockPassword = $_POST['lock_password'] ?? '';
            if ($lockPassword !== '') {
                if (!$this->noteModel->verifyLockPassword($noteId, $lockPassword)) {
                    jsonResponse(['error' => 'Mật khẩu khóa ghi chú không chính xác.'], 422);
                }
            } elseif (!$this->isNoteUnlockedInSession($noteId)) {
                jsonResponse([
                    'error' => 'Vui lòng nhập mật khẩu khóa của ghi chú để thực hiện thao tác.',
                    'require_lock_password' => true,
                ], 403);
            }
        }

        $newPinned = !((bool) $note['is_pinned']);
        $this->noteModel->togglePin($noteId, $newPinned);

        jsonResponse(['success' => true, 'is_pinned' => $newPinned]);
    }

    /**
     * Đặt khóa mật khẩu riêng cho ghi chú: bắt buộc nhập mật khẩu 2 lần khớp nhau.
     */
    public function setLock(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $noteId = (int) $id;

        if (!$this->noteModel->isOwner($noteId, $userId)) {
            jsonResponse(['error' => 'Chỉ chủ sở hữu mới có quyền đặt khóa mật khẩu.'], 403);
        }

        $note = $this->noteModel->findById($noteId);
        if (!$note) {
            jsonResponse(['error' => 'Ghi chú không tồn tại.'], 404);
        }
        if ((int) $note['is_locked'] === 1) {
            jsonResponse(['error' => 'Ghi chú này đã được khóa. Vui lòng chọn chức năng đổi mật khẩu khóa.'], 422);
        }

        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (strlen($password) < 4) {
            jsonResponse(['error' => 'Mật khẩu khóa phải có ít nhất 4 ký tự.'], 422);
        }
        if ($password !== $passwordConfirm) {
            jsonResponse(['error' => 'Mật khẩu nhập lại không khớp.'], 422);
        }

        $this->noteModel->setLock($noteId, password_hash($password, PASSWORD_BCRYPT));
        $this->markNoteUnlockedInSession($noteId);

        jsonResponse(['success' => true]);
    }

    /**
     * Đổi mật khẩu khóa của ghi chú: bắt buộc nhập mật khẩu hiện tại + mật khẩu mới 2 lần.
     */
    public function changeLockPassword(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $noteId = (int) $id;

        if (!$this->noteModel->isOwner($noteId, $userId)) {
            jsonResponse(['error' => 'Chỉ chủ sở hữu mới có quyền đổi mật khẩu khóa.'], 403);
        }

        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        if (!$this->noteModel->verifyLockPassword($noteId, $oldPassword)) {
            jsonResponse(['error' => 'Mật khẩu khóa hiện tại không chính xác.'], 422);
        }
        if (strlen($newPassword) < 4) {
            jsonResponse(['error' => 'Mật khẩu khóa mới phải có ít nhất 4 ký tự.'], 422);
        }
        if ($newPassword !== $newPasswordConfirm) {
            jsonResponse(['error' => 'Mật khẩu khóa mới nhập lại không khớp.'], 422);
        }

        $this->noteModel->setLock($noteId, password_hash($newPassword, PASSWORD_BCRYPT));
        $this->markNoteUnlockedInSession($noteId);

        jsonResponse(['success' => true]);
    }

    /**
     * Tắt khóa mật khẩu của ghi chú: bắt buộc nhập lại mật khẩu hiện tại để xác nhận.
     */
    public function removeLock(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $noteId = (int) $id;

        if (!$this->noteModel->isOwner($noteId, $userId)) {
            jsonResponse(['error' => 'Chỉ chủ sở hữu mới có quyền tắt khóa mật khẩu.'], 403);
        }

        $password = $_POST['password'] ?? '';

        if (!$this->noteModel->verifyLockPassword($noteId, $password)) {
            jsonResponse(['error' => 'Mật khẩu khóa hiện tại không chính xác.'], 422);
        }

        $this->noteModel->removeLock($noteId);
        $this->clearNoteUnlockedInSession($noteId);

        jsonResponse(['success' => true]);
    }

    /**
     * Xác thực mật khẩu khóa để mở xem/sửa ghi chú trong phiên làm việc hiện tại.
     */
    public function unlock(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $noteId = (int) $id;

        if (!$this->canAccessNote($noteId, $userId, 'view')) {
            jsonResponse(['error' => 'Không có quyền truy cập ghi chú này.'], 403);
        }

        $password = $_POST['password'] ?? '';

        if (!$this->noteModel->verifyLockPassword($noteId, $password)) {
            jsonResponse(['error' => 'Mật khẩu khóa không chính xác.'], 422);
        }

        $this->markNoteUnlockedInSession($noteId);
        jsonResponse(['success' => true, 'redirect' => '/notes/edit/' . $noteId]);
    }

    /**
     * Tải lên file đính kèm (ảnh, video, tài liệu) cho ghi chú.
     */
    public function uploadAttachment(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $noteId = (int) $id;

        $note = $this->noteModel->findById($noteId);
        if (!$note || !$this->canAccessNote($noteId, $userId, 'edit')) {
            jsonResponse(['error' => 'Không có quyền thêm tệp vào ghi chú này.'], 403);
        }
        if ((int) $note['is_locked'] === 1 && !$this->isNoteUnlockedInSession($noteId)) {
            jsonResponse(['error' => 'Vui lòng mở khóa ghi chú trước khi đính kèm tệp.'], 403);
        }

        if (empty($_FILES['file'])) {
            jsonResponse(['error' => 'Không nhận được tệp tải lên.'], 422);
        }

        $result = $this->attachmentModel->handleUpload($_FILES['file']);

        if (!$result) {
            jsonResponse([
                'error' => 'Tệp không hợp lệ (chỉ hỗ trợ ảnh, video MP4/WebM, PDF, Word, Excel, PowerPoint, TXT, ZIP tối đa 15MB).',
            ], 422);
        }

        $attachmentId = $this->attachmentModel->create(
            $noteId,
            $result['original_name'],
            $result['stored_name'],
            $result['mime_type'],
            $result['file_size']
        );

        jsonResponse([
            'id' => $attachmentId,
            'original_name' => $result['original_name'],
            'url' => UPLOAD_URL . '/' . $result['stored_name'],
            'mime_type' => $result['mime_type'],
            'file_size_formatted' => formatBytes($result['file_size']),
        ]);
    }

    /**
     * Xóa tệp đính kèm khỏi ghi chú.
     */
    public function deleteAttachment(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $attachmentId = (int) $id;

        $attachment = $this->attachmentModel->findById($attachmentId);
        if (!$attachment) {
            jsonResponse(['error' => 'Không tìm thấy tệp đính kèm.'], 404);
        }

        $noteId = (int) $attachment['note_id'];
        $note = $this->noteModel->findById($noteId);

        if (!$note || !$this->canAccessNote($noteId, $userId, 'edit')) {
            jsonResponse(['error' => 'Không có quyền xóa tệp đính kèm này.'], 403);
        }
        if ((int) $note['is_locked'] === 1 && !$this->isNoteUnlockedInSession($noteId)) {
            jsonResponse(['error' => 'Vui lòng mở khóa ghi chú trước khi xóa tệp.'], 403);
        }

        $filePath = UPLOAD_PATH . '/' . $attachment['stored_name'];
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $this->attachmentModel->delete($attachmentId);
        jsonResponse(['success' => true]);
    }

    /**
     * Hiển thị màn hình yêu cầu mật khẩu khi truy cập trực tiếp URL ghi chú bị khóa.
     */
    private function renderUnlockForm(int $noteId): void
    {
        $userId = currentUserId();
        $currentUser = $this->userModel->findById($userId);
        $labels = $this->labelModel->getAllByUser($userId);
        $unseenShareCount = $this->shareModel->countUnseenShares($userId);

        $pageTitle = 'Ghi chú được bảo vệ bằng mật khẩu';
        $viewFile = __DIR__ . '/../views/notes/unlock.php';
        require __DIR__ . '/../views/layouts/app.php';
    }

    /**
     * Kiểm tra quyền truy cập ghi chú (view hoặc edit).
     */
    private function canAccessNote(int $noteId, int $userId, string $permission): bool
    {
        if ($this->noteModel->isOwner($noteId, $userId)) {
            return true;
        }

        $share = $this->shareModel->getShareForRecipient($noteId, $userId);
        if (!$share) {
            return false;
        }

        if ($permission === 'view') {
            return true;
        }

        return $share['permission'] === 'edit';
    }

    private function markNoteUnlockedInSession(int $noteId): void
    {
        $_SESSION['unlocked_notes'][$noteId] = time();
    }

    private function clearNoteUnlockedInSession(int $noteId): void
    {
        unset($_SESSION['unlocked_notes'][$noteId]);
    }

    private function isNoteUnlockedInSession(int $noteId): bool
    {
        return isset($_SESSION['unlocked_notes'][$noteId]);
    }

    /**
     * Bổ sung nhãn, cờ chia sẻ, thời gian tương đối và che giấu tuyệt đối nội dung
     * của các ghi chú đang khóa mật khẩu trước khi trả ra danh sách/JSON.
     */
    private function attachNoteMeta(array $notes): array
    {
        foreach ($notes as &$note) {
            $nid = (int) $note['id'];
            $note['is_pinned'] = (int) $note['is_pinned'];
            $note['is_locked'] = (int) $note['is_locked'];
            $note['labels'] = $this->noteModel->getLabelsForNote($nid);
            $note['is_shared'] = $this->noteModel->isShared($nid);
            $note['time_ago'] = timeAgo($note['updated_at'] ?? '');

            // Bảo mật: không bao giờ gửi nội dung thật hoặc hash khóa ra danh sách khi ghi chú đang khóa
            unset($note['lock_password_hash']);
            if ($note['is_locked'] === 1) {
                $note['content'] = '';
            }
        }
        unset($note);
        return $notes;
    }
}
