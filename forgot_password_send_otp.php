<?php
session_start();
require_once 'config.php';
require_once 'MailHelper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email harus diisi!']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Format email tidak valid!']);
    exit;
}

$stmt = $conn->prepare("SELECT id, nama, email FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email tidak terdaftar dalam sistem!']);
    $stmt->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

date_default_timezone_set('Asia/Jakarta');

// Generate 6-digit OTP
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

$expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$check_stmt = $conn->prepare("SELECT id FROM password_resets WHERE email = ?");
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $update_stmt = $conn->prepare("UPDATE password_resets SET otp = ?, expires_at = ?, used = 0, created_at = NOW() WHERE email = ?");
    $update_stmt->bind_param("sss", $otp, $expiry, $email);
    $update_stmt->execute();
    $update_stmt->close();
} else {
    $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("sss", $email, $otp, $expiry);
    $insert_stmt->execute();
    $insert_stmt->close();
}
$check_stmt->close();

try {
    $mailer = new MailHelper($conn);
    
    $subject = "Kode OTP Reset Password - Fix Signature";
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #6366f1; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; }
            .otp-box { background: white; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; border: 2px solid #6366f1; }
            .otp-code { font-size: 32px; font-weight: bold; color: #6366f1; letter-spacing: 8px; }
            .footer { text-align: center; padding: 20px; color: #64748b; font-size: 12px; }
            .warning { background: #fef9c3; border-left: 4px solid #f59e0b; padding: 12px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🔐 Reset Password</h2>
            </div>
            <div class='content'>
                <p>Halo <strong>{$user['nama']}</strong>,</p>
                <p>Anda telah meminta untuk mereset password akun Fix Signature Anda.</p>
                
                <div class='otp-box'>
                    <p style='margin: 0 0 10px 0; font-size: 14px; color: #64748b;'>Kode OTP Anda:</p>
                    <div class='otp-code'>{$otp}</div>
                    <p style='margin: 10px 0 0 0; font-size: 12px; color: #64748b;'>Berlaku selama 10 menit</p>
                </div>
                
                <p>Masukkan kode OTP di atas pada halaman verifikasi untuk melanjutkan proses reset password.</p>
                
                <div class='warning'>
                    <strong>⚠️ Penting:</strong>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>Jangan bagikan kode ini kepada siapapun</li>
                        <li>Kode ini hanya berlaku selama 10 menit</li>
                        <li>Jika Anda tidak meminta reset password, abaikan email ini</li>
                    </ul>
                </div>
                
                <p style='margin-top: 20px;'>
                    Jika Anda mengalami kesulitan, silakan hubungi administrator kami.
                </p>
            </div>
            <div class='footer'>
                <p>Email ini dikirim secara otomatis dari sistem Fix Signature</p>
                <p>&copy; 2026 Fix Signature. All rights reserved.</p>
                <p>M. Wira Sb. S.Kom - 082177846209</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mailer->sendEmail($email, $subject, $body);
    
    error_log("OTP sent to {$email}: {$otp}");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Kode OTP telah dikirim ke email Anda. Silakan cek inbox atau folder spam.'
    ]);
    
} catch (Exception $e) {
    error_log("Failed to send OTP email: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal mengirim email. Pastikan konfigurasi SMTP sudah benar. Error: ' . $e->getMessage()
    ]);
}
?>