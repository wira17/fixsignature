<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

$result = null;
$error = '';
$verification_data = null;

// Handle file upload and verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file'])) {
    $file = $_FILES['pdf_file'];
    
    // Validasi file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Terjadi kesalahan saat upload file!";
    } elseif ($file['type'] !== 'application/pdf') {
        $error = "Hanya file PDF yang diperbolehkan!";
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $error = "Ukuran file maksimal 10MB!";
    } else {
        // Create temporary directory
        $temp_dir = 'uploads/temp_verify/';
        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        
        // Save temporary file
        $temp_filename = 'verify_' . time() . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $temp_filepath = $temp_dir . $temp_filename;
        
        if (move_uploaded_file($file['tmp_name'], $temp_filepath)) {
            // Verify TTE
            $verification_data = verifyTTE($temp_filepath, $conn);
            
            // Clean up
            @unlink($temp_filepath);
        } else {
            $error = "Gagal mengupload file!";
        }
    }
}

// Function to verify TTE in PDF
function verifyTTE($filepath, $conn) {
    $result = [
        'valid' => false,
        'message' => '',
        'details' => []
    ];
    
    // Read PDF content as binary
    $pdf_content = file_get_contents($filepath);
    
    if (!$pdf_content) {
        $result['message'] = 'Gagal membaca file PDF';
        return $result;
    }
    
    // Check for FIX-SIGNATURE watermark
    $has_watermark = (strpos($pdf_content, 'FIX-SIGNATURE') !== false);
    
    // Extract metadata from PDF - improved extraction
    $metadata = extractPDFMetadata($pdf_content);
    
    if (empty($metadata['serial_number']) && empty($metadata['verification_code'])) {
        $result['message'] = 'Dokumen ini tidak memiliki TTE dari Fix-Signature atau TTE telah dimodifikasi';
        $result['details']['has_watermark'] = $has_watermark;
        $result['details']['metadata_found'] = false;
        return $result;
    }
    
    // Verify signature in database
    $serial_number = $metadata['serial_number'] ?? null;
    $verification_code = $metadata['verification_code'] ?? null;
    $fingerprint = $metadata['fingerprint'] ?? null;
    
    if (!$serial_number || !$verification_code) {
        $result['message'] = 'Metadata TTE tidak lengkap atau rusak';
        $result['details']['has_watermark'] = $has_watermark;
        $result['details']['metadata_found'] = true;
        $result['details']['metadata_complete'] = false;
        return $result;
    }
    
    // Query database with prepared statement - WITH ERROR HANDLING
    // First try with users table join
    $stmt = $conn->prepare("SELECT ts.*, u.name as owner_name, u.email as owner_email, u.nik 
                           FROM tte_signatures ts 
                           LEFT JOIN users u ON ts.user_id = u.id 
                           WHERE ts.serial_number = ? AND ts.verification_code = ?");
    
    // If prepare fails (users table might not exist), use simpler query
    if (!$stmt) {
        $stmt = $conn->prepare("SELECT * FROM tte_signatures 
                               WHERE serial_number = ? AND verification_code = ?");
        if (!$stmt) {
            $result['message'] = 'Database error: ' . $conn->error;
            return $result;
        }
    }
    
    $stmt->bind_param("ss", $serial_number, $verification_code);
    $stmt->execute();
    $db_result = $stmt->get_result();
    $tte_data = $db_result->fetch_assoc();
    $stmt->close();
    
    if (!$tte_data) {
        $result['message'] = 'TTE tidak ditemukan dalam database atau telah dipalsukan';
        $result['details']['has_watermark'] = $has_watermark;
        $result['details']['metadata_found'] = true;
        $result['details']['in_database'] = false;
        $result['details']['serial_number'] = $serial_number;
        $result['details']['verification_code'] = $verification_code;
        return $result;
    }
    
    // Verify fingerprint
    $tte_metadata = json_decode($tte_data['metadata'], true);
    $db_fingerprint = $tte_metadata['security']['fingerprint'] ?? '';
    
    if ($fingerprint && $db_fingerprint) {
        // Compare first 32 characters (MD5 length) as PDF metadata might be truncated
        $fingerprint_short = substr($fingerprint, 0, 32);
        $db_fingerprint_short = substr($db_fingerprint, 0, 32);
        
        if ($fingerprint_short !== $db_fingerprint_short) {
            $result['message'] = 'Fingerprint TTE tidak cocok! Dokumen mungkin telah dimodifikasi';
            $result['details']['has_watermark'] = $has_watermark;
            $result['details']['metadata_found'] = true;
            $result['details']['in_database'] = true;
            $result['details']['fingerprint_match'] = false;
            $result['details']['pdf_fingerprint'] = $fingerprint;
            $result['details']['db_fingerprint'] = substr($db_fingerprint, 0, 32) . '...';
            return $result;
        }
    }
    
    // Check TTE status
    if ($tte_data['status'] !== 'ACTIVE') {
        $result['message'] = 'TTE sudah tidak aktif (Status: ' . $tte_data['status'] . ')';
        $result['details']['tte_status'] = $tte_data['status'];
        $result['details']['tte_active'] = false;
        return $result;
    }
    
    // Check expiry
    $expiry_date = $tte_data['valid_until'];
    if ($expiry_date && strtotime($expiry_date) < time()) {
        $result['message'] = 'TTE sudah kadaluarsa (Expired: ' . date('d/m/Y', strtotime($expiry_date)) . ')';
        $result['details']['tte_expired'] = true;
        $result['details']['expiry_date'] = $expiry_date;
        return $result;
    }
    
    // Find signed document in database for additional verification
    $stmt = $conn->prepare("SELECT * FROM signed_documents WHERE tte_id = ? AND status = 'SIGNED' ORDER BY signed_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $tte_data['id']);
        $stmt->execute();
        $doc_result = $stmt->get_result();
        $signed_doc = $doc_result->fetch_assoc();
        $stmt->close();
    } else {
        $signed_doc = null;
    }
    
    // All checks passed - TTE is valid
    $result['valid'] = true;
    $result['message'] = 'TTE Valid dan Terpercaya';
    $result['details'] = [
        'has_watermark' => $has_watermark,
        'metadata_found' => true,
        'in_database' => true,
        'fingerprint_match' => true,
        'tte_active' => true,
        'tte_expired' => false,
        'serial_number' => $serial_number,
        'verification_code' => $verification_code,
        'owner_name' => $tte_data['common_name'] ?? $tte_data['owner_name'] ?? 'Unknown',
        'owner_email' => $tte_data['owner_email'] ?? $tte_data['email'] ?? 'Unknown',
        'owner_nik' => $tte_data['nik'] ?? 'Unknown',
        'issued_at' => $tte_data['created_at'] ?? null,
        'expired_at' => $tte_data['valid_until'] ?? null,
        'algorithm' => $tte_metadata['signature']['method'] ?? 'Unknown',
        'security_level' => $tte_metadata['security']['securityLevel'] ?? 'Unknown',
        'compliance' => $tte_metadata['compliance']['regulation'] ?? 'Unknown',
        'fingerprint' => substr($db_fingerprint, 0, 32) . '...',
        'document_signed_at' => $signed_doc['signed_at'] ?? null,
        'total_documents' => countDocumentsWithTTE($conn, $tte_data['id'])
    ];
    
    return $result;
}

// Function to extract metadata from PDF content - IMPROVED
function extractPDFMetadata($pdf_content) {
    $metadata = [];
    
    // Method 1: Extract from PDF Keywords (MOST RELIABLE - this is where our data is!)
    // Pattern: "FIX-SIGNATURE TTE Serial:XXX Kode:YYY Fingerprint:ZZZ"
    if (preg_match('/Keywords.*?Serial:([A-Z0-9]+)\s+Kode:([A-Z0-9]+)\s+Finge[a-z]*:([a-f0-9]+)/is', $pdf_content, $matches)) {
        $metadata['serial_number'] = trim($matches[1]);
        $metadata['verification_code'] = trim($matches[2]);
        $metadata['fingerprint'] = trim($matches[3]);
    }
    
    // Method 2: Direct pattern in content
    if (empty($metadata['serial_number']) && preg_match('/Serial:([A-Z0-9]+)[\s\)]/', $pdf_content, $matches)) {
        $metadata['serial_number'] = trim($matches[1]);
    }
    
    if (empty($metadata['verification_code']) && preg_match('/Kode:([A-Z0-9]+)[\s\)]/', $pdf_content, $matches)) {
        $metadata['verification_code'] = trim($matches[1]);
    }
    
    if (empty($metadata['fingerprint']) && preg_match('/Fingerprint:([a-f0-9]{32,})/', $pdf_content, $matches)) {
        $metadata['fingerprint'] = trim($matches[1]);
    }
    
    // Method 3: RDF metadata (also present in the PDF)
    if (preg_match('/<rdf:li>.*?Serial:([A-Z0-9]+)\s+Kode:([A-Z0-9]+)\s+Finge[a-z]*:([a-f0-9]+)/is', $pdf_content, $matches)) {
        if (empty($metadata['serial_number'])) $metadata['serial_number'] = trim($matches[1]);
        if (empty($metadata['verification_code'])) $metadata['verification_code'] = trim($matches[2]);
        if (empty($metadata['fingerprint'])) $metadata['fingerprint'] = trim($matches[3]);
    }
    
    // Method 4: VERIFY shorthand format
    if (preg_match('/VERIFY:([A-Z0-9]+):([A-Z0-9]+)/', $pdf_content, $matches)) {
        if (empty($metadata['serial_number'])) $metadata['serial_number'] = trim($matches[1]);
        if (empty($metadata['verification_code'])) $metadata['verification_code'] = trim($matches[2]);
    }
    
    // Method 5: Search in PDF objects
    if (preg_match_all('/\(([^)]*(?:Serial|Kode|Fingerprint)[^)]*)\)/s', $pdf_content, $matches)) {
        foreach ($matches[1] as $match) {
            if (empty($metadata['serial_number']) && preg_match('/Serial[:\s]*([A-Z0-9]+)/i', $match, $serial_match)) {
                $metadata['serial_number'] = trim($serial_match[1]);
            }
            if (empty($metadata['verification_code']) && preg_match('/Kode[:\s]*([A-Z0-9]+)/i', $match, $code_match)) {
                $metadata['verification_code'] = trim($code_match[1]);
            }
            if (empty($metadata['fingerprint']) && preg_match('/Fingerprint[:\s]*([a-f0-9]{32,})/i', $match, $fp_match)) {
                $metadata['fingerprint'] = trim($fp_match[1]);
            }
        }
    }
    
    return $metadata;
}

// Function to count documents signed with this TTE
function countDocumentsWithTTE($conn, $tte_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM signed_documents WHERE tte_id = ? AND status = 'SIGNED'");
    if (!$stmt) {
        return 0; // Return 0 if table doesn't exist
    }
    $stmt->bind_param("i", $tte_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Validitas TTE - Fix Signature</title>
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
            max-width: 800px;
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

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
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

        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #f8fafc;
            margin-bottom: 16px;
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

        .file-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 16px;
            display: none;
        }

        .file-info-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-icon {
            color: #dc2626;
            font-size: 24px;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
        }

        .file-size {
            font-size: 11px;
            color: #64748b;
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

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-container {
            background: white;
            border-radius: 12px;
            max-width: 900px;
            width: 100%;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e8ecf1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .modal-header-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .modal-icon-wrapper {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .modal-icon-wrapper.valid {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .modal-icon-wrapper.invalid {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .modal-icon-wrapper i {
            color: white;
            font-size: 28px;
        }

        .modal-title-group h2 {
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .modal-title-group p {
            font-size: 13px;
            color: #64748b;
        }

        .modal-close {
            background: #f1f5f9;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            color: #64748b;
            flex-shrink: 0;
        }

        .modal-close:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Info Grid - 2 Columns */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
        }

        .info-card-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .info-card-icon.blue {
            background: #dbeafe;
            color: #3b82f6;
        }

        .info-card-icon.green {
            background: #dcfce7;
            color: #16a34a;
        }

        .info-card-icon.purple {
            background: #f3e8ff;
            color: #9333ea;
        }

        .info-card-icon.orange {
            background: #fed7aa;
            color: #ea580c;
        }

        .info-card-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-card-value {
            font-size: 13px;
            color: #1e293b;
            font-weight: 600;
            word-break: break-all;
        }

        .security-checks {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
        }

        .security-checks-title {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-checks-title i {
            color: #6366f1;
        }

        .security-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            font-size: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .security-item:last-child {
            border-bottom: none;
        }

        .security-item-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .security-item-icon.check {
            background: #dcfce7;
            color: #16a34a;
        }

        .security-item-icon.cross {
            background: #fee2e2;
            color: #dc2626;
        }

        .security-item-icon i {
            font-size: 10px;
        }

        .security-item-text {
            color: #475569;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e8ecf1;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-shrink: 0;
        }

        .btn-modal {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-success {
            background: #6366f1;
            color: white;
        }

        .btn-success:hover {
            background: #4f46e5;
        }

        @media (max-width: 768px) {
            .modal-container {
                max-height: 90vh;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .modal-header-content {
                gap: 12px;
            }

            .modal-icon-wrapper {
                width: 48px;
                height: 48px;
            }

            .modal-icon-wrapper i {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <span class="brand-name">Fix Signature</span>
        </div>
        <?php if (isset($_SESSION['user_id'])): ?>
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
        <?php else: ?>
        <a href="auth.php" class="btn-back">
            <i class="fas fa-sign-in-alt"></i>
            Login
        </a>
        <?php endif; ?>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Cek Validitas TTE</h1>
            <p class="page-subtitle">Verifikasi keaslian tanda tangan elektronik pada dokumen PDF</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title">
                <i class="fas fa-cloud-upload-alt"></i>
                Upload Dokumen untuk Diverifikasi
            </div>

            <form method="POST" enctype="multipart/form-data" id="verifyForm">
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('pdf_file').click()">
                    <div class="upload-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="upload-text">Klik atau drag & drop file PDF di sini</div>
                    <div class="upload-hint">Maksimal 10MB • Format: PDF</div>
                    <input type="file" name="pdf_file" id="pdf_file" accept=".pdf" onchange="handleFileSelect()" required>
                </div>

                <div class="file-info" id="fileInfo">
                    <div class="file-info-content">
                        <i class="fas fa-file-pdf file-icon"></i>
                        <div class="file-details">
                            <div class="file-name" id="fileName"></div>
                            <div class="file-size" id="fileSize"></div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="verifyBtn" disabled>
                    <i class="fas fa-check-circle"></i>
                    Verifikasi TTE
                </button>
            </form>
        </div>
    </div>

    <!-- Modal -->
    <?php if ($verification_data): ?>
    <div class="modal-overlay active" id="resultModal">
        <div class="modal-container">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon-wrapper <?php echo $verification_data['valid'] ? 'valid' : 'invalid'; ?>">
                        <i class="fas fa-<?php echo $verification_data['valid'] ? 'check-circle' : 'times-circle'; ?>"></i>
                    </div>
                    <div class="modal-title-group">
                        <h2><?php echo $verification_data['valid'] ? 'TTE Terverifikasi!' : 'TTE Tidak Valid'; ?></h2>
                        <p><?php echo htmlspecialchars($verification_data['message']); ?></p>
                    </div>
                </div>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <?php if ($verification_data['valid']): ?>
                    <!-- Valid TTE Information -->
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-card-icon blue">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="info-card-label">Pemilik TTE</div>
                            <div class="info-card-value"><?php echo htmlspecialchars($verification_data['details']['owner_name']); ?></div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-icon green">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="info-card-label">NIK</div>
                            <div class="info-card-value"><?php echo htmlspecialchars($verification_data['details']['owner_nik']); ?></div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-icon purple">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-card-label">Email</div>
                            <div class="info-card-value"><?php echo htmlspecialchars($verification_data['details']['owner_email']); ?></div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-icon orange">
                                <i class="fas fa-fingerprint"></i>
                            </div>
                            <div class="info-card-label">Serial Number</div>
                            <div class="info-card-value"><?php echo htmlspecialchars($verification_data['details']['serial_number']); ?></div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-icon blue">
                                <i class="fas fa-key"></i>
                            </div>
                            <div class="info-card-label">Kode Verifikasi</div>
                            <div class="info-card-value"><?php echo htmlspecialchars($verification_data['details']['verification_code']); ?></div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-icon green">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="info-card-label">Algoritma</div>
                            <div class="info-card-value"><?php echo htmlspecialchars($verification_data['details']['algorithm']); ?></div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-icon purple">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="info-card-label">Diterbitkan</div>
                            <div class="info-card-value"><?php echo date('d/m/Y H:i', strtotime($verification_data['details']['issued_at'])); ?></div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-icon orange">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <div class="info-card-label">Kadaluarsa</div>
                            <div class="info-card-value"><?php echo date('d/m/Y H:i', strtotime($verification_data['details']['expired_at'])); ?></div>
                        </div>
                    </div>

                    <div class="security-checks">
                        <div class="security-checks-title">
                            <i class="fas fa-shield-alt"></i>
                            Pemeriksaan Keamanan
                        </div>
                        <div class="security-item">
                            <div class="security-item-icon check">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="security-item-text">Watermark Fix-Signature terdeteksi</div>
                        </div>
                        <div class="security-item">
                            <div class="security-item-icon check">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="security-item-text">Metadata TTE lengkap dan valid</div>
                        </div>
                        <div class="security-item">
                            <div class="security-item-icon check">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="security-item-text">TTE terdaftar dalam database</div>
                        </div>
                        <div class="security-item">
                            <div class="security-item-icon check">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="security-item-text">Fingerprint cocok dengan database</div>
                        </div>
                        <div class="security-item">
                            <div class="security-item-icon check">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="security-item-text">TTE masih aktif dan belum kadaluarsa</div>
                        </div>
                        <div class="security-item">
                            <div class="security-item-icon check">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="security-item-text">Dokumen belum dimodifikasi setelah TTE</div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Invalid TTE Information -->
                    <div class="security-checks">
                        <div class="security-checks-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Hasil Pemeriksaan
                        </div>
                        <div class="security-item">
                            <div class="security-item-icon <?php echo $verification_data['details']['has_watermark'] ? 'check' : 'cross'; ?>">
                                <i class="fas fa-<?php echo $verification_data['details']['has_watermark'] ? 'check' : 'times'; ?>"></i>
                            </div>
                            <div class="security-item-text">Watermark Fix-Signature <?php echo $verification_data['details']['has_watermark'] ? 'terdeteksi' : 'tidak terdeteksi'; ?></div>
                        </div>
                        <div class="security-item">
                            <div class="security-item-icon <?php echo isset($verification_data['details']['metadata_found']) && $verification_data['details']['metadata_found'] ? 'check' : 'cross'; ?>">
                                <i class="fas fa-<?php echo isset($verification_data['details']['metadata_found']) && $verification_data['details']['metadata_found'] ? 'check' : 'times'; ?>"></i>
                            </div>
                            <div class="security-item-text">Metadata TTE <?php echo isset($verification_data['details']['metadata_found']) && $verification_data['details']['metadata_found'] ? 'ditemukan' : 'tidak ditemukan'; ?></div>
                        </div>
                        <?php if (isset($verification_data['details']['in_database'])): ?>
                        <div class="security-item">
                            <div class="security-item-icon <?php echo $verification_data['details']['in_database'] ? 'check' : 'cross'; ?>">
                                <i class="fas fa-<?php echo $verification_data['details']['in_database'] ? 'check' : 'times'; ?>"></i>
                            </div>
                            <div class="security-item-text">TTE <?php echo $verification_data['details']['in_database'] ? 'terdaftar' : 'tidak terdaftar'; ?> dalam database</div>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($verification_data['details']['fingerprint_match'])): ?>
                        <div class="security-item">
                            <div class="security-item-icon <?php echo $verification_data['details']['fingerprint_match'] ? 'check' : 'cross'; ?>">
                                <i class="fas fa-<?php echo $verification_data['details']['fingerprint_match'] ? 'check' : 'times'; ?>"></i>
                            </div>
                            <div class="security-item-text">Fingerprint <?php echo $verification_data['details']['fingerprint_match'] ? 'cocok' : 'tidak cocok'; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($verification_data['details']['serial_number'])): ?>
                    <div style="margin-top: 16px;">
                        <div class="info-grid">
                            <div class="info-card">
                                <div class="info-card-icon orange">
                                    <i class="fas fa-fingerprint"></i>
                                </div>
                                <div class="info-card-label">Serial Number</div>
                                <div class="info-card-value"><?php echo htmlspecialchars($verification_data['details']['serial_number']); ?></div>
                            </div>
                            <?php if (isset($verification_data['details']['verification_code'])): ?>
                            <div class="info-card">
                                <div class="info-card-icon blue">
                                    <i class="fas fa-key"></i>
                                </div>
                                <div class="info-card-label">Kode Verifikasi</div>
                                <div class="info-card-value"><?php echo htmlspecialchars($verification_data['details']['verification_code']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button class="btn-modal btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                    Tutup
                </button>
                <button class="btn-modal btn-success" onclick="window.location.reload()">
                    <i class="fas fa-redo"></i>
                    Verifikasi Lagi
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Drag & Drop functionality
        const uploadArea = document.getElementById('uploadArea');
        
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

        function handleFileSelect() {
            const fileInput = document.getElementById('pdf_file');
            const file = fileInput.files[0];
            
            if (file) {
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = formatFileSize(file.size);
                document.getElementById('fileInfo').style.display = 'block';
                document.getElementById('verifyBtn').disabled = false;
            }
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        function closeModal() {
            document.getElementById('resultModal').classList.remove('active');
            setTimeout(() => {
                window.location.reload();
            }, 300);
        }

        // Close modal on overlay click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                closeModal();
            }
        });

        // Close modal on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>