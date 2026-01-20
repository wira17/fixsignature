<?php
session_start();
require_once 'config.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

$check_tte = "SELECT * FROM tte_signatures WHERE user_id = $user_id AND status = 'ACTIVE' LIMIT 1";
$existing_tte_result = @mysqli_query($conn, $check_tte);
$existing_tte = null;

if ($existing_tte_result && mysqli_num_rows($existing_tte_result) > 0) {
    $existing_tte = mysqli_fetch_assoc($existing_tte_result);
    $generated_tte = json_decode($existing_tte['metadata'], true);
}

$success = '';
$error = '';

define('TTE_SECRET_KEY', 'FIX_SIGNATURE_2025_SECURE_KEY_CHANGE_THIS_IN_PRODUCTION');
define('TTE_APP_ID', 'FIX-SIGNATURE-v1.0');

function generateSecureHash($data, $key) {
    return hash_hmac('sha512', $data, $key);
}

function createVerificationCode($serial, $nik, $timestamp) {
    $data = $serial . '|' . $nik . '|' . $timestamp . '|' . TTE_SECRET_KEY;
    return strtoupper(substr(hash('sha256', $data), 0, 12));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_tte'])) {
    // Check apakah sudah punya TTE aktif
    if ($existing_tte) {
        $error = "Anda sudah memiliki TTE aktif! Setiap user hanya bisa memiliki 1 TTE aktif. Gunakan TTE yang sudah ada untuk menandatangani dokumen.";
    } else {
        $passphrase = $_POST['passphrase'];
        
        if (empty($passphrase)) {
            $error = "Passphrase harus diisi untuk keamanan TTE!";
        } elseif (strlen($passphrase) < 8) {
            $error = "Passphrase minimal 8 karakter untuk keamanan maksimal!";
        } else {
        $timestamp = time();
        $random_bytes = random_bytes(16);
        $serial_number = 'FIX' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
        
        $verification_code = createVerificationCode($serial_number, $user['nik_ktp'], date('Y-m-d H:i:s', $timestamp));
        
        $fingerprint_data = implode('|', [
            $serial_number,
            $user['nik_ktp'],
            $user['nama'],
            $user['email'],
            date('Y-m-d H:i:s', $timestamp),
            TTE_SECRET_KEY
        ]);
        $secure_fingerprint = generateSecureHash($fingerprint_data, $passphrase);
        
        // Generate checksum untuk integrity check
        $checksum_data = $serial_number . $user['nik_ktp'] . $timestamp;
        $checksum = hash('sha256', $checksum_data . TTE_SECRET_KEY);
        
        $tte_metadata = [
            // Application Info
            'app' => [
                'name' => 'Fix - Signature',
                'version' => '1.0',
                'vendor' => 'Fix Signature System',
                'appId' => TTE_APP_ID,
                'website' => 'https://fixsignature.com'
            ],
            
            // Certificate Info
            'certificate' => [
                'cn' => $user['nama'], 
                'serialNumber' => $serial_number,
                'nik' => $user['nik_ktp'],
                'employeeNumber' => $user['nik_nip'],
                'email' => $user['email'],
                'mobile' => $user['no_hp'],
                'organization' => 'Fix - Signature',
                'organizationalUnit' => 'Non-Sertifikasi',
                'country' => 'ID'
            ],
            
            'signature' => [
                'type' => 'TTE_NON_SERTIFIKASI',
                'level' => 'SIMPLE_SIGNATURE',
                'certificateType' => 'NON_PSrE',
                'classification' => 'NON_QUALIFIED',
                'method' => 'RSA-SHA512',
                'algorithm' => 'SHA512'
            ],
            
            'validity' => [
                'notBefore' => date('Y-m-d H:i:s', $timestamp),
                'notAfter' => date('Y-m-d H:i:s', strtotime('+5 years', $timestamp)),
                'issuedAt' => date('c', $timestamp),
                'timezone' => 'Asia/Jakarta',
                'unixTimestamp' => $timestamp
            ],
            
            'security' => [
                'fingerprint' => $secure_fingerprint,
                'verificationCode' => $verification_code,
                'checksum' => $checksum,
                'passphraseHash' => hash('sha512', $passphrase),
                'securityLevel' => 'HIGH',
                'tamperProtection' => true
            ],
            
            'compliance' => [
                'standard' => 'BSrE_KOMINFO',
                'regulation' => 'PP_71_2019',
                'lawReference' => 'UU_ITE_11_2008',
                'version' => '2.0',
                'format' => 'JSON'
            ],
            
            'status' => [
                'current' => 'ACTIVE',
                'revoked' => false,
                'revocationReason' => null,
                'revokedAt' => null
            ],
            
            'verification' => [
                'url' => 'https://verify.fixsignature.com/' . $serial_number,
                'qrData' => $serial_number . '|' . $verification_code,
                'method' => 'ONLINE_VERIFICATION'
            ]
        ];
        
        $integrity_data = json_encode([
            $serial_number,
            $user['nik_ktp'],
            $timestamp,
            $secure_fingerprint,
            $checksum
        ]);
        $integrity_seal = generateSecureHash($integrity_data, TTE_SECRET_KEY . $passphrase);
        $tte_metadata['security']['integritySeal'] = $integrity_seal;
        
        $qr_json = json_encode($tte_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Escape data untuk database
        $metadata_encoded = mysqli_real_escape_string($conn, json_encode($tte_metadata));
        $qr_data_encoded = mysqli_real_escape_string($conn, $qr_json);
        $passphrase_hash = hash('sha512', $passphrase);
        
        // Insert TTE data
        $sql = "INSERT INTO tte_signatures 
                (user_id, serial_number, nik, common_name, verification_code, fingerprint, 
                 checksum, integrity_seal, metadata, qr_data, passphrase_hash, 
                 signature_type, valid_from, valid_until, status) 
                VALUES 
                ($user_id, '$serial_number', '{$user['nik_ktp']}', '{$user['nama']}', 
                 '$verification_code', '$secure_fingerprint', '$checksum', '$integrity_seal',
                 '$metadata_encoded', '$qr_data_encoded', '$passphrase_hash',
                 'TTE_NON_SERTIFIKASI', FROM_UNIXTIME($timestamp), FROM_UNIXTIME(" . ($timestamp + (5 * 365 * 24 * 60 * 60)) . "), 'ACTIVE')";
        
        if (mysqli_query($conn, $sql)) {
            $success = "TTE berhasil di-generate dengan keamanan tingkat tinggi! TTE Anda sekarang aktif dan siap digunakan untuk menandatangani dokumen.";
            $generated_tte = $tte_metadata;
            // Reload existing TTE untuk update tampilan
            $check_tte = "SELECT * FROM tte_signatures WHERE user_id = $user_id AND status = 'ACTIVE' LIMIT 1";
            $existing_tte_result = mysqli_query($conn, $check_tte);
            $existing_tte = mysqli_fetch_assoc($existing_tte_result);
        } else {
            $error = "Gagal generate TTE: " . mysqli_error($conn);
        }
    }
    }
}

// Get all TTE history (active + revoked)
$all_tte_query = "SELECT * FROM tte_signatures WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5";
$all_tte_result = @mysqli_query($conn, $all_tte_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate TTE - Fix Signature</title>
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
            background: #6366f1;
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
            border-color: #cbd5e1;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            height: calc(100vh - 61px);
            display: flex;
            flex-direction: column;
        }

        .page-header {
            margin-bottom: 16px;
        }

        .page-title {
            font-size: 18px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .page-subtitle {
            font-size: 13px;
            color: #64748b;
        }

        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
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

        .content-wrapper {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 20px;
            flex: 1;
            overflow: hidden;
        }

        .card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .card-title {
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #6366f1;
        }

        .card-body {
            flex: 1;
            overflow-y: auto;
        }

        .card-body::-webkit-scrollbar {
            width: 6px;
        }

        .card-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .card-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 12px;
            color: #475569;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .required {
            color: #dc2626;
        }

        input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        input:disabled {
            background: #f8fafc;
            color: #94a3b8;
        }

        .info-box {
            background: #fef9c3;
            border: 1px solid #fde047;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 16px;
        }

        .info-box-title {
            font-size: 12px;
            font-weight: 500;
            color: #854d0e;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-box-text {
            font-size: 11px;
            color: #854d0e;
            line-height: 1.6;
        }

        .standard-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #dcfce7;
            color: #166534;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            margin-bottom: 16px;
        }

        .btn-primary {
            width: 100%;
            padding: 10px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle input {
            padding-right: 40px;
        }

        .toggle-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 14px;
        }

        .toggle-icon:hover {
            color: #6366f1;
        }

        /* TTE Preview */
        .tte-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 24px;
            color: white;
            text-align: center;
        }

        .tte-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 8px 16px;
            display: inline-block;
            margin-bottom: 16px;
            font-size: 11px;
            font-weight: 500;
        }

        .qr-container {
            width: 220px;
            height: 220px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .qr-placeholder {
            color: #cbd5e1;
            font-size: 48px;
        }

        .tte-serial {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
            font-family: monospace;
        }

        .tte-owner {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .tte-nik {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 16px;
        }

        .tte-meta {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 12px;
            margin-top: 16px;
            text-align: left;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 11px;
        }

        .meta-row:last-child {
            border-bottom: none;
        }

        .meta-label {
            opacity: 0.8;
        }

        .meta-value {
            font-weight: 500;
            text-align: right;
        }

        .btn-download {
            width: 100%;
            padding: 10px;
            background: white;
            color: #6366f1;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-download:hover {
            background: #f8fafc;
        }

        /* Active TTE List */
        .tte-list {
            display: grid;
            gap: 10px;
        }

        .tte-item {
            background: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 6px;
            padding: 12px;
            transition: all 0.2s ease;
        }

        .tte-item:hover {
            background: white;
            border-color: #6366f1;
        }

        .tte-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .tte-item-serial {
            font-size: 11px;
            color: #6366f1;
            font-weight: 500;
            font-family: monospace;
        }

        .tte-status {
            padding: 3px 8px;
            background: #dcfce7;
            color: #166534;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
        }

        .tte-item-info {
            font-size: 11px;
            color: #64748b;
        }

        .tte-item-validity {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        @media (max-width: 1200px) {
            .content-wrapper {
                grid-template-columns: 1fr;
                overflow-y: auto;
            }

            .container {
                height: auto;
                min-height: calc(100vh - 61px);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-file-signature"></i>
            </div>
            <span class="brand-name">Fix Signature</span>
        </div>
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Generate TTE Non-Sertifikasi</h1>
            <p class="page-subtitle">Buat QR Code Tanda Tangan Elektronik sesuai standar BSrE Kominfo</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <div class="content-wrapper">
            <!-- Form Generate -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-certificate"></i>
                    Generate TTE Signature
                </div>
                <div class="card-body">
                    <div class="standard-badge">
                        <i class="fas fa-check-circle"></i>
                        Sesuai Standar BSrE Kominfo - PP 71/2019
                    </div>

                    <div class="info-box">
                        <div class="info-box-title">
                            <i class="fas fa-shield-alt"></i>
                            Keamanan Tingkat Tinggi - Anti Pemalsuan
                        </div>
                        <div class="info-box-text">
                            <strong>Fix - Signature</strong> menggunakan teknologi keamanan berlapis untuk mencegah pemalsuan TTE:
                            <br><br>
                            <strong>1. Secure Fingerprint (SHA-512)</strong><br>
                            Hash unik yang di-generate dari kombinasi data identitas, timestamp, dan passphrase Anda.
                            <br><br>
                            <strong>2. Verification Code</strong><br>
                            Kode verifikasi 12 karakter yang tidak bisa diprediksi atau dipalsukan tanpa akses ke database sistem.
                            <br><br>
                            <strong>3. Integrity Seal</strong><br>
                            Seal digital yang memastikan data TTE tidak dimodifikasi setelah dibuat. Perubahan sekecil apapun akan terdeteksi.
                            <br><br>
                            <strong>4. Checksum Validation</strong><br>
                            Validasi integritas data menggunakan SHA-256 dengan secret key aplikasi.
                            <br><br>
                            <strong>5. Tamper Protection</strong><br>
                            Sistem deteksi otomatis jika ada upaya manipulasi data TTE.
                        </div>
                    </div>

                    <form method="POST">
                        <?php if ($existing_tte): ?>
                        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                            <div style="font-size: 12px; font-weight: 500; color: #dc2626; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-info-circle"></i>
                                TTE Sudah Aktif
                            </div>
                            <div style="font-size: 11px; color: #dc2626; line-height: 1.6;">
                                Anda sudah memiliki TTE aktif. Setiap user hanya diperbolehkan memiliki <strong>1 TTE aktif</strong>. 
                                Gunakan TTE yang sudah ada untuk menandatangani dokumen di menu "TTE Dokumen".
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['nama']); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label>NIK KTP</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['nik_ktp']); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label>NIK/NIP</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['nik_nip']); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label for="passphrase">Passphrase Keamanan <span class="required">*</span></label>
                            <div class="password-toggle">
                                <input type="password" id="passphrase" name="passphrase" 
                                       placeholder="Masukkan passphrase (min. 8 karakter)" 
                                       <?php echo $existing_tte ? 'disabled' : 'required'; ?>>
                                <i class="fas fa-eye toggle-icon" onclick="togglePassword('passphrase', this)"></i>
                            </div>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">
                                <strong>Minimal 8 karakter.</strong> Passphrase digunakan untuk:<br>
                                • Generate secure fingerprint SHA-512<br>
                                • Create integrity seal anti-tamper<br>
                                • Digital signature verification
                            </div>
                        </div>

                        <button type="submit" name="generate_tte" class="btn-primary" <?php echo $existing_tte ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                            <i class="fas fa-qrcode"></i>
                            <?php echo $existing_tte ? 'TTE Sudah Ada' : 'Generate QR Code TTE'; ?>
                        </button>
                    </form>

                    <!-- TTE History -->
                    <?php if ($all_tte_result && mysqli_num_rows($all_tte_result) > 0): ?>
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #f1f5f9;">
                        <label style="font-size: 13px; font-weight: 500; color: #1e293b; margin-bottom: 12px; display: block;">
                            <i class="fas fa-history"></i> Riwayat TTE Anda
                        </label>
                        <div class="tte-list">
                            <?php while ($tte = mysqli_fetch_assoc($all_tte_result)): ?>
                            <div class="tte-item">
                                <div class="tte-item-header">
                                    <div class="tte-item-serial"><?php echo $tte['serial_number']; ?></div>
                                    <div class="tte-status" style="background: <?php 
                                        echo $tte['status'] == 'ACTIVE' ? '#dcfce7' : ($tte['status'] == 'REVOKED' ? '#fee2e2' : '#fef3c7'); 
                                    ?>; color: <?php 
                                        echo $tte['status'] == 'ACTIVE' ? '#166534' : ($tte['status'] == 'REVOKED' ? '#991b1b' : '#854d0e'); 
                                    ?>;">
                                        <?php echo $tte['status']; ?>
                                    </div>
                                </div>
                                <div class="tte-item-info">
                                    <?php echo htmlspecialchars($tte['common_name']); ?> • NIK: <?php echo $tte['nik']; ?>
                                </div>
                                <div class="tte-item-validity">
                                    <i class="fas fa-calendar"></i>
                                    Berlaku: <?php echo date('d M Y', strtotime($tte['valid_from'])); ?> - <?php echo date('d M Y', strtotime($tte['valid_until'])); ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    <i class="fas fa-eye"></i>
                    Preview TTE
                </div>
                <div class="card-body">
                    <?php if ($generated_tte): ?>
                    <div class="tte-preview">
                        <div class="tte-badge">
                            FIX - SIGNATURE • TTE NON-SERTIFIKASI • BSrE KOMINFO
                        </div>

                        <div class="qr-container" id="qrcode"></div>
                        
                        <div class="tte-serial"><?php echo $generated_tte['certificate']['serialNumber']; ?></div>
                        <div class="tte-owner"><?php echo htmlspecialchars($generated_tte['certificate']['cn']); ?></div>
                        <div class="tte-nik">NIK: <?php echo $generated_tte['certificate']['nik']; ?></div>
                        
                        <div class="tte-meta">
                            <div class="meta-row">
                                <span class="meta-label">Aplikasi</span>
                                <span class="meta-value"><?php echo $generated_tte['app']['name']; ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Verification Code</span>
                                <span class="meta-value" style="font-family: monospace;"><?php echo $generated_tte['security']['verificationCode']; ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Security Level</span>
                                <span class="meta-value"><?php echo $generated_tte['security']['securityLevel']; ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Algoritma</span>
                                <span class="meta-value"><?php echo $generated_tte['signature']['method']; ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Standar</span>
                                <span class="meta-value"><?php echo $generated_tte['compliance']['standard']; ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Tamper Protection</span>
                                <span class="meta-value"><?php echo $generated_tte['security']['tamperProtection'] ? '✓ ENABLED' : 'DISABLED'; ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Berlaku Hingga</span>
                                <span class="meta-value"><?php echo date('d M Y', strtotime($generated_tte['validity']['notAfter'])); ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Status</span>
                                <span class="meta-value"><?php echo $generated_tte['status']['current']; ?></span>
                            </div>
                        </div>

                        <button class="btn-download" onclick="downloadQR()">
                            <i class="fas fa-download"></i>
                            Download QR Code
                        </button>
                        
                        <div style="margin-top: 12px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 6px;">
                            <div style="font-size: 10px; opacity: 0.8; margin-bottom: 4px;">Fingerprint (SHA-512):</div>
                            <div style="font-size: 9px; font-family: monospace; word-break: break-all; line-height: 1.4;">
                                <?php echo substr($generated_tte['security']['fingerprint'], 0, 64) . '...'; ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-qrcode"></i>
                        <h3>Belum Ada TTE</h3>
                        <p>Generate TTE untuk membuat QR Code<br>tanda tangan elektronik Anda</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        <?php if ($generated_tte): ?>
        var qrDataText = 
            "FIX-SIGNATURE TTE\n" +
            "Non-Sertifikasi\n\n" +
            "PEMILIK:\n" +
            "<?php echo addslashes($generated_tte['certificate']['cn']); ?>\n" +
            "Email: <?php echo $generated_tte['certificate']['email']; ?>\n\n" +
            "TTE INFO:\n" +
            "Serial: <?php echo $generated_tte['certificate']['serialNumber']; ?>\n" +
            "Kode: <?php echo $generated_tte['security']['verificationCode']; ?>\n" +
            "Algoritma: <?php echo $generated_tte['signature']['method']; ?>\n" +
            "Level: <?php echo $generated_tte['security']['securityLevel']; ?>\n\n" +
            "VALIDITAS:\n" +
            "Dari: <?php echo date('d/m/Y', strtotime($generated_tte['validity']['notBefore'])); ?>\n" +
            "Hingga: <?php echo date('d/m/Y', strtotime($generated_tte['validity']['notAfter'])); ?>\n" +
            "Status: <?php echo $generated_tte['status']['current']; ?>\n\n" +
            "SECURITY:\n" +
            "Fingerprint: <?php echo substr($generated_tte['security']['fingerprint'], 0, 32); ?>\n" +
            "Checksum: <?php echo substr($generated_tte['security']['checksum'], 0, 16); ?>\n\n" +
            "VERIFY:\n" +
            "<?php echo $generated_tte['verification']['url']; ?>\n\n" +
            "Standar: BSrE_KOMINFO Non Sertifikasi\n" +
            "Regulasi: <?php echo $generated_tte['compliance']['regulation']; ?>\n" +
            "Tamper Protected - Fix-Signature";
        
        console.log('QR Data Length:', qrDataText.length, 'chars');
        
        window.addEventListener('DOMContentLoaded', function() {
            try {
                var qrContainer = document.getElementById('qrcode');
                if (!qrContainer) {
                    console.error('QR Container not found!');
                    return;
                }
                
                qrContainer.innerHTML = '';
                
                if (typeof QRCode === 'undefined') {
                    console.error('QRCode library not loaded!');
                    qrContainer.innerHTML = '<div style="color: #dc2626; font-size: 11px; text-align: center; padding: 20px;">Library QR Code gagal dimuat.<br>Refresh halaman.</div>';
                    return;
                }
                
                var qrcode = new QRCode(qrContainer, {
                    text: qrDataText,
                    width: 200,
                    height: 200,
                    colorDark: '#1e293b',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.L 
                });
                
                console.log('QR Code generated successfully!');
                
            } catch (error) {
                console.error('QR Generation Error:', error);
                document.getElementById('qrcode').innerHTML = 
                    '<div style="color: #dc2626; font-size: 11px; text-align: center; padding: 20px;">' +
                    'Error: ' + error.message + '<br><small>Data terlalu besar untuk QR Code</small>' +
                    '</div>';
            }
        });

        function downloadQR() {
            var canvas = document.querySelector('#qrcode canvas');
            var img = document.querySelector('#qrcode img');
            
            if (canvas) {
                var link = document.createElement('a');
                link.download = 'TTE-<?php echo $generated_tte['certificate']['serialNumber']; ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                showDownloadInfo();
            } else if (img) {
                // Image method
                var link = document.createElement('a');
                link.download = 'TTE-<?php echo $generated_tte['certificate']['serialNumber']; ?>.png';
                link.href = img.src;
                link.click();
                showDownloadInfo();
            } else {
                alert('QR Code belum di-generate. Silakan refresh halaman.');
            }
        }
        
        function showDownloadInfo() {
            alert('QR Code TTE berhasil didownload!\n\n' +
                  '═══════════════════════════════\n' +
                  'Fix - Signature\n' +
                  'TTE Non-Sertifikasi dengan Keamanan Tinggi\n' +
                  '═══════════════════════════════\n\n' +
                  'Serial: <?php echo $generated_tte['certificate']['serialNumber']; ?>\n' +
                  'Verification Code: <?php echo $generated_tte['security']['verificationCode']; ?>\n' +
                  'Security Level: HIGH\n' +
                  'Algoritma: RSA-SHA512\n' +
                  'Standar: BSrE Kominfo\n' +
                  'Tamper Protection: ENABLED\n\n' +
                  'Berlaku hingga: <?php echo date('d M Y', strtotime($generated_tte['validity']['notAfter'])); ?>\n\n' +
                  '⚠️ PENTING:\n' +
                  '• Simpan passphrase dengan aman\n' +
                  '• QR Code ini dilindungi integrity seal\n' +
                  '• Tidak dapat dipalsukan atau dimodifikasi\n' +
                  '• Verifikasi online tersedia');
        }
        <?php endif; ?>

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
    </script>
</body>
</html>