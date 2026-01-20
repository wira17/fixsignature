<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';


$total_users = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role = 'user'"));
$pending_users = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE status = 'pending'"));
$active_users = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE status = 'active' AND role = 'user'"));
$inactive_users = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE status = 'inactive'"));


$total_tte = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tte_signatures"));
$active_tte = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tte_signatures WHERE status = 'ACTIVE'"));
$revoked_tte = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tte_signatures WHERE status = 'REVOKED'"));
$expired_tte = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tte_signatures WHERE status = 'EXPIRED'"));


$total_docs = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM signed_documents"));
$docs_today = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM signed_documents WHERE DATE(signed_at) = CURDATE()"));
$docs_this_month = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM signed_documents WHERE MONTH(signed_at) = MONTH(CURDATE()) AND YEAR(signed_at) = YEAR(CURDATE())"));


$storage_query = mysqli_query($conn, "SELECT SUM(file_size) as total_size FROM signed_documents");
$storage_data = mysqli_fetch_assoc($storage_query);
$total_storage = $storage_data['total_size'] ?? 0;


$recent_docs_query = "SELECT sd.*, u.nama as user_name, ts.serial_number 
                      FROM signed_documents sd 
                      LEFT JOIN users u ON sd.user_id = u.id 
                      LEFT JOIN tte_signatures ts ON sd.tte_id = ts.id 
                      ORDER BY sd.signed_at DESC 
                      LIMIT 5";
$recent_docs = mysqli_query($conn, $recent_docs_query);


$recent_tte_query = "SELECT ts.*, u.nama as user_name 
                     FROM tte_signatures ts 
                     LEFT JOIN users u ON ts.user_id = u.id 
                     ORDER BY ts.created_at DESC 
                     LIMIT 5";
$recent_tte = mysqli_query($conn, $recent_tte_query);


$top_users_query = "SELECT u.nama, u.email, COUNT(sd.id) as doc_count 
                    FROM users u 
                    LEFT JOIN signed_documents sd ON u.id = sd.user_id 
                    WHERE u.role = 'user' 
                    GROUP BY u.id 
                    ORDER BY doc_count DESC 
                    LIMIT 5";
$top_users = mysqli_query($conn, $top_users_query);


