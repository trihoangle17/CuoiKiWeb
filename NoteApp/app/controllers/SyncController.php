<?php
/**
 * SyncController.php
 * Phục vụ PWA Offline (IndexedDB + Service Worker):
 * - GET /sync/pull  : Tải danh sách ghi chú & nhãn về lưu cục bộ trong IndexedDB.
 * - POST /sync/push : Đồng bộ các thao tác tạo/sửa/xóa/ghim thực hiện lúc mất mạng lên MySQL
 *                     ngay khi có kết nối Internet trở lại.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/Label.php';

class SyncController
{
    private Note $noteModel;
    private Label $labelModel;

    public function __construct()
    {
        $this->noteModel = new Note();
        $this->labelModel = new Label();
    }

    /**
     * Alias mặc định /sync -> pull().
     */
    public function index(): void
    {
        $this->pull();
    }

    /**
     * Trả về toàn bộ ghi chú và nhãn của người dùng để lưu vào IndexedDB.
     * Ghi chú bị khóa mật khẩu được ẩn nội dung để bảo mật dữ liệu cục bộ.
     */
    public function pull(): void
    {
        $userId = currentUserId();
        $notes = $this->noteModel->getAllByUser($userId);

        foreach ($notes as &$note) {
            $nid = (int) $note['id'];
            $note['id'] = $nid;
            $note['is_pinned'] = (int) $note['is_pinned'];
            $note['is_locked'] = (int) $note['is_locked'];
            $note['labels'] = $this->noteModel->getLabelsForNote($nid);
            $note['is_shared'] = $this->noteModel->isShared($nid);
            $note['time_ago'] = timeAgo($note['updated_at'] ?? '');
            unset($note['lock_password_hash']);

            if ($note['is_locked'] === 1) {
                $note['content'] = '';
            }
        }
        unset($note);

        $labels = $this->labelModel->getAllByUser($userId);

        jsonResponse([
            'notes' => $notes,
            'labels' => $labels,
            'synced_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Nhận hàng đợi thay đổi (outbox) từ IndexedDB khi trình duyệt có mạng trở lại
     * và cập nhật vào cơ sở dữ liệu MySQL.
     */
    public function push(): void
    {
        requireCsrf();
        $userId = currentUserId();

        $rawPayload = $_POST['operations'] ?? '[]';
        $operations = is_string($rawPayload) ? json_decode($rawPayload, true) : $rawPayload;

        if (!is_array($operations)) {
            jsonResponse(['error' => 'Dữ liệu đồng bộ không hợp lệ.'], 422);
        }

        $idMap = []; // Ánh xạ temp_id (âm hoặc chuỗi local_...) sang ID thực trên MySQL
        $applied = 0;

        foreach ($operations as $op) {
            if (!is_array($op) || empty($op['action'])) {
                continue;
            }

            $action = (string) $op['action'];
            $rawId = $op['note_id'] ?? 0;

            // Nếu ghi chú vừa được tạo mới trong cùng đợt đồng bộ, tra ID thật từ $idMap
            if (is_string($rawId) && isset($idMap[$rawId])) {
                $noteId = $idMap[$rawId];
            } else {
                $noteId = (int) $rawId;
            }

            if ($action === 'create') {
                $title = trim((string) ($op['title'] ?? ''));
                $content = trim((string) ($op['content'] ?? ''));
                if ($title !== '' && $content !== '') {
                    $newId = $this->noteModel->create($userId, $title, $content);
                    if (!empty($op['labels']) && is_array($op['labels'])) {
                        $this->noteModel->syncLabels($newId, array_map('intval', $op['labels']), $userId);
                    }
                    if (!empty($op['temp_id'])) {
                        $idMap[(string) $op['temp_id']] = $newId;
                    }
                    $applied++;
                }
            } elseif ($action === 'update' && $noteId > 0) {
                $existing = $this->noteModel->findById($noteId);
                if ($existing && $this->noteModel->isOwner($noteId, $userId) && (int) $existing['is_locked'] === 0) {
                    $title = trim((string) ($op['title'] ?? ''));
                    $content = trim((string) ($op['content'] ?? ''));
                    if ($title !== '' && $content !== '') {
                        $this->noteModel->update($noteId, $title, $content);
                        if (isset($op['labels']) && is_array($op['labels'])) {
                            $this->noteModel->syncLabels($noteId, array_map('intval', $op['labels']), $userId);
                        }
                        $applied++;
                    }
                }
            } elseif ($action === 'toggle_pin' && $noteId > 0) {
                if ($this->noteModel->isOwner($noteId, $userId)) {
                    $this->noteModel->togglePin($noteId, !empty($op['is_pinned']));
                    $applied++;
                }
            } elseif ($action === 'delete' && $noteId > 0) {
                $existing = $this->noteModel->findById($noteId);
                if ($existing && $this->noteModel->isOwner($noteId, $userId) && (int) $existing['is_locked'] === 0) {
                    $this->noteModel->softDelete($noteId);
                    $applied++;
                }
            }
        }

        jsonResponse([
            'success' => true,
            'applied' => $applied,
            'id_map' => $idMap,
            'synced_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
