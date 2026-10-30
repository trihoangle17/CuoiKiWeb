<?php
/**
 * websocket/server.php
 * Máy chủ WebSocket chuẩn RFC 6455 viết bằng PHP thuần (stream_socket_server + stream_select),
 * phục vụ đồng bộ thời gian thực khi nhiều người cùng chỉnh sửa một ghi chú được chia sẻ.
 *
 * Cách chạy độc lập: php websocket/server.php
 * Trong Docker Compose: tự động khởi chạy ở container `websocket`.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy qua dòng lệnh CLI.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/helpers/functions.php';

$bindHost = WS_HOST !== '' ? WS_HOST : '0.0.0.0';
$bindPort = WS_PORT > 0 ? WS_PORT : 8081;

$server = @stream_socket_server(
    "tcp://{$bindHost}:{$bindPort}",
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
);

if (!$server) {
    fwrite(STDERR, "[WebSocket] Không thể mở cổng {$bindHost}:{$bindPort} - {$errstr} ({$errno})\n");
    exit(1);
}

stream_set_blocking($server, false);
echo "[WebSocket] Máy chủ cộng tác thời gian thực đang lắng nghe tại ws://{$bindHost}:{$bindPort}\n";

/**
 * Danh sách kết nối đang hoạt động:
 * socketId => [
 *   'stream' => resource,
 *   'handshaked' => bool,
 *   'buffer' => string,
 *   'noteId' => int|null,
 *   'userId' => int|null,
 *   'displayName' => string,
 * ]
 */
$clients = [];

while (true) {
    $read = [$server];
    foreach ($clients as $client) {
        $read[] = $client['stream'];
    }
    $write = null;
    $except = null;

    $changed = @stream_select($read, $write, $except, 1, 0);
    if ($changed === false) {
        continue;
    }

    // Chấp nhận kết nối TCP mới
    if (in_array($server, $read, true)) {
        $newStream = @stream_socket_accept($server, 0);
        if ($newStream) {
            stream_set_blocking($newStream, false);
            $id = (int) $newStream;
            $clients[$id] = [
                'stream' => $newStream,
                'handshaked' => false,
                'buffer' => '',
                'noteId' => null,
                'userId' => null,
                'displayName' => 'Người dùng',
            ];
        }
        unset($read[array_search($server, $read, true)]);
    }

    // Xử lý dữ liệu gửi đến từ các client
    foreach ($read as $stream) {
        $id = (int) $stream;
        if (!isset($clients[$id])) {
            continue;
        }

        $chunk = @fread($stream, 65535);
        if ($chunk === '' || $chunk === false) {
            if (feof($stream)) {
                disconnectClient($id, $clients);
            }
            continue;
        }

        $clients[$id]['buffer'] .= $chunk;

        // Bước 1: Bắt tay HTTP Upgrade lên giao thức WebSocket (RFC 6455)
        if (!$clients[$id]['handshaked']) {
            $headerEnd = strpos($clients[$id]['buffer'], "\r\n\r\n");
            if ($headerEnd === false) {
                if (strlen($clients[$id]['buffer']) > 8192) {
                    disconnectClient($id, $clients);
                }
                continue;
            }

            $rawHeader = substr($clients[$id]['buffer'], 0, $headerEnd + 4);
            $clients[$id]['buffer'] = substr($clients[$id]['buffer'], $headerEnd + 4);

            if (!performHandshake($stream, $rawHeader)) {
                disconnectClient($id, $clients);
                continue;
            }

            $clients[$id]['handshaked'] = true;
        }

        // Bước 2: Giải mã các khung dữ liệu (Frame) WebSocket trong bộ đệm
        while (($frame = extractWsFrame($clients[$id]['buffer'])) !== null) {
            $opcode = $frame['opcode'];

            if ($opcode === 0x8) {
                // Close frame
                disconnectClient($id, $clients);
                break;
            }
            if ($opcode === 0x9) {
                // Ping -> phản hồi Pong
                @fwrite($stream, encodeWsFrame($frame['payload'], 0xA));
                continue;
            }
            if ($opcode !== 0x1) {
                // Chỉ xử lý Text frame JSON
                continue;
            }

            $data = json_decode($frame['payload'], true);
            if (!is_array($data) || empty($data['type'])) {
                continue;
            }

            handleClientMessage($id, $data, $clients);
        }
    }
}

/**
 * Thực hiện bắt tay chuẩn RFC 6455.
 */
function performHandshake($stream, string $requestHeader): bool
{
    if (!preg_match('/Sec-WebSocket-Key:\s*([^\r\n]+)/i', $requestHeader, $matches)) {
        return false;
    }

    $key = trim($matches[1]);
    $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

    $upgradeResponse = "HTTP/1.1 101 Switching Protocols\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

    return @fwrite($stream, $upgradeResponse) !== false;
}

/**
 * Giải mã 1 frame từ buffer của client (hỗ trợ đầy đủ độ dài <126, 16-bit và 64-bit).
 * Trả về null nếu dữ liệu trong buffer chưa đủ 1 frame trọn vẹn.
 */
