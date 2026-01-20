<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';

$success = '';
$error = '';
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_permissions'])) {
    $user_id = (int)$_POST['user_id'];
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    
 
    $user_check = mysqli_query($conn, "SELECT id, role FROM users WHERE id = $user_id");
    if (mysqli_num_rows($user_check) == 0) {
        $error = "User tidak ditemukan!";
    } else {
        $user_data = mysqli_fetch_assoc($user_check);
        if ($user_data['role'] == 'admin') {
            $error = "Tidak dapat mengatur permission untuk admin!";
        } else {
           
            $menus_query = mysqli_query($conn, "SELECT menu_key FROM menus WHERE is_active = 1");
            
      
            mysqli_begin_transaction($conn);
            
            try {
                // Delete existing permissions for this user
                $delete_query = "DELETE FROM user_permissions WHERE user_id = $user_id";
                if (!mysqli_query($conn, $delete_query)) {
                    throw new Exception("Gagal menghapus permission lama");
                }
                
            
                $insert_count = 0;
                while ($menu = mysqli_fetch_assoc($menus_query)) {
                    $menu_key = $menu['menu_key'];
                    $can_access = in_array($menu_key, $permissions) ? 1 : 0;
                    
                    $sql = "INSERT INTO user_permissions (user_id, menu_key, can_access) 
                            VALUES ($user_id, '$menu_key', $can_access)";
                    
                    if (!mysqli_query($conn, $sql)) {
                        throw new Exception("Gagal menyimpan permission untuk menu: $menu_key");
                    }
                    $insert_count++;
                }
                
              
                mysqli_commit($conn);
                
                $success = "Hak akses menu berhasil disimpan! ($insert_count menu diproses)";
                $selected_user_id = $user_id;
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}


$users_query = "SELECT id, nama, email, nik_nip FROM users WHERE role = 'user' AND status = 'active' ORDER BY nama ASC";
$users = mysqli_query($conn, $users_query);

if (!$users) {
    die("Error getting users: " . mysqli_error($conn));
}


$selected_user = null;
if ($selected_user_id > 0) {
    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $selected_user_id AND role = 'user'");
    if ($user_query && mysqli_num_rows($user_query) > 0) {
        $selected_user = mysqli_fetch_assoc($user_query);
    } else {
        $error = "User tidak ditemukan atau bukan user biasa!";
        $selected_user_id = 0;
    }
}


$menus_query = "SELECT * FROM menus WHERE is_active = 1 ORDER BY menu_category, menu_order";
$menus = mysqli_query($conn, $menus_query);

if (!$menus) {
    die("Error getting menus: " . mysqli_error($conn) . " - Pastikan tabel 'menus' sudah dibuat!");
}


$grouped_menus = [];
while ($menu = mysqli_fetch_assoc($menus)) {
    $grouped_menus[$menu['menu_category']][] = $menu;
}


$user_permissions = [];
if ($selected_user_id > 0) {
    $perm_query = mysqli_query($conn, "SELECT menu_key FROM user_permissions WHERE user_id = $selected_user_id AND can_access = 1");
    while ($perm = mysqli_fetch_assoc($perm_query)) {
        $user_permissions[] = $perm['menu_key'];
    }
}


$total_users = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role = 'user' AND status = 'active'"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hak Akses Menu - Fix Signature</title>
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
            max-width: 1200px;
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

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
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

        .stats-bar {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #1e40af;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px;
        }

        .card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .user-list {
            display: grid;
            gap: 8px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border: 1px solid #e8ecf1;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .user-item:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .user-item.active {
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: #6366f1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
            font-weight: 500;
            flex-shrink: 0;
        }

        .user-item.active .user-avatar {
            background: #2563eb;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            font-size: 11px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .no-user-selected {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .no-user-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .selected-user-info {
            background: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .selected-avatar {
            width: 48px;
            height: 48px;
            background: #6366f1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            font-weight: 500;
        }

        .selected-details h3 {
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .selected-details p {
            font-size: 12px;
            color: #64748b;
        }

        .permissions-section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }

        .permissions-grid {
            display: grid;
            gap: 8px;
        }

        .permission-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .permission-item:hover {
            background: white;
        }

        .checkbox-wrapper {
            position: relative;
        }

        .permission-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #6366f1;
        }

        .permission-icon {
            width: 32px;
            height: 32px;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6366f1;
            font-size: 14px;
        }

        .permission-label {
            flex: 1;
            font-size: 13px;
            color: #1e293b;
            cursor: pointer;
        }

        .btn-save {
            width: 100%;
            padding: 12px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-save:hover {
            background: #4f46e5;
        }

        .btn-save:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }

        .select-all {
            padding: 12px;
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 6px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .select-all input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #6366f1;
        }

        .select-all label {
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 16px;
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
            <h1 class="page-title">Hak Akses Menu</h1>
            <p class="page-subtitle">Atur akses menu untuk setiap pengguna</p>
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

        <div class="stats-bar">
            <i class="fas fa-users"></i>
            <span>Total <?php echo $total_users; ?> pengguna aktif</span>
        </div>

        <div class="content-grid">
            <div class="card">
                <div class="card-title">Pilih Pengguna</div>
                <div class="user-list">
                    <?php 
                    mysqli_data_seek($users, 0);
                    if (mysqli_num_rows($users) == 0): 
                    ?>
                        <div style="text-align: center; padding: 20px; color: #94a3b8;">
                            <i class="fas fa-user-slash" style="font-size: 32px; margin-bottom: 10px;"></i>
                            <p>Tidak ada user aktif</p>
                        </div>
                    <?php else: ?>
                        <?php while ($user = mysqli_fetch_assoc($users)): ?>
                        <a href="?user_id=<?php echo $user['id']; ?>" 
                           class="user-item <?php echo $selected_user_id == $user['id'] ? 'active' : ''; ?>">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['nama'], 0, 1)); ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                        </a>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <?php if ($selected_user): ?>
                    <div class="selected-user-info">
                        <div class="selected-avatar">
                            <?php echo strtoupper(substr($selected_user['nama'], 0, 1)); ?>
                        </div>
                        <div class="selected-details">
                            <h3><?php echo htmlspecialchars($selected_user['nama']); ?></h3>
                            <p><?php echo htmlspecialchars($selected_user['email']); ?> • <?php echo htmlspecialchars($selected_user['nik_nip']); ?></p>
                        </div>
                    </div>

                    <?php if (empty($grouped_menus)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            Tidak ada menu yang tersedia. Pastikan tabel 'menus' sudah terisi data.
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">

                            <div class="select-all">
                                <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
                                <label for="selectAll">Pilih Semua Menu</label>
                            </div>

                            <?php foreach ($grouped_menus as $category => $menus): ?>
                            <div class="permissions-section">
                                <div class="section-title"><?php echo htmlspecialchars($category); ?></div>
                                <div class="permissions-grid">
                                    <?php foreach ($menus as $menu): ?>
                                    <div class="permission-item">
                                        <div class="checkbox-wrapper">
                                            <input type="checkbox" 
                                                   name="permissions[]" 
                                                   value="<?php echo $menu['menu_key']; ?>" 
                                                   id="menu_<?php echo $menu['menu_key']; ?>"
                                                   class="permission-checkbox"
                                                   <?php echo in_array($menu['menu_key'], $user_permissions) ? 'checked' : ''; ?>>
                                        </div>
                                        <div class="permission-icon">
                                            <i class="<?php echo $menu['menu_icon']; ?>"></i>
                                        </div>
                                        <label for="menu_<?php echo $menu['menu_key']; ?>" class="permission-label">
                                            <?php echo htmlspecialchars($menu['menu_name']); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <button type="submit" name="save_permissions" class="btn-save">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-user-selected">
                        <div class="no-user-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3>Pilih Pengguna</h3>
                        <p>Pilih pengguna dari daftar untuk mengatur hak akses menu</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('.permission-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }


        document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.permission-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.permission-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                if (selectAll) {
                    selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                }
            });
        });

   
        window.addEventListener('DOMContentLoaded', function() {
            const allCheckboxes = document.querySelectorAll('.permission-checkbox');
            const checkedCheckboxes = document.querySelectorAll('.permission-checkbox:checked');
            const selectAll = document.getElementById('selectAll');
            
            if (selectAll && allCheckboxes.length > 0) {
                selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
            }
        });
    </script>
</body>
</html>