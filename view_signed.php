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

$sql = "SELECT sd.*, ts.serial_number, ts.verification_code 
        FROM signed_documents sd 
        LEFT JOIN tte_signatures ts ON sd.tte_id = ts.id 
        WHERE sd.id = $doc_id AND sd.user_id = $user_id";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    die('Dokumen tidak ditemukan atau Anda tidak memiliki akses!');
}

$doc = mysqli_fetch_assoc($result);

if (!file_exists($doc['signed_file'])) {
    die('File tidak ditemukan di server!');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Signed Document - Fix Signature</title>
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
            color: #2c3e50;
        }

        .header {
            background: white;
            border-bottom: 1px solid #e8ecf1;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .doc-title {
            font-size: 16px;
            font-weight: 500;
            color: #1e293b;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #6366f1;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .btn-secondary {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f8fafc;
        }

        .info-bar {
            background: white;
            border-bottom: 1px solid #e8ecf1;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
        }

        .pdf-viewer {
            width: 100%;
            height: calc(100vh - 110px);
            border: none;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #dcfce7;
            color: #166534;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="doc-title">
            <i class="fas fa-file-pdf" style="color: #dc2626; margin-right: 8px;"></i>
            <?php echo htmlspecialchars($doc['document_name']); ?>
        </div>
        <div class="header-actions">
            <a href="download_signed.php?id=<?php echo $doc['id']; ?>" class="btn btn-primary">
                <i class="fas fa-download"></i>
                Download
            </a>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                Tutup
            </button>
        </div>
    </div>

    <div class="info-bar">
        <div style="display: flex; gap: 24px;">
            <div class="info-item">
                <i class="fas fa-calendar"></i>
                Ditandatangani: <?php echo date('d M Y, H:i', strtotime($doc['signed_at'])); ?>
            </div>
            <div class="info-item">
                <i class="fas fa-file"></i>
                Ukuran: <?php echo number_format($doc['file_size'] / 1024, 2); ?> KB
            </div>
            <div class="info-item">
                <i class="fas fa-file-alt"></i>
                Halaman: <?php echo $doc['total_pages']; ?>
            </div>
        </div>
        <div>
            <span class="status-badge">
                <i class="fas fa-check-circle"></i>
                <?php echo $doc['status']; ?>
            </span>
        </div>
    </div>

    <iframe src="<?php echo $doc['signed_file']; ?>" class="pdf-viewer"></iframe>
</body>
</html>