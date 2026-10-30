<?php
/**
 * Share.php
 * Model quản lý chia sẻ ghi chú qua email: thêm/sửa/thu hồi quyền,
 * lấy danh sách người nhận, danh sách ghi chú được chia sẻ với tôi và thông báo mới.
 */

declare(strict_types=1);

class Share
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Chia sẻ ghi chú cho một user với quyền view (chỉ đọc) hoặc edit (chỉnh sửa).
     * Nếu đã chia sẻ trước đó thì cập nhật lại quyền và bật lại thông báo mới.
     */
    public function shareWithUser(int $noteId, int $sharedWithUserId, string $permission, int $sharedByUserId): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO note_shares (note_id, shared_with_user_id, permission, shared_by_user_id, is_seen)
                 VALUES (:note_id, :shared_with, :permission, :shared_by, 0)
                 ON DUPLICATE KEY UPDATE permission = :permission2, is_seen = 0'
            );
            $stmt->execute([
                'note_id' => $noteId,
                'shared_with' => $sharedWithUserId,
                'permission' => $permission,
                'shared_by' => $sharedByUserId,
                'permission2' => $permission,
            ]);
        } catch (PDOException $e) {
            // Tương thích ngược nếu bảng cũ chưa có cột is_seen
            $stmt = $this->db->prepare(
                'INSERT INTO note_shares (note_id, shared_with_user_id, permission, shared_by_user_id)
                 VALUES (:note_id, :shared_with, :permission, :shared_by)
                 ON DUPLICATE KEY UPDATE permission = :permission2'
            );
            $stmt->execute([
                'note_id' => $noteId,
                'shared_with' => $sharedWithUserId,
                'permission' => $permission,
                'shared_by' => $sharedByUserId,
                'permission2' => $permission,
            ]);
        }
    }

    /**
     * Lấy danh sách người nhận đã được chia sẻ của một ghi chú.
     */
    public function getSharesForNote(int $noteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ns.id, ns.permission, ns.created_at,
                    u.id AS user_id, u.email, u.display_name, u.avatar_path
             FROM note_shares ns
             INNER JOIN users u ON u.id = ns.shared_with_user_id
             WHERE ns.note_id = :note_id
             ORDER BY ns.created_at DESC'
        );
        $stmt->execute(['note_id' => $noteId]);
        return $stmt->fetchAll();
    }

    /**
     * Tìm bản ghi chia sẻ theo id.
     */
    public function findById(int $shareId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM note_shares WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $shareId]);
        $share = $stmt->fetch();
        return $share ?: null;
    }

    /**
     * Lấy thông tin quyền chia sẻ của một user cụ thể đối với một ghi chú.
     */
    public function getShareForRecipient(int $noteId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ns.*, u.display_name AS owner_name, u.email AS owner_email
             FROM note_shares ns
             INNER JOIN users u ON u.id = ns.shared_by_user_id
             WHERE ns.note_id = :note_id AND ns.shared_with_user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['note_id' => $noteId, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Cập nhật quyền (view / edit) cho bản ghi chia sẻ.
     */
    public function updatePermission(int $shareId, string $permission): void
    {
        $stmt = $this->db->prepare('UPDATE note_shares SET permission = :permission WHERE id = :id');
        $stmt->execute(['permission' => $permission, 'id' => $shareId]);
    }

    /**
     * Thu hồi (xóa) quyền chia sẻ của một người nhận.
     */
    public function revoke(int $shareId): void
    {
        $stmt = $this->db->prepare('DELETE FROM note_shares WHERE id = :id');
        $stmt->execute(['id' => $shareId]);
    }

    /**
     * Lấy danh sách ghi chú trong mục "Được chia sẻ với tôi" kèm người chia sẻ,
     * thời gian chia sẻ và quyền truy cập (view / edit).
     */
    public function getSharedWithMe(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT n.*, ns.id AS share_id, ns.permission, ns.created_at AS shared_at,
                    u.display_name AS shared_by_name, u.email AS shared_by_email, u.avatar_path AS shared_by_avatar
             FROM note_shares ns
             INNER JOIN notes n ON n.id = ns.note_id AND n.is_deleted = 0
             INNER JOIN users u ON u.id = ns.shared_by_user_id
             WHERE ns.shared_with_user_id = :user_id
             ORDER BY ns.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Đếm số lượng ghi chú mới được chia sẻ chưa xem để hiện banner thông báo nổi bật.
     */
    public function countUnseenShares(int $userId): int
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM note_shares ns
                 INNER JOIN notes n ON n.id = ns.note_id AND n.is_deleted = 0
                 WHERE ns.shared_with_user_id = :user_id AND ns.is_seen = 0'
            );
            $stmt->execute(['user_id' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Đánh dấu tất cả ghi chú được chia sẻ với user là đã xem thông báo.
     */
    public function markAllSeen(int $userId): void
    {
        try {
            $stmt = $this->db->prepare(
                'UPDATE note_shares SET is_seen = 1 WHERE shared_with_user_id = :user_id AND is_seen = 0'
            );
            $stmt->execute(['user_id' => $userId]);
        } catch (PDOException $e) {
            // Bỏ qua nếu bảng cũ chưa có cột is_seen
        }
    }
}