function extractWsFrame(string &$buffer): ?array
{
    $len = strlen($buffer);
    if ($len < 2) {
        return null;
    }

    $firstByte = ord($buffer[0]);
    $secondByte = ord($buffer[1]);

    $opcode = $firstByte & 0x0F;
    $isMasked = ($secondByte & 0x80) !== 0;
    $payloadLen = $secondByte & 0x7F;
    $offset = 2;

    if ($payloadLen === 126) {
        if ($len < 4) {
            return null;
        }
        $payloadLen = unpack('n', substr($buffer, 2, 2))[1];
        $offset = 4;
    } elseif ($payloadLen === 127) {
        if ($len < 10) {
            return null;
        }
        $parts = unpack('N2', substr($buffer, 2, 8));
        $payloadLen = ($parts[1] << 32) | $parts[2];
        $offset = 10;
    }

    $maskKey = '';
    if ($isMasked) {
        if ($len < $offset + 4) {
            return null;
        }
        $maskKey = substr($buffer, $offset, 4);
        $offset += 4;
    }

    if ($len < $offset + $payloadLen) {
        return null;
    }

    $rawPayload = substr($buffer, $offset, $payloadLen);
    $buffer = substr($buffer, $offset + $payloadLen);

    if ($isMasked) {
        $unmasked = '';
        for ($i = 0; $i < $payloadLen; $i++) {
            $unmasked .= $rawPayload[$i] ^ $maskKey[$i % 4];
        }
        $rawPayload = $unmasked;
    }

    return [
        'opcode' => $opcode,
        'payload' => $rawPayload,
    ];
}

/**
 * Đóng gói dữ liệu từ server gửi xuống trình duyệt (Server -> Client không mask).
 */
function encodeWsFrame(string $payload, int $opcode = 0x1): string
{
    $firstByte = chr(0x80 | ($opcode & 0x0F));
    $len = strlen($payload);

    if ($len <= 125) {
        return $firstByte . chr($len) . $payload;
    }
    if ($len <= 65535) {
        return $firstByte . chr(126) . pack('n', $len) . $payload;
    }
    return $firstByte . chr(127) . pack('NN', ($len >> 32) & 0xFFFFFFFF, $len & 0xFFFFFFFF) . $payload;
}

/**
 * Xử lý thông điệp nghiệp vụ cộng tác:
 * - "join" : Xác thực chữ ký HMAC, đưa người dùng vào phòng của ghi chú và phát danh sách người đang mở.
 * - "edit" : Phát thay đổi tiêu đề / nội dung tới tất cả người cộng tác khác đang mở cùng ghi chú.
 */
function handleClientMessage(int $senderId, array $data, array &$clients): void
{
    $type = (string) $data['type'];

    if ($type === 'join') {
        $noteId = (int) ($data['noteId'] ?? 0);
        $userId = (int) ($data['userId'] ?? 0);
        $displayName = trim((string) ($data['displayName'] ?? 'Người dùng'));
        $exp = (int) ($data['exp'] ?? 0);
        $sig = (string) ($data['sig'] ?? '');

        if ($noteId <= 0 || $userId <= 0 || !verifyWsCollabToken($noteId, $userId, $displayName, $exp, $sig)) {
            disconnectClient($senderId, $clients);
            return;
        }

        $clients[$senderId]['noteId'] = $noteId;
        $clients[$senderId]['userId'] = $userId;
        $clients[$senderId]['displayName'] = $displayName;

        broadcastPresence($noteId, $clients);
        return;
    }

    if ($type === 'edit') {
        $noteId = $clients[$senderId]['noteId'] ?? null;
        if ($noteId === null) {
            return;
        }

        $title = (string) ($data['title'] ?? '');
        $content = (string) ($data['content'] ?? '');
        $editorName = $clients[$senderId]['displayName'] ?? 'Người cộng tác';

        foreach ($clients as $targetId => $target) {
            if ($targetId === $senderId || $target['noteId'] !== $noteId || !$target['handshaked']) {
                continue;
            }

            $packet = json_encode([
                'type' => 'edit',
                'noteId' => $noteId,
                'senderSocketId' => $senderId,
                'selfSocketId' => $targetId,
                'editorName' => $editorName,
                'title' => $title,
                'content' => $content,
            ], JSON_UNESCAPED_UNICODE);

            @fwrite($target['stream'], encodeWsFrame((string) $packet));
        }
    }
}

/**
 * Gửi danh sách những người đang mở cùng một ghi chú để hiển thị thanh trạng thái cộng tác.
 */
function broadcastPresence(int $noteId, array &$clients): void
{
    $uniqueUsers = [];
    foreach ($clients as $c) {
        if ($c['noteId'] === $noteId && $c['userId'] !== null) {
            $uniqueUsers[$c['userId']] = [
                'userId' => $c['userId'],
                'displayName' => $c['displayName'],
            ];
        }
    }

    $packet = json_encode([
        'type' => 'presence',
        'noteId' => $noteId,
        'users' => array_values($uniqueUsers),
    ], JSON_UNESCAPED_UNICODE);

    $frame = encodeWsFrame((string) $packet);
    foreach ($clients as $c) {
        if ($c['noteId'] === $noteId && $c['handshaked']) {
            @fwrite($c['stream'], $frame);
        }
    }
}

/**
 * Đóng kết nối của client và cập nhật lại danh sách người đang cộng tác trong phòng.
 */
function disconnectClient(int $id, array &$clients): void
{
    if (!isset($clients[$id])) {
        return;
    }

    $noteId = $clients[$id]['noteId'];
    @fclose($clients[$id]['stream']);
    unset($clients[$id]);

    if ($noteId !== null) {
        broadcastPresence($noteId, $clients);
    }
}
