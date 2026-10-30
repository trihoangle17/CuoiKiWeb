<?php
/**
 * Attachment.php
 * Model quản lý file đính kèm (ảnh, video, tài liệu) của ghi chú:
 * kiểm tra MIME thực tế bằng finfo, giới hạn dung lượng, đổi tên ngẫu nhiên an toàn.
 */

declare(strict_types=1);

class Attachment
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Lấy danh sách file đính kèm của một ghi chú.
     */
    public function getByNoteId(int $noteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM attachments WHERE note_id = :note_id ORDER BY created_at ASC');
        $stmt->execute(['note_id' => $noteId]);
        return $stmt->fetchAll();
    }

    /**
     * Tìm thông tin file đính kèm theo id.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM attachments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $attachment = $stmt->fetch();
        return $attachment ?: null;
    }

    /**
     * Lưu thông tin file đính kèm vào cơ sở dữ liệu.
     */
    public function create(int $noteId, string $originalName, string $storedName, string $mimeType, int $fileSize): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO attachments (note_id, original_name, stored_name, mime_type, file_size)
             VALUES (:note_id, :original_name, :stored_name, :mime_type, :file_size)'
        );
        $stmt->execute([
            'note_id' => $noteId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Xóa bản ghi file đính kèm khỏi cơ sở dữ liệu.
     */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Xử lý tải lên một file: kiểm tra lỗi upload, kích thước, MIME thực tế (finfo)
     * và đổi sang tên ngẫu nhiên để chống thực thi mã độc hoặc ghi đè file.
     */
    public function handleUpload(array $file): ?array
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        if ($file['size'] <= 0 || $file['size'] > UPLOAD_MAX_SIZE) {
            return null;
        }

        // Xác thực MIME type thực tế từ nội dung binary của file
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMimeType = (string) $finfo->file($file['tmp_name']);

        if (!in_array($realMimeType, UPLOAD_ALLOWED_MIME, true)) {
            return null;
        }

        $extension = $this->getSafeExtension((string) $file['name'], $realMimeType);
        $storedName = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
        $destination = UPLOAD_PATH . '/' . $storedName;

        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return null;
        }

        // Làm sạch tên file gốc chỉ để hiển thị
        $cleanOriginal = preg_replace('/[\x00-\x1F\x7F]/u', '', basename((string) $file['name']));
        if ($cleanOriginal === '' || $cleanOriginal === null) {
            $cleanOriginal = 'attachment.' . ($extension ?: 'bin');
        }

        return [
            'original_name' => mb_substr($cleanOriginal, 0, 200),
            'stored_name' => $storedName,
            'mime_type' => $realMimeType,
            'file_size' => (int) $file['size'],
        ];
    }

    /**
     * Lấy đuôi mở rộng an toàn khớp danh sách cho phép, tuyệt đối không cho phép .php/.phtml...
     */
    private function getSafeExtension(string $fileName, string $mimeType): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $extension = (string) preg_replace('/[^a-z0-9]/', '', $extension);

        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'mp4', 'webm', 'ogg',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'txt', 'csv',
        ];

        if (in_array($extension, $allowedExtensions, true)) {
            return $extension;
        }

        $mimeFallback = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
        ];

        return $mimeFallback[$mimeType] ?? '';
    }
}
