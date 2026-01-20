<?php
session_start();
require_once 'config.php';


header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['document_id'])) {
    echo json_encode(['success' => false, 'message' => 'Document ID tidak ditemukan']);
    exit();
}

$document_id = intval($input['document_id']);


$stmt = $conn->prepare("SELECT * FROM signed_documents WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $document_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();
$stmt->close();

if (!$document) {
    echo json_encode(['success' => false, 'message' => 'Dokumen tidak ditemukan atau bukan milik Anda']);
    exit();
}

try {
 
    if (file_exists($document['signed_file'])) {
        @unlink($document['signed_file']);
    }
    
    if (file_exists($document['original_file'])) {
        @unlink($document['original_file']);
    }
    
    
    $stmt = $conn->prepare("DELETE FROM signed_documents WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $document_id, $user_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
       
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) 
                               VALUES (?, 'delete_document', ?, NOW())");
        $action_desc = "Menghapus dokumen: " . $document['document_name'];
        $stmt->bind_param("is", $user_id, $action_desc);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Dokumen berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus dokumen dari database']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>