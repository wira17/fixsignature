<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';
require_once '../MailHelper.php';

$success = '';
$error = '';
$test_result = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $smtp_host = mysqli_real_escape_string($conn, trim($_POST['smtp_host']));
        $smtp_port = intval($_POST['smtp_port']);
        $smtp_username = mysqli_real_escape_string($conn, trim($_POST['smtp_username']));
        $smtp_password = mysqli_real_escape_string($conn, trim($_POST['smtp_password']));
        $smtp_encryption = mysqli_real_escape_string($conn, $_POST['smtp_encryption']);
        $from_email = mysqli_real_escape_string($conn, trim($_POST['from_email']));
        $from_name = mysqli_real_escape_string($conn, trim($_POST['from_name']));
        
        // Check if settings exist
        $check_query = "SELECT id FROM mail_settings LIMIT 1";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Update existing settings
            $update_query = "UPDATE mail_settings SET 
                            smtp_host = '$smtp_host',
                            smtp_port = $smtp_port,
                            smtp_username = '$smtp_username',
                            smtp_password = '$smtp_password',
                            smtp_encryption = '$smtp_encryption',
                            from_email = '$from_email',
                            from_name = '$from_name',
                            updated_at = NOW()
                            WHERE id = 1";
            
            if (mysqli_query($conn, $update_query)) {
                $success = "Pengaturan email berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui pengaturan: " . mysqli_error($conn);
            }
        } else {
        
            $insert_query = "INSERT INTO mail_settings 
                            (smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, from_email, from_name)
                            VALUES 
                            ('$smtp_host', $smtp_port, '$smtp_username', '$smtp_password', '$smtp_encryption', '$from_email', '$from_name')";
            
            if (mysqli_query($conn, $insert_query)) {
                $success = "Pengaturan email berhasil disimpan!";
            } else {
                $error = "Gagal menyimpan pengaturan: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['test_connection'])) {
        try {
            $mailer = new MailHelper($conn);
            $mailer->testConnection();
            $test_result = 'success';
            $success = "✅ Koneksi SMTP berhasil! Pengaturan email Anda sudah benar.";
        } catch (Exception $e) {
            $test_result = 'error';
            $error = "❌ Koneksi SMTP gagal: " . $e->getMessage();
        }
    } elseif (isset($_POST['send_test_email'])) {
        $test_email = mysqli_real_escape_string($conn, trim($_POST['test_email']));
        
        if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            try {
                $mailer = new MailHelper($conn);
                $subject = "Test Email - Fix Signature";
                $body = "
                <html>
                <body style='font-family: Arial, sans-serif; padding: 20px;'>
                    <h2 style='color: #6366f1;'>✅ Test Email Berhasil!</h2>
                    <p>Email ini adalah test email dari sistem Fix Signature.</p>
                    <p>Jika Anda menerima email ini, berarti pengaturan SMTP Anda sudah benar.</p>
                    <hr style='border: 1px solid #e2e8f0; margin: 20px 0;'>
                    <p style='color: #64748b; font-size: 12px;'>
                        Dikirim pada: " . date('d/m/Y H:i:s') . "<br>
                        Sistem: Fix Signature v1.0
                    </p>
                </body>
                </html>
                ";
                
                if ($mailer->sendEmail($test_email, $subject, $body)) {
                    $test_result = 'success';
                    $success = "✅ Test email berhasil dikirim ke $test_email. Silakan cek inbox/spam folder.";
                }
            } catch (Exception $e) {
                $test_result = 'error';
                $error = "❌ Gagal mengirim test email: " . $e->getMessage();
            }
        } else {
            $error = "❌ Email tidak valid!";
        }
    }
}


$settings_query = "SELECT * FROM mail_settings WHERE is_active = 1 LIMIT 1";
$settings_result = mysqli_query($conn, $settings_query);
$settings = mysqli_fetch_assoc($settings_result);


