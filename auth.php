<?php
session_start();
require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'login';

// Proses Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi!";
    } else {
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $user['password'])) {
                // Cek status akun
                if ($user['status'] == 'pending') {
                    $error = "Akun Anda masih menunggu persetujuan admin. Silakan hubungi administrator.";
                } elseif ($user['status'] == 'inactive') {
                    $error = "Akun Anda telah dinonaktifkan. Silakan hubungi administrator.";
                } else {
                    // Login berhasil
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['nik_nip'] = $user['nik_nip'];
                    $_SESSION['nama'] = $user['nama'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirect berdasarkan role
                    if ($user['role'] == 'admin') {
                        header("Location: admin/dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit();
                }
            } else {
                $error = "Email atau password salah!";
            }
        } else {
            $error = "Email atau password salah!";
        }
    }
    $active_tab = 'login';
}

// Proses Register
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $nik_nip = clean_input($_POST['nik_nip']);
    $nik_ktp = clean_input($_POST['nik_ktp']);
    $nama = clean_input($_POST['nama']);
    $no_hp = clean_input($_POST['no_hp']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($nik_nip) || empty($nik_ktp) || empty($nama) || empty($no_hp) || empty($email) || empty($password)) {
        $error = "Semua field harus diisi!";
    } elseif (strlen($nik_ktp) != 16) {
        $error = "NIK KTP harus 16 digit!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif ($password !== $confirm_password) {
        $error = "Password dan konfirmasi password tidak sama!";
    } else {
        $check_nik = "SELECT id FROM users WHERE nik_nip = '$nik_nip'";
        $result_nik = mysqli_query($conn, $check_nik);
        
        $check_ktp = "SELECT id FROM users WHERE nik_ktp = '$nik_ktp'";
        $result_ktp = mysqli_query($conn, $check_ktp);
        
        $check_email = "SELECT id FROM users WHERE email = '$email'";
        $result_email = mysqli_query($conn, $check_email);

        if (mysqli_num_rows($result_nik) > 0) {
            $error = "NIK/NIP sudah terdaftar!";
        } elseif (mysqli_num_rows($result_ktp) > 0) {
            $error = "NIK KTP sudah terdaftar!";
        } elseif (mysqli_num_rows($result_email) > 0) {
            $error = "Email sudah terdaftar!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (nik_nip, nik_ktp, nama, no_hp, email, password) 
                    VALUES ('$nik_nip', '$nik_ktp', '$nama', '$no_hp', '$email', '$hashed_password')";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Registrasi berhasil! Akun Anda menunggu persetujuan admin. Kami akan mengirim notifikasi setelah akun disetujui.";
                $active_tab = 'login';
            } else {
                $error = "Terjadi kesalahan: " . mysqli_error($conn);
            }
        }
    }
    if ($error) {
        $active_tab = 'register';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication - Fix Signature</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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

        .auth-container {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 380px 1fr;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .auth-sidebar {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            padding: 48px 36px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .logo i {
            font-size: 36px;
            color: #6366f1;
        }

        .app-name {
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .app-tagline {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 300;
        }

        .features {
            margin-top: 40px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 24px;
        }

        .feature-icon {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-icon i {
            font-size: 14px;
        }

        .feature-text h4 {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .feature-text p {
            font-size: 12px;
            opacity: 0.85;
            line-height: 1.5;
        }

        .copyright {
            margin-top: 48px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .copyright-text {
            font-size: 11px;
            opacity: 0.8;
            margin-bottom: 4px;
        }

        .copyright-contact {
            font-size: 11px;
            opacity: 0.7;
        }

        .auth-content {
            padding: 48px 40px;
        }

        .tab-container {
            display: flex;
            gap: 8px;
            margin-bottom: 32px;
            background: #f8fafc;
            padding: 4px;
            border-radius: 8px;
        }

        .tab-btn {
            flex: 1;
            padding: 10px 20px;
            border: none;
            background: transparent;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            background: white;
            color: #1e293b;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-title {
            font-size: 20px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .form-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 28px;
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
            margin-bottom: 18px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
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
            padding: 10px 14px;
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
            padding: 11px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .btn-primary {
            background: #6366f1;
            color: white;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            background: white;
            padding: 0 12px;
            color: #94a3b8;
            font-size: 12px;
            position: relative;
        }

        .info-text {
            text-align: center;
            font-size: 12px;
            color: #64748b;
            margin-top: 16px;
        }

        .forgot-password-link {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 16px;
        }

        .forgot-password-link a {
            color: #6366f1;
            font-size: 12px;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-password-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .auth-container {
                grid-template-columns: 1fr;
                max-width: 480px;
            }

            .auth-sidebar {
                padding: 32px 24px;
            }

            .features {
                display: none;
            }

            .copyright {
                margin-top: 24px;
                padding-top: 20px;
            }

            .auth-content {
                padding: 32px 24px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <!-- Sidebar -->
        <div class="auth-sidebar">
            <div>
                <div class="logo-section">
                    <div class="logo">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <div class="app-name">Fix Signature</div>
                    <div class="app-tagline">Sistem Tanda Tangan Digital Profesional</div>
                </div>

                <div class="features">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Aman & Terpercaya</h4>
                            <p>Dokumen Anda dilindungi dengan enkripsi tingkat enterprise</p>
                        </div>
                    </div>

                    <div class="feature-item tte-info" data-bs-toggle="modal" data-bs-target="#modalTteNonSertifikasi" style="cursor:pointer;">
                        <div class="feature-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div class="feature-text">
                            <h4>TTE Non Sertifikasi</h4>
                            <p>
                                Informasi hukum & keabsahan<br>
                                Tanda Tangan Elektronik Non Sertifikasi
                                <br><small><em>Klik untuk membaca</em></small>
                            </p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-hospital"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Fix - Signature</h4>
                            <p>
                                Jl. xxxxx.xxxx.xxxxx<br>
                                Kabupaten xxx.xxx <br>
                                <strong>Telp:</strong> 082177846209<br>
                                <strong>Provinsi:</strong> Jambi
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="copyright">
                <div class="copyright-text">
                    &copy; 2026 Fix Signature. All rights reserved.
                </div>
                <div class="copyright-contact">
                    M. Wira Sb. S.Kom – 082177846209
                </div>
            </div>
        </div>

        <!-- Auth Content -->
        <div class="auth-content">
            <!-- Tab Buttons -->
            <div class="tab-container">
                <button class="tab-btn <?php echo $active_tab == 'login' ? 'active' : ''; ?>" onclick="switchTab('login')">
                    Masuk
                </button>
                <button class="tab-btn <?php echo $active_tab == 'register' ? 'active' : ''; ?>" onclick="switchTab('register')">
                    Daftar
                </button>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <!-- Login Tab -->
            <div id="login-tab" class="tab-content <?php echo $active_tab == 'login' ? 'active' : ''; ?>">
                <h2 class="form-title">Masuk ke Akun</h2>
                <p class="form-subtitle">Masukkan kredensial Anda untuk melanjutkan</p>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="login_email">Email</label>
                        <input type="email" id="login_email" name="email" placeholder="nama@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="login_password">Password</label>
                        <div class="input-group">
                            <input type="password" id="login_password" name="password" placeholder="Masukkan password" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('login_password', this)"></i>
                        </div>
                    </div>

                    <div class="forgot-password-link">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Lupa Password?</a>
                    </div>

                    <button type="submit" name="login" class="btn btn-primary">
                        Masuk
                    </button>
                </form>

                <div class="info-text">
                    Belum punya akun? <a href="#" onclick="switchTab('register')" style="color: #6366f1; font-weight: 500;">Daftar sekarang</a>
                </div>
            </div>

            <!-- Register Tab -->
            <div id="register-tab" class="tab-content <?php echo $active_tab == 'register' ? 'active' : ''; ?>">
                <h2 class="form-title">Buat Akun Baru</h2>
                <p class="form-subtitle">Lengkapi data di bawah untuk mendaftar</p>

                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nik_nip">NIK/NIP</label>
                            <input type="text" id="nik_nip" name="nik_nip" placeholder="NIK/NIP" 
                                   value="<?php echo isset($_POST['nik_nip']) ? htmlspecialchars($_POST['nik_nip']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="nik_ktp">NIK KTP</label>
                            <input type="text" id="nik_ktp" name="nik_ktp" placeholder="16 digit" maxlength="16" 
                                   value="<?php echo isset($_POST['nik_ktp']) ? htmlspecialchars($_POST['nik_ktp']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="nama">Nama Lengkap</label>
                        <input type="text" id="nama" name="nama" placeholder="Nama lengkap sesuai KTP" 
                               value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="no_hp">Nomor HP</label>
                            <input type="text" id="no_hp" name="no_hp" placeholder="08xxxxxxxxxx" 
                                   value="<?php echo isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="reg_email">Email</label>
                            <input type="email" id="reg_email" name="email" placeholder="nama@email.com" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg_password">Password</label>
                            <div class="input-group">
                                <input type="password" id="reg_password" name="password" placeholder="Min. 6 karakter" required>
                                <i class="fas fa-eye password-toggle" onclick="togglePassword('reg_password', this)"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password</label>
                            <div class="input-group">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required>
                                <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', this)"></i>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="register" class="btn btn-primary">
                        Daftar Sekarang
                    </button>
                </form>

                <div class="info-text">
                    Sudah punya akun? <a href="#" onclick="switchTab('login')" style="color: #6366f1; font-weight: 500;">Masuk di sini</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal TTE Non Sertifikasi -->
    <div class="modal fade" id="modalTteNonSertifikasi" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border: none; border-radius: 12px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
                <div class="modal-header" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border: none; padding: 24px 32px; border-radius: 12px 12px 0 0;">
                    <h5 class="modal-title" style="font-size: 18px; font-weight: 500; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-gavel" style="font-size: 20px;"></i> 
                        Tanda Tangan Elektronik Non Sertifikasi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 32px; background: #f8fafc; max-height: 70vh; overflow-y: auto;">
                    
                    <!-- Pengertian -->
                    <div style="background: white; border-radius: 8px; padding: 24px; margin-bottom: 20px; border-left: 4px solid #6366f1;">
                        <h6 style="color: #1e293b; font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-info-circle" style="color: #6366f1;"></i>
                            Pengertian Tanda Tangan Elektronik Non Sertifikasi
                        </h6>
                        <p style="color: #475569; font-size: 13px; line-height: 1.8; margin-bottom: 12px;">
                            <strong style="color: #1e293b;">Tanda Tangan Elektronik (TTE) Non Sertifikasi</strong> adalah tanda tangan elektronik yang dibuat <strong>tanpa menggunakan Sertifikat Elektronik</strong> yang diterbitkan oleh <em>Penyelenggara Sertifikasi Elektronik (PSrE)</em> yang telah mendapatkan akreditasi atau pengakuan dari pemerintah.
                        </p>
                        <p style="color: #475569; font-size: 13px; line-height: 1.8; margin: 0;">
                            TTE Non Sertifikasi tetap menggunakan <strong>metode elektronik</strong> untuk memastikan <span style="background: #eff6ff; padding: 2px 6px; border-radius: 3px; color: #1e40af;">identitas penanda tangan</span>, <span style="background: #eff6ff; padding: 2px 6px; border-radius: 3px; color: #1e40af;">persetujuan terhadap dokumen</span>, serta <span style="background: #eff6ff; padding: 2px 6px; border-radius: 3px; color: #1e40af;">keutuhan (integritas) dokumen elektronik</span>.
                        </p>
                    </div>

                    <!-- Dasar Hukum -->
                    <div style="background: white; border-radius: 8px; padding: 24px; margin-bottom: 20px; border-left: 4px solid #22c55e;">
                        <h6 style="color: #1e293b; font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-balance-scale" style="color: #22c55e;"></i>
                            Dasar Hukum & Regulasi yang Berlaku di Indonesia
                        </h6>
                        <p style="color: #475569; font-size: 13px; line-height: 1.8; margin-bottom: 16px;">
                            Keabsahan TTE Non Sertifikasi diatur secara jelas dalam peraturan perundang-undangan berikut:
                        </p>
                        <div style="display: grid; gap: 12px;">
                            <div style="background: #f0fdf4; border-radius: 6px; padding: 12px 16px; border-left: 3px solid #22c55e;">
                                <div style="font-weight: 600; color: #166534; font-size: 13px; margin-bottom: 4px;">
                                    📋 Undang-Undang Nomor 11 Tahun 2008
                                </div>
                                <div style="color: #166534; font-size: 12px;">
                                    Tentang Informasi dan Transaksi Elektronik (UU ITE)
                                </div>
                            </div>
                            <div style="background: #f0fdf4; border-radius: 6px; padding: 12px 16px; border-left: 3px solid #22c55e;">
                                <div style="font-weight: 600; color: #166534; font-size: 13px; margin-bottom: 4px;">
                                    📋 Undang-Undang Nomor 19 Tahun 2016
                                </div>
                                <div style="color: #166534; font-size: 12px;">
                                    Tentang Perubahan atas UU ITE
                                </div>
                            </div>
                            <div style="background: #f0fdf4; border-radius: 6px; padding: 12px 16px; border-left: 3px solid #22c55e;">
                                <div style="font-weight: 600; color: #166534; font-size: 13px; margin-bottom: 4px;">
                                    📋 Peraturan Pemerintah Nomor 71 Tahun 2019
                                </div>
                                <div style="color: #166534; font-size: 12px;">
                                    Tentang Penyelenggaraan Sistem dan Transaksi Elektronik (PSTE)
                                </div>
                            </div>
                            <div style="background: #f0fdf4; border-radius: 6px; padding: 12px 16px; border-left: 3px solid #22c55e;">
                                <div style="font-weight: 600; color: #166534; font-size: 13px; margin-bottom: 4px;">
                                    📋 Peraturan Menkominfo Nomor 11 Tahun 2022
                                </div>
                                <div style="color: #166534; font-size: 12px;">
                                    Tentang Sertifikat Elektronik dan Sistem Elektronik Tersertifikasi
                                </div>
                            </div>
                        </div>
                        <div style="background: #fef3c7; border-radius: 6px; padding: 14px 16px; margin-top: 16px; border-left: 3px solid #f59e0b;">
                            <p style="color: #78350f; font-size: 13px; line-height: 1.6; margin: 0;">
                                <strong>⚠️ Penting:</strong> Dalam <strong>PP No. 71 Tahun 2019</strong> ditegaskan bahwa Tanda Tangan Elektronik terdiri dari <strong>TTE Tersertifikasi</strong> dan <strong>TTE Tidak Tersertifikasi (Non Sertifikasi)</strong>. Keduanya memiliki kekuatan hukum yang sah.
                            </p>
                        </div>
                    </div>

                    <!-- Pasal 11 UU ITE -->
                    <div style="background: white; border-radius: 8px; padding: 24px; margin-bottom: 20px; border-left: 4px solid #f59e0b;">
                        <h6 style="color: #1e293b; font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-file-contract" style="color: #f59e0b;"></i>
                            Ketentuan Pasal 11 UU ITE
                        </h6>
                        <p style="color: #475569; font-size: 13px; line-height: 1.8; margin-bottom: 16px;">
                            Berdasarkan <strong>Pasal 11 ayat (1) UU ITE</strong>, Tanda Tangan Elektronik memiliki <strong style="color: #dc2626;">kekuatan hukum dan akibat hukum yang sah</strong> apabila memenuhi persyaratan berikut:
                        </p>
                        <div style="display: grid; gap: 10px;">
                            <div style="display: flex; gap: 12px; align-items: start;">
                                <div style="background: #fef3c7; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600; color: #78350f; font-size: 12px;">1</div>
                                <div style="color: #475569; font-size: 13px; line-height: 1.6; padding-top: 4px;">
                                    <strong>Data pembuatan</strong> tanda tangan elektronik terkait <strong>hanya</strong> kepada penanda tangan
                                </div>
                            </div>
                            <div style="display: flex; gap: 12px; align-items: start;">
                                <div style="background: #fef3c7; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600; color: #78350f; font-size: 12px;">2</div>
                                <div style="color: #475569; font-size: 13px; line-height: 1.6; padding-top: 4px;">
                                    Data pembuatan tanda tangan elektronik pada saat proses penandatanganan elektronik berada <strong>dalam kuasa penanda tangan</strong>
                                </div>
                            </div>
                            <div style="display: flex; gap: 12px; align-items: start;">
                                <div style="background: #fef3c7; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600; color: #78350f; font-size: 12px;">3</div>
                                <div style="color: #475569; font-size: 13px; line-height: 1.6; padding-top: 4px;">
                                    Segala <strong>perubahan</strong> terhadap tanda tangan elektronik yang terjadi setelah waktu penandatanganan <strong>dapat diketahui</strong>
                                </div>
                            </div>
                            <div style="display: flex; gap: 12px; align-items: start;">
                                <div style="background: #fef3c7; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600; color: #78350f; font-size: 12px;">4</div>
                                <div style="color: #475569; font-size: 13px; line-height: 1.6; padding-top: 4px;">
                                    Segala <strong>perubahan</strong> terhadap informasi elektronik yang terkait dengan informasi elektronik tersebut sejak waktu penandatanganan <strong>dapat diketahui</strong>
                                </div>
                            </div>
                            <div style="display: flex; gap: 12px; align-items: start;">
                                <div style="background: #fef3c7; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600; color: #78350f; font-size: 12px;">5</div>
                                <div style="color: #475569; font-size: 13px; line-height: 1.6; padding-top: 4px;">
                                    Terdapat <strong>cara tertentu</strong> yang dipakai untuk <strong>mengidentifikasi siapa penanda tangannya</strong>
                                </div>
                            </div>
                            <div style="display: flex; gap: 12px; align-items: start;">
                                <div style="background: #fef3c7; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600; color: #78350f; font-size: 12px;">6</div>
                                <div style="color: #475569; font-size: 13px; line-height: 1.6; padding-top: 4px;">
                                    Terdapat <strong>cara tertentu</strong> untuk menunjukkan bahwa penanda tangan telah <strong>memberikan persetujuan</strong> terhadap informasi elektronik yang terkait
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grid Layout untuk Karakteristik dan Penerapan -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <!-- Karakteristik -->
                        <div style="background: white; border-radius: 8px; padding: 24px; border-left: 4px solid #a855f7;">
                            <h6 style="color: #1e293b; font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-list-check" style="color: #a855f7;"></i>
                                Karakteristik TTE Non Sertifikasi
                            </h6>
                            <div style="display: grid; gap: 10px;">
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-check-circle" style="color: #22c55e; font-size: 14px; margin-top: 2px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;">Tidak menggunakan sertifikat elektronik dari PSrE</span>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-check-circle" style="color: #22c55e; font-size: 14px; margin-top: 2px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;">Mengandalkan sistem aplikasi untuk identifikasi pengguna</span>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-check-circle" style="color: #22c55e; font-size: 14px; margin-top: 2px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;">Menggunakan autentikasi (akun, password, OTP, biometrik)</span>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-check-circle" style="color: #22c55e; font-size: 14px; margin-top: 2px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;">Memiliki jejak audit (audit trail) aktivitas penandatanganan</span>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-check-circle" style="color: #22c55e; font-size: 14px; margin-top: 2px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;">Menjaga integritas dokumen dari perubahan tanpa izin</span>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-check-circle" style="color: #22c55e; font-size: 14px; margin-top: 2px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;">Timestamp untuk mencatat waktu penandatanganan</span>
                                </div>
                            </div>
                        </div>

                        <!-- Penerapan -->
                        <div style="background: white; border-radius: 8px; padding: 24px; border-left: 4px solid #3b82f6;">
                            <h6 style="color: #1e293b; font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-cogs" style="color: #3b82f6;"></i>
                                Penerapan di Fix Signature
                            </h6>
                            <div style="display: grid; gap: 10px;">
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-arrow-right" style="color: #3b82f6; font-size: 12px; margin-top: 3px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;"><strong>Verifikasi identitas</strong> berbasis akun terverifikasi admin</span>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-arrow-right" style="color: #3b82f6; font-size: 12px; margin-top: 3px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;"><strong>Pencatatan waktu</strong> (timestamp) penandatanganan yang akurat</span>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-arrow-right" style="color: #3b82f6; font-size: 12px; margin-top: 3px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;"><strong>Integritas dokumen</strong> melalui hash & kontrol perubahan</span>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-arrow-right" style="color: #3b82f6; font-size: 12px; margin-top: 3px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;"><strong>Jejak audit lengkap</strong> sebagai bukti proses elektronik</span>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-arrow-right" style="color: #3b82f6; font-size: 12px; margin-top: 3px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;"><strong>Enkripsi data</strong> untuk keamanan informasi</span>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: start;">
                                    <i class="fas fa-arrow-right" style="color: #3b82f6; font-size: 12px; margin-top: 3px;"></i>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.6;"><strong>Metadata lengkap</strong> (NIK, nama, waktu, lokasi)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Perbedaan TTE Tersertifikasi vs Non Sertifikasi -->
                    <div style="background: white; border-radius: 8px; padding: 24px; margin-bottom: 20px; border-left: 4px solid #ec4899;">
                        <h6 style="color: #1e293b; font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-exchange-alt" style="color: #ec4899;"></i>
                            Perbedaan TTE Tersertifikasi vs Non Sertifikasi
                        </h6>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; font-weight: 600; color: #1e293b;">Aspek</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; font-weight: 600; color: #1e293b;">TTE Tersertifikasi</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; font-weight: 600; color: #1e293b;">TTE Non Sertifikasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569; font-weight: 500;">Sertifikat Elektronik</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569;"><span style="background: #dcfce7; padding: 2px 8px; border-radius: 3px; color: #166534; font-weight: 500;">✓ Menggunakan</span></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569;"><span style="background: #fee2e2; padding: 2px 8px; border-radius: 3px; color: #991b1b; font-weight: 500;">✗ Tidak menggunakan</span></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569; font-weight: 500;">Verifikasi Identitas</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569;">Melalui PSrE terakreditasi</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569;">Melalui sistem aplikasi</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569; font-weight: 500;">Kekuatan Hukum</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569;"><strong>Sah</strong> (Pasal 11 UU ITE)</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569;"><strong>Sah</strong> (Pasal 11 UU ITE)</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569; font-weight: 500;">Biaya Implementasi</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569;">Tinggi</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569;">Rendah - Menengah</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569; font-weight: 500;">Kecepatan Implementasi</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569;">Lambat (perlu proses sertifikasi)</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #f1f5f9; color: #475569;">Cepat</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; color: #475569; font-weight: 500;">Cocok Untuk</td>
                                        <td style="padding: 12px; color: #475569;">Transaksi bernilai tinggi, kontrak besar</td>
                                        <td style="padding: 12px; color: #475569;">Dokumen internal, surat, laporan</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Kesimpulan -->
                    <div style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); border-radius: 8px; padding: 24px; color: white;">
                        <h6 style="font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                            Kesimpulan
                        </h6>
                        <p style="font-size: 13px; line-height: 1.8; margin-bottom: 12px;">
                            <strong>Tanda Tangan Elektronik Non Sertifikasi adalah SAH dan LEGAL di mata hukum Indonesia</strong>, dapat digunakan sebagai alat bukti yang sah, dan memiliki kekuatan hukum yang sama dengan TTE Tersertifikasi sepanjang memenuhi ketentuan <strong>Pasal 11 UU ITE</strong> dan peraturan turunannya.
                        </p>
                        <p style="font-size: 13px; line-height: 1.8; margin: 0;">
                            Penggunaan TTE Non Sertifikasi pada sistem <strong>Fix Signature</strong> di lingkungan RS. Medika Mulia memiliki dasar hukum yang jelas, dapat dipertanggungjawabkan, dan sesuai dengan regulasi yang berlaku di Indonesia.
                        </p>
                    </div>

                </div>
                <div class="modal-footer" style="background: white; border: none; padding: 20px 32px; border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="padding: 10px 24px; font-size: 13px; border-radius: 6px;">
                        <i class="fas fa-check"></i> Saya Mengerti
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Lupa Password -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-key"></i> Lupa Password
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                        Masukkan email Anda dan kami akan mengirimkan kode OTP untuk reset password.
                    </p>
                    <div id="forgotPasswordAlert"></div>
                    <form id="forgotPasswordForm">
                        <div class="mb-3">
                            <label for="forgot_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="forgot_email" placeholder="nama@email.com" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> Kirim Kode OTP
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);

            // Update tabs
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            if (tab === 'login') {
                document.querySelector('.tab-btn:first-child').classList.add('active');
                document.getElementById('login-tab').classList.add('active');
            } else {
                document.querySelector('.tab-btn:last-child').classList.add('active');
                document.getElementById('register-tab').classList.add('active');
            }
        }

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

        // Handle forgot password form
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('forgot_email').value;
            const alertDiv = document.getElementById('forgotPasswordAlert');
            const submitBtn = e.target.querySelector('button[type="submit"]');
            
            // Disable button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
            
            // Send AJAX request
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
                    alertDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                    // Redirect to verify OTP page after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'verify_otp.php?email=' + encodeURIComponent(email);
                    }, 2000);
                } else {
                    alertDiv.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Kode OTP';
                }
            })
            .catch(error => {
                alertDiv.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Terjadi kesalahan. Silakan coba lagi.</div>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Kode OTP';
            });
        });
    </script>
</body>
</html>