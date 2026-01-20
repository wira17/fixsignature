<?php
session_start();
require_once 'config.php';
require_once 'check_permission.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}


$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);


$total_users = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users"));


$accessible_menus = getUserAccessibleMenus($conn, $user_id);


$grouped_menus = [];
foreach ($accessible_menus as $menu) {
    $grouped_menus[$menu['menu_category']][] = $menu;
}


$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
if ($error_message) {
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fix Signature</title>
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

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
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

        .alert-warning {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
        }

        /* Navbar */
        .navbar {
            background: white;
            border-bottom: 1px solid #e8ecf1;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
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

        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar-small {
            width: 32px;
            height: 32px;
            background: #6366f1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
            font-weight: 500;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            color: #1e293b;
            font-weight: 500;
            font-size: 13px;
        }

        .user-email {
            color: #64748b;
            font-size: 11px;
        }

        .logout-btn {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            padding: 7px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #475569;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: #64748b;
            cursor: pointer;
            padding: 8px;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0;
        }

        .main-content {
            padding: 24px;
        }

        /* Page Header */
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
            font-weight: 400;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 20px;
        }

        /* Sidebar */
        .sidebar {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 80px;
        }

        .profile-header {
            text-align: center;
            padding-bottom: 18px;
            border-bottom: 1px solid #e8ecf1;
            margin-bottom: 18px;
        }

        .user-avatar {
            width: 64px;
            height: 64px;
            background: #6366f1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            font-weight: 400;
            margin: 0 auto 12px;
        }

        .profile-name {
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .profile-id {
            font-size: 11px;
            color: #64748b;
            font-weight: 400;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 12px;
        }

        .stat-badge {
            background: #f8fafc;
            border: 1px solid #e8ecf1;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
        }

        .stat-label {
            font-size: 10px;
            color: #64748b;
            margin-bottom: 3px;
        }

        .stat-value {
            font-size: 12px;
            color: #1e293b;
            font-weight: 500;
        }

        /* Menu Navigation */
        .menu-section {
            margin-bottom: 20px;
        }

        .menu-title {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
            padding: 0 8px;
            letter-spacing: 0.5px;
        }

        .menu-items {
            display: grid;
            gap: 4px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: white;
            border: 1px solid transparent;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: #475569;
            font-size: 13px;
        }

        .menu-item:hover {
            background: #f8fafc;
            border-color: #e8ecf1;
            color: #1e293b;
        }

        .menu-item.active {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1e40af;
        }

        .menu-item i {
            color: #64748b;
            font-size: 14px;
            width: 16px;
            text-align: center;
        }

        .menu-item.active i {
            color: #1e40af;
        }

        .no-menu-access {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
            font-size: 12px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e8ecf1;
        }

        /* Main Panel */
        .main-panel {
            display: grid;
            gap: 20px;
        }

        .info-card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
        }

        .card-title {
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #6366f1;
            font-size: 16px;
        }

        .info-table {
            display: grid;
            gap: 12px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 400;
        }

        .info-value {
            color: #1e293b;
            font-size: 13px;
            font-weight: 400;
        }

        .activity-card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            background: #f8fafc;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6366f1;
            font-size: 13px;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .activity-time {
            font-size: 11px;
            color: #64748b;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 24px;
            color: #94a3b8;
            font-size: 11px;
        }

        .footer-copyright {
            margin-bottom: 3px;
        }

        .footer-contact {
            opacity: 0.8;
        }

        /* Responsive Styles */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 240px 1fr;
            }

            .user-details {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 12px 16px;
            }

            .brand-name {
                font-size: 15px;
            }

            .logout-btn span {
                display: none;
            }

            .logout-btn {
                padding: 7px 10px;
            }

            .mobile-menu-btn {
                display: block;
            }

            .main-content {
                padding: 16px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                height: 100vh;
                width: 280px;
                z-index: 1000;
                transition: left 0.3s ease;
                overflow-y: auto;
                box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
            }

            .sidebar.active {
                left: 0;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .sidebar-close {
                display: flex;
                justify-content: flex-end;
                margin-bottom: 12px;
            }

            .close-btn {
                background: none;
                border: none;
                font-size: 20px;
                color: #64748b;
                cursor: pointer;
                padding: 8px;
            }

            .info-row {
                grid-template-columns: 1fr;
                gap: 4px;
            }

            .page-title {
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .logo-icon {
                width: 28px;
                height: 28px;
            }

            .logo-icon i {
                font-size: 14px;
            }

            .brand-name {
                font-size: 14px;
            }

            .user-avatar-small {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .main-content {
                padding: 12px;
            }

            .info-card, .activity-card {
                padding: 16px;
            }

            .page-header {
                margin-bottom: 16px;
            }

            .card-title {
                font-size: 14px;
            }
        }

        /* Desktop Large Screen */
        @media (min-width: 1400px) {
            .content-grid {
                grid-template-columns: 320px 1fr;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="logo-section">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo-icon">
                <i class="fas fa-file-signature"></i>
            </div>
            <span class="brand-name">Fix Signature</span>
        </div>
        <div class="nav-right">
            <div class="user-info">
                <div class="user-avatar-small">
                    <?php echo strtoupper(substr($user['nama'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
            </div>
            <a href="logout.php">
                <button class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Keluar</span>
                </button>
            </a>
        </div>
    </nav>


    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="container">
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Kelola tanda tangan elektronik dan dokumen digital Anda</p>
            </div>

            <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($accessible_menus) && $user['role'] != 'admin'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i> Anda belum memiliki akses ke menu apapun. Silakan hubungi administrator untuk mengatur hak akses Anda.
            </div>
            <?php endif; ?>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Sidebar -->
                <div class="sidebar" id="sidebar">
                    <div class="sidebar-close">
                        <button class="close-btn" onclick="toggleSidebar()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="profile-header">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['nama'], 0, 1)); ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                        <div class="profile-id">ID: <?php echo htmlspecialchars($user['nik_nip']); ?></div>
                        
                        <div class="profile-stats">
                            <div class="stat-badge">
                                <div class="stat-label">Status</div>
                                <div class="stat-value">Aktif</div>
                            </div>
                            <div class="stat-badge">
                                <div class="stat-label">Verified</div>
                                <div class="stat-value">
                                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

              
                    <?php if (empty($grouped_menus)): ?>
                        <div class="no-menu-access">
                            <i class="fas fa-lock" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
                            Tidak ada menu yang dapat diakses. Hubungi administrator.
                        </div>
                    <?php else: ?>
                        <?php foreach ($grouped_menus as $category => $menus): ?>
                        <div class="menu-section">
                            <div class="menu-title"><?php echo htmlspecialchars($category); ?></div>
                            <div class="menu-items">
                                <?php foreach ($menus as $menu): ?>
                                <a href="<?php echo htmlspecialchars($menu['menu_url']); ?>" class="menu-item">
                                    <i class="<?php echo htmlspecialchars($menu['menu_icon']); ?>"></i>
                                    <span><?php echo htmlspecialchars($menu['menu_name']); ?></span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="main-panel">
              
                    <div class="info-card">
                        <div class="card-title">
                            <i class="fas fa-id-card"></i>
                            Informasi Pribadi
                        </div>
                        <div class="info-table">
                            <div class="info-row">
                                <div class="info-label">NIK/NIP</div>
                                <div class="info-value"><?php echo htmlspecialchars($user['nik_nip']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">NIK KTP</div>
                                <div class="info-value"><?php echo htmlspecialchars($user['nik_ktp']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Nama Lengkap</div>
                                <div class="info-value"><?php echo htmlspecialchars($user['nama']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="card-title">
                            <i class="fas fa-address-book"></i>
                            Informasi Kontak
                        </div>
                        <div class="info-table">
                            <div class="info-row">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Nomor Telepon</div>
                                <div class="info-value"><?php echo htmlspecialchars($user['no_hp']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-card">
                        <div class="card-title">
                            <i class="fas fa-clock"></i>
                            Aktivitas Terkini
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">Akun berhasil dibuat</div>
                                <div class="activity-time"><?php echo date('d M Y, H:i', strtotime($user['created_at'])); ?></div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-sync"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">Profil terakhir diperbarui</div>
                                <div class="activity-time"><?php echo date('d M Y, H:i', strtotime($user['updated_at'])); ?></div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">Login terakhir</div>
                                <div class="activity-time">Hari ini, <?php echo date('H:i'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-copyright">&copy; 2025 Fix Signature. All rights reserved.</div>
        <div class="footer-contact">M. Wira Sb. S. Kom - 082177846209</div>
    </footer>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        });
    </script>
</body>
</html>