$pending_approvals_query = "SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5";
$pending_approvals = mysqli_query($conn, $pending_approvals_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Fix Signature</title>
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
            background: #dc2626;
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

        .user-role {
            color: #dc2626;
            font-size: 11px;
            font-weight: 500;
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
            text-decoration: none;
        }

        .logout-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .container {
            max-width: 1400px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .stat-icon.blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .stat-icon.yellow {
            background: #fef9c3;
            color: #ca8a04;
        }

        .stat-icon.green {
            background: #f0fdf4;
            color: #16a34a;
        }

        .stat-icon.red {
            background: #fef2f2;
            color: #dc2626;
        }

        .stat-icon.purple {
            background: #faf5ff;
            color: #9333ea;
        }

        .stat-icon.orange {
            background: #fff7ed;
            color: #f97316;
        }

        .stat-info h3 {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .stat-info p {
            font-size: 20px;
            color: #1e293b;
            font-weight: 600;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
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
            padding: 20px;
        }

        .btn-link {
            color: #6366f1;
            font-size: 12px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-link:hover {
            color: #4f46e5;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }

        .action-card {
            background: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 18px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            background: white;
            border-color: #6366f1;
            transform: translateY(-2px);
        }

        .action-icon {
            width: 44px;
            height: 44px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 18px;
            color: #6366f1;
        }

        .action-title {
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .action-desc {
            font-size: 11px;
            color: #64748b;
        }

        /* Activity List */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .activity-item {
            display: flex;
            gap: 12px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e8ecf1;
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            background: white;
            border-color: #cbd5e1;
        }

        .activity-icon-wrapper {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 14px;
        }

        .activity-icon-wrapper.doc {
            background: #fef3c7;
            color: #dc2626;
        }

        .activity-icon-wrapper.tte {
            background: #dbeafe;
            color: #2563eb;
        }

        .activity-icon-wrapper.user {
            background: #dcfce7;
            color: #16a34a;
        }

        .activity-content {
            flex: 1;
            min-width: 0;
        }

        .activity-title {
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .activity-meta {
            font-size: 11px;
            color: #64748b;
            display: flex;
            gap: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 36px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 13px;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-warning {
            background: #fef9c3;
            color: #854d0e;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            background: #f8fafc;
            border-bottom: 1px solid #e8ecf1;
        }

        td {
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }

        tr:hover {
            background: #f8fafc;
        }

        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 32px;
            color: #94a3b8;
            font-size: 11px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .user-details {
                display: none;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
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
        <div class="nav-right">
            <div class="user-info">
                <div class="user-avatar-small">
                    <?php echo strtoupper(substr($admin['nama'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($admin['nama']); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Keluar</span>
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Dashboard Administrator</h1>
            <p class="page-subtitle">Monitor sistem dan kelola pengguna Fix Signature</p>
        </div>

        <!-- Statistics - Users -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Pengguna</h3>
                    <p><?php echo $total_users; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3>Menunggu Persetujuan</h3>
                    <p><?php echo $pending_users; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Pengguna Aktif</h3>
                    <p><?php echo $active_users; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-info">
                    <h3>Pengguna Nonaktif</h3>
                    <p><?php echo $inactive_users; ?></p>
                </div>
            </div>
        </div>

        <!-- Statistics - TTE & Documents -->
        <div class="stats-grid" style="margin-bottom: 24px;">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="stat-info">
                    <h3>Total TTE</h3>
                    <p><?php echo $total_tte; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>TTE Aktif</h3>
                    <p><?php echo $active_tte; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Dokumen</h3>
                    <p><?php echo $total_docs; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-hdd"></i>
                </div>
                <div class="stat-info">
                    <h3>Storage Digunakan</h3>
                    <p><?php echo number_format($total_storage / 1024 / 1024, 1); ?> MB</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bolt"></i>
                    Menu Administrasi
                </h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="users.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="action-title">Kelola Pengguna</div>
                        <div class="action-desc">Setujui & kelola user</div>
                    </a>


                    <a href="admin_documents.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="action-title">Dokumen TTE</div>
                        <div class="action-desc">Monitor dokumen signed</div>
                    </a>
                

                    <a href="tte_management.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <div class="action-title">Kelola TTE</div>
                        <div class="action-desc">Monitor & revoke TTE</div>
                    </a>

                    <a href="permissions.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="action-title">Hak Akses</div>
                        <div class="action-desc">Atur menu user</div>
                    </a>

                    <a href="logs.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <div class="action-title">Log Aktivitas</div>
                        <div class="action-desc">Riwayat sistem</div>
                    </a>


                    <a href="mail_settings.php" class="action-card">
    <div class="action-icon">
        <i class="fas fa-envelope-open-text"></i>
    </div>
    <div class="action-title">Mail Settings</div>
    <div class="action-desc">Konfigurasi SMTP</div>
</a>

                    <a href="reports.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-title">Laporan</div>
                        <div class="action-desc">Statistik & analisis</div>
                    </a>
                </div>
            </div>
        </div>

       
        <div class="content-grid">
            <div class="card">
              <div class="card-header">
    <h3 class="card-title">
        <i class="fas fa-file-signature"></i>
        Dokumen Terbaru
    </h3>
    <a href="view_all_documents.php" class="btn-link">
        Lihat Semua <i class="fas fa-arrow-right"></i>
    </a>
</div>
                <div class="card-body">
                    <div class="activity-list">
                        <?php if (mysqli_num_rows($recent_docs) > 0): ?>
                            <?php while ($doc = mysqli_fetch_assoc($recent_docs)): ?>
                            <div class="activity-item">
                                <div class="activity-icon-wrapper doc">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                    <div class="activity-meta">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($doc['user_name']); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($doc['signed_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Belum ada dokumen</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-certificate"></i>
                        TTE Terbaru
                    </h3>
                    <a href="tte_management.php" class="btn-link">
                        Lihat Semua <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <div class="activity-list">
                        <?php if (mysqli_num_rows($recent_tte) > 0): ?>
                            <?php while ($tte = mysqli_fetch_assoc($recent_tte)): ?>
                            <div class="activity-item">
                                <div class="activity-icon-wrapper tte">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($tte['common_name']); ?></div>
                                    <div class="activity-meta">
                                        <span><i class="fas fa-key"></i> <?php echo htmlspecialchars(substr($tte['serial_number'], 0, 16)); ?>...</span>
                                        <span class="badge badge-<?php echo $tte['status'] == 'ACTIVE' ? 'success' : 'danger'; ?>">
                                            <?php echo $tte['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Belum ada TTE</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

       
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock"></i>
                        Persetujuan Pending
                    </h3>
                    <a href="users.php?filter=pending" class="btn-link">
                        Lihat Semua <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($pending_approvals) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($pending = mysqli_fetch_assoc($pending_approvals)): ?>
                                <tr style="cursor: pointer;" onclick="window.location='users.php?id=<?php echo $pending['id']; ?>'">
                                    <td><?php echo htmlspecialchars($pending['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['email']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($pending['created_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>Tidak ada persetujuan pending</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

    
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line"></i>
                        Pengguna Teraktif
                    </h3>
                    <a href="users.php" class="btn-link">
                        Lihat Semua <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($top_users) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Dokumen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($top_user = mysqli_fetch_assoc($top_users)): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($top_user['nama']); ?></div>
                                        <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($top_user['email']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $top_user['doc_count']; ?> docs</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>Belum ada aktivitas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div>&copy; 2025 Fix Signature. All rights reserved.</div>
        <div>M. Wira Sb. S. Kom - 082177846209</div>
    </footer>
</body>
</html>