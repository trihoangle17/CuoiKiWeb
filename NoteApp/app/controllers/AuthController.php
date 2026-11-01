<?php
/**
 * AuthController.php
 * Xử lý đăng ký, đăng nhập, đăng xuất, kích hoạt tài khoản qua email,
 * quên mật khẩu và đặt lại mật khẩu (hỗ trợ cả qua Link hoặc mã OTP).
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/Mailer.php';

class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Hiển thị và xử lý form đăng nhập (có rate-limit chống dò mật khẩu).
     */
    public function login(): void
    {
        if (isLoggedIn()) {
            redirect('/notes');
        }

        $errors = [];

        if (requestMethod() === 'POST') {
            requireCsrf();

            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                $errors[] = 'Vui lòng nhập đầy đủ email và mật khẩu.';
            } else {
                $user = $this->userModel->findByEmail($email);

                if (!$user) {
                    $errors[] = 'Email hoặc mật khẩu không chính xác.';
                } elseif ($this->userModel->isLocked($user)) {
                    $errors[] = 'Tài khoản tạm thời bị khóa do đăng nhập sai quá nhiều lần. Vui lòng thử lại sau ít phút.';
                } elseif (!password_verify($password, $user['password_hash'])) {
                    $this->userModel->recordFailedLogin((int) $user['id']);
                    $errors[] = 'Email hoặc mật khẩu không chính xác.';
                } else {
                    $this->userModel->resetFailedLogin((int) $user['id']);
                    loginUser((int) $user['id']);

                    $redirectTo = $_SESSION['redirect_after_login'] ?? '/notes';
                    unset($_SESSION['redirect_after_login']);

                    setFlash('success', 'Đăng nhập thành công. Chào mừng bạn trở lại!');
                    redirect($redirectTo);
                }
            }
        }

        $pageTitle = 'Đăng nhập';
        $viewFile = __DIR__ . '/../views/auth/login.php';
        require __DIR__ . '/../views/layouts/guest.php';
    }

    /**
     * Hiển thị và xử lý form đăng ký:
     * Chỉ yêu cầu email, tên hiển thị và mật khẩu nhập 2 lần.
     * Lưu bcrypt, tự động đăng nhập ngay và gửi email kích hoạt.
     */
    public function register(): void
    {
        if (isLoggedIn()) {
            redirect('/notes');
        }

        $errors = [];
        $old = ['email' => '', 'display_name' => ''];

        if (requestMethod() === 'POST') {
            requireCsrf();

            $email = trim($_POST['email'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            $old['email'] = $email;
            $old['display_name'] = $displayName;

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Địa chỉ email không hợp lệ.';
            } elseif ($this->userModel->findByEmail($email)) {
                $errors[] = 'Email này đã được đăng ký tài khoản.';
            }

            if ($displayName === '') {
                $errors[] = 'Vui lòng nhập tên hiển thị.';
            } elseif (mb_strlen($displayName, 'UTF-8') > 100) {
                $errors[] = 'Tên hiển thị tối đa 100 ký tự.';
            }

            if (strlen($password) < 6) {
                $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
            } elseif ($password !== $passwordConfirm) {
                $errors[] = 'Mật khẩu xác nhận không khớp.';
            }

            if (empty($errors)) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $activationToken = bin2hex(random_bytes(32));

                $userId = $this->userModel->create($email, $passwordHash, $displayName, $activationToken);

                // Tự động đăng nhập ngay sau khi đăng ký thành công
                loginUser($userId);

                // Gửi email chứa liên kết kích hoạt tài khoản
                $this->sendActivationEmail($email, $displayName, $activationToken);

                setFlash('success', 'Đăng ký thành công! Vui lòng kiểm tra email để kích hoạt tài khoản.');
                redirect('/notes');
            }
        }

        $pageTitle = 'Đăng ký tài khoản';
        $viewFile = __DIR__ . '/../views/auth/register.php';
        require __DIR__ . '/../views/layouts/guest.php';
    }

    /**
     * Đăng xuất an toàn và xóa toàn bộ session.
     */
    public function logout(): void
    {
        if (requestMethod() !== 'POST') {
            redirect('/notes');
        }
        requireCsrf();
        logoutUser();
        setFlash('info', 'Bạn đã đăng xuất thành công.');
        redirect('/login');
    }

    /**
     * Kích hoạt tài khoản qua liên kết trong email: /activate?token=xxx
     */
    public function activate(): void
    {
        $token = trim($_GET['token'] ?? '');

        if ($token === '') {
            setFlash('error', 'Liên kết kích hoạt không hợp lệ.');
            redirect(isLoggedIn() ? '/notes' : '/login');
        }

        $success = $this->userModel->activateByToken($token);

        if ($success) {
            setFlash('success', 'Kích hoạt tài khoản thành công! Cảm ơn bạn đã xác thực email.');
        } else {
            setFlash('error', 'Liên kết kích hoạt không hợp lệ hoặc đã hết hạn.');
        }

        redirect(isLoggedIn() ? '/notes' : '/login');
    }

    /**
     * Hiển thị và xử lý form quên mật khẩu: gửi cả liên kết đặt lại và mã OTP qua email.
     */
    public function forgotPassword(): void
    {
        if (isLoggedIn()) {
            redirect('/notes');
        }

        $errors = [];
        $sent = false;
        $submittedEmail = '';

        if (requestMethod() === 'POST') {
            requireCsrf();

            $email = trim($_POST['email'] ?? '');
            $submittedEmail = $email;

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Vui lòng nhập địa chỉ email hợp lệ.';
            } else {
                $user = $this->userModel->findByEmail($email);

                // Không tiết lộ email có tồn tại hay không để chống dò tìm tài khoản
                if ($user) {
                    $this->createAndSendPasswordReset($user);
                }

                $sent = true;
            }
        }

        $pageTitle = 'Quên mật khẩu';
        $viewFile = __DIR__ . '/../views/auth/forgot_password.php';
        require __DIR__ . '/../views/layouts/guest.php';
    }

    /**
     * Đặt lại mật khẩu mới theo đúng quy trình 2 bước:
     * - Bước xác thực: Bấm vào liên kết trong email (/reset-password?token=xxx)
     *   HOẶC nhập Email + mã OTP 6 chữ số để hệ thống xác thực rồi chuyển hướng
     *   (redirect) sang màn hình nhập mật khẩu mới (/reset-password?token=xxx).
     * - Bước đặt mật khẩu mới: Nhập mật khẩu mới 2 lần, lưu bcrypt và yêu cầu đăng nhập lại thủ công.
     */
    public function resetPassword(): void
    {
        if (isLoggedIn()) {
            redirect('/notes');
        }

        $db = Database::getInstance();
        $errors = [];
        $tokenInvalid = false;

        $token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
        $email = trim($_GET['email'] ?? ($_POST['email'] ?? ''));
        $step = trim($_POST['step'] ?? '');

        // Nếu truy cập màn hình nhập mật khẩu mới qua token (GET), kiểm tra hiệu lực token ngay
        if (requestMethod() === 'GET' && $token !== '') {
            $stmt = $db->prepare(
                'SELECT pr.id
                 FROM password_resets pr
                 INNER JOIN users u ON u.id = pr.user_id
                 WHERE pr.token = :token AND pr.used_at IS NULL AND pr.expires_at >= NOW()
                 ORDER BY pr.id DESC LIMIT 1'
            );
            $stmt->execute(['token' => $token]);
            if (!$stmt->fetch()) {
                $tokenInvalid = true;
                $errors[] = 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
            }
        }

        if (requestMethod() === 'POST') {
            requireCsrf();

            // Trường hợp 1: Người dùng gửi mã OTP để xác thực -> chuyển hướng sang màn hình nhập mật khẩu mới
            if ($step === 'verify_otp' || $token === '') {
                $otp = trim($_POST['otp'] ?? '');

                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Vui lòng nhập đúng email đã yêu cầu đặt lại mật khẩu.';
                } elseif ($otp === '') {
                    $errors[] = 'Vui lòng nhập mã OTP gồm 6 chữ số từ email.';
                } else {
                    $stmt = $db->prepare(
                        'SELECT pr.token
                         FROM password_resets pr
                         INNER JOIN users u ON u.id = pr.user_id
                         WHERE u.email = :email AND pr.otp_code = :otp
                           AND pr.used_at IS NULL AND pr.expires_at >= NOW()
                         ORDER BY pr.id DESC LIMIT 1'
                    );
                    $stmt->execute(['email' => $email, 'otp' => $otp]);
                    $otpRecord = $stmt->fetch();

                    if (!$otpRecord) {
                        $errors[] = 'Mã OTP hoặc email không chính xác, hoặc mã đã hết hạn.';
                    } else {
                        // Xác thực OTP thành công -> Chuyển hướng tới màn hình nhập mật khẩu mới
                        redirect('/reset-password?token=' . urlencode((string) $otpRecord['token']));
                    }
                }
            } else {
                // Trường hợp 2: Đã ở màn hình nhập mật khẩu mới (có token hợp lệ)
                $password = $_POST['password'] ?? '';
                $passwordConfirm = $_POST['password_confirm'] ?? '';

                $stmt = $db->prepare(
                    'SELECT pr.*, u.id AS user_id
                     FROM password_resets pr
                     INNER JOIN users u ON u.id = pr.user_id
                     WHERE pr.token = :token AND pr.used_at IS NULL AND pr.expires_at >= NOW()
                     ORDER BY pr.id DESC LIMIT 1'
                );
                $stmt->execute(['token' => $token]);
                $resetRecord = $stmt->fetch();

                if (!$resetRecord) {
                    $tokenInvalid = true;
                    $errors[] = 'Phiên đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
                } elseif (strlen($password) < 6) {
                    $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
                } elseif ($password !== $passwordConfirm) {
                    $errors[] = 'Mật khẩu xác nhận không khớp.';
                } else {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $this->userModel->updatePassword((int) $resetRecord['user_id'], $passwordHash);
                    $this->userModel->resetFailedLogin((int) $resetRecord['user_id']);

                    // Vô hiệu hóa tất cả mã reset chưa dùng của user này
                    $markUsed = $db->prepare(
                        'UPDATE password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL'
                    );
                    $markUsed->execute(['uid' => $resetRecord['user_id']]);

                    // Yêu cầu người dùng đăng nhập lại thủ công
                    setFlash('success', 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập bằng mật khẩu mới.');
                    redirect('/login');
                }
            }
        }

        $pageTitle = $token !== '' ? 'Đặt lại mật khẩu mới' : 'Xác thực mã OTP';
        $viewFile = __DIR__ . '/../views/auth/reset_password.php';
        require __DIR__ . '/../views/layouts/guest.php';
    }

    /**
     * Gửi email chứa liên kết kích hoạt tài khoản.
     */
    private function sendActivationEmail(string $email, string $displayName, string $token): void
    {
        $activationUrl = baseUrl() . '/activate?token=' . urlencode($token);

        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#222;">'
            . '<h2>Chào mừng ' . e($displayName) . ' đến với NoteApp!</h2>'
            . '<p>Cảm ơn bạn đã đăng ký tài khoản. Vui lòng bấm vào liên kết bên dưới để kích hoạt tài khoản của bạn:</p>'
            . '<p><a href="' . e($activationUrl) . '" style="display:inline-block;padding:10px 20px;background:#4a6cf7;color:#fff;text-decoration:none;border-radius:6px;">Kích hoạt tài khoản</a></p>'
            . '<p>Hoặc sao chép đường dẫn: <a href="' . e($activationUrl) . '">' . e($activationUrl) . '</a></p>'
            . '<p><small>Liên kết có hiệu lực trong vòng 24 giờ.</small></p>'
            . '</div>';

        $mailer = new Mailer();
        $mailer->send($email, $displayName, 'Kích hoạt tài khoản NoteApp', $html);
    }

    /**
     * Tạo bản ghi khôi phục mật khẩu (gồm cả liên kết token và mã OTP 6 số) và gửi email.
     */
    private function createAndSendPasswordReset(array $user): void
    {
        $db = Database::getInstance();

        $token = bin2hex(random_bytes(32));
        $otp = (string) random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // Hiệu lực 1 giờ

        $stmt = $db->prepare(
            'INSERT INTO password_resets (user_id, token, otp_code, expires_at)
             VALUES (:user_id, :token, :otp, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $user['id'],
            'token' => $token,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ]);

        $resetUrl = baseUrl() . '/reset-password?token=' . urlencode($token);

        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#222;">'
            . '<h2>Khôi phục mật khẩu NoteApp</h2>'
            . '<p>Xin chào <strong>' . e($user['display_name']) . '</strong>,</p>'
            . '<p>Bạn vừa yêu cầu đặt lại mật khẩu. Bạn có thể chọn một trong hai cách sau:</p>'
            . '<p><strong>Cách 1 - Nhập mã OTP:</strong> <span style="font-size:20px;font-weight:bold;letter-spacing:3px;color:#4a6cf7;">' . e($otp) . '</span></p>'
            . '<p><strong>Cách 2 - Bấm trực tiếp vào liên kết:</strong><br><a href="' . e($resetUrl) . '">' . e($resetUrl) . '</a></p>'
            . '<p><small>Mã OTP và liên kết có hiệu lực trong 1 giờ.</small></p>'
            . '</div>';

        $mailer = new Mailer();
        $mailer->send($user['email'], $user['display_name'], 'Đặt lại mật khẩu NoteApp', $html);
    }
}
