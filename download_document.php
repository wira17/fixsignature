<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: auth.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doc_id <= 0) {
    $_SESSION['error'] = 'ID dokumen tidak valid!';
    header('Location: dokumen_saya.php');
    exit;
}

$user_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

$is_admin = ($user_data['role'] === 'admin');

if ($is_admin) {
    $stmt = $conn->prepare("SELECT * FROM signed_documents WHERE id = ?");
    $stmt->bind_param("i", $doc_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM signed_documents WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $doc_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();
$stmt->close();

if (!$document) {
    $_SESSION['error'] = 'Dokumen tidak ditemukan atau Anda tidak memiliki akses!';
    if ($is_admin) {
        header('Location: admin/documents.php');
    } else {
        header('Location: dokumen_saya.php');
    }
    exit;
}

if (!file_exists($document['signed_file'])) {
    $_SESSION['error'] = 'File dokumen tidak ditemukan di server!';
    if ($is_admin) {
        header('Location: admin/documents.php');
    } else {
        header('Location: dokumen_saya.php');
    }
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($document['document_name']) . '"');
header('Content-Length: ' . filesize($document['signed_file']));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Output the file
readfile($document['signed_file']);
exit;
?>