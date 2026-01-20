<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';

$success = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_menu'])) {
    $menu_id = isset($_POST['menu_id']) ? (int)$_POST['menu_id'] : 0;
    $menu_key = clean_input($_POST['menu_key']);
    $menu_name = clean_input($_POST['menu_name']);
    $menu_icon = clean_input($_POST['menu_icon']);
    $menu_category = clean_input($_POST['menu_category']);
    $menu_order = (int)$_POST['menu_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($menu_id > 0) {
        $sql = "UPDATE menus SET 
                menu_key = '$menu_key',
                menu_name = '$menu_name',
                menu_icon = '$menu_icon',
                menu_category = '$menu_category',
                menu_order = $menu_order,
                is_active = $is_active
                WHERE id = $menu_id";
        
        if (mysqli_query($conn, $sql)) {
            $success = "Menu berhasil diperbarui!";
        } else {
            $error = "Gagal memperbarui menu: " . mysqli_error($conn);
        }
    } else {
        $sql = "INSERT INTO menus (menu_key, menu_name, menu_icon, menu_category, menu_order, is_active) 
                VALUES ('$menu_key', '$menu_name', '$menu_icon', '$menu_category', $menu_order, $is_active)";
        
        if (mysqli_query($conn, $sql)) {
            $success = "Menu berhasil ditambahkan!";
        } else {
            $error = "Gagal menambahkan menu: " . mysqli_error($conn);
        }
    }
}


if (isset($_GET['delete'])) {
    $menu_id = (int)$_GET['delete'];
    
    // Delete related permissions first
    mysqli_query($conn, "DELETE FROM user_permissions WHERE menu_key = (SELECT menu_key FROM menus WHERE id = $menu_id)");
    
    // Delete menu
    if (mysqli_query($conn, "DELETE FROM menus WHERE id = $menu_id")) {
        $success = "Menu berhasil dihapus!";
    } else {
        $error = "Gagal menghapus menu!";
    }
}


$edit_menu = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = mysqli_query($conn, "SELECT * FROM menus WHERE id = $edit_id");
    $edit_menu = mysqli_fetch_assoc($edit_query);
}


$menus_query = "SELECT * FROM menus ORDER BY menu_category, menu_order";
$menus = mysqli_query($conn, $menus_query);


$grouped_menus = [];
mysqli_data_seek($menus, 0);
while ($menu = mysqli_fetch_assoc($menus)) {
    $grouped_menus[$menu['menu_category']][] = $menu;
}


