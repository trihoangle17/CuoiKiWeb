<?php
/**
 * AiController.php
 * Xử lý 2 tính năng AI bắt buộc:
 * 1. AI Tóm tắt một ghi chú bằng LLM (có thể tạo lại bất kỳ lúc nào).
 * 2. AI Hỏi - Đáp (RAG) trên các ghi chú của người dùng kèm danh sách ghi chú tham chiếu.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/Share.php';
require_once __DIR__ . '/../models/Label.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/LlmService.php';

class AiController
{
    private Note $noteModel;
    private Share $shareModel;
    private Label $labelModel;
    private User $userModel;
    private LlmService $llmService;

    public function __construct()
    {
        $this->noteModel = new Note();
        $this->shareModel = new Share();
        $this->labelModel = new Label();
        $this->userModel = new User();
        $this->llmService = new LlmService();
    }

    /**
     * Trang giao diện AI Hỏi - Đáp (RAG) trên kho ghi chú của người dùng.
     */
    public function index(): void
    {
        $userId = currentUserId();
        $currentUser = $this->userModel->findById($userId);
        $labels = $this->labelModel->getAllByUser($userId);
        $unseenShareCount = $this->shareModel->countUnseenShares($userId);
        $hasLlmKey = $this->llmService->hasValidApiKey();

        $pageTitle = 'AI Hỏi – Đáp trên ghi chú';
        $viewFile = __DIR__ . '/../views/notes/ai_qa.php';
        require __DIR__ . '/../views/layouts/app.php';
    }

    /**
     * Tóm tắt một ghi chú cụ thể bằng LLM (AJAX POST /ai/summarize/{id}).
     * Hỗ trợ tham số regenerate=1 để yêu cầu tạo lại bản tóm tắt mới.
     */
    public function summarize(string $id): void
    {
        requireCsrf();
        $userId = currentUserId();
        $noteId = (int) $id;

        $note = $this->noteModel->findById($noteId);
        if (!$note) {
            jsonResponse(['error' => 'Không tìm thấy ghi chú.'], 404);
        }

        // Kiểm tra quyền xem (chủ sở hữu hoặc được chia sẻ)
        $isOwner = $this->noteModel->isOwner($noteId, $userId);
        $share = $isOwner ? null : $this->shareModel->getShareForRecipient($noteId, $userId);
        if (!$isOwner && !$share) {
            jsonResponse(['error' => 'Bạn không có quyền truy cập ghi chú này.'], 403);
        }

        if ((int) $note['is_locked'] === 1 && !isset($_SESSION['unlocked_notes'][$noteId])) {
            jsonResponse(['error' => 'Vui lòng mở khóa ghi chú trước khi sử dụng AI tóm tắt.'], 403);
        }

        $regenerate = ($_POST['regenerate'] ?? '0') === '1';

        // Nếu đã có bản tóm tắt lưu sẵn và không phải yêu cầu "Tạo lại" thì trả về nhanh
        if (!$regenerate && !empty($note['ai_summary'])) {
            jsonResponse([
                'summary' => $note['ai_summary'],
                'cached' => true,
            ]);
        }

        // Ưu tiên nội dung mới nhất đang gõ trên trình soạn thảo nếu được gửi kèm
        $title = isset($_POST['title']) ? trim((string) $_POST['title']) : (string) $note['title'];
        $content = isset($_POST['content']) ? trim((string) $_POST['content']) : (string) $note['content'];

        $result = $this->llmService->summarize($title, $content, $regenerate);
        if (isset($result['error'])) {
            jsonResponse(['error' => $result['error']], 422);
        }

        $this->noteModel->saveAiSummary($noteId, $result['summary']);

        jsonResponse([
            'summary' => $result['summary'],
            'provider' => $result['provider'] ?? 'llm',
            'cached' => false,
        ]);
    }

    /**
     * Hỏi - Đáp ngôn ngữ tự nhiên trên tập ghi chú của người dùng (AJAX POST /ai/ask).
     * Trả về câu trả lời tổng hợp kèm danh sách ghi chú tham chiếu để bấm mở trực tiếp.
     */
    public function ask(): void
    {
        requireCsrf();
        $userId = currentUserId();
        $question = trim($_POST['question'] ?? '');

        if ($question === '') {
            jsonResponse(['error' => 'Vui lòng nhập câu hỏi của bạn.'], 422);
        }

        $unlockedIds = isset($_SESSION['unlocked_notes']) && is_array($_SESSION['unlocked_notes'])
            ? array_map('intval', array_keys($_SESSION['unlocked_notes']))
            : [];

        $accessibleNotes = $this->noteModel->getAccessibleNotesForRag($userId, $unlockedIds);
        $result = $this->llmService->answerQuestion($question, $accessibleNotes);

        if (isset($result['error'])) {
            jsonResponse(['error' => $result['error']], 422);
        }

        jsonResponse([
            'answer' => $result['answer'],
            'references' => $result['references'] ?? [],
            'provider' => $result['provider'] ?? 'llm',
        ]);
    }
}
