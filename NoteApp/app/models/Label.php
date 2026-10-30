<?php
/**
 * Label.php
 * Model quan ly nhan ghi chu: liet ke, them, doi ten, xoa.
 * Xoa nhan khong xoa ghi chu (ON DELETE CASCADE chi xoa lien ket note_labels).
 */

declare(strict_types=1);

class Label
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Lay tat ca nhan cua mot user, sap xep theo ten.
     */
    public function getAllByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM labels WHERE user_id = :user_id ORDER BY name');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Tim nhan theo id, tra ve null neu khong ton tai.
     */
    public function findById(int $labelId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM labels WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $labelId]);
        $label = $stmt->fetch();
        return $label ?: null;
    }

    /**
     * Kiem tra user co phai chu so huu nhan khong.
     */
    public function isOwner(int $labelId, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM labels WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $labelId, 'user_id' => $userId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Kiem tra ten nhan da ton tai cho user nay chua (tranh trung ten).
     */
    public function existsByName(int $userId, string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM labels WHERE user_id = :user_id AND name = :name';
        $params = ['user_id' => $userId, 'name' => $name];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    /**
     * Tao nhan moi, tra ve id vua tao.
     */
    public function create(int $userId, string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO labels (user_id, name) VALUES (:user_id, :name)');
        $stmt->execute(['user_id' => $userId, 'name' => $name]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Doi ten nhan. Cac ghi chu dang gan nhan nay se tu dong hien ten moi
     * vi chi luu label_id trong bang note_labels, khong luu ten truc tiep.
     */
    public function rename(int $labelId, string $newName): void
    {
        $stmt = $this->db->prepare('UPDATE labels SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $newName, 'id' => $labelId]);
    }

    /**
     * Xoa nhan. Nho ON DELETE CASCADE tren note_labels, cac lien ket voi
     * ghi chu se tu dong bi xoa nhung ghi chu goc khong bi anh huong.
     */
    public function delete(int $labelId): void
    {
        $stmt = $this->db->prepare('DELETE FROM labels WHERE id = :id');
        $stmt->execute(['id' => $labelId]);
    }
}
