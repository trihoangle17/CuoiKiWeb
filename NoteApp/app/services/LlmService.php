<?php
/**
 * LlmService.php
 * Dịch vụ tích hợp Large Language Model (LLM) phục vụ 2 tính năng AI bắt buộc:
 * 1. AI Tóm tắt ghi chú (AI Summary) - có hỗ trợ tạo lại (regenerate).
 * 2. AI Hỏi - Đáp trên các ghi chú của người dùng theo kiến trúc RAG
 *    (Retrieval-Augmented Generation): truy xuất ghi chú liên quan theo ngữ nghĩa
 *    & điểm tương đồng từ vựng (BM25 + mở rộng từ đồng nghĩa/không dấu), tổng hợp
 *    câu trả lời tự nhiên và trả về danh sách ghi chú tham chiếu gốc.
 *
 * Hỗ trợ mọi API tương thích chuẩn OpenAI Chat Completions (OpenAI, Groq, Gemini OpenAI endpoint,
 * OpenRouter) cũng như Google Gemini Native generateContent API qua biến môi trường .env.
 */

declare(strict_types=1);

class LlmService
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = trim(LLM_API_KEY);
        $this->apiUrl = trim(LLM_API_URL);
        $this->model = trim(LLM_MODEL) !== '' ? trim(LLM_MODEL) : 'gpt-4o-mini';
    }

    /**
     * Kiểm tra xem người dùng đã cấu hình API key thực tế trong .env chưa.
     */
    public function hasValidApiKey(): bool
    {
        return $this->apiKey !== ''
            && $this->apiKey !== 'your_llm_api_key_here'
            && $this->apiUrl !== '';
    }

    /**
     * Tạo bản tóm tắt ngắn gọn, súc tích cho một ghi chú.
     * Khi $regenerate = true, tăng nhẹ temperature và yêu cầu diễn đạt góc nhìn mới.
     */
    public function summarize(string $title, string $content, bool $regenerate = false): array
    {
        $cleanContent = trim($content);
        if ($cleanContent === '' && trim($title) === '') {
            return ['error' => 'Ghi chú chưa có nội dung để tóm tắt.'];
        }

        if ($this->hasValidApiKey()) {
            $systemPrompt = 'Bạn là trợ lý AI thông minh chuyên tóm tắt ghi chú. '
                . 'Hãy tóm tắt ngắn gọn, rõ ràng, giữ trọn vẹn ý chính và thông tin quan trọng bằng tiếng Việt '
                . '(hoặc cùng ngôn ngữ với ghi chú). Trình bày mạch lạc dưới dạng các ý chính dễ đọc.';

            $userPrompt = "Tiêu đề ghi chú: {$title}\n\nNội dung ghi chú:\n{$cleanContent}\n\n"
                . ($regenerate
                    ? 'Hãy tạo lại một bản tóm tắt mới cô đọng, diễn đạt sắc nét và trực diện hơn.'
                    : 'Hãy tóm tắt các ý chính quan trọng nhất của ghi chú trên.');

            $temperature = $regenerate ? 0.65 : 0.3;
            $llmReply = $this->callChatCompletion($systemPrompt, $userPrompt, $temperature);

            if ($llmReply !== null) {
                return [
                    'summary' => $llmReply,
                    'provider' => 'llm',
                ];
            }
        }

        // Cơ chế dự phòng thông minh khi chưa cấu hình LLM_API_KEY trong .env hoặc mất kết nối tới API ngoài
        $fallbackSummary = $this->extractiveSummarize($title, $cleanContent, $regenerate);
        return [
            'summary' => $fallbackSummary,
            'provider' => 'local_fallback',
        ];
    }

    /**
     * Hỏi - Đáp thông minh (RAG) trên tập ghi chú của người dùng:
     * Bước 1 (Retrieval): Xếp hạng độ liên quan ngữ nghĩa/từ vựng (kết hợp có dấu, không dấu,
     *         cụm từ 2 âm tiết và mở rộng từ khóa chủ đề) để lấy Top ghi chú ứng viên.
     * Bước 2 (Augmented Generation): Gửi ngữ cảnh ghi chú ứng viên cho LLM tổng hợp câu trả lời
     *         và xác định chính xác ID các ghi chú thực sự được dùng để trả lời.
     */
    public function answerQuestion(string $question, array $notes): array
    {
        $question = trim($question);
        if ($question === '') {
            return ['error' => 'Vui lòng nhập câu hỏi của bạn.'];
        }

        if (empty($notes)) {
            return [
                'answer' => 'Bạn hiện chưa có ghi chú nào (hoặc các ghi chú đều đang khóa mật khẩu) để AI tra cứu thông tin.',
                'references' => [],
            ];
        }

        // Bước 1: Xếp hạng và lọc các ghi chú ứng viên liên quan nhất
        $scoredNotes = $this->retrieveRelevantNotes($question, $notes, 10);

        // Nếu tổng số ghi chú ít (<= 12), đưa toàn bộ vào ngữ cảnh LLM để LLM tự hiểu ngữ nghĩa sâu
        // ngay cả khi câu hỏi dùng từ đồng nghĩa hoàn toàn khác từ khóa gốc!
        $candidates = count($notes) <= 12 ? $notes : array_column($scoredNotes, 'note');
        if (empty($candidates)) {
            $candidates = array_slice($notes, 0, 8);
        }

        // Bước 2: Gọi LLM tổng hợp câu trả lời kèm trích dẫn ID ghi chú
        if ($this->hasValidApiKey()) {
            $contextBlocks = [];
            $noteMap = [];
            foreach ($candidates as $n) {
                $nid = (int) $n['id'];
                $noteMap[$nid] = $n;
                $labelStr = !empty($n['labels']) ? ' | Nhãn: ' . implode(', ', $n['labels']) : '';
                $snippet = mb_substr(trim((string) $n['content']), 0, 1500);
                $contextBlocks[] = "[NOTE_ID={$nid}] Tiêu đề: {$n['title']}{$labelStr}\nNội dung:\n{$snippet}";
            }

            $systemPrompt = "Bạn là trợ lý AI Hỏi-Đáp (RAG) trên kho ghi chú cá nhân của người dùng.\n"
                . "NHIỆM VỤ:\n"
                . "1. Đọc kỹ danh sách ghi chú được cung cấp và hiểu ngữ nghĩa câu hỏi (kể cả từ đồng nghĩa, diễn đạt gián tiếp).\n"
                . "2. Tổng hợp câu trả lời chính xác, tự nhiên, rõ ràng dựa trên nội dung các ghi chú liên quan.\n"
                . "3. Nếu thông tin không có trong bất kỳ ghi chú nào, hãy nói rõ là không tìm thấy thông tin đó trong các ghi chú hiện có.\n"
                . "4. BẮT BUỘC trả về đúng định dạng JSON hợp lệ (không bọc markdown nếu không cần) theo cấu trúc:\n"
                . '{"answer": "Câu trả lời tổng hợp chi tiết...", "referenced_ids": [1, 2]}';

            $userPrompt = "DANH SÁCH GHI CHÚ CỦA NGƯỜI DÙNG:\n\n"
                . implode("\n\n---\n\n", $contextBlocks)
                . "\n\n===\nCÂU HỎI CỦA NGƯỜI DÙNG: " . $question;

            $rawReply = $this->callChatCompletion($systemPrompt, $userPrompt, 0.2);

            if ($rawReply !== null) {
                $parsed = $this->parseRagJsonReply($rawReply);
                if ($parsed !== null) {
                    $refs = [];
                    foreach ($parsed['referenced_ids'] as $rid) {
                        $rid = (int) $rid;
                        if (isset($noteMap[$rid])) {
                            $refs[] = $this->formatReference($noteMap[$rid]);
                        }
                    }
                    // Nếu LLM quên điền mảng referenced_ids nhưng có điểm truy xuất cao thì bổ sung tham chiếu đầu
                    if (empty($refs) && !empty($scoredNotes) && $scoredNotes[0]['score'] > 1.5) {
                        $refs[] = $this->formatReference($scoredNotes[0]['note']);
                    }

                    return [
                        'answer' => $parsed['answer'],
                        'references' => $refs,
                        'provider' => 'llm',
                    ];
                }

                // Trường hợp LLM trả về văn bản thuần thay vì JSON
                $fallbackRefs = [];
                foreach (array_slice($scoredNotes, 0, 3) as $item) {
                    if ($item['score'] > 0) {
                        $fallbackRefs[] = $this->formatReference($item['note']);
                    }
                }
                return [
                    'answer' => $rawReply,
                    'references' => $fallbackRefs,
                    'provider' => 'llm',
                ];
            }
        }

        // Cơ chế tổng hợp RAG cục bộ khi chưa điền LLM_API_KEY trong .env
        return $this->localSynthesizeAnswer($question, $scoredNotes);
    }

    /**
     * Gọi API LLM (hỗ trợ cả chuẩn OpenAI Chat Completions và Google Gemini Native URL).
     */
    private function callChatCompletion(string $systemPrompt, string $userPrompt, float $temperature = 0.3): ?string
    {
        if (!function_exists('curl_init')) {
            error_log('LlmService: PHP chưa bật tiện ích curl.');
            return null;
        }

        // Hỗ trợ tự động nếu URL là Google Gemini Native (:generateContent)
        $isGeminiNative = str_contains($this->apiUrl, ':generateContent');

        if ($isGeminiNative) {
            $url = $this->apiUrl . (str_contains($this->apiUrl, '?') ? '&' : '?') . 'key=' . urlencode($this->apiKey);
            $payload = [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
                ],
                'generationConfig' => [
                    'temperature' => $temperature,
                ],
            ];
            $headers = ['Content-Type: application/json'];
        } else {
            $url = $this->apiUrl;
            $payload = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => $temperature,
            ];
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            error_log("LlmService HTTP {$httpCode} lỗi: {$curlErr} | {$response}");
            return null;
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return null;
        }

        if ($isGeminiNative) {
            $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
            return is_string($text) ? trim($text) : null;
        }

        $text = $decoded['choices'][0]['message']['content'] ?? null;
        return is_string($text) ? trim($text) : null;
    }

    /**
     * Tách JSON kết quả từ câu trả lời của LLM (xử lý cả khi LLM bọc trong ```json ... ```).
     */
    private function parseRagJsonReply(string $raw): ?array
    {
        $cleaned = trim($raw);
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/su', $cleaned, $m)) {
            $cleaned = $m[1];
        } elseif (preg_match('/(\{.*\})/su', $cleaned, $m)) {
            $cleaned = $m[1];
        }

        $data = json_decode($cleaned, true);
        if (!is_array($data) || !isset($data['answer'])) {
            return null;
        }

        $ids = isset($data['referenced_ids']) && is_array($data['referenced_ids'])
            ? array_values(array_unique(array_map('intval', $data['referenced_ids'])))
            : [];

        return [
            'answer' => trim((string) $data['answer']),
            'referenced_ids' => $ids,
        ];
    }

    /**
     * Truy xuất ghi chú liên quan theo điểm kết hợp:
     * - Từ khóa gốc + không dấu tiếng Việt
     * - Mở rộng nhóm từ đồng nghĩa / chủ đề phổ biến
     * - Điểm thưởng cho cụm từ liền kề (bigram), tiêu đề và nhãn.
     */
    private function retrieveRelevantNotes(string $question, array $notes, int $limit = 5): array
    {
        $expandedQuery = $this->expandSemanticSynonyms($question);
        $tokens = $this->tokenize($expandedQuery);
        $unaccentedTokens = array_unique(array_map([$this, 'stripVietnameseAccents'], $tokens));

        $results = [];
        foreach ($notes as $note) {
            $titleLower = mb_strtolower((string) $note['title'], 'UTF-8');
            $contentLower = mb_strtolower((string) $note['content'], 'UTF-8');
            $labelsLower = mb_strtolower(implode(' ', $note['labels'] ?? []), 'UTF-8');

            $titleUnaccent = $this->stripVietnameseAccents($titleLower);
            $contentUnaccent = $this->stripVietnameseAccents($contentLower);
            $labelsUnaccent = $this->stripVietnameseAccents($labelsLower);

            $score = 0.0;

            foreach ($tokens as $tok) {
                if (mb_strlen($tok, 'UTF-8') < 2) {
                    continue;
                }
                if (str_contains($titleLower, $tok)) {
                    $score += 3.5;
                }
                if (str_contains($labelsLower, $tok)) {
                    $score += 2.5;
                }
                $occ = substr_count($contentLower, $tok);
                if ($occ > 0) {
                    $score += min(4.0, 1.5 + log($occ + 1));
                }
            }

            foreach ($unaccentedTokens as $utok) {
                if (strlen($utok) < 3) {
                    continue;
                }
                if (str_contains($titleUnaccent, $utok)) {
                    $score += 1.5;
                }
                if (str_contains($labelsUnaccent, $utok)) {
                    $score += 1.0;
                }
                if (str_contains($contentUnaccent, $utok)) {
                    $score += 0.8;
                }
            }

            // Điểm thưởng cho cụm 2 từ liền nhau trong câu hỏi gốc
            $rawWords = $this->tokenize($question);
            for ($i = 0; $i < count($rawWords) - 1; $i++) {
                $bigram = $rawWords[$i] . ' ' . $rawWords[$i + 1];
                if (str_contains($titleLower, $bigram)) {
                    $score += 4.0;
                }
                if (str_contains($contentLower, $bigram)) {
                    $score += 2.5;
                }
            }

            if ($score > 0) {
                $results[] = ['note' => $note, 'score' => $score];
            }
        }

        usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Mở rộng từ đồng nghĩa chủ đề tiếng Việt & tiếng Anh để hỗ trợ tìm theo ý nghĩa.
     */
    private function expandSemanticSynonyms(string $query): string
    {
        $lower = mb_strtolower($query, 'UTF-8');
        $synonymGroups = [
            ['lịch họp', 'họp nhóm', 'thời gian họp', 'cuộc họp', 'phòng họp', 'meeting', 'thứ sáu', '14h'],
            ['bảo mật', 'an toàn', 'mật khẩu', 'xss', 'sql injection', 'csrf', 'bcrypt', 'security'],
            ['lộ trình', 'kế hoạch', 'tiến độ', 'lịch trình', 'tuần', 'deadline', 'đồ án', 'project'],
            ['triển khai', 'cài đặt', 'docker', 'container', 'server', 'mysql', 'websocket', 'port', 'cổng'],
            ['giao diện', 'thiết kế', 'màu sắc', 'sáng tối', 'dark mode', 'responsive', 'ui', 'ux', 'cỡ chữ'],
            ['chia sẻ', 'cộng tác', 'quyền sửa', 'chỉ đọc', 'email'],
        ];

        $extra = [];
        foreach ($synonymGroups as $group) {
            foreach ($group as $term) {
                if (str_contains($lower, $term)) {
                    $extra = array_merge($extra, $group);
                    break;
                }
            }
        }

        return $query . ' ' . implode(' ', array_unique($extra));
    }

    /**
     * Tách từ và loại bỏ hư từ tiếng Việt / tiếng Anh phổ biến.
     */
    private function tokenize(string $text): array
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $clean = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $lower);
        $parts = preg_split('/\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stopWords = [
            'là', 'và', 'của', 'có', 'những', 'các', 'một', 'trong', 'cho', 'với', 'được',
            'khi', 'nào', 'gì', 'ở', 'đâu', 'như', 'thế', 'này', 'đó', 'tôi', 'bạn', 'ghi',
            'chú', 'hãy', 'cho', 'biết', 'về', 'theo', 'từ', 'đến', 'the', 'is', 'in', 'at', 'of', 'and',
        ];

        return array_values(array_filter(
            $parts,
            static fn(string $w): bool => !in_array($w, $stopWords, true) && mb_strlen($w, 'UTF-8') >= 2
        ));
    }

    /**
     * Chuyển chuỗi tiếng Việt có dấu sang không dấu để so khớp linh hoạt.
     */
    private function stripVietnameseAccents(string $str): string
    {
        $map = [
            '/[àáạảãâầấậẩẫăằắặẳẵ]/u' => 'a',
            '/[èéẹẻẽêềếệểễ]/u' => 'e',
            '/[ìíịỉĩ]/u' => 'i',
            '/[òóọỏõôồốộổỗơờớợởỡ]/u' => 'o',
            '/[ùúụủũưừứựửữ]/u' => 'u',
            '/[ỳýỵỷỹ]/u' => 'y',
            '/[đ]/u' => 'd',
        ];
        return (string) preg_replace(array_keys($map), array_values($map), mb_strtolower($str, 'UTF-8'));
    }

    /**
     * Tóm tắt trích xuất cục bộ (dự phòng khi chưa điền LLM_API_KEY trong .env).
     */
    private function extractiveSummarize(string $title, string $content, bool $regenerate): string
    {
        $lines = preg_split('/(?:\r?\n|(?<=[.!?])\s+)/u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $l): bool => $l !== ''));

        if (empty($lines)) {
            return 'Nội dung chính: ' . $title;
        }

        // Chấm điểm câu theo độ dài thông tin và từ khóa tiêu đề
        $titleTokens = $this->tokenize($title);
        $scored = [];
        foreach ($lines as $idx => $line) {
            $cleanLine = ltrim($line, "-*• \t");
            $len = mb_strlen($cleanLine, 'UTF-8');
            $score = ($idx === 0 ? 1.5 : 1.0) + min(2.0, $len / 80.0);
            foreach ($titleTokens as $tk) {
                if (str_contains(mb_strtolower($cleanLine, 'UTF-8'), $tk)) {
                    $score += 1.2;
                }
            }
            if ($regenerate) {
                // Đổi trọng số nhẹ khi người dùng bấm "Tạo lại" để góc nhìn tóm tắt đa dạng hơn
                $score += (($idx % 2 === 1) ? 0.9 : 0.0);
            }
            $scored[] = ['idx' => $idx, 'text' => $cleanLine, 'score' => $score];
        }

        usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $selected = array_slice($scored, 0, min(4, count($scored)));
        usort($selected, static fn(array $a, array $b): int => $a['idx'] <=> $b['idx']);

        $bullets = array_map(static fn(array $s): string => '• ' . $s['text'], $selected);
        $header = $title !== '' ? "Ý chính của \"{$title}\":\n" : "Tóm tắt các ý chính:\n";

        return $header . implode("\n", $bullets);
    }

    /**
     * Tổng hợp câu trả lời cục bộ kèm tham chiếu ghi chú khi chưa cấu hình LLM_API_KEY.
     */
    private function localSynthesizeAnswer(string $question, array $scoredNotes): array
    {
        $top = array_values(array_filter($scoredNotes, static fn(array $x): bool => $x['score'] > 0.5));
        $top = array_slice($top, 0, 3);

        if (empty($top)) {
            return [
                'answer' => 'Không tìm thấy thông tin liên quan đến câu hỏi của bạn trong các ghi chú hiện có. Bạn có thể thử diễn đạt cụ thể hơn hoặc bổ sung ghi chú mới.',
                'references' => [],
                'provider' => 'local_fallback',
            ];
        }

        $qTokens = $this->tokenize($this->expandSemanticSynonyms($question));
        $synthesizedParts = [];
        $references = [];

        foreach ($top as $item) {
            $note = $item['note'];
            $references[] = $this->formatReference($note);

            $sentences = preg_split('/(?:\r?\n|(?<=[.!?])\s+)/u', (string) $note['content'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $matchedLines = [];
            foreach ($sentences as $sent) {
                $sentTrim = trim($sent);
                if ($sentTrim === '') {
                    continue;
                }
                $sentLower = mb_strtolower($sentTrim, 'UTF-8');
                foreach ($qTokens as $tk) {
                    if (str_contains($sentLower, $tk)) {
                        $matchedLines[] = ltrim($sentTrim, "-*• \t");
                        break;
                    }
                }
                if (count($matchedLines) >= 3) {
                    break;
                }
            }

            if (empty($matchedLines) && !empty($sentences)) {
                $matchedLines[] = ltrim(trim($sentences[0]), "-*• \t");
            }

            if (!empty($matchedLines)) {
                $title = $note['title'] !== '' ? $note['title'] : 'Ghi chú #' . $note['id'];
                $synthesizedParts[] = "Theo ghi chú \"{$title}\":\n- " . implode("\n- ", $matchedLines);
            }
        }

        $answer = "Dựa trên các ghi chú liên quan của bạn, thông tin tổng hợp như sau:\n\n"
            . implode("\n\n", $synthesizedParts);

        return [
            'answer' => $answer,
            'references' => $references,
            'provider' => 'local_fallback',
        ];
    }

    /**
     * Chuẩn hóa dữ liệu tham chiếu ghi chú trả về cho giao diện AI Hỏi-Đáp.
     */
    private function formatReference(array $note): array
    {
        $snippet = trim((string) $note['content']);
        if (mb_strlen($snippet, 'UTF-8') > 140) {
            $snippet = mb_substr($snippet, 0, 140, 'UTF-8') . '...';
        }

        return [
            'id' => (int) $note['id'],
            'title' => $note['title'] !== '' ? $note['title'] : '(Không có tiêu đề)',
            'snippet' => $snippet,
            'updated_at' => timeAgo($note['updated_at'] ?? ''),
            'url' => '/notes/edit/' . (int) $note['id'],
        ];
    }
}
