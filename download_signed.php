<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Document ID tidak valid!');
}

$doc_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM signed_documents WHERE id = $doc_id AND user_id = $user_id";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    die('Dokumen tidak ditemukan atau Anda tidak memiliki akses!');
}

$doc = mysqli_fetch_assoc($result);

if (!file_exists($doc['signed_file'])) {
    die('File tidak ditemukan di server!');
}

$filename = $doc['document_name'];
$filepath = $doc['signed_file'];
$filesize = filesize($filepath);

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="SIGNED_' . basename($filename) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $filesize);

ob_clean();
flush();

readfile($filepath);
exit();
?>