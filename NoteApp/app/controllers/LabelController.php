<?php
/**
 * LabelController.php
 * Quản lý nhãn ghi chú qua AJAX: liệt kê, thêm mới, đổi tên, xóa.
 * Khi xóa nhãn thì ghi chú không bị xóa; khi đổi tên nhãn thì mọi ghi chú
 * gắn nhãn đó tự động hiển thị tên mới.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Label.php';

class LabelController
{
    private Label $labelModel;

    public function __construct()
    {
        $this->labelModel = new Label();
    }

    /**
     * Trả về danh sách nhãn của user hiện tại dưới dạng JSON.
     */
    public function index(): void
    {
        $labels = $this->labelModel->getAllByUser(currentUserId());
        jsonResponse(['labels' => $labels]);
    }

    /**
     * Thêm nhãn mới.
     */
    public function create(): void
    {
        requireCsrf();
        $userId = currentUserId();
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            jsonResponse(['error' => 'Vui lòng nhập tên nhãn.'], 422);
        }
        if (mb_strlen($name, 'UTF-8') > 50) {
            jsonResponse(['error' => 'Tên nhãn tối đa 50 ký tự.'], 422);
        }
        if ($this->labelModel->existsByName($userId, $name)) {
            jsonResponse(['error' => 'Nhãn này đã tồn tại.'], 422);
        }

        $labelId = $this->labelModel->create($userId, $name);
        jsonResponse(['id' => $labelId, 'name' => $name]);
    }

    /**
     * Đổi tên nhãn hiện có.
     */
    public function rename(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $labelId = (int) $id;
        $newName = trim($_POST['name'] ?? '');

        if (!$this->labelModel->isOwner($labelId, $userId)) {
            jsonResponse(['error' => 'Không có quyền thao tác trên nhãn này.'], 403);
        }
        if ($newName === '') {
            jsonResponse(['error' => 'Vui lòng nhập tên nhãn mới.'], 422);
        }
        if (mb_strlen($newName, 'UTF-8') > 50) {
            jsonResponse(['error' => 'Tên nhãn tối đa 50 ký tự.'], 422);
        }
        if ($this->labelModel->existsByName($userId, $newName, $labelId)) {
            jsonResponse(['error' => 'Tên nhãn này đã tồn tại.'], 422);
        }

        $this->labelModel->rename($labelId, $newName);
        jsonResponse(['success' => true, 'id' => $labelId, 'name' => $newName]);
    }

    /**
     * Xóa nhãn (chỉ xóa liên kết trong note_labels, không xóa ghi chú gốc).
     */
    public function delete(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $labelId = (int) $id;

        if (!$this->labelModel->isOwner($labelId, $userId)) {
            jsonResponse(['error' => 'Không có quyền xóa nhãn này.'], 403);
        }

        $this->labelModel->delete($labelId);
        jsonResponse(['success' => true]);
    }
}
