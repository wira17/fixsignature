<?php
session_start();
require_once 'config.php';
require_once 'check_permission.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$user_id = $_SESSION['user_id'];


requireMenuAccess($conn, $user_id, 'dokumen_saya', 'dashboard.php');

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


if (isset($_POST['delete_doc']) && isset($_POST['doc_id'])) {
    $doc_id = intval($_POST['doc_id']);
    
    // Get document info first
    $stmt = $conn->prepare("SELECT sd.* FROM signed_documents sd WHERE sd.id = ? AND sd.user_id = ?");
    $stmt->bind_param("ii", $doc_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->fetch_assoc();
    $stmt->close();
    
    if ($doc) {
     
        if (file_exists($doc['signed_file'])) {
            @unlink($doc['signed_file']);
        }
        
     
        $stmt = $conn->prepare("DELETE FROM signed_documents WHERE id = ?");
        $stmt->bind_param("i", $doc_id);
        if ($stmt->execute()) {
            $success = "Dokumen berhasil dihapus!";
        } else {
            $error = "Gagal menghapus dokumen!";
        }
        $stmt->close();
    } else {
        $error = "Dokumen tidak ditemukan atau Anda tidak memiliki akses!";
    }
}


$records_per_page = 10;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Date filter - Default to today
$date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$where_conditions = ["sd.user_id = ?", "sd.status = 'SIGNED'"];
$params = [$user_id];
$param_types = "i";


if ($date_from && $date_to) {
    $where_conditions[] = "DATE(sd.signed_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $param_types .= "ss";
}


if ($search) {
    $where_conditions[] = "(sd.document_name LIKE ? OR ts.serial_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

$where_clause = implode(" AND ", $where_conditions);


$count_query = "SELECT COUNT(*) as total 
                FROM signed_documents sd 
                LEFT JOIN tte_signatures ts ON sd.tte_id = ts.id 
                WHERE {$where_clause}";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $records_per_page);


$documents = [];
$query = "SELECT sd.*, ts.serial_number, ts.common_name as signer_name 
          FROM signed_documents sd 
          LEFT JOIN tte_signatures ts ON sd.tte_id = ts.id 
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


$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();


$stats_query = "SELECT 
                COUNT(*) as total_docs,
                SUM(file_size) as total_size,
                SUM(total_pages) as total_pages
                FROM signed_documents 
                WHERE user_id = ? AND status = 'SIGNED'";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

$total_size = $stats['total_size'] ?? 0;
$total_pages_count = $stats['total_pages'] ?? 0;
$total_docs = $stats['total_docs'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumen Saya - Fix Signature</title>
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

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-container {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 400px;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e2e8f0;
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 16px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .loading-subtext {
            font-size: 13px;
            color: #64748b;
        }

        /* Navbar - Same as dashboard */
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
            color: #6366f1;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
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

        .btn-filter {
            background: #6366f1;
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
            background: #4f46e5;
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
            color: #6366f1;
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
            color: #6366f1;
        }

        .card-title .badge-count {
            background: #eff6ff;
            color: #3b82f6;
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

        .btn-email {
            background: #fef3c7;
            color: #92400e;
        }

        .btn-email:hover {
            background: #fde68a;
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
            background: #6366f1;
            color: white;
            border-color: #6366f1;
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

        .btn-primary {
            background: #6366f1;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #4f46e5;
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

        .modal-body .form-group {
            margin-bottom: 16px;
        }

        .modal-body .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
            color: #475569;
        }

        .modal-body .form-control {
            width: 100%;
        }

        textarea.form-control {
            resize: vertical;
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
            background: #6366f1;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-confirm:hover {
            background: #4f46e5;
        }

        .btn-confirm.danger {
            background: #dc2626;
        }

        .btn-confirm.danger:hover {
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
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
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

            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-container">
            <div class="loading-spinner"></div>
            <div class="loading-text">Mengirim Email...</div>
            <div class="loading-subtext">Mohon tunggu sebentar</div>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <span class="brand-name">Fix Signature</span>
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
            <h1 class="page-title">Dokumen Saya</h1>
            <p class="page-subtitle">Kelola semua dokumen yang telah Anda tandatangani dengan TTE</p>
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
                    <div class="stat-value"><?php echo $total_docs; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Halaman</div>
                    <div class="stat-value"><?php echo $total_pages_count; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-hdd"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Ukuran</div>
                    <div class="stat-value"><?php echo number_format($total_size / 1024 / 1024, 1); ?> MB</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Dokumen Hari Ini</div>
                    <div class="stat-value"><?php echo count($documents); ?></div>
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
                    <label class="form-label">Cari Dokumen</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama dokumen atau serial..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i>
                        Filter
                    </button>
                    <a href="dokumen_saya.php" class="btn-reset">
                        <i class="fas fa-redo"></i>
                        Reset
                    </a>
                </div>
            </form>
            <div class="filter-info">
                <i class="fas fa-info-circle"></i>
                Menampilkan <?php echo count($documents); ?> dari <?php echo $total_records; ?> dokumen
                <?php if ($date_from === $date_to && $date_from === date('Y-m-d')): ?>
                    (Hari Ini)
                <?php else: ?>
                    (<?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>)
                <?php endif; ?>
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
                    <a href="dokumen_saya.php" class="btn-primary">
                        <i class="fas fa-redo"></i>
                        Reset Filter
                    </a>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Dokumen</th>
                            <th>Ukuran</th>
                            <th>Halaman</th>
                            <th>Ditandatangani</th>
                            <th>Status</th>
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
                                        <p>Serial: <?php echo htmlspecialchars($doc['serial_number']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo number_format($doc['file_size'] / 1024, 2); ?> KB</td>
                            <td><?php echo $doc['total_pages']; ?> hal</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($doc['signed_at'])); ?></td>
                            <td>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Signed
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="view_document.php?id=<?php echo $doc['id']; ?>" class="btn-action btn-view" title="Lihat" target="_blank">
                                        <i class="fas fa-eye"></i>
                                        <span>Lihat</span>
                                    </a>
                                    <button onclick="openEmailModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['document_name'])); ?>')" class="btn-action btn-email" title="Kirim Email">
                                        <i class="fas fa-envelope"></i>
                                        <span>Email</span>
                                    </button>
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

                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?>
                    </div>
                    <div class="pagination">
                        <?php
                        $query_params = [];
                        if ($search) $query_params['search'] = $search;
                        if ($date_from) $query_params['date_from'] = $date_from;
                        if ($date_to) $query_params['date_to'] = $date_to;
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
                    <button type="submit" name="delete_doc" class="btn-confirm danger">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="emailModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-envelope" style="color: #6366f1;"></i>
                    Kirim Dokumen via Email
                </h3>
            </div>
            <form action="send_document_email.php" method="POST" id="emailForm">
                <div class="modal-body">
                    <input type="hidden" name="doc_id" id="emailDocId">
                    <p style="margin-bottom: 16px;">Kirim dokumen "<strong id="emailDocName"></strong>" ke email:</p>
                    
                    <div class="form-group">
                        <label class="form-label">Email Tujuan</label>
                        <input type="email" name="recipient_email" class="form-control" required placeholder="contoh@email.com">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Pesan (Opsional)</label>
                        <textarea name="message" rows="3" class="form-control" placeholder="Tambahkan pesan untuk penerima..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEmailModal()">Batal</button>
                    <button type="submit" class="btn-confirm">
                        <i class="fas fa-paper-plane"></i> Kirim
                    </button>
                </div>
            </form>
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

        function openEmailModal(docId, docName) {
            document.getElementById('emailDocId').value = docId;
            document.getElementById('emailDocName').textContent = docName;
            document.getElementById('emailModal').classList.add('active');
        }

        function closeEmailModal() {
            document.getElementById('emailModal').classList.remove('active');
        }

        document.getElementById('emailForm').addEventListener('submit', function(e) {
            // Show loading overlay
            document.getElementById('loadingOverlay').classList.add('active');
            // Close email modal
            closeEmailModal();
        });

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                closeDeleteModal();
                closeEmailModal();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeDeleteModal();
                closeEmailModal();
            }
        });
    </script>
</body>
</html>