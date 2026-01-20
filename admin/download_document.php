<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';

$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doc_id <= 0) {
    $_SESSION['error'] = 'ID dokumen tidak valid!';
    header('Location: documents.php');
    exit;
}


$stmt = $conn->prepare("SELECT * FROM signed_documents WHERE id = ?");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();
$stmt->close();

if (!$document) {
    $_SESSION['error'] = 'Dokumen tidak ditemukan!';
    header('Location: documents.php');
    exit;
}


if (!file_exists('../' . $document['signed_file'])) {
    $_SESSION['error'] = 'File dokumen tidak ditemukan di server!';
    header('Location: documents.php');
    exit;
}


header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($document['document_name']) . '"');
header('Content-Length: ' . filesize('../' . $document['signed_file']));
header('Cache-Control: must-revalidate');
header('Pragma: public');


readfile('../' . $document['signed_file']);
exit;
?>