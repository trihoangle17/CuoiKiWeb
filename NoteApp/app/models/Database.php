<?php
/**
 * Database.php
 * Quản lý kết nối PDO tới MySQL theo mô hình Singleton.
 * Bật prepared statements thực sự trên MySQL để chống SQL Injection.
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;
    private static bool $connectionFailed = false;

    /**
     * Lấy instance PDO duy nhất trong toàn bộ vòng đời request.
     * Trả về null nếu không kết nối được để ứng dụng hiển thị trang
     * "Yêu cầu kết nối cơ sở dữ liệu".
     */
    public static function getInstance(): ?PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (self::$connectionFailed) {
            return null;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            // Đồng bộ múi giờ phiên MySQL với PHP (+07:00)
            self::$instance->exec("SET time_zone = '+07:00'");

            return self::$instance;
        } catch (PDOException $e) {
            self::$connectionFailed = true;

            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log('Lỗi kết nối cơ sở dữ liệu: ' . $e->getMessage());
            }

            return null;
        }
    }

    /**
     * Đặt lại kết nối (hữu ích cho tiến trình dài hạn như WebSocket server).
     */
    public static function resetConnection(): void
    {
        self::$instance = null;
        self::$connectionFailed = false;
    }

    /**
     * Kiểm tra xem có kết nối được tới MySQL hay không.
     */
    public static function isConnected(): bool
    {
        return self::getInstance() !== null;
    }
}
