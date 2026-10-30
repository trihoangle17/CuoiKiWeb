<?php
/**
 * Mailer.php
 * Service gui email bang SMTP thuan (khong dung thu vien ngoai/Composer).
 * Ho tro STARTTLS va AUTH LOGIN, du dung voi Gmail SMTP hoac SMTP noi bo.
 */

declare(strict_types=1);

class Mailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;

    /** @var resource|null */
    private $socket;

    public function __construct()
    {
        $this->host = MAIL_HOST;
        $this->port = MAIL_PORT;
        $this->username = MAIL_USERNAME;
        $this->password = MAIL_PASSWORD;
        $this->encryption = MAIL_ENCRYPTION;
        $this->fromAddress = MAIL_FROM_ADDRESS;
        $this->fromName = MAIL_FROM_NAME;
    }

    /**
     * Gui email HTML don gian. Tra ve true/false, loi duoc ghi vao error_log.
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        if (empty($this->host) || empty($this->username)) {
            error_log('Mailer: chua cau hinh SMTP, bo qua gui email toi ' . $toEmail);
            return false;
        }

        try {
            $this->connect();
            $this->readResponse(220);

            $this->command('EHLO ' . $this->getClientDomain(), 250);

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Khong the thiet lap TLS voi SMTP server');
                }
                $this->command('EHLO ' . $this->getClientDomain(), 250);
            }

            $this->command('AUTH LOGIN', 334);
            $this->command(base64_encode($this->username), 334);
            $this->command(base64_encode($this->password), 235);

            $this->command('MAIL FROM:<' . $this->fromAddress . '>', 250);
            $this->command('RCPT TO:<' . $toEmail . '>', 250);
            $this->command('DATA', 354);

            $headers = $this->buildHeaders($toEmail, $toName, $subject);
            $this->rawWrite($headers . "\r\n" . $this->escapeBody($htmlBody) . "\r\n.\r\n");
            $this->readResponse(250);

            $this->command('QUIT', 221);
            $this->disconnect();

            return true;
        } catch (Throwable $e) {
            error_log('Mailer loi gui email: ' . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    private function connect(): void
    {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 10);
        if (!$this->socket) {
            throw new RuntimeException("Khong ket noi duoc SMTP {$this->host}:{$this->port} - {$errstr}");
        }
        stream_set_timeout($this->socket, 10);
    }

    private function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function command(string $cmd, int $expectedCode): string
    {
        $this->rawWrite($cmd . "\r\n");
        return $this->readResponse($expectedCode);
    }

    private function rawWrite(string $data): void
    {
        fwrite($this->socket, $data);
    }

    private function readResponse(int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            // Dong cuoi cung co dang "250 " (khong co dau gach ngang sau ma so)
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException("SMTP tra ve ma khong mong doi: {$response}");
        }

        return $response;
    }

    private function buildHeaders(string $toEmail, string $toName, string $subject): string
    {
        $encodedSubject = $this->encodeHeaderValue($subject);
        $fromHeader = $this->encodeHeaderValue($this->fromName) . ' <' . $this->fromAddress . '>';
        $toHeader = $this->encodeHeaderValue($toName) . ' <' . $toEmail . '>';

        return implode("\r\n", [
            'From: ' . $fromHeader,
            'To: ' . $toHeader,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Date: ' . date('r'),
        ]);
    }

    /**
     * Ma hoa gia tri header theo chuan MIME encoded-word (=?UTF-8?B?...?=)
     * de ho tro ky tu tieng Viet trong Subject/From/To.
     */
    private function encodeHeaderValue(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /**
     * Ma hoa noi dung email bang base64 (tranh loi dau dong ".") va tach dong 76 ky tu.
     */
    private function escapeBody(string $body): string
    {
        return chunk_split(base64_encode($body));
    }

    private function getClientDomain(): string
    {
        return $_SERVER['SERVER_NAME'] ?? 'localhost';
    }
}
