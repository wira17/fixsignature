<?php
// Copy lengkap dari document 3, hanya modifikasi bagian stampQRCode untuk metadata

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM tte_signatures WHERE user_id = ? AND status = 'ACTIVE' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tte_result = $stmt->get_result();
$user_tte = $tte_result->fetch_assoc();
$stmt->close();

$success = '';
$error = '';
$step = 1; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file']) && isset($_POST['upload_pdf'])) {
    if (!$user_tte) {
        $error = "Anda belum memiliki TTE aktif! Silakan generate TTE terlebih dahulu.";
    } else {
        $file = $_FILES['pdf_file'];
        $allowed = ['application/pdf'];
        
        if (!in_array($file['type'], $allowed)) {
            $error = "Hanya file PDF yang diperbolehkan!";
        } elseif ($file['size'] > 10 * 1024 * 1024) { 
            $error = "Ukuran file maksimal 10MB!";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "Terjadi kesalahan saat upload file!";
        } else {
            $upload_dir = 'uploads/documents/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $filename = 'doc_' . time() . '_' . bin2hex(random_bytes(8)) . '.pdf';
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                chmod($filepath, 0644);
                $_SESSION['temp_document'] = [
                    'filename' => basename($file['name']),
                    'filepath' => $filepath,
                    'size' => $file['size']
                ];
                $step = 2;
                $success = "Dokumen berhasil diupload! Posisikan TTE Anda pada dokumen.";
            } else {
                $error = "Gagal upload dokumen!";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sign_document'])) {
    if (!isset($_SESSION['temp_document'])) {
        $error = "Tidak ada dokumen yang diupload!";
    } else {
        $position_x = floatval($_POST['position_x']);
        $position_y = floatval($_POST['position_y']);
        $page_number = intval($_POST['page_number']);
        $canvas_width = isset($_POST['canvas_width']) ? intval($_POST['canvas_width']) : 595;
        $canvas_height = isset($_POST['canvas_height']) ? intval($_POST['canvas_height']) : 842;
        
        $temp_doc = $_SESSION['temp_document'];
        
        $signed_dir = 'uploads/signed/';
        $qr_temp_dir = 'uploads/qr_temp/';
        
        if (!file_exists($signed_dir)) {
            mkdir($signed_dir, 0755, true);
        }
        
        if (!file_exists($qr_temp_dir)) {
            mkdir($qr_temp_dir, 0755, true);
        }
        
        if (!$error) {
            $use_stamper = file_exists('pdf_stamper.php') && 
                          file_exists('lib/tcpdf/tcpdf.php') && 
                          file_exists('lib/fpdi/src/autoload.php') &&
                          file_exists('lib/phpqrcode/qrlib.php');
            
            $signed_filename = 'signed_' . time() . '_' . bin2hex(random_bytes(8)) . '.pdf';
            $signed_filepath = $signed_dir . $signed_filename;
            
            if ($use_stamper) {
                require_once 'pdf_stamper.php';
                
                $tte_metadata = json_decode($user_tte['metadata'], true);
                
                $qr_data = "FIX-SIGNATURE TTE\n" .
                          "Non-Sertifikasi\n\n" .
                          "PEMILIK:\n" .
                          $tte_metadata['certificate']['cn'] . "\n" .
                          "Email: " . $tte_metadata['certificate']['email'] . "\n\n" .
                          "TTE INFO:\n" .
                          "Serial: " . $tte_metadata['certificate']['serialNumber'] . "\n" .
                          "Kode: " . $tte_metadata['security']['verificationCode'] . "\n" .
                          "Algoritma: " . $tte_metadata['signature']['method'] . "\n" .
                          "Level: " . $tte_metadata['security']['securityLevel'] . "\n\n" .
                          "VALIDITAS:\n" .
                          "Dari: " . date('d/m/Y', strtotime($tte_metadata['validity']['notBefore'])) . "\n" .
                          "Hingga: " . date('d/m/Y', strtotime($tte_metadata['validity']['notAfter'])) . "\n" .
                          "Status: " . $tte_metadata['status']['current'] . "\n\n" .
                          "SECURITY:\n" .
                          "Fingerprint: " . substr($tte_metadata['security']['fingerprint'], 0, 32) . "\n" .
                          "Checksum: " . substr($tte_metadata['security']['checksum'], 0, 16) . "\n\n" .
                          "VERIFY:\n" .
                          $tte_metadata['verification']['url'] . "\n\n" .
                          "Standar: BSrE_KOMINFO Non Sertifikasi\n" .
                          "Regulasi: " . $tte_metadata['compliance']['regulation'] . "\n" .
                          "Tamper Protected - Fix-Signature";
                
                $qr_image_path = $qr_temp_dir . 'qr_' . bin2hex(random_bytes(8)) . '.png';
                $qr_generated = PDFStamper::generateQRImage($qr_data, $qr_image_path, 10);
                
                if ($qr_generated) {
                    $total_pages = PDFStamper::getPageCount($temp_doc['filepath']);
                    if ($total_pages == 0) {
                        $total_pages = $page_number;
                    }
                    
                    if ($canvas_width <= 0 || $canvas_height <= 0) {
                        $canvas_width = 595;
                        $canvas_height = 842;
                    }
                    
                    $embed_metadata = [
                        'serial' => $tte_metadata['certificate']['serialNumber'],
                        'verification_code' => $tte_metadata['security']['verificationCode'],
                        'fingerprint' => $tte_metadata['security']['fingerprint'],
                        'checksum' => $tte_metadata['security']['checksum'],
                        'date' => date('Y-m-d H:i:s'),
                        'owner_name' => $tte_metadata['certificate']['cn'],
                        'owner_email' => $tte_metadata['certificate']['email'],
                        'owner_nik' => $tte_metadata['certificate']['nik'],
                        'algorithm' => $tte_metadata['signature']['method'],
                        'security_level' => $tte_metadata['security']['securityLevel']
                    ];
                    
                    $result = PDFStamper::stampQRCode(
                        $temp_doc['filepath'],
                        $signed_filepath,
                        $qr_image_path,
                        $position_x,
                        $position_y,
                        $page_number,
                        100,
                        100,
                        $embed_metadata,  
                        $canvas_width,
                        $canvas_height
                    );
                    
                    @unlink($qr_image_path);
                    
                    if (!$result['success']) {
                        $use_stamper = false;
                        $error = "QR Stamping gagal: " . $result['message'];
                    }
                } else {
                    $use_stamper = false;
                    $error = "QR generation gagal";
                }
            }
            
            if (!$use_stamper && !$error) {
                if (@copy($temp_doc['filepath'], $signed_filepath)) {
                    @chmod($signed_filepath, 0644);
                    $total_pages = $page_number;
                    $success = "Dokumen berhasil disimpan! (Catatan: QR Code belum ter-stamp. Install library TCPDF, FPDI, dan PHPQRCode untuk fitur lengkap)";
                } else {
                    $error = "Gagal menyalin file PDF!";
                }
            }
            
            if (file_exists($signed_filepath) && !$error) {
                $position_json = json_encode([
                    'x' => $position_x,
                    'y' => $position_y,
                    'page' => $page_number,
                    'width' => 100,
                    'height' => 100,
                    'canvas_width' => $canvas_width,
                    'canvas_height' => $canvas_height
                ]);
                
                $stmt = $conn->prepare("INSERT INTO signed_documents 
                        (user_id, tte_id, document_name, original_file, signed_file, file_size, total_pages, signature_position, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'SIGNED')");
                
                $stmt->bind_param("iisssiis", 
                    $user_id, 
                    $user_tte['id'], 
                    $temp_doc['filename'], 
                    $temp_doc['filepath'], 
                    $signed_filepath, 
                    $temp_doc['size'], 
                    $total_pages, 
                    $position_json
                );
                
                if ($stmt->execute()) {
                    $signed_doc_id = $stmt->insert_id;
                    $_SESSION['signed_doc_id'] = $signed_doc_id;
                    unset($_SESSION['temp_document']);
                    $step = 3;
                    
                    if (!$success) {
                        $success = "Dokumen berhasil dibubuhkan TTE!";
                    }
                } else {
                    $error = "Gagal menyimpan ke database: " . $stmt->error;
                    @unlink($signed_filepath);
                }
                $stmt->close();
            }
        }
    }
}

$stmt = $conn->prepare("SELECT sd.*, ts.serial_number, ts.verification_code 
               FROM signed_documents sd 
               LEFT JOIN tte_signatures ts ON sd.tte_id = ts.id 
               WHERE sd.user_id = ? 
               ORDER BY sd.signed_at DESC 
               LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$docs_result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TTE Dokumen - Fix Signature</title>
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
            background: #6366f1;
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
            transition: all 0.2s ease;
        }

        .btn-back:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            margin-bottom: 16px;
        }

        .page-title {
            font-size: 18px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .page-subtitle {
            font-size: 13px;
            color: #64748b;
        }

        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
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

        .alert-warning {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
        }

        .card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
        }

        .card-title {
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #6366f1;
        }

        /* Step Indicator */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 24px;
            position: relative;
        }

        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e8ecf1;
            z-index: 0;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e8ecf1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            color: #94a3b8;
            font-size: 14px;
        }

        .step.active .step-circle {
            background: #6366f1;
            border-color: #6366f1;
            color: white;
        }

        .step.completed .step-circle {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }

        .step-label {
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }

        .step.active .step-label {
            color: #1e293b;
            font-weight: 500;
        }

        /* Upload Area */
        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #f8fafc;
        }

        .upload-area:hover {
            border-color: #6366f1;
            background: #eff6ff;
        }

        .upload-area.dragover {
            border-color: #6366f1;
            background: #eff6ff;
        }

        .upload-icon {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .upload-area:hover .upload-icon {
            color: #6366f1;
        }

        .upload-text {
            font-size: 14px;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .upload-hint {
            font-size: 12px;
            color: #64748b;
        }

        input[type="file"] {
            display: none;
        }

        .btn-primary {
            width: 100%;
            padding: 10px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .btn-primary:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }

        .btn-secondary {
            width: 100%;
            padding: 10px;
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        /* PDF Canvas Area */
        .pdf-canvas-container {
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 0;
            background: #f8fafc;
            position: relative;
            min-height: 600px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
        }

        .canvas-wrapper {
            position: relative;
            display: inline-block;
        }

        .pdf-canvas-container canvas {
            display: block;
            max-width: 100%;
            height: auto;
            border: 1px solid #e8ecf1;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            background: white;
        }

        .tte-stamp {
            position: absolute;
            width: 100px;
            height: 100px;
            cursor: move;
            border: 2px solid #6366f1;
            border-radius: 4px;
            background: rgba(99, 102, 241, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #6366f1;
            user-select: none;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
            transition: all 0.2s ease;
            left: 50px;
            top: 50px;
        }

        .tte-stamp:hover {
            border-color: #4f46e5;
            background: rgba(79, 70, 229, 0.2);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .tte-stamp:active {
            cursor: grabbing;
        }

        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-nav:hover:not(:disabled) {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #1e293b;
        }

        .btn-nav:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* TTE Info Card */
        .tte-info-box {
            background: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 16px;
        }

        .tte-info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #e8ecf1;
            font-size: 12px;
        }

        .tte-info-row:last-child {
            border-bottom: none;
        }

        .tte-label {
            color: #64748b;
        }

        .tte-value {
            color: #1e293b;
            font-weight: 500;
            font-family: monospace;
        }

        /* Documents Button */
        .btn-view-docs {
            width: 100%;
            padding: 12px;
            background: white;
            color: #6366f1;
            border: 2px solid #6366f1;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-view-docs:hover {
            background: #6366f1;
            color: white;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e8ecf1;
        }

        .modal-header-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-icon {
            width: 48px;
            height: 48px;
            background: #dcfce7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #16a34a;
            font-size: 24px;
        }

        .modal-icon.info {
            background: #dbeafe;
            color: #2563eb;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 500;
            color: #1e293b;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: #f1f5f9;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-info {
            background: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .modal-info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
        }

        .modal-label {
            color: #64748b;
            font-weight: 500;
        }

        .modal-value {
            color: #1e293b;
            font-weight: 500;
            text-align: right;
            word-break: break-word;
            max-width: 60%;
        }

        .modal-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .modal-btn {
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            text-decoration: none;
        }

        .modal-btn-primary {
            background: #6366f1;
            color: white;
        }

        .modal-btn-primary:hover {
            background: #4f46e5;
        }

        .modal-btn-secondary {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .modal-btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .modal-btn-danger {
            background: #dc2626;
            color: white;
        }

        .modal-btn-danger:hover {
            background: #b91c1c;
        }

        .modal-btn-success {
            background: #16a34a;
            color: white;
        }

        .modal-btn-success:hover {
            background: #15803d;
        }

        .doc-list-container {
            max-height: 400px;
            overflow-y: auto;
        }

        .doc-list-item {
            background: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .doc-list-item:hover {
            background: white;
            border-color: #6366f1;
            transform: translateX(4px);
        }

        .doc-list-name {
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .doc-list-meta {
            font-size: 11px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .doc-status-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #dcfce7;
            color: #166534;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .pdf-canvas-container {
                min-height: 400px;
            }

            .modal-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-file-signature"></i>
            </div>
            <span class="brand-name">Fix Signature</span>
        </div>
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">TTE Dokumen</h1>
            <p class="page-subtitle">Bubuhkan tanda tangan elektronik pada dokumen PDF Anda</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if (!$user_tte): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            Anda belum memiliki TTE aktif! <a href="generate_tte.php" style="color: #854d0e; text-decoration: underline;">Generate TTE sekarang</a>
        </div>
        <?php endif; ?>

        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'completed' : ''; ?> <?php echo $step == 1 ? 'active' : ''; ?>">
                <div class="step-circle"><?php echo $step > 1 ? '<i class="fas fa-check"></i>' : '1'; ?></div>
                <div class="step-label">Upload PDF</div>
            </div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <div class="step-circle"><?php echo $step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?></div>
                <div class="step-label">Posisi TTE</div>
            </div>
            <div class="step <?php echo $step == 3 ? 'active' : ''; ?>">
                <div class="step-circle">3</div>
                <div class="step-label">Selesai</div>
            </div>
        </div>

        <div class="content-grid">
            <div>
                <?php if ($step == 1): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Upload Dokumen PDF
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-area" id="uploadArea" onclick="document.getElementById('pdf_file').click()">
                            <div class="upload-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="upload-text">Klik atau drag & drop file PDF di sini</div>
                            <div class="upload-hint">Maksimal 10MB • Format: PDF</div>
                            <input type="file" name="pdf_file" id="pdf_file" accept=".pdf" onchange="handleFileSelect()" required>
                        </div>
                        <div id="fileInfo" style="margin-top: 16px; display: none;">
                            <div style="background: #f8fafc; border: 1px solid #e8ecf1; border-radius: 6px; padding: 12px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 24px;"></i>
                                    <div style="flex: 1;">
                                        <div id="fileName" style="font-size: 13px; font-weight: 500; color: #1e293b;"></div>
                                        <div id="fileSize" style="font-size: 11px; color: #64748b;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="upload_pdf" class="btn-primary" style="margin-top: 16px;" id="uploadBtn" disabled>
                            <i class="fas fa-arrow-right"></i>
                            Lanjutkan
                        </button>
                    </form>
                </div>
                <?php elseif ($step == 2): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-bullseye"></i>
                        Posisikan TTE pada Dokumen
                    </div>
                    
                    <div style="margin-bottom: 12px; background: #f8fafc; border: 1px solid #e8ecf1; border-radius: 6px; padding: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: #64748b;">
                            <i class="fas fa-info-circle" style="color: #6366f1;"></i>
                            <strong>File:</strong> <?php echo htmlspecialchars($_SESSION['temp_document']['filename']); ?>
                            <span style="margin-left: auto;">
                                <i class="fas fa-file-pdf" style="color: #dc2626;"></i>
                                <?php echo number_format($_SESSION['temp_document']['size'] / 1024, 2); ?> KB
                            </span>
                        </div>
                        <div style="margin-top: 8px; font-size: 11px; color: #94a3b8;">
                            <i class="fas fa-hand-pointer"></i> Drag kotak biru untuk memposisikan TTE pada dokumen
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div style="font-size: 12px; color: #64748b;">
                            <strong>Halaman:</strong> 
                            <span id="pageInfo">1 / <span id="totalPages">1</span></span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" onclick="prevPage()" class="btn-nav" id="prevBtn" style="padding: 6px 12px; background: white; border: 1px solid #e2e8f0; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                <i class="fas fa-chevron-left"></i> Prev
                            </button>
                            <button type="button" onclick="nextPage()" class="btn-nav" id="nextBtn" style="padding: 6px 12px; background: white; border: 1px solid #e2e8f0; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="pdf-canvas-container" id="pdfContainer">
                        <div class="canvas-wrapper" id="canvasWrapper">
                            <canvas id="pdfCanvas" style="border: 1px solid #e8ecf1; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background: white;"></canvas>
                            <div class="tte-stamp" id="tteStamp">
                                <div style="text-align: center; pointer-events: none;">
                                    <i class="fas fa-qrcode" style="font-size: 40px;"></i>
                                    <div style="font-size: 8px; margin-top: 4px;">TTE</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" id="signForm" style="margin-top: 16px;">
                        <input type="hidden" name="position_x" id="position_x" value="50">
                        <input type="hidden" name="position_y" id="position_y" value="50">
                        <input type="hidden" name="page_number" id="page_number" value="1">
                        <input type="hidden" name="canvas_width" id="canvas_width" value="0">
                        <input type="hidden" name="canvas_height" id="canvas_height" value="0">
                        
                        <div style="background: #f8fafc; border: 1px solid #e8ecf1; border-radius: 6px; padding: 12px; margin-bottom: 12px; font-size: 12px;">
                            <div style="color: #64748b; margin-bottom: 4px;">Posisi TTE:</div>
                            <div style="display: flex; gap: 16px; color: #1e293b;">
                                <span><strong>X:</strong> <span id="displayX">50</span>px</span>
                                <span><strong>Y:</strong> <span id="displayY">50</span>px</span>
                                <span><strong>Halaman:</strong> <span id="displayPage">1</span></span>
                            </div>
                        </div>
                        
                        <button type="submit" name="sign_document" class="btn-primary">
                            <i class="fas fa-signature"></i>
                            Bubuhkan TTE
                        </button>
                        <button type="button" onclick="location.reload()" class="btn-secondary">
                            <i class="fas fa-times"></i>
                            Batal
                        </button>
                    </form>
                </div>
                <?php elseif ($step == 3): ?>
                <div class="card" style="text-align: center; padding: 40px;">
                    <div style="width: 80px; height: 80px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-check" style="font-size: 40px; color: #16a34a;"></i>
                    </div>
                    <h2 style="font-size: 20px; color: #1e293b; margin-bottom: 8px;">Dokumen Berhasil Di-TTE!</h2>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 24px;">
                        Dokumen Anda telah berhasil dibubuhkan tanda tangan elektronik
                    </p>
                    <div style="display: grid; gap: 10px;">
                        <a href="tte_dokumen.php" class="btn-primary" style="text-decoration: none;">
                            <i class="fas fa-plus"></i>
                            TTE Dokumen Baru
                        </a>
                        <a href="dashboard.php" class="btn-secondary" style="text-decoration: none;">
                            <i class="fas fa-home"></i>
                            Kembali ke Dashboard
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($user_tte): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-certificate"></i>
                        TTE Aktif Anda
                    </div>
                    <div class="tte-info-box">
                        <div class="tte-info-row">
                            <span class="tte-label">Serial</span>
                            <span class="tte-value"><?php echo htmlspecialchars(substr($user_tte['serial_number'], 0, 16)); ?>...</span>
                        </div>
                        <div class="tte-info-row">
                            <span class="tte-label">Kode</span>
                            <span class="tte-value"><?php echo htmlspecialchars($user_tte['verification_code']); ?></span>
                        </div>
                        <div class="tte-info-row">
                            <span class="tte-label">Status</span>
                            <span class="tte-value" style="color: #16a34a;">ACTIVE</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <div class="modal" id="successModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="modal-title">Dokumen Berhasil Di-TTE!</div>
                </div>
                <button class="modal-close" onclick="closeSuccessModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">
                    Dokumen Anda telah berhasil dibubuhkan tanda tangan elektronik dengan detail berikut:
                </p>
                <div class="modal-info">
                    <div class="modal-info-row">
                        <span class="modal-label">Nama File</span>
                        <span class="modal-value" id="modalFileName">-</span>
                    </div>
                    <div class="modal-info-row">
                        <span class="modal-label">Serial TTE</span>
                        <span class="modal-value" id="modalSerial">-</span>
                    </div>
                    <div class="modal-info-row">
                        <span class="modal-label">Waktu</span>
                        <span class="modal-value" id="modalTime">-</span>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button onclick="downloadSuccessDocument()" class="modal-btn modal-btn-primary">
                    <i class="fas fa-download"></i>
                    Download
                </button>
                <button onclick="closeSuccessModal()" class="modal-btn modal-btn-secondary">
                    Tutup
                </button>
            </div>
        </div>
    </div>

   

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    
    <script>
        const documentsData = <?php 
            $docs_result->data_seek(0);
            $docs_array = [];
            while ($doc = $docs_result->fetch_assoc()) {
                $docs_array[] = [
                    'id' => $doc['id'],
                    'document_name' => $doc['document_name'],
                    'serial_number' => $doc['serial_number'],
                    'verification_code' => $doc['verification_code'],
                    'signed_at' => $doc['signed_at'],
                    'file_size' => $doc['file_size'],
                    'total_pages' => $doc['total_pages'],
                    'status' => $doc['status']
                ];
            }
            echo json_encode($docs_array);
        ?>;

        let currentDocId = null;

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        
        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.5;
        
        <?php if ($step == 2 && isset($_SESSION['temp_document'])): ?>
        const pdfPath = '<?php echo $_SESSION['temp_document']['filepath']; ?>';
        
        pdfjsLib.getDocument(pdfPath).promise.then(function(pdf) {
            pdfDoc = pdf;
            document.getElementById('totalPages').textContent = pdf.numPages;
            renderPage(pageNum);
        }).catch(function(error) {
            console.error('Error loading PDF:', error);
            alert('Gagal memuat PDF. Error: ' + error.message);
        });
        
        function renderPage(num) {
            pageRendering = true;
            
            pdfDoc.getPage(num).then(function(page) {
                const canvas = document.getElementById('pdfCanvas');
                const ctx = canvas.getContext('2d');
                const viewport = page.getViewport({scale: scale});
                
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
                const canvasWidthInput = document.getElementById('canvas_width');
                const canvasHeightInput = document.getElementById('canvas_height');
                if (canvasWidthInput) canvasWidthInput.value = Math.round(viewport.width);
                if (canvasHeightInput) canvasHeightInput.value = Math.round(viewport.height);
                
                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                
                const renderTask = page.render(renderContext);
                
                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });
            
            const pageInfoEl = document.getElementById('pageInfo');
            const totalPagesEl = document.getElementById('totalPages');
            const pageNumberInput = document.getElementById('page_number');
            const displayPageEl = document.getElementById('displayPage');
            const prevBtnEl = document.getElementById('prevBtn');
            const nextBtnEl = document.getElementById('nextBtn');
            
            if (pageInfoEl && totalPagesEl) {
                pageInfoEl.textContent = num + ' / ' + totalPagesEl.textContent;
            }
            if (pageNumberInput) {
                pageNumberInput.value = num;
            }
            if (displayPageEl) {
                displayPageEl.textContent = num;
            }
            
            if (prevBtnEl) {
                prevBtnEl.disabled = (num <= 1);
            }
            if (nextBtnEl && pdfDoc) {
                nextBtnEl.disabled = (num >= pdfDoc.numPages);
            }
        }
        
        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }
        
        function prevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }
        
        function nextPage() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }
        <?php endif; ?>
        
        // Drag & Drop Upload
        const uploadArea = document.getElementById('uploadArea');
        if (uploadArea) {
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                const file = e.dataTransfer.files[0];
                if (file && file.type === 'application/pdf') {
                    document.getElementById('pdf_file').files = e.dataTransfer.files;
                    handleFileSelect();
                }
            });
        }

        function handleFileSelect() {
            const fileInput = document.getElementById('pdf_file');
            const file = fileInput.files[0];
            
            if (file) {
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = formatFileSize(file.size);
                document.getElementById('fileInfo').style.display = 'block';
                document.getElementById('uploadBtn').disabled = false;
            }
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        // Draggable TTE Stamp
        const tteStamp = document.getElementById('tteStamp');
        if (tteStamp) {
            let isDragging = false;
            let startX, startY, startLeft, startTop;
            
            function updateCanvasDimensions() {
                const canvas = document.getElementById('pdfCanvas');
                if (canvas) {
                    document.getElementById('canvas_width').value = canvas.width;
                    document.getElementById('canvas_height').value = canvas.height;
                }
            }
            
            updateCanvasDimensions();

            tteStamp.addEventListener('mousedown', function(e) {
                isDragging = true;
                updateCanvasDimensions();
                
                const rect = tteStamp.getBoundingClientRect();
                const canvas = document.getElementById('pdfCanvas');
                const canvasRect = canvas.getBoundingClientRect();
                
                startLeft = rect.left - canvasRect.left;
                startTop = rect.top - canvasRect.top;
                
                startX = e.clientX;
                startY = e.clientY;
                
                tteStamp.style.cursor = 'grabbing';
                e.preventDefault();
            });

            document.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                
                const deltaX = e.clientX - startX;
                const deltaY = e.clientY - startY;
                
                let newLeft = startLeft + deltaX;
                let newTop = startTop + deltaY;
                
                const canvas = document.getElementById('pdfCanvas');
                const maxLeft = canvas.width - 100;
                const maxTop = canvas.height - 100;
                
                if (newLeft < 0) newLeft = 0;
                if (newTop < 0) newTop = 0;
                if (newLeft > maxLeft) newLeft = maxLeft;
                if (newTop > maxTop) newTop = maxTop;
                
                tteStamp.style.left = newLeft + 'px';
                tteStamp.style.top = newTop + 'px';
                
                const percentX = (newLeft / canvas.width) * 100;
                const percentY = (newTop / canvas.height) * 100;
                
                document.getElementById('position_x').value = percentX.toFixed(2);
                document.getElementById('position_y').value = percentY.toFixed(2);
                document.getElementById('displayX').textContent = Math.round(newLeft);
                document.getElementById('displayY').textContent = Math.round(newTop);
            });

            document.addEventListener('mouseup', function() {
                if (isDragging) {
                    isDragging = false;
                    tteStamp.style.cursor = 'move';
                    updateCanvasDimensions();
                }
            });
            
            // Touch support
            tteStamp.addEventListener('touchstart', function(e) {
                isDragging = true;
                const touch = e.touches[0];
                const rect = tteStamp.getBoundingClientRect();
                const canvas = document.getElementById('pdfCanvas');
                const canvasRect = canvas.getBoundingClientRect();
                
                startLeft = rect.left - canvasRect.left;
                startTop = rect.top - canvasRect.top;
                startX = touch.clientX;
                startY = touch.clientY;
                
                e.preventDefault();
            });
            
            document.addEventListener('touchmove', function(e) {
                if (!isDragging) return;
                const touch = e.touches[0];
                const deltaX = touch.clientX - startX;
                const deltaY = touch.clientY - startY;
                
                let newLeft = startLeft + deltaX;
                let newTop = startTop + deltaY;
                
                const canvas = document.getElementById('pdfCanvas');
                const maxLeft = canvas.width - 100;
                const maxTop = canvas.height - 100;
                
                if (newLeft < 0) newLeft = 0;
                if (newTop < 0) newTop = 0;
                if (newLeft > maxLeft) newLeft = maxLeft;
                if (newTop > maxTop) newTop = maxTop;
                
                tteStamp.style.left = newLeft + 'px';
                tteStamp.style.top = newTop + 'px';
                
                document.getElementById('position_x').value = Math.round(newLeft);
                document.getElementById('position_y').value = Math.round(newTop);
                document.getElementById('displayX').textContent = Math.round(newLeft);
                document.getElementById('displayY').textContent = Math.round(newTop);
            });
            
            document.addEventListener('touchend', function() {
                isDragging = false;
            });
        }

        function showDocumentsList() {
            document.getElementById('documentsModal').classList.add('active');
        }

        function closeDocumentsModal() {
            document.getElementById('documentsModal').classList.remove('active');
        }

        function showDocumentDetail(docId) {
            currentDocId = docId;
            const doc = documentsData.find(d => d.id == docId);
            
            if (doc) {
                document.getElementById('detailDocName').textContent = doc.document_name;
                document.getElementById('detailSerial').textContent = doc.serial_number;
                document.getElementById('detailCode').textContent = doc.verification_code;
                document.getElementById('detailTime').textContent = new Date(doc.signed_at).toLocaleString('id-ID');
                document.getElementById('detailSize').textContent = formatFileSize(doc.file_size);
                document.getElementById('detailPages').textContent = doc.total_pages + ' halaman';
                document.getElementById('detailStatus').textContent = doc.status;
                
                document.getElementById('btnView').href = 'view_signed.php?id=' + docId;
                document.getElementById('btnDownload').href = 'download_signed.php?id=' + docId;
                
                closeDocumentsModal();
                document.getElementById('documentDetailModal').classList.add('active');
            }
        }

        function closeDocumentDetail() {
            document.getElementById('documentDetailModal').classList.remove('active');
            currentDocId = null;
        }

        function showEmailModal() {
            if (!currentDocId) return;
            
            const doc = documentsData.find(d => d.id == currentDocId);
            if (doc) {
                document.getElementById('emailSubject').value = 'Dokumen TTE: ' + doc.document_name;
            }
            
            document.getElementById('emailModal').classList.add('active');
        }

        function closeEmailModal() {
            document.getElementById('emailModal').classList.remove('active');
            document.getElementById('emailForm').reset();
        }

        function sendEmail() {
            const email = document.getElementById('recipientEmail').value;
            const subject = document.getElementById('emailSubject').value;
            const message = document.getElementById('emailMessage').value;
            
            if (!email) {
                alert('Email penerima harus diisi!');
                return;
            }

            if (!currentDocId) {
                alert('Dokumen tidak ditemukan!');
                return;
            }

            fetch('send_document_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    document_id: currentDocId,
                    recipient_email: email,
                    subject: subject,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Email berhasil dikirim!');
                    closeEmailModal();
                    closeDocumentDetail();
                } else {
                    alert('Gagal mengirim email: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengirim email');
            });
        }

        function confirmDelete() {
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        function deleteDocument() {
            if (!currentDocId) return;

            fetch('delete_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    document_id: currentDocId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Dokumen berhasil dihapus!');
                    closeDeleteModal();
                    closeDocumentDetail();
                    location.reload();
                } else {
                    alert('Gagal menghapus dokumen: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus dokumen');
            });
        }

        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
            location.href = 'tte_dokumen.php';
        }

        function downloadSuccessDocument() {
            <?php if (isset($_SESSION['signed_doc_id'])): ?>
            window.location.href = 'download_signed.php?id=<?php echo $_SESSION['signed_doc_id']; ?>';
            <?php else: ?>
            alert('File tidak ditemukan. Silakan hubungi administrator.');
            <?php endif; ?>
        }

        <?php if ($step == 3 && isset($_SESSION['signed_doc_id'])): ?>
        document.getElementById('successModal').classList.add('active');
        document.getElementById('modalFileName').textContent = '<?php echo isset($temp_doc['filename']) ? addslashes($temp_doc['filename']) : 'N/A'; ?>';
        document.getElementById('modalSerial').textContent = '<?php echo $user_tte['serial_number']; ?>';
        document.getElementById('modalTime').textContent = '<?php echo date('d M Y, H:i'); ?>';
        <?php unset($_SESSION['signed_doc_id']); ?>
        <?php endif; ?>
    </script>
</body>
</html>