<?php

if (!file_exists(__DIR__ . '/lib/tcpdf/tcpdf.php')) {
    throw new Exception('TCPDF library tidak ditemukan');
}
if (!file_exists(__DIR__ . '/lib/fpdi/src/autoload.php')) {
    throw new Exception('FPDI library tidak ditemukan');
}

require_once __DIR__ . '/lib/tcpdf/tcpdf.php';
require_once __DIR__ . '/lib/fpdi/src/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;

class PDFStamper
{
    public static function stampQRCode(
        string $originalPDF,
        string $outputPDF,
        string $qrImage,
        float $position_x_percent,
        float $position_y_percent,
        int   $pageTarget,
        int   $qrWidthPx,
        int   $qrHeightPx,
        array $metadata = [],
        int   $canvasWidth = 595,
        int   $canvasHeight = 842
    ): array {
        try {
            $originalPDF = self::absPath($originalPDF);
            $outputPDF   = self::absPath($outputPDF);
            $qrImage     = self::absPath($qrImage);

            if (!file_exists($originalPDF)) {
                throw new Exception('File PDF sumber tidak ditemukan');
            }
            if (!file_exists($qrImage)) {
                throw new Exception('File QR tidak ditemukan');
            }

            $outDir = dirname($outputPDF);
            if (!file_exists($outDir)) {
                mkdir($outDir, 0777, true);
            }

            $pdf = new Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);

            $pageCount = $pdf->setSourceFile($originalPDF);
            
            for ($page = 1; $page <= $pageCount; $page++) {
                $tpl = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($tpl);

                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);

                if ($page === $pageTarget) {
                    // Calculate QR size
                    $qr_size_percent = 3.5;
                    $qr_size = ($qr_size_percent / 100) * $size['width'];
                    
                    if ($qr_size < 30) $qr_size = 30;
                    if ($qr_size > 80) $qr_size = 80;
                    
                    // Calculate position
                    $x = ($position_x_percent / 100) * $size['width'];
                    $y = ($position_y_percent / 100) * $size['height'];
                    
                    // Keep QR within bounds
                    if ($x + $qr_size > $size['width']) {
                        $x = $size['width'] - $qr_size - 5;
                    }
                    if ($y + $qr_size > $size['height']) {
                        $y = $size['height'] - $qr_size - 5;
                    }
                    if ($x < 0) $x = 5;
                    if ($y < 0) $y = 5;

                    // Stamp QR image
                    if (file_exists($qrImage)) {
                        $pdf->Image($qrImage, $x, $y, $qr_size, $qr_size);
                    }

                    $pdf->SetFont('courier', '', 3); 
                    $pdf->SetTextColor(250, 250, 250); 
                    
                    $meta_y = $size['height'] - 8; 
                    $meta_x = 5; 
                    
                  
                    $pdf->SetXY($meta_x, $meta_y);
                    $pdf->Write(0, 'FIX-SIGNATURE-METADATA');
                    
                    $meta_y += 2;
                    $pdf->SetXY($meta_x, $meta_y);
                    $pdf->Write(0, 'Serial:' . ($metadata['serial'] ?? 'N/A'));
                    
                    $meta_y += 2;
                    $pdf->SetXY($meta_x, $meta_y);
                    $pdf->Write(0, 'Kode:' . ($metadata['verification_code'] ?? 'N/A'));
                    
                    $meta_y += 2;
                    $pdf->SetXY($meta_x, $meta_y);
                    $pdf->Write(0, 'Fingerprint:' . substr($metadata['fingerprint'] ?? '', 0, 40));
                    
                    $pdf->SetXY($size['width'] - 100, 5); 
                    $pdf->SetFont('courier', '', 2);
                    $pdf->Write(0, 'VERIFY:' . ($metadata['serial'] ?? '') . ':' . ($metadata['verification_code'] ?? ''));
                    
                    $annotation_text = sprintf(
                        "TTE-DATA Serial:%s Kode:%s FP:%s",
                        $metadata['serial'] ?? '',
                        $metadata['verification_code'] ?? '',
                        substr($metadata['fingerprint'] ?? '', 0, 32)
                    );
                    $pdf->Annotation(2, 2, 50, 3, $annotation_text, ['Subtype' => 'Text']);
                    
                    $pdf->SetTextColor(0, 0, 0);
                    
                    if (!empty($metadata['serial'])) {
                        $pdf->SetFont('helvetica', '', 5);
                        $pdf->SetTextColor(80, 80, 80);
                        $text_y = $y + $qr_size + 1;
                        $pdf->Text($x, $text_y, 'TTE: ' . substr($metadata['serial'], 0, 20));
                        
                        if (!empty($metadata['date'])) {
                            $pdf->Text($x, $text_y + 3, $metadata['date']);
                        }
                    }
                }
            }

            $pdf->SetCreator('Fix-Signature TTE System');
            $pdf->SetAuthor($metadata['owner_name'] ?? 'TTE User');
            $pdf->SetTitle('Dokumen dengan TTE');
            $pdf->SetSubject('TTE Non-Sertifikasi BSrE Kominfo');
            
            $keywords = sprintf(
                'FIX-SIGNATURE TTE Serial:%s Kode:%s Fingerprint:%s',
                $metadata['serial'] ?? '',
                $metadata['verification_code'] ?? '',
                substr($metadata['fingerprint'] ?? '', 0, 32)
            );
            $pdf->SetKeywords($keywords);
            
            $outputDir = dirname($outputPDF);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            if (!is_writable($outputDir)) {
                throw new Exception("Output directory not writable: $outputDir");
            }
            
            $cleanOutputPath = str_replace('//', '/', $outputPDF);
            $pdf->Output($cleanOutputPath, 'F');
            
            if (!file_exists($cleanOutputPath)) {
                throw new Exception("Failed to create PDF file");
            }
            
            @chmod($cleanOutputPath, 0644);

            return [
                'success' => true,
                'file'    => $cleanOutputPath,
                'pages'   => $pageCount,
                'message' => 'PDF berhasil dibubuhi TTE dengan metadata terverifikasi'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public static function generateQRImage(string $data, string $output, int $size = 8): bool
    {
        $qrLib = __DIR__ . '/lib/phpqrcode/qrlib.php';
        if (!file_exists($qrLib)) {
            return false;
        }
        require_once $qrLib;
        QRcode::png($data, $output, QR_ECLEVEL_M, $size, 2);
        return file_exists($output);
    }

    public static function getPageCount(string $pdfPath): int
    {
        try {
            $pdf = new Fpdi();
            return $pdf->setSourceFile(self::absPath($pdfPath));
        } catch (Exception $e) {
            return 0;
        }
    }

    private static function absPath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        
        $real = realpath($path);
        if ($real !== false) {
            return $real;
        }
        
        if (str_starts_with($path, __DIR__)) {
            return $path;
        }
        
        return __DIR__ . '/' . ltrim($path, './');
    }
}