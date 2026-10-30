/**
 * ai-qa.js
 * Xử lý giao diện AI Hỏi – Đáp (RAG) trên các ghi chú của người dùng (views/notes/ai_qa.php).
 * Gửi câu hỏi ngôn ngữ tự nhiên tới /ai/ask, hiển thị câu trả lời tổng hợp và
 * danh sách thẻ ghi chú tham chiếu có thể bấm mở trực tiếp ghi chú gốc.
 */

(function () {
    'use strict';

    const form = document.getElementById('aiQaForm');
    if (!form) {
        return;
    }

    const questionInput = document.getElementById('aiQuestionInput');
    const btnAsk = document.getElementById('btnAskAi');
    const loadingBox = document.getElementById('aiQaLoading');
    const errorBox = document.getElementById('aiQaError');
    const resultBox = document.getElementById('aiQaResult');
    const answerText = document.getElementById('aiAnswerText');
    const refsSection = document.getElementById('aiReferencesSection');
    const refsList = document.getElementById('aiReferencesList');

    function csrf() {
        if (window.csrfToken) return window.csrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // Bấm vào câu hỏi mẫu để thử nhanh
    document.querySelectorAll('.ai-sample-question').forEach(function (chip) {
        chip.addEventListener('click', function () {
            questionInput.value = this.textContent.trim();
            form.requestSubmit();
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const question = questionInput.value.trim();
        if (!question) return;

        btnAsk.disabled = true;
        errorBox.classList.add('d-none');
        resultBox.classList.add('d-none');
        loadingBox.classList.remove('d-none');

        fetch('/ai/ask', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: 'question=' + encodeURIComponent(question) + '&csrf_token=' + encodeURIComponent(csrf()),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                btnAsk.disabled = false;
                loadingBox.classList.add('d-none');

                if (data.error) {
                    errorBox.textContent = data.error;
                    errorBox.classList.remove('d-none');
                    return;
                }

                answerText.textContent = data.answer || '';
                const references = data.references || [];

                if (references.length === 0) {
                    refsSection.classList.add('d-none');
                    refsList.innerHTML = '';
                } else {
                    refsSection.classList.remove('d-none');
                    refsList.innerHTML = references.map(function (ref) {
                        return (
                            '<div class="col-12 col-md-6">' +
                            '<a href="' + escapeHtml(ref.url) + '" class="ai-ref-card">' +
                            '<div class="d-flex align-items-center justify-content-between gap-2 mb-1">' +
                            '<span class="fw-semibold text-primary text-truncate"><i class="bi bi-file-earmark-text me-1"></i>' + escapeHtml(ref.title) + '</span>' +
                            '<i class="bi bi-box-arrow-up-right small text-muted flex-shrink-0"></i>' +
                            '</div>' +
                            '<p class="small text-muted mb-1 text-truncate">' + escapeHtml(ref.snippet) + '</p>' +
                            '<div class="text-secondary" style="font-size: 0.72rem;">Cập nhật: ' + escapeHtml(ref.updated_at) + '</div>' +
                            '</a>' +
                            '</div>'
                        );
                    }).join('');
                }

                resultBox.classList.remove('d-none');
            })
            .catch(function () {
                btnAsk.disabled = false;
                loadingBox.classList.add('d-none');
                errorBox.textContent = 'Không thể kết nối tới dịch vụ AI Hỏi – Đáp. Vui lòng kiểm tra kết nối mạng.';
                errorBox.classList.remove('d-none');
            });
    });
})();