$categories = array_keys($grouped_menus);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - Fix Signature</title>
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

        .btn-add {
            background: #6366f1;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn-add:hover {
            background: #4f46e5;
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

        .content-grid {
            display: grid;
            grid-template-columns: 400px 1fr;
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
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 6px;
            font-weight: 500;
        }

        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .btn-submit {
            width: 100%;
            padding: 10px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-submit:hover {
            background: #4f46e5;
        }

        .btn-cancel {
            width: 100%;
            padding: 10px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 8px;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .menu-section {
            margin-bottom: 24px;
        }

        .section-header {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .menu-items {
            display: grid;
            gap: 8px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .menu-item:hover {
            background: #f8fafc;
        }

        .menu-item.inactive {
            opacity: 0.6;
        }

        .menu-icon {
            width: 36px;
            height: 36px;
            background: #f8fafc;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6366f1;
            font-size: 16px;
        }

        .menu-info {
            flex: 1;
        }

        .menu-name {
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
        }

        .menu-key {
            font-size: 11px;
            color: #64748b;
            font-family: monospace;
        }

        .menu-order {
            font-size: 11px;
            color: #94a3b8;
            padding: 4px 8px;
            background: #f1f5f9;
            border-radius: 4px;
        }

        .menu-actions {
            display: flex;
            gap: 6px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-edit {
            background: #eff6ff;
            color: #2563eb;
        }

        .btn-edit:hover {
            background: #dbeafe;
        }

        .btn-delete {
            background: #fef2f2;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #fee2e2;
        }

        .status-badge {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .status-active {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-inactive {
            background: #fee2e2;
            color: #dc2626;
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
            <h1 class="page-title">Kelola Menu</h1>
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

        <div class="content-grid">
            <!-- Form Add/Edit -->
            <div class="card">
                <div class="card-title">
                    <?php echo $edit_menu ? 'Edit Menu' : 'Tambah Menu Baru'; ?>
                </div>

                <form method="POST">
                    <?php if ($edit_menu): ?>
                    <input type="hidden" name="menu_id" value="<?php echo $edit_menu['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="menu_key">Menu Key *</label>
                        <input type="text" id="menu_key" name="menu_key" 
                               placeholder="e.g., sign_new" 
                               value="<?php echo $edit_menu ? htmlspecialchars($edit_menu['menu_key']) : ''; ?>"
                               <?php echo $edit_menu ? 'readonly' : ''; ?>
                               required>
                        <small style="font-size: 11px; color: #94a3b8; margin-top: 4px; display: block;">
                            Gunakan underscore, lowercase, tanpa spasi
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="menu_name">Nama Menu *</label>
                        <input type="text" id="menu_name" name="menu_name" 
                               placeholder="e.g., Tanda Tangan Baru" 
                               value="<?php echo $edit_menu ? htmlspecialchars($edit_menu['menu_name']) : ''; ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="menu_icon">Icon (Font Awesome) *</label>
                        <input type="text" id="menu_icon" name="menu_icon" 
                               placeholder="e.g., fas fa-plus-circle" 
                               value="<?php echo $edit_menu ? htmlspecialchars($edit_menu['menu_icon']) : ''; ?>"
                               required>
                        <small style="font-size: 11px; color: #94a3b8; margin-top: 4px; display: block;">
                            Lihat icon di <a href="https://fontawesome.com/icons" target="_blank" style="color: #6366f1;">fontawesome.com</a>
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="menu_category">Kategori *</label>
                        <select id="menu_category" name="menu_category" required>
                            <option value="">Pilih kategori...</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"
                                    <?php echo ($edit_menu && $edit_menu['menu_category'] == $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="_new">+ Kategori Baru</option>
                        </select>
                    </div>

                    <div class="form-group" id="new_category_group" style="display: none;">
                        <label for="new_category">Nama Kategori Baru *</label>
                        <input type="text" id="new_category" placeholder="e.g., Laporan">
                    </div>

                    <div class="form-group">
                        <label for="menu_order">Urutan *</label>
                        <input type="number" id="menu_order" name="menu_order" 
                               value="<?php echo $edit_menu ? $edit_menu['menu_order'] : '1'; ?>"
                               min="0" required>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" 
                                   <?php echo (!$edit_menu || $edit_menu['is_active']) ? 'checked' : ''; ?>>
                            <label for="is_active" style="margin: 0; cursor: pointer;">Menu Aktif</label>
                        </div>
                    </div>

                    <button type="submit" name="save_menu" class="btn-submit">
                        <i class="fas fa-save"></i> <?php echo $edit_menu ? 'Update Menu' : 'Tambah Menu'; ?>
                    </button>

                    <?php if ($edit_menu): ?>
                    <a href="menus.php" class="btn-cancel">Batal</a>
                    <?php endif; ?>
                </form>
            </div>

          
            <div class="card">
                <div class="card-title">Daftar Menu</div>

                <?php foreach ($grouped_menus as $category => $menus): ?>
                <div class="menu-section">
                    <div class="section-header">
                        <i class="fas fa-folder"></i> <?php echo htmlspecialchars($category); ?>
                    </div>
                    <div class="menu-items">
                        <?php foreach ($menus as $menu): ?>
                        <div class="menu-item <?php echo $menu['is_active'] ? '' : 'inactive'; ?>">
                            <div class="menu-icon">
                                <i class="<?php echo $menu['menu_icon']; ?>"></i>
                            </div>
                            <div class="menu-info">
                                <div class="menu-name"><?php echo htmlspecialchars($menu['menu_name']); ?></div>
                                <div class="menu-key"><?php echo htmlspecialchars($menu['menu_key']); ?></div>
                            </div>
                            <div class="menu-order">#<?php echo $menu['menu_order']; ?></div>
                            <span class="status-badge <?php echo $menu['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $menu['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                            </span>
                            <div class="menu-actions">
                                <a href="?edit=<?php echo $menu['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteMenu(<?php echo $menu['id']; ?>, '<?php echo htmlspecialchars($menu['menu_name']); ?>')" 
                                        class="btn-icon btn-delete" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>

        document.getElementById('menu_category').addEventListener('change', function() {
            const newCategoryGroup = document.getElementById('new_category_group');
            if (this.value === '_new') {
                newCategoryGroup.style.display = 'block';
                document.getElementById('new_category').required = true;
            } else {
                newCategoryGroup.style.display = 'none';
                document.getElementById('new_category').required = false;
            }
        });

        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const categorySelect = document.getElementById('menu_category');
            const newCategory = document.getElementById('new_category');
            
            if (categorySelect.value === '_new' && newCategory.value) {
                categorySelect.value = newCategory.value;
            }
        });

        function deleteMenu(id, name) {
            if (confirm('Apakah Anda yakin ingin menghapus menu "' + name + '"?\n\nPeringatan: Semua hak akses terkait menu ini akan dihapus!')) {
                window.location.href = '?delete=' + id;
            }
        }
    </script>
</body>
</html>