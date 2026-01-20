<?php
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailHelper {
    private $conn;
    private $mail;
    private $settings;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->mail = new PHPMailer(true);
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $query = "SELECT * FROM mail_settings WHERE is_active = 1 LIMIT 1";
        $result = mysqli_query($this->conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $this->settings = mysqli_fetch_assoc($result);
            $this->configureMailer();
        } else {
            throw new Exception('Mail settings not found. Please configure SMTP settings in admin panel.');
        }
    }
    
    private function configureMailer() {
        try {
     
            $this->mail->isSMTP();
            $this->mail->Host = $this->settings['smtp_host'];
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->settings['smtp_username'];
            $this->mail->Password = $this->settings['smtp_password'];
            $this->mail->Port = $this->settings['smtp_port'];
            
      
            if ($this->settings['smtp_encryption'] === 'ssl') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($this->settings['smtp_encryption'] === 'tls') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
        
            $this->mail->setFrom($this->settings['from_email'], $this->settings['from_name']);
            
            // Character set
            $this->mail->CharSet = 'UTF-8';
            
            
        } catch (Exception $e) {
            throw new Exception('Mail configuration error: ' . $e->getMessage());
        }
    }
    
    /**
     * Send email with attachment
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string|null $attachment_path Path to file attachment
     * @param string|null $attachment_name Custom name for attachment
     * @return bool Success status
     */
    public function sendEmail($to, $subject, $body, $attachment_path = null, $attachment_name = null) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            $this->mail->addAddress($to);
            
            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            if ($attachment_path && file_exists($attachment_path)) {
                if ($attachment_name) {
                    $this->mail->addAttachment($attachment_path, $attachment_name);
                } else {
                    $this->mail->addAttachment($attachment_path);
                }
            }
            
            $this->mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log('Mail Error: ' . $this->mail->ErrorInfo);
            throw new Exception('Failed to send email: ' . $this->mail->ErrorInfo);
        }
    }
    
    public function sendDocumentEmail($to, $document_name, $file_path, $message = '') {
        $subject = "Dokumen TTE: " . $document_name;
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #6366f1; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; }
                .message { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #64748b; font-size: 12px; }
                .btn { display: inline-block; padding: 12px 24px; background: #6366f1; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>📄 Dokumen Elektronik</h2>
                </div>
                <div class='content'>
                    <h3>Anda menerima dokumen yang telah ditandatangani secara elektronik</h3>
                    <div class='message'>
                        <p><strong>Nama Dokumen:</strong> {$document_name}</p>
                        " . ($message ? "<p><strong>Pesan:</strong><br>{$message}</p>" : "") . "
                        <p style='color: #64748b; font-size: 14px; margin-top: 20px;'>
                            ✅ Dokumen ini telah ditandatangani dengan Tanda Tangan Elektronik (TTE) yang sah dan dapat diverifikasi.
                        </p>
                    </div>
                    <p><strong>⚠️ Penting:</strong></p>
                    <ul style='color: #64748b; font-size: 14px;'>
                        <li>Dokumen terlampir dalam email ini</li>
                        <li>Jangan bagikan dokumen ini kepada pihak yang tidak berwenang</li>
                        <li>Dokumen ini memiliki kekuatan hukum yang sama dengan dokumen fisik</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>Email ini dikirim secara otomatis dari sistem Fix Signature</p>
                    <p>&copy; 2025 Fix Signature. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($to, $subject, $body, $file_path, $document_name);
    }
    
    public function testConnection() {
        try {
            $this->mail->smtpConnect();
            $this->mail->smtpClose();
            return true;
        } catch (Exception $e) {
            throw new Exception('SMTP Connection failed: ' . $e->getMessage());
        }
    }
    
    public function getSettings() {
        return $this->settings;
    }
}
?>