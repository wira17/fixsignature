<?php
session_start();
require_once 'config.php';
require_once 'check_permission.php';
require_once 'MailHelper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$user_id = $_SESSION['user_id'];

requireMenuAccess($conn, $user_id, 'dokumen_saya', 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : 0;
    $recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Email tujuan tidak valid!";
        header('Location: dokumen_saya.php');
        exit;
    }
    
    $stmt = $conn->prepare("SELECT sd.*, u.nama as user_name 
                            FROM signed_documents sd 
                            LEFT JOIN users u ON sd.user_id = u.id 
                            WHERE sd.id = ? AND sd.user_id = ?");
    $stmt->bind_param("ii", $doc_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();
    $stmt->close();
    
    if (!$document) {
        $_SESSION['error'] = "Dokumen tidak ditemukan atau Anda tidak memiliki akses!";
        header('Location: dokumen_saya.php');
        exit;
    }
    
    if (!file_exists($document['signed_file'])) {
        $_SESSION['error'] = "File dokumen tidak ditemukan!";
        header('Location: dokumen_saya.php');
        exit;
    }
    
    try {
        $mailer = new MailHelper($conn);
        
        $mailer->sendDocumentEmail(
            $recipient_email,
            $document['document_name'],
            $document['signed_file'],
            $message
        );
        
        $log_query = "INSERT INTO email_logs (user_id, document_id, recipient_email, sent_at) 
                      VALUES (?, ?, ?, NOW())";
        $log_stmt = $conn->prepare($log_query);
        if ($log_stmt) {
            $log_stmt->bind_param("iis", $user_id, $doc_id, $recipient_email);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        $_SESSION['success'] = "✅ Email berhasil dikirim ke $recipient_email!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ Gagal mengirim email: " . $e->getMessage();
        error_log("Email send error: " . $e->getMessage());
    }
    
} else {
    $_SESSION['error'] = "Invalid request method!";
}

header('Location: dokumen_saya.php');
exit;
?>