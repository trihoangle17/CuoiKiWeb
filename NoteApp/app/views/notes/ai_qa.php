<?php
/**
 * views/notes/ai_qa.php
 * Giao diện AI Hỏi – Đáp thông minh (RAG) trên kho ghi chú của người dùng.
 * Cho phép đặt câu hỏi bằng ngôn ngữ tự nhiên, truy xuất các ghi chú liên quan,
 * tổng hợp câu trả lời và hiển thị danh sách ghi chú tham chiếu bấm mở trực tiếp.
 */
$hasLlmKey = $hasLlmKey ?? false;
?>
<div class="mx-auto" style="max-width: 820px;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <i class="bi bi-stars text-primary me-2"></i>AI Hỏi – Đáp trên ghi chú (RAG)
            </h1>
            <p class="text-muted small mb-0">
                Đặt câu hỏi bằng ngôn ngữ tự nhiên để AI tra cứu, tổng hợp thông tin từ các ghi chú của bạn và trích dẫn nguồn gốc.
            </p>
        </div>
    </div>

    <!-- Gợi ý câu hỏi nhanh -->
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <span class="small text-muted">Gợi ý thử nhanh:</span>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill ai-sample-question">
            Lịch trình thực hiện đồ án và thời gian họp nhóm hàng tuần?
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill ai-sample-question">
            Hệ thống đã áp dụng những biện pháp bảo mật web nào?
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill ai-sample-question">
            Các dịch vụ container trong Docker Compose gồm những gì?
        </button>
    </div>

    <!-- Form đặt câu hỏi -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form id="aiQaForm">
                <?= csrfField() ?>
                <label for="aiQuestionInput" class="form-label fw-semibold">Câu hỏi của bạn</label>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <input type="text"
                           class="form-control"
                           id="aiQuestionInput"
                           name="question"
                           placeholder="Ví dụ: Nhóm họp vào mấy giờ thứ mấy và ở phòng nào?..."
                           required
                           autocomplete="off">
                    <button type="submit" class="btn btn-primary px-4 flex-shrink-0" id="btnAskAi">
                        <i class="bi bi-send-fill me-1"></i>Hỏi AI
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Trạng thái đang xử lý RAG -->
    <div id="aiQaLoading" class="card shadow-sm mb-4 d-none" role="status" aria-live="polite">
        <div class="card-body p-4 text-center">
            <div class="spinner-border text-primary mb-2" role="status"></div>
            <p class="small text-muted mb-0">Đang truy xuất các ghi chú liên quan và tổng hợp câu trả lời...</p>
        </div>
    </div>

    <!-- Hộp hiển thị lỗi nếu có -->
    <div id="aiQaError" class="alert alert-danger d-none" role="alert"></div>

    <!-- Kết quả trả lời & danh sách ghi chú tham chiếu -->
    <div id="aiQaResult" class="d-none">
        <div class="card shadow-sm border-primary-subtle mb-4">
            <div class="card-body p-3 p-md-4">
                <h2 class="h6 fw-bold text-primary mb-3">
                    <i class="bi bi-chat-square-quote-fill me-2"></i>Câu trả lời tổng hợp
                </h2>
                <div id="aiAnswerText" class="ai-answer-content mb-4"></div>

                <div id="aiReferencesSection" class="pt-3 border-top">
                    <h3 class="small fw-bold text-uppercase text-muted mb-2">
                        <i class="bi bi-journal-bookmark-fill me-1"></i>Ghi chú nguồn tham chiếu (bấm để mở ghi chú gốc)
                    </h3>
                    <div id="aiReferencesList" class="row g-2">
                        <!-- Danh sách thẻ tham chiếu bấm mở trực tiếp -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $extraScripts = ['/assets/js/ai-qa.js']; ?>