if (!$settings) {
    $settings = [
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'from_email' => '',
        'from_name' => 'Fix Signature'
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Email - Fix Signature</title>
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
            color: #2c3e50;
            font-size: 14px;
        }

        .navbar {
            background: white;
            border-bottom: 1px solid #e8ecf1;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: #dc2626;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon i {
            color: white;
            font-size: 16px;
        }

        .brand-name {
            color: #1e293b;
            font-size: 16px;
            font-weight: 500;
        }

        .admin-badge {
            background: #dc2626;
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 8px;
        }

        .btn-back {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            padding: 7px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-back:hover {
            background: #f8fafc;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 20px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .page-subtitle {
            font-size: 13px;
            color: #64748b;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .alert-warning {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde68a;
        }

        .card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e8ecf1;
            background: #f8fafc;
        }

        .card-title {
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #6366f1;
        }

        .card-body {
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            margin-bottom: 6px;
        }

        .form-label .required {
            color: #dc2626;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-help {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }

        .form-help a {
            color: #6366f1;
            text-decoration: none;
        }

        .form-help a:hover {
            text-decoration: underline;
        }

        select.form-control {
            cursor: pointer;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: #6366f1;
            color: white;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .btn-success {
            background: #22c55e;
            color: white;
        }

        .btn-success:hover {
            background: #16a34a;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #6366f1;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .info-box h4 {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-box p {
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .info-box ul {
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
            margin-left: 20px;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle input {
            padding-right: 40px;
        }

        .password-toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 5px;
        }

        .test-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 6px;
            margin-top: 24px;
        }

        .test-section h4 {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 12px;
        }

        .test-controls {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .test-controls .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .test-controls {
                flex-direction: column;
            }

            .test-controls .form-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <span class="brand-name">Fix Signature<span class="admin-badge">ADMIN</span></span>
        </div>
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Pengaturan Email</h1>
            <p class="page-subtitle">Konfigurasi SMTP untuk pengiriman email otomatis sistem</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <h4><i class="fas fa-info-circle"></i> Informasi Penting</h4>
            <p>Sistem ini memerlukan konfigurasi SMTP untuk mengirim email seperti:</p>
            <ul>
                <li>Notifikasi persetujuan akun pengguna</li>
                <li>Pengiriman dokumen yang telah ditandatangani</li>
                <li>Notifikasi sistem lainnya</li>
            </ul>
            <p><strong>Untuk Gmail:</strong> Gunakan App Password, bukan password akun biasa. 
            <a href="https://support.google.com/accounts/answer/185833" target="_blank">Pelajari cara membuat App Password</a></p>
        </div>

        <form method="POST" action="">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-server"></i>
                        Konfigurasi SMTP Server
                    </h3>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                SMTP Host <span class="required">*</span>
                            </label>
                            <input type="text" name="smtp_host" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['smtp_host']); ?>" 
                                   placeholder="smtp.gmail.com" required>
                            <div class="form-help">Contoh: smtp.gmail.com, smtp.office365.com</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                SMTP Port <span class="required">*</span>
                            </label>
                            <input type="number" name="smtp_port" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['smtp_port']); ?>" 
                                   placeholder="587" required>
                            <div class="form-help">TLS: 587 | SSL: 465</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                SMTP Username <span class="required">*</span>
                            </label>
                            <input type="text" name="smtp_username" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['smtp_username']); ?>" 
                                   placeholder="your-email@gmail.com" required>
                            <div class="form-help">Biasanya sama dengan email address</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                SMTP Password <span class="required">*</span>
                            </label>
                            <div class="password-toggle">
                                <input type="password" name="smtp_password" id="smtp_password" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['smtp_password']); ?>" 
                                       placeholder="••••••••" required>
                                <button type="button" class="password-toggle-btn" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="password-icon"></i>
                                </button>
                            </div>
                            <div class="form-help">Untuk Gmail, gunakan App Password</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Enkripsi <span class="required">*</span>
                            </label>
                            <select name="smtp_encryption" class="form-control" required>
                                <option value="tls" <?php echo $settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                                <option value="ssl" <?php echo $settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo $settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                From Email <span class="required">*</span>
                            </label>
                            <input type="email" name="from_email" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['from_email']); ?>" 
                                   placeholder="noreply@yourdomain.com" required>
                            <div class="form-help">Email pengirim yang akan muncul</div>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">
                                From Name <span class="required">*</span>
                            </label>
                            <input type="text" name="from_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['from_name']); ?>" 
                                   placeholder="Fix Signature" required>
                            <div class="form-help">Nama pengirim yang akan muncul di email</div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Pengaturan
                        </button>
                    </div>
                </div>
            </div>
        </form>

      
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-vial"></i>
                    Test Koneksi & Email
                </h3>
            </div>
            <div class="card-body">
                <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">
                    Pastikan untuk menyimpan pengaturan terlebih dahulu sebelum melakukan test.
                </p>

                <form method="POST" action="" style="margin-bottom: 20px;">
                    <button type="submit" name="test_connection" class="btn btn-secondary">
                        <i class="fas fa-plug"></i>
                        Test Koneksi SMTP
                    </button>
                </form>

                <div class="test-section">
                    <h4>Kirim Test Email</h4>
                    <form method="POST" action="">
                        <div class="test-controls">
                            <div class="form-group">
                                <label class="form-label">Email Tujuan</label>
                                <input type="email" name="test_email" class="form-control" 
                                       placeholder="test@example.com" required>
                            </div>
                            <button type="submit" name="send_test_email" class="btn btn-success">
                                <i class="fas fa-paper-plane"></i>
                                Kirim Test Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

     
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-book"></i>
                    Panduan SMTP Provider
                </h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                    <div style="background: #f8fafc; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <h4 style="font-size: 14px; color: #1e293b; margin-bottom: 8px;">
                            <i class="fab fa-google" style="color: #ea4335;"></i> Gmail
                        </h4>
                        <p style="font-size: 12px; color: #64748b; line-height: 1.6;">
                            <strong>Host:</strong> smtp.gmail.com<br>
                            <strong>Port:</strong> 587 (TLS)<br>
                            <strong>Password:</strong> App Password
                        </p>
                    </div>

                    <div style="background: #f8fafc; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <h4 style="font-size: 14px; color: #1e293b; margin-bottom: 8px;">
                            <i class="fab fa-microsoft" style="color: #0078d4;"></i> Outlook/Office365
                        </h4>
                        <p style="font-size: 12px; color: #64748b; line-height: 1.6;">
                            <strong>Host:</strong> smtp.office365.com<br>
                            <strong>Port:</strong> 587 (TLS)<br>
                            <strong>Username:</strong> Email lengkap
                        </p>
                    </div>

                    <div style="background: #f8fafc; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <h4 style="font-size: 14px; color: #1e293b; margin-bottom: 8px;">
                            <i class="fas fa-envelope" style="color: #6366f1;"></i> SendGrid
                        </h4>
                        <p style="font-size: 12px; color: #64748b; line-height: 1.6;">
                            <strong>Host:</strong> smtp.sendgrid.net<br>
                            <strong>Port:</strong> 587 (TLS)<br>
                            <strong>Username:</strong> apikey
                        </p>
                    </div>

                    <div style="background: #f8fafc; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <h4 style="font-size: 14px; color: #1e293b; margin-bottom: 8px;">
                            <i class="fas fa-server" style="color: #f97316;"></i> Mailgun
                        </h4>
                        <p style="font-size: 12px; color: #64748b; line-height: 1.6;">
                            <strong>Host:</strong> smtp.mailgun.org<br>
                            <strong>Port:</strong> 587 (TLS)<br>
                            <strong>Username:</strong> postmaster@...
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('smtp_password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>