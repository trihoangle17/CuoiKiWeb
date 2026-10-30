<?php
/**
 * Note.php
 * Model quản lý ghi chú: tạo, sửa, xóa (mềm), ghim, khóa mật khẩu riêng,
 * tìm kiếm, lọc theo nhãn, truy xuất cho AI RAG và đồng bộ PWA Offline.
 * Tất cả truy vấn đều dùng PDO Prepared Statements.
 */

declare(strict_types=1);

class Note
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Lấy danh sách ghi chú của user, sắp xếp:
     * 1. Ghi chú đã ghim lên đầu, xếp theo thời điểm ghim (mới ghim lên trước)
     * 2. Các ghi chú còn lại xếp theo thời gian cập nhật mới nhất lên đầu.
     */
    public function getAllByUser(int $userId, ?int $labelId = null, string $search = ''): array
    {
        $sql = 'SELECT DISTINCT n.* FROM notes n';
        $params = ['user_id' => $userId];

        if ($labelId !== null && $labelId > 0) {
            $sql .= ' INNER JOIN note_labels nl ON nl.note_id = n.id AND nl.label_id = :label_id';
            $params['label_id'] = $labelId;
        }

        $sql .= ' WHERE n.user_id = :user_id AND n.is_deleted = 0';

        if ($search !== '') {
            $sql .= ' AND (n.title LIKE :search_title OR (n.is_locked = 0 AND n.content LIKE :search_content))';
            $params['search_title'] = '%' . $search . '%';
            $params['search_content'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY n.is_pinned DESC, n.pinned_at DESC, n.updated_at DESC, n.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Lấy thông tin một ghi chú theo id (chưa bị xóa mềm).
     */
    public function findById(int $noteId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM notes WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $stmt->execute(['id' => $noteId]);
        $note = $stmt->fetch();
        return $note ?: null;
    }

    /**
     * Kiểm tra user có phải chủ sở hữu của ghi chú không.
     */
    public function isOwner(int $noteId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM notes WHERE id = :id AND user_id = :user_id AND is_deleted = 0 LIMIT 1'
        );
        $stmt->execute(['id' => $noteId, 'user_id' => $userId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Tạo ghi chú mới, trả về id vừa tạo.
     */
    public function create(int $userId, string $title, string $content): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notes (user_id, title, content) VALUES (:user_id, :title, :content)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Cập nhật tiêu đề và nội dung ghi chú (phục vụ tự động lưu và đồng bộ).
     */
    public function update(int $noteId, string $title, string $content): void
    {
        $stmt = $this->db->prepare(
            'UPDATE notes SET title = :title, content = :content WHERE id = :id AND is_deleted = 0'
        );
        $stmt->execute([
            'title' => $title,
            'content' => $content,
            'id' => $noteId,
        ]);
    }

    /**
     * Lưu bản tóm tắt AI cho ghi chú (bỏ qua êm nếu cột ai_summary chưa có trong DB cũ).
     */
    public function saveAiSummary(int $noteId, string $summary): void
    {
        try {
            $stmt = $this->db->prepare('UPDATE notes SET ai_summary = :summary WHERE id = :id');
            $stmt->execute(['summary' => $summary, 'id' => $noteId]);
        } catch (PDOException $e) {
            // Bỏ qua nếu CSDL cũ chưa có cột ai_summary
        }
    }

    /**
     * Xóa mềm ghi chú (đánh dấu is_deleted = 1).
     */
    public function softDelete(int $noteId): void
    {
        $stmt = $this->db->prepare('UPDATE notes SET is_deleted = 1 WHERE id = :id');
        $stmt->execute(['id' => $noteId]);
    }

    /**
     * Ghim hoặc bỏ ghim ghi chú. Khi ghim cập nhật pinned_at = NOW()
     * để nhiều ghi chú ghim được xếp theo đúng thời điểm ghim.
     */
    public function togglePin(int $noteId, bool $pin): void
    {
        if ($pin) {
            $stmt = $this->db->prepare(
                'UPDATE notes SET is_pinned = 1, pinned_at = NOW() WHERE id = :id'
            );
        } else {
            $stmt = $this->db->prepare(
                'UPDATE notes SET is_pinned = 0, pinned_at = NULL WHERE id = :id'
            );
        }
        $stmt->execute(['id' => $noteId]);
    }

    /**
     * Đặt khóa mật khẩu riêng cho ghi chú.
     */
    public function setLock(int $noteId, string $passwordHash): void
    {
        $stmt = $this->db->prepare(
            'UPDATE notes SET is_locked = 1, lock_password_hash = :hash WHERE id = :id'
        );
        $stmt->execute(['hash' => $passwordHash, 'id' => $noteId]);
    }

    /**
     * Tắt khóa mật khẩu riêng của ghi chú.
     */
    public function removeLock(int $noteId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE notes SET is_locked = 0, lock_password_hash = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $noteId]);
    }

    /**
     * Xác thực mật khẩu khóa của ghi chú.
     */
    public function verifyLockPassword(int $noteId, string $password): bool
    {
        $note = $this->findById($noteId);
        if (!$note || empty($note['lock_password_hash'])) {
            return false;
        }
        return password_verify($password, $note['lock_password_hash']);
    }

    /**
     * Lấy danh sách id nhãn đang gắn cho một ghi chú.
     */
    public function getLabelIds(int $noteId): array
    {
        $stmt = $this->db->prepare('SELECT label_id FROM note_labels WHERE note_id = :note_id');
        $stmt->execute(['note_id' => $noteId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'label_id'));
    }

    /**
     * Đồng bộ danh sách nhãn gắn vào ghi chú (chỉ gán các nhãn thuộc sở hữu của user).
     */
    public function syncLabels(int $noteId, array $labelIds, ?int $ownerUserId = null): void
    {
        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare('DELETE FROM note_labels WHERE note_id = :note_id');
            $delete->execute(['note_id' => $noteId]);

            if (!empty($labelIds)) {
                if ($ownerUserId !== null) {
                    $checkStmt = $this->db->prepare(
                        'SELECT id FROM labels WHERE id = :id AND user_id = :user_id LIMIT 1'
                    );
                }
                $insert = $this->db->prepare(
                    'INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (:note_id, :label_id)'
                );
                foreach (array_unique($labelIds) as $labelId) {
                    $lid = (int) $labelId;
                    if ($lid <= 0) {
                        continue;
                    }
                    if ($ownerUserId !== null) {
                        $checkStmt->execute(['id' => $lid, 'user_id' => $ownerUserId]);
                        if (!$checkStmt->fetch()) {
                            continue;
                        }
                    }
                    $insert->execute(['note_id' => $noteId, 'label_id' => $lid]);
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Lấy danh sách nhãn (id, name) của một ghi chú để hiển thị ngoài grid/list.
     */
    public function getLabelsForNote(int $noteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.id, l.name FROM labels l
             INNER JOIN note_labels nl ON nl.label_id = l.id
             WHERE nl.note_id = :note_id
             ORDER BY l.name'
        );
        $stmt->execute(['note_id' => $noteId]);
        return $stmt->fetchAll();
    }

    /**
     * Kiểm tra ghi chú có đang được chia sẻ với ít nhất 1 người nhận hay không.
     */
    public function isShared(int $noteId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM note_shares WHERE note_id = :note_id LIMIT 1');
        $stmt->execute(['note_id' => $noteId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Lấy danh sách ghi chú của user (kèm ghi chú được chia sẻ) phục vụ AI Hỏi-Đáp (RAG).
     * Bỏ qua nội dung ghi chú đang khóa mật khẩu (trừ khi đã mở khóa trong phiên hiện tại)
     * nhằm đảm bảo quyền riêng tư của ghi chú khóa mật khẩu.
     */
    public function getAccessibleNotesForRag(int $userId, array $unlockedNoteIds = []): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT n.id, n.user_id, n.title, n.content, n.is_locked, n.updated_at
             FROM notes n
             LEFT JOIN note_shares ns ON ns.note_id = n.id AND ns.shared_with_user_id = :uid_share
             WHERE n.is_deleted = 0
               AND (n.user_id = :uid_owner OR ns.id IS NOT NULL)
             ORDER BY n.updated_at DESC'
        );
        $stmt->execute([
            'uid_share' => $userId,
            'uid_owner' => $userId,
        ]);

        $rows = $stmt->fetchAll();
        $result = [];

        foreach ($rows as $row) {
            $nid = (int) $row['id'];
            if ((int) $row['is_locked'] === 1 && !in_array($nid, $unlockedNoteIds, true)) {
                continue;
            }
            $row['labels'] = array_column($this->getLabelsForNote($nid), 'name');
            $result[] = $row;
        }

        return $result;
    }
}
