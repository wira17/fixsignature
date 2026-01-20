<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';

// Filter
$filter_action = isset($_GET['action']) ? $_GET['action'] : 'all';
$filter_admin = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Build query
$where_clauses = [];

if ($filter_action != 'all') {
    $where_clauses[] = "al.action = '$filter_action'";
}

if ($filter_admin > 0) {
    $where_clauses[] = "al.admin_id = $filter_admin";
}

if (!empty($search)) {
    $where_clauses[] = "(u.nama LIKE '%$search%' OR u.email LIKE '%$search%' OR u.nik_nip LIKE '%$search%')";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get logs with user and admin info
$sql = "SELECT al.*, 
        u.nama as user_nama, u.email as user_email, u.nik_nip as user_nik,
        a.nama as admin_nama
        FROM approval_logs al
        JOIN users u ON al.user_id = u.id
        JOIN users a ON al.admin_id = a.id
        $where_sql
        ORDER BY al.created_at DESC
        LIMIT 100";

$logs = mysqli_query($conn, $sql);


$admins_query = mysqli_query($conn, "SELECT id, nama FROM users WHERE role = 'admin' ORDER BY nama");

// Statistics
$stats_approve = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM approval_logs WHERE action = 'approve'"));
$stats_reject = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM approval_logs WHERE action = 'reject'"));
$stats_activate = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM approval_logs WHERE action = 'activate'"));
$stats_deactivate = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM approval_logs WHERE action = 'deactivate'"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas - Fix Signature</title>
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
        }

        .btn-back:hover {
            background: #f8fafc;
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
            gap: 12px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .stat-icon.green {
            background: #f0fdf4;
            color: #16a34a;
        }

        .stat-icon.red {
            background: #fef2f2;
            color: #dc2626;
        }

        .stat-icon.blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .stat-icon.yellow {
            background: #fef9c3;
            color: #ca8a04;
        }

        .stat-info h3 {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .stat-info p {
            font-size: 20px;
            color: #1e293b;
            font-weight: 600;
        }

        .card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
        }

        .filter-section {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 6px;
            display: block;
        }

        select, input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
        }

        .btn-filter {
            padding: 8px 16px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            height: fit-content;
            margin-top: 21px;
        }

        .btn-reset {
            padding: 8px 16px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            margin-top: 21px;
        }

        .timeline {
            position: relative;
            padding-left: 32px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e8ecf1;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 24px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -28px;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 1px #e8ecf1;
        }

        .timeline-dot.approve {
            background: #16a34a;
        }

        .timeline-dot.reject {
            background: #dc2626;
        }

        .timeline-dot.activate {
            background: #2563eb;
        }

        .timeline-dot.deactivate {
            background: #f59e0b;
        }

        .timeline-content {
            background: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 16px;
        }

        .timeline-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .action-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .action-approve {
            background: #dcfce7;
            color: #16a34a;
        }

        .action-reject {
            background: #fee2e2;
            color: #dc2626;
        }

        .action-activate {
            background: #dbeafe;
            color: #2563eb;
        }

        .action-deactivate {
            background: #fef9c3;
            color: #ca8a04;
        }

        .timeline-time {
            font-size: 12px;
            color: #64748b;
        }

        .timeline-body {
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .timeline-body strong {
            color: #0f172a;
        }

        .timeline-notes {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 4px;
            padding: 10px 12px;
            font-size: 12px;
            color: #475569;
            margin-top: 8px;
        }

        .timeline-meta {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 8px;
        }

        .no-logs {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .no-logs i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .filter-section {
                flex-direction: column;
            }

            .filter-group {
                min-width: 100%;
            }

            .btn-filter, .btn-reset {
                width: 100%;
                margin-top: 0;
            }

            .stats-grid {
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
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Log Aktivitas</h1>
            <p class="page-subtitle">Riwayat persetujuan dan aktivitas admin</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Disetujui</h3>
                    <p><?php echo $stats_approve; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-times"></i>
                </div>
                <div class="stat-info">
                    <h3>Ditolak</h3>
                    <p><?php echo $stats_reject; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Diaktifkan</h3>
                    <p><?php echo $stats_activate; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-info">
                    <h3>Dinonaktifkan</h3>
                    <p><?php echo $stats_deactivate; ?></p>
                </div>
            </div>
        </div>

  
        <div class="card">
            <form method="GET" class="filter-section">
                <div class="filter-group">
                    <label class="filter-label">Aksi</label>
                    <select name="action">
                        <option value="all" <?php echo $filter_action == 'all' ? 'selected' : ''; ?>>Semua Aksi</option>
                        <option value="approve" <?php echo $filter_action == 'approve' ? 'selected' : ''; ?>>Disetujui</option>
                        <option value="reject" <?php echo $filter_action == 'reject' ? 'selected' : ''; ?>>Ditolak</option>
                        <option value="activate" <?php echo $filter_action == 'activate' ? 'selected' : ''; ?>>Diaktifkan</option>
                        <option value="deactivate" <?php echo $filter_action == 'deactivate' ? 'selected' : ''; ?>>Dinonaktifkan</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Admin</label>
                    <select name="admin_id">
                        <option value="0">Semua Admin</option>
                        <?php while ($admin_opt = mysqli_fetch_assoc($admins_query)): ?>
                        <option value="<?php echo $admin_opt['id']; ?>" 
                                <?php echo $filter_admin == $admin_opt['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($admin_opt['nama']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Cari Pengguna</label>
                    <input type="text" name="search" placeholder="Nama, email, atau NIK..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Filter
                </button>

                <a href="logs.php" class="btn-reset">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </form>

     
            <div class="timeline">
                <?php if (mysqli_num_rows($logs) > 0): ?>
                    <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?php echo $log['action']; ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="action-badge action-<?php echo $log['action']; ?>">
                                    <?php 
                                    $action_text = [
                                        'approve' => 'Disetujui',
                                        'reject' => 'Ditolak',
                                        'activate' => 'Diaktifkan',
                                        'deactivate' => 'Dinonaktifkan'
                                    ];
                                    echo $action_text[$log['action']];
                                    ?>
                                </span>
                                <span class="timeline-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('d M Y, H:i', strtotime($log['created_at'])); ?>
                                </span>
                            </div>
                            <div class="timeline-body">
                                <strong><?php echo htmlspecialchars($log['user_nama']); ?></strong>
                                (<?php echo htmlspecialchars($log['user_email']); ?>)
                                <?php echo $action_text[$log['action']]; ?> oleh
                                <strong><?php echo htmlspecialchars($log['admin_nama']); ?></strong>
                            </div>
                            <?php if (!empty($log['notes'])): ?>
                            <div class="timeline-notes">
                                <i class="fas fa-sticky-note"></i>
                                <?php echo nl2br(htmlspecialchars($log['notes'])); ?>
                            </div>
                            <?php endif; ?>
                            <div class="timeline-meta">
                                NIK/NIP: <?php echo htmlspecialchars($log['user_nik']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-logs">
                        <i class="fas fa-inbox"></i>
                        <h3>Tidak Ada Log</h3>
                        <p>Belum ada aktivitas yang tercatat</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>