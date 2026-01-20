<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';

$success = '';
$error = '';

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

$records_per_page = 20;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

$days_filter = isset($_GET['days']) ? intval($_GET['days']) : 7; // Default 7 days

$count_query = "SELECT COUNT(*) as total 
                FROM signed_documents sd 
                WHERE sd.signed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $days_filter);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $records_per_page);

$documents = [];
$query = "SELECT sd.*, u.nama as user_name, u.email as user_email, 
          ts.serial_number, ts.common_name as signer_name, ts.status as tte_status
          FROM signed_documents sd 
          LEFT JOIN users u ON sd.user_id = u.id 
          LEFT JOIN tte_signatures ts ON sd.tte_id = ts.id 
          WHERE sd.signed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
          ORDER BY sd.signed_at DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $days_filter, $records_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
}
$stmt->close();

$stats_query = "SELECT 
                COUNT(*) as total_docs,
                SUM(file_size) as total_size,
                SUM(total_pages) as total_pages,
                COUNT(DISTINCT user_id) as total_users
                FROM signed_documents 
                WHERE signed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $days_filter);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumen Terbaru - Admin Dashboard</title>
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
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
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
            color: #3b82f6;
        }

        .stat-icon.green {
            background: #f0fdf4;
            color: #22c55e;
        }

        .stat-icon.purple {
            background: #faf5ff;
            color: #a855f7;
        }

        .stat-icon.orange {
            background: #fff7ed;
            color: #f97316;
        }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .filter-bar {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
        }

        .filter-tab {
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .filter-tab:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .filter-tab.active {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        .filter-info {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-info i {
            color: #dc2626;
        }

        .card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e8ecf1;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            color: #dc2626;
        }

        .badge-count {
            background: #fee2e2;
            color: #dc2626;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e8ecf1;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #1e293b;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .doc-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .doc-icon {
            width: 36px;
            height: 36px;
            background: #fef3c7;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc2626;
            font-size: 16px;
            flex-shrink: 0;
        }

        .doc-details h4 {
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .doc-details p {
            font-size: 11px;
            color: #64748b;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            background: #dc2626;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
        }

        .user-details h5 {
            font-size: 12px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .user-details p {
            font-size: 11px;
            color: #64748b;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef9c3;
            color: #854d0e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 4px;
        }

        .btn-action {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }

        .btn-view {
            background: #eff6ff;
            color: #1e40af;
        }

        .btn-view:hover {
            background: #dbeafe;
        }

        .btn-download {
            background: #d1fae5;
            color: #065f46;
        }

        .btn-download:hover {
            background: #a7f3d0;
        }

        .pagination-container {
            padding: 16px 20px;
            border-top: 1px solid #e8ecf1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pagination-info {
            font-size: 13px;
            color: #64748b;
        }

        .pagination {
            display: flex;
            gap: 4px;
        }

        .page-btn {
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .page-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .page-btn.active {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 16px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .empty-text {
            font-size: 13px;
            color: #64748b;
        }

        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 24px;
            color: #94a3b8;
            font-size: 11px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .filter-tabs {
                width: 100%;
                overflow-x: auto;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions .btn-action span {
                display: none;
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
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Dashboard
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Dokumen Terbaru</h1>
            <p class="page-subtitle">Lihat semua dokumen yang baru ditandatangani</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Dokumen</div>
                    <div class="stat-value"><?php echo $stats['total_docs']; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Halaman</div>
                    <div class="stat-value"><?php echo number_format($stats['total_pages']); ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-hdd"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Storage</div>
                    <div class="stat-value"><?php echo number_format($stats['total_size'] / 1024 / 1024, 1); ?> MB</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Pengguna Aktif</div>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                </div>
            </div>
        </div>

        <div class="filter-bar">
            <div class="filter-tabs">
                <a href="?days=1" class="filter-tab <?php echo $days_filter == 1 ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-day"></i> Hari Ini
                </a>
                <a href="?days=7" class="filter-tab <?php echo $days_filter == 7 ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week"></i> 7 Hari
                </a>
                <a href="?days=30" class="filter-tab <?php echo $days_filter == 30 ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> 30 Hari
                </a>
                <a href="?days=90" class="filter-tab <?php echo $days_filter == 90 ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i> 90 Hari
                </a>
            </div>
            <div class="filter-info">
                <i class="fas fa-info-circle"></i>
                Menampilkan <?php echo count($documents); ?> dari <?php echo $total_records; ?> dokumen
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-folder-open"></i>
                    Daftar Dokumen
                    <span class="badge-count"><?php echo $total_records; ?></span>
                </div>
            </div>

            <div class="table-container">
                <?php if (empty($documents)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="empty-title">Tidak Ada Dokumen</div>
                    <div class="empty-text">Tidak ada dokumen dalam periode yang dipilih</div>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Dokumen</th>
                            <th>Pengguna</th>
                            <th>Ukuran</th>
                            <th>Halaman</th>
                            <th>Ditandatangani</th>
                            <th>TTE Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td>
                                <div class="doc-info">
                                    <div class="doc-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="doc-details">
                                        <h4><?php echo htmlspecialchars($doc['document_name']); ?></h4>
                                        <p>Serial: <?php echo htmlspecialchars(substr($doc['serial_number'] ?? 'N/A', 0, 20)); ?>...</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($doc['user_name'], 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <h5><?php echo htmlspecialchars($doc['user_name']); ?></h5>
                                        <p><?php echo htmlspecialchars($doc['user_email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo number_format($doc['file_size'] / 1024, 2); ?> KB</td>
                            <td><?php echo $doc['total_pages']; ?> hal</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($doc['signed_at'])); ?></td>
                            <td>
                                <?php if ($doc['tte_status'] == 'ACTIVE'): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Active
                                    </span>
                                <?php elseif ($doc['tte_status'] == 'REVOKED'): ?>
                                    <span class="badge badge-danger">
                                        <i class="fas fa-ban"></i> Revoked
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-exclamation-triangle"></i> <?php echo $doc['tte_status'] ?? 'Unknown'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="view_document.php?id=<?php echo $doc['id']; ?>" class="btn-action btn-view" title="Lihat" target="_blank">
                                        <i class="fas fa-eye"></i>
                                        <span>Lihat</span>
                                    </a>
                                    <a href="download_document.php?id=<?php echo $doc['id']; ?>" class="btn-action btn-download" title="Download">
                                        <i class="fas fa-download"></i>
                                        <span>Download</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?>
                    </div>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                        <a href="?page=1&days=<?php echo $days_filter; ?>" class="page-btn">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $current_page - 1; ?>&days=<?php echo $days_filter; ?>" class="page-btn">
                            <i class="fas fa-angle-left"></i>
                        </a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                        <a href="?page=<?php echo $i; ?>&days=<?php echo $days_filter; ?>" 
                           class="page-btn <?php echo $i === $current_page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>&days=<?php echo $days_filter; ?>" class="page-btn">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?>&days=<?php echo $days_filter; ?>" class="page-btn">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div>&copy; 2025 Fix Signature. All rights reserved.</div>
        <div>M. Wira Sb. S. Kom - 082177846209</div>
    </footer>
</body>
</html>