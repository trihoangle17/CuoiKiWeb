<?php
/**
 * User.php
 * Model quan ly du lieu nguoi dung: dang ky, dang nhap, kich hoat,
 * quen mat khau, ho so ca nhan. Tat ca truy van deu dung prepared statement.
 */

declare(strict_types=1);

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Tim user theo email. Tra ve null neu khong ton tai.
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Tim user theo id. Tra ve null neu khong ton tai.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Tao user moi, mat khau da duoc hash truoc khi goi ham nay.
     * Tra ve id cua user vua tao.
     */
    public function create(string $email, string $passwordHash, string $displayName, string $activationToken): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, password_hash, display_name, activation_token, activation_token_expires_at)
             VALUES (:email, :password_hash, :display_name, :activation_token, :expires_at)'
        );
        $stmt->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
            'activation_token' => $activationToken,
            'expires_at' => date('Y-m-d H:i:s', time() + 86400), // Token kich hoat het han sau 24h
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Kich hoat tai khoan theo token, tra ve true neu thanh cong.
     */
    public function activateByToken(string $token): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM users WHERE activation_token = :token
             AND activation_token_expires_at >= NOW() AND is_active = 0 LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $update = $this->db->prepare(
            'UPDATE users SET is_active = 1, activation_token = NULL, activation_token_expires_at = NULL WHERE id = :id'
        );
        $update->execute(['id' => $user['id']]);

        return true;
    }

    /**
     * Cap nhat so lan dang nhap sai va thoi gian khoa tai khoan (rate limit).
     */
    public function recordFailedLogin(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);

        $user = $this->findById($userId);
        if ($user && (int) $user['failed_login_count'] >= RATE_LIMIT_LOGIN_MAX_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + RATE_LIMIT_LOGIN_LOCK_MINUTES * 60);
            $lockStmt = $this->db->prepare('UPDATE users SET locked_until = :locked_until WHERE id = :id');
            $lockStmt->execute(['locked_until' => $lockUntil, 'id' => $userId]);
        }
    }

    /**
     * Reset so lan dang nhap sai sau khi dang nhap thanh cong.
     */
    public function resetFailedLogin(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
    }

    /**
     * Kiem tra tai khoan dang bi khoa tam thoi do dang nhap sai nhieu lan.
     */
    public function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }
        return strtotime($user['locked_until']) > time();
    }

    /**
     * Cap nhat mat khau moi (da hash) cho user.
     */
    public function updatePassword(int $userId, string $newPasswordHash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $newPasswordHash, 'id' => $userId]);
    }

    /**
     * Cap nhat ten hien thi va avatar cua user.
     */
    public function updateProfile(int $userId, string $displayName, ?string $avatarPath): void
    {
        if ($avatarPath !== null) {
            $stmt = $this->db->prepare(
                'UPDATE users SET display_name = :display_name, avatar_path = :avatar_path WHERE id = :id'
            );
            $stmt->execute([
                'display_name' => $displayName,
                'avatar_path' => $avatarPath,
                'id' => $userId,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE users SET display_name = :display_name WHERE id = :id'
            );
            $stmt->execute([
                'display_name' => $displayName,
                'id' => $userId,
            ]);
        }
    }

    /**
     * Cap nhat tuy chon hien thi: co chu, mau ghi chu, sang/toi.
     */
    public function updatePreferences(int $userId, string $fontSize, string $noteColor, string $theme): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET font_size = :font_size, note_color = :note_color, theme = :theme WHERE id = :id'
        );
        $stmt->execute([
            'font_size' => $fontSize,
            'note_color' => $noteColor,
            'theme' => $theme,
            'id' => $userId,
        ]);
    }

    /**
     * Cap nhat rieng che do sang/toi (dung cho toggle nhanh tren navbar).
     */
    public function updateTheme(int $userId, string $theme): void
    {
        $stmt = $this->db->prepare('UPDATE users SET theme = :theme WHERE id = :id');
        $stmt->execute(['theme' => $theme, 'id' => $userId]);
    }
}
