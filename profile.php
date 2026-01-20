<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

$success = '';
$error = '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nama = clean_input($_POST['nama']);
    $no_hp = clean_input($_POST['no_hp']);
    $email = clean_input($_POST['email']);
    
    if (empty($nama) || empty($no_hp) || empty($email)) {
        $error = "Semua field harus diisi!";
        $tab = 'profile';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
        $tab = 'profile';
    } else {
        $check_email = "SELECT id FROM users WHERE email = '$email' AND id != $user_id";
        $result_email = mysqli_query($conn, $check_email);
        
        if (mysqli_num_rows($result_email) > 0) {
            $error = "Email sudah digunakan oleh user lain!";
            $tab = 'profile';
        } else {
            $sql = "UPDATE users SET nama = '$nama', no_hp = '$no_hp', email = '$email' WHERE id = $user_id";
            
            if (mysqli_query($conn, $sql)) {
                $_SESSION['nama'] = $nama;
                $_SESSION['email'] = $email;
                
                $success = "Profil berhasil diperbarui!";
                $tab = 'profile';
                
                $result = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
                $user = mysqli_fetch_assoc($result);
            } else {
                $error = "Gagal memperbarui profil: " . mysqli_error($conn);
                $tab = 'profile';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua field password harus diisi!";
        $tab = 'password';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Password saat ini salah!";
        $tab = 'password';
    } elseif (strlen($new_password) < 6) {
        $error = "Password baru minimal 6 karakter!";
        $tab = 'password';
    } elseif ($new_password !== $confirm_password) {
        $error = "Password baru dan konfirmasi tidak sama!";
        $tab = 'password';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
        
        if (mysqli_query($conn, $sql)) {
            $success = "Password berhasil diubah!";
            $tab = 'password';
        } else {
            $error = "Gagal mengubah password: " . mysqli_error($conn);
            $tab = 'password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Fix Signature</title>
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
            grid-template-columns: 280px 1fr 1fr;
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

        /* Profile Sidebar */
        .profile-center {
            text-align: center;
            padding: 16px 0;
        }

        .avatar-large {
            width: 80px;
            height: 80px;
            background: #6366f1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            font-weight: 500;
            margin: 0 auto 12px;
        }

        .profile-name {
            font-size: 16px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .profile-email {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 12px;
            word-break: break-all;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            background: #dcfce7;
            color: #16a34a;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .divider {
            height: 1px;
            background: #e8ecf1;
            margin: 16px 0;
        }

        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 13px;
            color: #1e293b;
            font-weight: 500;
        }

        /* Form */
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
        }

        input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        input:disabled {
            background: #f8fafc;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .input-hint {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 3px;
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
            margin-top: 8px;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .tips-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 16px;
        }

        .tips-title {
            font-size: 12px;
            font-weight: 500;
            color: #1e40af;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tips-list {
            font-size: 11px;
            color: #1e40af;
            line-height: 1.6;
            margin-left: 18px;
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
            <h1 class="page-title">Profil Saya</h1>
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
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-user-circle"></i>
                    Informasi Akun
                </div>
                <div class="card-body">
                    <div class="profile-center">
                        <div class="avatar-large">
                            <?php echo strtoupper(substr($user['nama'], 0, 1)); ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                        <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                        <span class="status-badge">
                            <i class="fas fa-check-circle"></i>
                            Aktif
                        </span>
                    </div>

                    <div class="divider"></div>

                    <div class="info-item">
                        <div class="info-label">NIK/NIP</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['nik_nip']); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">NIK KTP</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['nik_ktp']); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Nomor HP</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['no_hp']); ?></div>
                    </div>

                    <div class="divider"></div>

                    <div class="info-item">
                        <div class="info-label">Terdaftar Sejak</div>
                        <div class="info-value"><?php echo date('d M Y', strtotime($user['created_at'])); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Terakhir Update</div>
                        <div class="info-value"><?php echo date('d M Y', strtotime($user['updated_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-edit"></i>
                    Edit Profil
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="nama">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($user['nama']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="no_hp">Nomor HP <span class="required">*</span></label>
                            <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($user['no_hp']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="nik_nip">NIK/NIP</label>
                            <input type="text" id="nik_nip" value="<?php echo htmlspecialchars($user['nik_nip']); ?>" disabled>
                            <div class="input-hint">Tidak dapat diubah</div>
                        </div>

                        <div class="form-group">
                            <label for="nik_ktp">NIK KTP</label>
                            <input type="text" id="nik_ktp" value="<?php echo htmlspecialchars($user['nik_ktp']); ?>" disabled>
                            <div class="input-hint">Tidak dapat diubah</div>
                        </div>

                        <button type="submit" name="update_profile" class="btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    <i class="fas fa-key"></i>
                    Ubah Password
                </div>
                <div class="card-body">
                    <div class="tips-box">
                        <div class="tips-title">
                            <i class="fas fa-shield-alt"></i>
                            Tips Keamanan
                        </div>
                        <div class="tips-list">
                            • Min. 6 karakter<br>
                            • Kombinasi huruf & angka<br>
                            • Ubah secara berkala
                        </div>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Password Saat Ini <span class="required">*</span></label>
                            <div class="password-toggle">
                                <input type="password" id="current_password" name="current_password" required>
                                <i class="fas fa-eye toggle-icon" onclick="togglePassword('current_password', this)"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Password Baru <span class="required">*</span></label>
                            <div class="password-toggle">
                                <input type="password" id="new_password" name="new_password" required>
                                <i class="fas fa-eye toggle-icon" onclick="togglePassword('new_password', this)"></i>
                            </div>
                            <div class="input-hint">Minimal 6 karakter</div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password <span class="required">*</span></label>
                            <div class="password-toggle">
                                <input type="password" id="confirm_password" name="confirm_password" required>
                                <i class="fas fa-eye toggle-icon" onclick="togglePassword('confirm_password', this)"></i>
                            </div>
                        </div>

                        <button type="submit" name="update_password" class="btn-primary">
                            <i class="fas fa-key"></i>
                            Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
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
    </script>
</body>
</html>