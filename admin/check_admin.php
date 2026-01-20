<?php
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth.php");
    exit();
}

if ($_SESSION['role'] != 'admin') {
    header("Location: ../dashboard.php");
    exit();
}


$admin_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $admin_id AND role = 'admin'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) != 1) {
    session_destroy();
    header("Location: ../auth.php");
    exit();
}

$admin = mysqli_fetch_assoc($result);
?>