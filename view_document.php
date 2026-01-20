<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die('Access denied. Please login first.');
}

$user_id = $_SESSION['user_id'];
$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doc_id <= 0) {
    die('Invalid document ID.');
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
    die('Document not found or access denied.');
}

if (!file_exists($document['signed_file'])) {
    die('Document file not found on server.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($document['document_name']) . '"');
header('Content-Length: ' . filesize($document['signed_file']));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($document['signed_file']);
exit;
?>