<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';

$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doc_id <= 0) {
    die('Invalid document ID.');
}

$stmt = $conn->prepare("SELECT * FROM signed_documents WHERE id = ?");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();
$stmt->close();

if (!$document) {
    die('Document not found.');
}

if (!file_exists('../' . $document['signed_file'])) {
    die('Document file not found on server.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($document['document_name']) . '"');
header('Content-Length: ' . filesize('../' . $document['signed_file']));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile('../' . $document['signed_file']);
exit;
?>