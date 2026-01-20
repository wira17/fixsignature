<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

date_default_timezone_set('Asia/Jakarta');

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$error = '';
$success = '';
$step = isset($_POST['step']) ? $_POST['step'] : 'verify_otp';

if (empty($email)) {
    header('Location: auth.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'verify_otp') {
    $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    
    if (empty($otp)) {
        $error = "Kode OTP harus diisi!";
    } else {
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND otp = ? AND used = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            $current_time = time();
            $expiry_time = strtotime($row['expires_at']);
            
            if ($current_time > $expiry_time) {
                $error = "Kode OTP sudah kadaluarsa! Silakan minta OTP baru.";
            } else {
                // OTP valid, move to reset password step
                $_SESSION['otp_verified_email'] = $email;
                $step = 'reset_password';
            }
        } else {
            $error = "Kode OTP salah! Silakan periksa kembali.";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset_password' && isset($_SESSION['otp_verified_email'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Password dan konfirmasi password harus diisi!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Password dan konfirmasi password tidak sama!";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $verified_email = $_SESSION['otp_verified_email'];
        
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $update_stmt->bind_param("ss", $hashed_password, $verified_email);
        
        if ($update_stmt->execute()) {
            $mark_used = $conn->prepare("UPDATE password_resets SET used = 1 WHERE email = ?");
            $mark_used->bind_param("s", $verified_email);
            $mark_used->execute();
            $mark_used->close();
            
            unset($_SESSION['otp_verified_email']);
            
            $success = "Password berhasil direset! Silakan login dengan password baru Anda.";
            $step = 'success';
        } else {
            $error = "Gagal mereset password. Silakan coba lagi.";
        }
        $update_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP - Fix Signature</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #2c3e50;
        }

        .verify-container {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            width: 100%;
            max-width: 480px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo {
            width: 64px;
            height: 64px;
            background: #6366f1;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .logo i {
            font-size: 28px;
            color: white;
        }

        .app-name {
            font-size: 20px;
            font-weight: 500;
            color: #1e293b;
        }

        .verify-title {
            font-size: 18px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 8px;
            text-align: center;
        }

        .verify-subtitle {
            font-size: 13px;
            color: #64748b;
            text-align: center;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .email-badge {
            background: #eff6ff;
            color: #1e40af;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-align: center;
            margin-bottom: 24px;
            word-break: break-all;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #475569;
            font-size: 13px;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .input-group {
            position: relative;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: white;
        }

        input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .otp-input {
            text-align: center;
            font-size: 24px;
            letter-spacing: 8px;
            font-weight: 600;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 14px;
        }

        .password-toggle:hover {
            color: #6366f1;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: #6366f1;
            color: white;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            margin-top: 12px;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .resend-link {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: #64748b;
        }

        .resend-link a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
        }

        .resend-link a:hover {
            text-decoration: underline;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #dcfce7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .success-icon i {
            font-size: 36px;
            color: #16a34a;
        }

        @media (max-width: 480px) {
            .verify-container {
                padding: 28px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="logo-section">
            <div class="logo">
                <i class="fas fa-file-signature"></i>
            </div>
            <div class="app-name">Fix Signature</div>
        </div>

        <?php if ($step === 'verify_otp'): ?>
            <h2 class="verify-title">Verifikasi Kode OTP</h2>
            <p class="verify-subtitle">
                Masukkan kode 6 digit yang telah kami kirim ke email:
            </p>
            <div class="email-badge">
                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($email); ?>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="step" value="verify_otp">
                <div class="form-group">
                    <label for="otp">Kode OTP</label>
                    <input type="text" id="otp" name="otp" class="otp-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i>
                    Verifikasi
                </button>
            </form>

            <div class="resend-link">
                Tidak menerima kode? 
                <a href="#" onclick="resendOTP('<?php echo htmlspecialchars($email); ?>'); return false;">
                    Kirim ulang
                </a>
            </div>

            <a href="auth.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Login
            </a>

        <?php elseif ($step === 'reset_password'): ?>
            <h2 class="verify-title">Buat Password Baru</h2>
            <p class="verify-subtitle">
                Masukkan password baru untuk akun Anda
            </p>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="step" value="reset_password">
                
                <div class="form-group">
                    <label for="new_password">Password Baru</label>
                    <div class="input-group">
                        <input type="password" id="new_password" name="new_password" placeholder="Minimal 6 karakter" required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password', this)"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <div class="input-group">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password baru" required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', this)"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i>
                    Reset Password
                </button>
            </form>

        <?php elseif ($step === 'success'): ?>
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2 class="verify-title">Password Berhasil Direset!</h2>
            <p class="verify-subtitle">
                Password Anda telah berhasil diubah. Silakan login dengan password baru Anda.
            </p>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <a href="auth.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                Login Sekarang
            </a>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function resendOTP(email) {
            if (confirm('Kirim ulang kode OTP ke ' + email + '?')) {
                fetch('forgot_password_send_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'email=' + encodeURIComponent(email)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                        location.reload();
                    } else {
                        alert('❌ ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Terjadi kesalahan. Silakan coba lagi.');
                });
            }
        }

        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }
    </script>
</body>
</html>