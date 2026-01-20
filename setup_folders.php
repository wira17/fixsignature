<?php

$folders = [
    'uploads',
    'uploads/documents',
    'uploads/signed',
    'uploads/qr_temp',
    'lib',
    'lib/tcpdf',
    'lib/tcpdf/cache',
    'lib/fpdi',
    'lib/phpqrcode'
];

$results = [];
$all_success = true;

echo "<html>
<head>
    <title>Setup Folders - Fix Signature</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            padding: 40px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1e293b;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 30px;
        }
        .folder-item {
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .warning {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }
        .info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .icon {
            font-size: 18px;
        }
        .path {
            font-family: monospace;
            flex: 1;
        }
        .status {
            font-weight: 500;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e8ecf1;
        }
        .summary h3 {
            color: #1e293b;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .summary p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .command {
            background: #1e293b;
            color: #f8fafc;
            padding: 12px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 13px;
            margin-top: 10px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #6366f1;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #4f46e5;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Setup Folders - Fix Signature</h1>
        <div class='subtitle'>Membuat dan mengatur permission folder yang diperlukan</div>";

foreach ($folders as $folder) {
    $folder_path = __DIR__ . '/' . $folder;
    $exists = file_exists($folder_path);
    $writable = is_writable($folder_path);
    
    if ($exists) {
        if ($writable) {
            $results[] = [
                'folder' => $folder,
                'status' => 'success',
                'message' => 'Folder sudah ada dan writable'
            ];
            echo "<div class='folder-item success'>
                    <span class='icon'>✓</span>
                    <span class='path'>{$folder}/</span>
                    <span class='status'>OK</span>
                  </div>";
        } else {
            // Try to chmod
            if (@chmod($folder_path, 0777)) {
                $results[] = [
                    'folder' => $folder,
                    'status' => 'success',
                    'message' => 'Permission diperbaiki'
                ];
                echo "<div class='folder-item success'>
                        <span class='icon'>✓</span>
                        <span class='path'>{$folder}/</span>
                        <span class='status'>Permission fixed</span>
                      </div>";
            } else {
                $results[] = [
                    'folder' => $folder,
                    'status' => 'error',
                    'message' => 'Folder ada tapi tidak writable'
                ];
                $all_success = false;
                echo "<div class='folder-item error'>
                        <span class='icon'>✗</span>
                        <span class='path'>{$folder}/</span>
                        <span class='status'>Not writable</span>
                      </div>";
            }
        }
    } else {
        // Try to create folder
        if (@mkdir($folder_path, 0777, true)) {
            @chmod($folder_path, 0777);
            $results[] = [
                'folder' => $folder,
                'status' => 'success',
                'message' => 'Folder berhasil dibuat'
            ];
            echo "<div class='folder-item success'>
                    <span class='icon'>✓</span>
                    <span class='path'>{$folder}/</span>
                    <span class='status'>Created</span>
                  </div>";
        } else {
            $results[] = [
                'folder' => $folder,
                'status' => 'error',
                'message' => 'Gagal membuat folder'
            ];
            $all_success = false;
            echo "<div class='folder-item error'>
                    <span class='icon'>✗</span>
                    <span class='path'>{$folder}/</span>
                    <span class='status'>Failed to create</span>
                  </div>";
        }
    }
}

// Summary
echo "<div class='summary'>";
if ($all_success) {
    echo "<h3 style='color: #166534;'>✓ Setup Berhasil!</h3>
          <p>Semua folder sudah siap digunakan.</p>
          <p><strong>Langkah selanjutnya:</strong></p>
          <p>1. Install libraries (TCPDF, FPDI, PHPQRCode) di folder lib/</p>
          <p>2. Upload pdf_stamper.php ke root directory</p>
          <p>3. Test bubuhkan TTE pada dokumen</p>
          <a href='tte_dokumen.php' class='btn'>Mulai TTE Dokumen</a>";
} else {
    echo "<h3 style='color: #dc2626;'>⚠ Ada Masalah!</h3>
          <p>Beberapa folder gagal dibuat atau tidak memiliki permission yang tepat.</p>
          <p><strong>Solusi manual via Terminal/SSH:</strong></p>
          <div class='command'>cd " . __DIR__ . "<br>mkdir -p uploads/documents uploads/signed uploads/qr_temp lib/tcpdf/cache<br>chmod -R 777 uploads/<br>chmod -R 777 lib/tcpdf/cache/</div>
          <p style='margin-top: 15px;'><strong>Atau via FTP/cPanel File Manager:</strong></p>
          <p>1. Buat folder secara manual: uploads/documents, uploads/signed, uploads/qr_temp</p>
          <p>2. Set permission ke 777 untuk semua folder uploads</p>
          <p>3. Refresh halaman ini untuk cek ulang</p>
          <a href='setup_folders.php' class='btn' style='background: #dc2626;'>Refresh & Cek Ulang</a>";
}
echo "</div>";

// Technical details
echo "<div class='folder-item info' style='margin-top: 20px;'>
        <span class='icon'>ℹ</span>
        <span style='flex: 1; font-size: 13px;'>
            <strong>Server Info:</strong> PHP " . PHP_VERSION . " | " . 
            "Working Dir: " . __DIR__ . " | " .
            "User: " . get_current_user()
        . "</span>
      </div>";

echo "</div></body></html>";

// Log results
$log_file = __DIR__ . '/setup_folders.log';
$log_content = date('Y-m-d H:i:s') . " - Setup Folders\n";
foreach ($results as $result) {
    $log_content .= sprintf("[%s] %s: %s\n", $result['status'], $result['folder'], $result['message']);
}
$log_content .= "\n";
@file_put_contents($log_file, $log_content, FILE_APPEND);
?>