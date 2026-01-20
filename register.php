<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nik_nip = clean_input($_POST['nik_nip']);
    $nik_ktp = clean_input($_POST['nik_ktp']);
    $nama = clean_input($_POST['nama']);
    $no_hp = clean_input($_POST['no_hp']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validasi input
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
                $success = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Terjadi kesalahan: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sign System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 480px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }

        .logo i {
            font-size: 28px;
            color: #764ba2;
        }

        h2 {
            color: #764ba2;
            font-size: 26px;
            margin-bottom: 5px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background-color: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #764ba2;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-top: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
            background: #e0e0e0;
        }

        .divider span {
            background: white;
            padding: 0 15px;
            color: #999;
            font-size: 14px;
            position: relative;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .back-link a:hover {
            color: #764ba2;
        }

        @media (max-width: 480px) {
            .container {
                padding: 25px 20px;
            }

            h2 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-user-plus"></i>
            </div>
            <h2>Registrasi Akun</h2>
            <p class="subtitle">Daftar untuk memulai</p>
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

        <form method="POST" action="">
            <div class="form-group">
                <label for="nik_nip">NIK/NIP</label>
                <div class="input-group">
                    <i class="fas fa-id-card input-icon"></i>
                    <input type="text" id="nik_nip" name="nik_nip" 
                           placeholder="Masukkan NIK/NIP" 
                           value="<?php echo isset($_POST['nik_nip']) ? htmlspecialchars($_POST['nik_nip']) : ''; ?>" 
                           required>
                </div>
            </div>

            <div class="form-group">
                <label for="nik_ktp">NIK KTP</label>
                <div class="input-group">
                    <i class="fas fa-address-card input-icon"></i>
                    <input type="text" id="nik_ktp" name="nik_ktp" 
                           placeholder="Masukkan NIK KTP (16 digit)" 
                           maxlength="16" 
                           value="<?php echo isset($_POST['nik_ktp']) ? htmlspecialchars($_POST['nik_ktp']) : ''; ?>" 
                           required>
                </div>
            </div>

            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="nama" name="nama" 
                           placeholder="Masukkan nama lengkap" 
                           value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" 
                           required>
                </div>
            </div>

            <div class="form-group">
                <label for="no_hp">Nomor HP</label>
                <div class="input-group">
                    <i class="fas fa-phone input-icon"></i>
                    <input type="text" id="no_hp" name="no_hp" 
                           placeholder="Masukkan nomor HP" 
                           value="<?php echo isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : ''; ?>" 
                           required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" 
                           placeholder="Masukkan email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" 
                           placeholder="Masukkan password (min. 6 karakter)" 
                           required>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="Konfirmasi password" 
                           required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Daftar Sekarang
            </button>
        </form>

        <div class="divider">
            <span>atau</span>
        </div>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i>
                Sudah punya akun? Login di sini
            </a>
        </div>
    </div>
</body>
</html>