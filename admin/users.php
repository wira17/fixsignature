<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';

$success = '';
$error = '';

// Proses Approve/Reject/Activate/Deactivate
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    $notes = isset($_POST['notes']) ? clean_input($_POST['notes']) : '';
    
    if ($action == 'approve') {
        $sql = "UPDATE users SET status = 'active', approved_by = {$admin['id']}, approved_at = NOW() WHERE id = $user_id";
        if (mysqli_query($conn, $sql)) {
            // Log approval
            $log_sql = "INSERT INTO approval_logs (user_id, admin_id, action, notes) VALUES ($user_id, {$admin['id']}, 'approve', '$notes')";
            mysqli_query($conn, $log_sql);
            $success = "Pengguna berhasil disetujui dan diaktifkan!";
        }
    } elseif ($action == 'reject') {
        $sql = "UPDATE users SET status = 'inactive' WHERE id = $user_id";
        if (mysqli_query($conn, $sql)) {
            $log_sql = "INSERT INTO approval_logs (user_id, admin_id, action, notes) VALUES ($user_id, {$admin['id']}, 'reject', '$notes')";
            mysqli_query($conn, $log_sql);
            $success = "Pengguna berhasil ditolak!";
        }
    } elseif ($action == 'activate') {
        $sql = "UPDATE users SET status = 'active' WHERE id = $user_id";
        if (mysqli_query($conn, $sql)) {
            $log_sql = "INSERT INTO approval_logs (user_id, admin_id, action, notes) VALUES ($user_id, {$admin['id']}, 'activate', '$notes')";
            mysqli_query($conn, $log_sql);
            $success = "Pengguna berhasil diaktifkan!";
        }
    } elseif ($action == 'deactivate') {
        $sql = "UPDATE users SET status = 'inactive' WHERE id = $user_id";
        if (mysqli_query($conn, $sql)) {
            $log_sql = "INSERT INTO approval_logs (user_id, admin_id, action, notes) VALUES ($user_id, {$admin['id']}, 'deactivate', '$notes')";
            mysqli_query($conn, $log_sql);
            $success = "Pengguna berhasil dinonaktifkan!";
        }
    }
}

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Query users
$where_clause = "WHERE role = 'user'";
if ($filter == 'pending') {
    $where_clause .= " AND status = 'pending'";
} elseif ($filter == 'active') {
    $where_clause .= " AND status = 'active'";
} elseif ($filter == 'inactive') {
    $where_clause .= " AND status = 'inactive'";
}

if (!empty($search)) {
    $where_clause .= " AND (nama LIKE '%$search%' OR email LIKE '%$search%' OR nik_nip LIKE '%$search%')";
}

