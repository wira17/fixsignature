<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';

$success = '';
$error = '';

// Check for session messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Handle document deletion (admin can delete any document)
if (isset($_POST['delete_doc']) && isset($_POST['doc_id'])) {
    $doc_id = intval($_POST['doc_id']);
    
    // Get document info first
    $stmt = $conn->prepare("SELECT * FROM signed_documents WHERE id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->fetch_assoc();
    $stmt->close();
    
    if ($doc) {
        // Delete file from server
        if (file_exists($doc['signed_file'])) {
            @unlink($doc['signed_file']);
        }
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM signed_documents WHERE id = ?");
        $stmt->bind_param("i", $doc_id);
        if ($stmt->execute()) {
            $success = "Dokumen berhasil dihapus!";
        } else {
            $error = "Gagal menghapus dokumen!";
        }
        $stmt->close();
    } else {
        $error = "Dokumen tidak ditemukan!";
    }
}

// Pagination settings
$records_per_page = 15;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Date filter - Default to all time (last 30 days)
$date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// User filter
$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query with filters
$where_conditions = ["sd.status != 'DELETED'"];
$params = [];
$param_types = "";

// Date filter
if ($date_from && $date_to) {
    $where_conditions[] = "DATE(sd.signed_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $param_types .= "ss";
}

// Search filter
if ($search) {
    $where_conditions[] = "(sd.document_name LIKE ? OR ts.serial_number LIKE ? OR u.nama LIKE ? OR u.email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

// User filter
if ($user_filter > 0) {
    $where_conditions[] = "sd.user_id = ?";
    $params[] = $user_filter;
    $param_types .= "i";
}

// Status filter
if ($status_filter !== 'all') {
    $where_conditions[] = "sd.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Count total records
$count_query = "SELECT COUNT(*) as total 
                FROM signed_documents sd 
                LEFT JOIN tte_signatures ts ON sd.tte_id = ts.id 
                LEFT JOIN users u ON sd.user_id = u.id
                WHERE {$where_clause}";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $records_per_page);

// Get documents with pagination
$documents = [];
$query = "SELECT sd.*, ts.serial_number, ts.common_name as signer_name, ts.status as tte_status,
          u.nama as user_name, u.email as user_email
          FROM signed_documents sd 
          LEFT JOIN tte_signatures ts ON sd.tte_id = ts.id 
          LEFT JOIN users u ON sd.user_id = u.id
          WHERE {$where_clause}
          ORDER BY sd.signed_at DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$params[] = $records_per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
}
$stmt->close();

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_docs,
                SUM(file_size) as total_size,
                SUM(total_pages) as total_pages,
                COUNT(DISTINCT user_id) as total_users
                FROM signed_documents 
                WHERE status = 'SIGNED'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$total_size = $stats['total_size'] ?? 0;
$total_pages_count = $stats['total_pages'] ?? 0;
$total_docs_all = $stats['total_docs'] ?? 0;
$total_users_with_docs = $stats['total_users'] ?? 0;

// Get all users for filter dropdown
$users_query = "SELECT id, nama, email FROM users WHERE role = 'user' AND status = 'active' ORDER BY nama ASC";
$users_result = mysqli_query($conn, $users_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumen TTE - Admin Dashboard</title>
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

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
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
        }

        /* Stats Grid */
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

        /* Alert */
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

        /* Filter Card */
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
            color: #dc2626;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 12px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group-full {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .form-label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
        }

        .form-control, .form-select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
            transition: all 0.2s ease;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .btn-filter {
            background: #dc2626;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-filter:hover {
            background: #b91c1c;
        }

        .btn-reset {
            background: #f1f5f9;
            color: #64748b;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-reset:hover {
            background: #e2e8f0;
        }

        .filter-info {
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #64748b;
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-info i {
            color: #dc2626;
        }

        /* Documents Card */
        .documents-card {
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
            flex-wrap: wrap;
            gap: 12px;
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

        .card-title .badge-count {
            background: #fee2e2;
            color: #dc2626;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Documents Table */
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

        /* Actions */
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

        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-delete:hover {
            background: #fecaca;
        }

        /* Pagination */
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

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Empty State */
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
            margin-bottom: 20px;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-container {
            background: white;
            border-radius: 8px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e8ecf1;
        }

        .modal-header h3 {
            font-size: 16px;
            font-weight: 500;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-body p {
            font-size: 13px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e8ecf1;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .btn-confirm {
            background: #dc2626;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-confirm:hover {
            background: #b91c1c;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 24px;
            color: #94a3b8;
            font-size: 11px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .filter-form {
                grid-template-columns: 1fr 1fr;
            }
            
            .form-group-full {
                grid-template-columns: 1fr;
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
                grid-template-columns: 1fr;
            }

            .actions {
                flex-wrap: wrap;
            }

            .btn-action span {
                display: none;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
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
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Dokumen TTE</h1>
            <p class="page-subtitle">Monitor dan kelola semua dokumen yang telah ditandatangani dengan TTE</p>
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

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Dokumen</div>
                    <div class="stat-value"><?php echo $total_docs_all; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Halaman</div>
                    <div class="stat-value"><?php echo number_format($total_pages_count); ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-hdd"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Storage</div>
                    <div class="stat-value"><?php echo number_format($total_size / 1024 / 1024, 1); ?> MB</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Pengguna Aktif</div>
                    <div class="stat-value"><?php echo $total_users_with_docs; ?></div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <div class="filter-header">
                <i class="fas fa-filter"></i>
                Filter Dokumen
            </div>
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Pengguna</label>
                    <select name="user_id" class="form-select">
                        <option value="0">Semua Pengguna</option>
                        <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['nama']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="SIGNED" <?php echo $status_filter == 'SIGNED' ? 'selected' : ''; ?>>Signed</option>
                        <option value="PENDING" <?php echo $status_filter == 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="form-group-full">
                    <div class="form-group">
                        <label class="form-label">Cari Dokumen</label>
                        <input type="text" name="search" class="form-control" placeholder="Nama dokumen, serial, nama user..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i>
                            Filter
                        </button>
                        <a href="documents.php" class="btn-reset">
                            <i class="fas fa-redo"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>
            <div class="filter-info">
                <i class="fas fa-info-circle"></i>
                Menampilkan <?php echo count($documents); ?> dari <?php echo $total_records; ?> dokumen
                (<?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>)
            </div>
        </div>

        <!-- Documents Card -->
        <div class="documents-card">
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
                    <div class="empty-text">Tidak ada dokumen yang ditemukan untuk filter yang dipilih</div>
                    <a href="documents.php" class="btn-reset" style="display: inline-flex;">
                        <i class="fas fa-redo"></i>
                        Reset Filter
                    </a>
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
                                        <p>Serial: <?php echo htmlspecialchars($doc['serial_number'] ?? 'N/A'); ?></p>
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
                                    <button onclick="openDeleteModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['document_name'])); ?>')" class="btn-action btn-delete" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                        <span>Hapus</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                        // Build query string for pagination
                        $query_params = [];
                        if ($search) $query_params['search'] = $search;
                        if ($date_from) $query_params['date_from'] = $date_from;
                        if ($date_to) $query_params['date_to'] = $date_to;
                        if ($user_filter) $query_params['user_id'] = $user_filter;
                        if ($status_filter !== 'all') $query_params['status'] = $status_filter;
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
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div>&copy; 2025 Fix Signature. All rights reserved.</div>
        <div>M. Wira Sb. S. Kom - 082177846209</div>
    </footer>

    <!-- Delete Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i>
                    Hapus Dokumen
                </h3>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus dokumen "<strong id="deleteDocName"></strong>"?</p>
                <p style="color: #dc2626; font-weight: 500;">⚠️ Tindakan ini tidak dapat dibatalkan!</p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeDeleteModal()">Batal</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="doc_id" id="deleteDocId">
                    <button type="submit" name="delete_doc" class="btn-confirm">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDeleteModal(docId, docName) {
            document.getElementById('deleteDocId').value = docId;
            document.getElementById('deleteDocName').textContent = docName;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Close modal on overlay click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                closeDeleteModal();
            }
        });

        // Close modal on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>