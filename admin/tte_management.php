<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';

// Pagination
$records_per_page = 15;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$user_filter = isset($_GET['user']) ? intval($_GET['user']) : 0;

// Build WHERE clause
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if ($search) {
    $where_conditions[] = "(ts.serial_number LIKE ? OR ts.common_name LIKE ? OR ts.verification_code LIKE ? OR u.nama LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

if ($status_filter) {
    $where_conditions[] = "ts.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($user_filter > 0) {
    $where_conditions[] = "ts.user_id = ?";
    $params[] = $user_filter;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Count total
$count_query = "SELECT COUNT(*) as total 
                FROM tte_signatures ts 
                LEFT JOIN users u ON ts.user_id = u.id 
                WHERE {$where_clause}";

if ($param_types) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $count_result = mysqli_query($conn, $count_query);
    $total_records = mysqli_fetch_assoc($count_result)['total'];
}

$total_pages = ceil($total_records / $records_per_page);

// Get TTE data
$query = "SELECT ts.*, u.nama as user_name, u.email as user_email,
          (SELECT COUNT(*) FROM signed_documents WHERE tte_id = ts.id) as doc_count
          FROM tte_signatures ts 
          LEFT JOIN users u ON ts.user_id = u.id 
          WHERE {$where_clause}
          ORDER BY ts.created_at DESC
          LIMIT ? OFFSET ?";

$params[] = $records_per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$tte_list = $stmt->get_result();
$stmt->close();

// Get users for filter
$users_query = "SELECT id, nama FROM users WHERE role = 'user' ORDER BY nama";
$users = mysqli_query($conn, $users_query);

// Stats
$total_tte = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tte_signatures"));
$active_tte = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tte_signatures WHERE status = 'ACTIVE'"));
$expired_tte = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tte_signatures WHERE status = 'EXPIRED'"));
$revoked_tte = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tte_signatures WHERE status = 'REVOKED'"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola TTE - Admin</title>
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.green { background: #f0fdf4; color: #22c55e; }
        .stat-icon.yellow { background: #fef9c3; color: #ca8a04; }
        .stat-icon.red { background: #fef2f2; color: #dc2626; }

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

        .filter-card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .filter-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
        }

        .filter-header i {
            color: #6366f1;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
        }

        .form-control {
            padding: 8px 12px;
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

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #6366f1;
            color: white;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
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
            color: #6366f1;
        }

        .badge-count {
            background: #eff6ff;
            color: #3b82f6;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
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

        .tte-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .tte-icon {
            width: 36px;
            height: 36px;
            background: #dbeafe;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2563eb;
            font-size: 16px;
            flex-shrink: 0;
        }

        .tte-details h4 {
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .tte-details p {
            font-size: 11px;
            color: #64748b;
            font-family: monospace;
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

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef9c3;
            color: #854d0e;
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
        }

        .btn-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn-info:hover {
            background: #bfdbfe;
        }

        .btn-revoke {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-revoke:hover {
            background: #fecaca;
        }

        .pagination-container {
            padding: 16px 20px;
            border-top: 1px solid #e8ecf1;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            background: #6366f1;
            color: white;
            border-color: #6366f1;
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

        @media (max-width: 1024px) {
            .filter-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <span class="brand-name">Fix Signature - Admin</span>
        </div>
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Dashboard
        </a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Kelola TTE</h1>
            <p class="page-subtitle">Monitor dan kelola semua TTE (Tanda Tangan Elektronik) yang terdaftar</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total TTE</div>
                    <div class="stat-value"><?php echo $total_tte; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">TTE Aktif</div>
                    <div class="stat-value"><?php echo $active_tte; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">TTE Expired</div>
                    <div class="stat-value"><?php echo $expired_tte; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">TTE Revoked</div>
                    <div class="stat-value"><?php echo $revoked_tte; ?></div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-card">
            <div class="filter-header">
                <i class="fas fa-filter"></i>
                Filter TTE
            </div>
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Cari TTE</label>
                    <input type="text" name="search" class="form-control" placeholder="Serial, nama, kode verifikasi..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="ACTIVE" <?php echo $status_filter == 'ACTIVE' ? 'selected' : ''; ?>>Active</option>
                        <option value="EXPIRED" <?php echo $status_filter == 'EXPIRED' ? 'selected' : ''; ?>>Expired</option>
                        <option value="REVOKED" <?php echo $status_filter == 'REVOKED' ? 'selected' : ''; ?>>Revoked</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Pengguna</label>
                    <select name="user" class="form-control">
                        <option value="0">Semua Pengguna</option>
                        <?php while ($user = mysqli_fetch_assoc($users)): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['nama']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Filter
                    </button>
                    <a href="tte_management.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- TTE Table -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-list"></i>
                    Daftar TTE
                    <span class="badge-count"><?php echo $total_records; ?></span>
                </div>
            </div>

            <?php if (mysqli_num_rows($tte_list) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Pemilik</th>
                        <th>Serial Number</th>
                        <th>Kode Verifikasi</th>
                        <th>Dibuat</th>
                        <th>Kadaluarsa</th>
                        <th>Dokumen</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($tte = mysqli_fetch_assoc($tte_list)): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 500;"><?php echo htmlspecialchars($tte['common_name']); ?></div>
                            <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($tte['user_email']); ?></div>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-size: 11px;">
                                <?php echo htmlspecialchars($tte['serial_number']); ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-weight: 600; color: #6366f1;">
                                <?php echo htmlspecialchars($tte['verification_code']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($tte['created_at'])); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($tte['valid_until'])); ?></td>
                        <td>
                            <span class="badge badge-success">
                                <?php echo $tte['doc_count']; ?> docs
                            </span>
                        </td>
                        <td>
                            <?php if ($tte['status'] == 'ACTIVE'): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check"></i> Active
                            </span>
                            <?php elseif ($tte['status'] == 'EXPIRED'): ?>
                            <span class="badge badge-warning">
                                <i class="fas fa-clock"></i> Expired
                            </span>
                            <?php else: ?>
                            <span class="badge badge-danger">
                                <i class="fas fa-ban"></i> Revoked
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <button onclick="viewTTE(<?php echo $tte['id']; ?>)" class="btn-action btn-info" title="Detail">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                                <?php if ($tte['status'] == 'ACTIVE'): ?>
                                <button onclick="revokeTTE(<?php echo $tte['id']; ?>)" class="btn-action btn-revoke" title="Revoke">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?>
                </div>
                <div class="pagination">
                    <?php
                    $query_params = [];
                    if ($search) $query_params['search'] = $search;
                    if ($status_filter) $query_params['status'] = $status_filter;
                    if ($user_filter) $query_params['user'] = $user_filter;
                    $query_string = http_build_query($query_params);
                    ?>
                    
                    <?php if ($current_page > 1): ?>
                    <a href="?page=1&<?php echo $query_string; ?>" class="page-btn">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $current_page - 1; ?>&<?php echo $query_string; ?>" class="page-btn">
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="?page=<?php echo $i; ?>&<?php echo $query_string; ?>" 
                       class="page-btn <?php echo $i === $current_page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>&<?php echo $query_string; ?>" class="page-btn">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?>&<?php echo $query_string; ?>" class="page-btn">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="empty-title">Tidak Ada TTE</div>
                <div class="empty-text">Tidak ada TTE yang ditemukan untuk filter yang dipilih</div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function viewTTE(tteId) {
            window.location.href = 'tte_detail.php?id=' + tteId;
        }

        function revokeTTE(tteId) {
            if (confirm('Apakah Anda yakin ingin me-revoke TTE ini?\n\nTTE yang di-revoke tidak dapat digunakan lagi untuk menandatangani dokumen.')) {
                window.location.href = 'revoke_tte.php?id=' + tteId;
            }
        }
    </script>
</body>
</html>