$sql = "SELECT * FROM users $where_clause ORDER BY created_at DESC";
$users = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Fix Signature</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 20px;
            font-weight: 500;
            color: #1e293b;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
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

        .filter-tabs {
            display: flex;
            gap: 8px;
            flex: 1;
        }

        .filter-tab {
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            color: #64748b;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .filter-tab.active {
            background: #6366f1;
            color: white;
            border-color: #6366f1;
        }

        .filter-tab:hover {
            border-color: #cbd5e1;
        }

        .search-box {
            display: flex;
            gap: 8px;
        }

        .search-input {
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            width: 250px;
        }

        .btn-search {
            padding: 8px 16px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        th {
            background: #f8fafc;
            font-weight: 500;
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
        }

        td {
            font-size: 13px;
            color: #1e293b;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .status-pending {
            background: #fef9c3;
            color: #ca8a04;
        }

        .status-active {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-inactive {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 6px;
            transition: all 0.2s;
        }

        .btn-approve {
            background: #16a34a;
            color: white;
        }

        .btn-approve:hover {
            background: #15803d;
        }

        .btn-reject {
            background: #dc2626;
            color: white;
        }

        .btn-reject:hover {
            background: #b91c1c;
        }

        .btn-deactivate {
            background: #f59e0b;
            color: white;
        }

        .btn-deactivate:hover {
            background: #d97706;
        }

        .btn-permissions {
            background: #6366f1;
            color: white;
        }

        .btn-permissions:hover {
            background: #4f46e5;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 6px;
        }

        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            resize: vertical;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-cancel {
            padding: 8px 16px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .filter-section {
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }

            .table-container {
                font-size: 12px;
            }

            th, td {
                padding: 8px;
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
                Kembali
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Kelola Pengguna</h1>
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

        <div class="card">
            <div class="filter-section">
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        Semua
                    </a>
                    <a href="?filter=pending" class="filter-tab <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                        Menunggu Persetujuan
                    </a>
                    <a href="?filter=active" class="filter-tab <?php echo $filter == 'active' ? 'active' : ''; ?>">
                        Aktif
                    </a>
                    <a href="?filter=inactive" class="filter-tab <?php echo $filter == 'inactive' ? 'active' : ''; ?>">
                        Nonaktif
                    </a>
                </div>

                <form method="GET" class="search-box">
                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                    <input type="text" name="search" class="search-input" placeholder="Cari nama, email, atau NIK..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Cari
                    </button>
                </form>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>NIK/NIP</th>
                            <th>Email</th>
                            <th>No. HP</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($users) > 0): ?>
                            <?php while ($user = mysqli_fetch_assoc($users)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['nama']); ?></td>
                                <td><?php echo htmlspecialchars($user['nik_nip']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['no_hp']); ?></td>
                                <td>
                                    <?php if ($user['status'] == 'pending'): ?>
                                        <span class="status-badge status-pending">Menunggu</span>
                                    <?php elseif ($user['status'] == 'active'): ?>
                                        <span class="status-badge status-active">Aktif</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['status'] == 'pending'): ?>
                                        <button class="btn-action btn-approve" onclick="openModal(<?php echo $user['id']; ?>, 'approve')">
                                            <i class="fas fa-check"></i> Setujui
                                        </button>
                                        <button class="btn-action btn-reject" onclick="openModal(<?php echo $user['id']; ?>, 'reject')">
                                            <i class="fas fa-times"></i> Tolak
                                        </button>
                                    <?php elseif ($user['status'] == 'active'): ?>
                                        <button class="btn-action btn-deactivate" onclick="openModal(<?php echo $user['id']; ?>, 'deactivate')">
                                            <i class="fas fa-ban"></i> Nonaktifkan
                                        </button>
                                        <a href="permissions.php?user_id=<?php echo $user['id']; ?>" class="btn-action btn-permissions">
                                            <i class="fas fa-shield-alt"></i> Hak Akses
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-action btn-approve" onclick="openModal(<?php echo $user['id']; ?>, 'activate')">
                                            <i class="fas fa-check"></i> Aktifkan
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    Tidak ada data pengguna
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal" id="actionModal">
        <div class="modal-content">
            <div class="modal-header" id="modalTitle">Konfirmasi Aksi</div>
            <form method="POST">
                <input type="hidden" name="user_id" id="modalUserId">
                <input type="hidden" name="action" id="modalAction">
                
                <div class="form-group">
                    <label>Catatan (Opsional)</label>
                    <textarea name="notes" rows="3" placeholder="Tambahkan catatan..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn-action btn-approve" id="modalSubmit">Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(userId, action) {
            const modal = document.getElementById('actionModal');
            const title = document.getElementById('modalTitle');
            const submit = document.getElementById('modalSubmit');
            
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalAction').value = action;
            
            if (action === 'approve') {
                title.textContent = 'Setujui Pengguna';
                submit.textContent = 'Setujui';
                submit.className = 'btn-action btn-approve';
            } else if (action === 'reject') {
                title.textContent = 'Tolak Pengguna';
                submit.textContent = 'Tolak';
                submit.className = 'btn-action btn-reject';
            } else if (action === 'activate') {
                title.textContent = 'Aktifkan Pengguna';
                submit.textContent = 'Aktifkan';
                submit.className = 'btn-action btn-approve';
            } else if (action === 'deactivate') {
                title.textContent = 'Nonaktifkan Pengguna';
                submit.textContent = 'Nonaktifkan';
                submit.className = 'btn-action btn-deactivate';
            }
            
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('actionModal').classList.remove('active');
        }
    </script>
</body>
</html>