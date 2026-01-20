<?php
// File: check_permission.php
// Helper untuk mengecek permission user

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

/**
 * Mengecek apakah user memiliki akses ke menu tertentu
 * @param mysqli $conn Database connection
 * @param int $user_id ID user
 * @param string $menu_key Key menu yang dicek
 * @return bool True jika punya akses, false jika tidak
 */
function hasMenuAccess($conn, $user_id, $menu_key) {
    // Admin selalu punya akses ke semua menu
    $user_query = mysqli_query($conn, "SELECT role FROM users WHERE id = $user_id");
    $user_data = mysqli_fetch_assoc($user_query);
    
    if ($user_data['role'] == 'admin') {
        return true;
    }
    
    // Cek permission user untuk menu ini
    $perm_query = mysqli_query($conn, "
        SELECT can_access 
        FROM user_permissions 
        WHERE user_id = $user_id AND menu_key = '$menu_key'
    ");
    
    if (mysqli_num_rows($perm_query) > 0) {
        $perm = mysqli_fetch_assoc($perm_query);
        return $perm['can_access'] == 1;
    }
    
    // Default: jika tidak ada permission, tidak punya akses
    return false;
}

/**
 * Mendapatkan semua menu yang bisa diakses user
 * @param mysqli $conn Database connection
 * @param int $user_id ID user
 * @return array Array menu yang bisa diakses
 */
function getUserAccessibleMenus($conn, $user_id) {
    // Admin bisa akses semua menu
    $user_query = mysqli_query($conn, "SELECT role FROM users WHERE id = $user_id");
    $user_data = mysqli_fetch_assoc($user_query);
    
    if ($user_data['role'] == 'admin') {
        $menus_query = mysqli_query($conn, "
            SELECT * FROM menus 
            WHERE is_active = 1 
            ORDER BY menu_category, menu_order
        ");
        $menus = [];
        while ($menu = mysqli_fetch_assoc($menus_query)) {
            $menus[] = $menu;
        }
        return $menus;
    }
    
    // User biasa, cek permission
    $menus_query = mysqli_query($conn, "
        SELECT m.* 
        FROM menus m
        INNER JOIN user_permissions up ON m.menu_key = up.menu_key
        WHERE m.is_active = 1 
        AND up.user_id = $user_id 
        AND up.can_access = 1
        ORDER BY m.menu_category, m.menu_order
    ");
    
    $menus = [];
    while ($menu = mysqli_fetch_assoc($menus_query)) {
        $menus[] = $menu;
    }
    
    return $menus;
}

/**
 * Redirect jika user tidak punya akses
 * @param mysqli $conn Database connection
 * @param int $user_id ID user
 * @param string $menu_key Key menu yang dicek
 * @param string $redirect_url URL redirect jika tidak punya akses
 */
function requireMenuAccess($conn, $user_id, $menu_key, $redirect_url = 'dashboard.php') {
    if (!hasMenuAccess($conn, $user_id, $menu_key)) {
        $_SESSION['error_message'] = "Anda tidak memiliki akses ke halaman ini.";
        header("Location: $redirect_url");
        exit();
    }
}
?>