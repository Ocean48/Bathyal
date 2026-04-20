<?php
// core/email_service.php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);

        try {
            // --- SMTP Server Settings ---
            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            
            // Read credentials from .htaccess Environment Variables
            // getenv() or $_SERVER can be used depending on Apache's mod_env configuration
            $smtpUser = getenv('SMTP_USER') !== false ? getenv('SMTP_USER') : ($_SERVER['SMTP_USER'] ?? '');
            $smtpPass = getenv('SMTP_PASS') !== false ? getenv('SMTP_PASS') : ($_SERVER['SMTP_PASS'] ?? '');

            $this->mail->Username   = $smtpUser; 
            $this->mail->Password   = $smtpPass;
            
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enables TLS encryption
            $this->mail->Port       = 587; // TCP port to connect to

            // --- Default Sender ---
            $this->mail->setFrom('YOUR_EMAIL@gmail.com', 'Bathyal System');
            
        } catch (Exception $e) {
            error_log("Email configuration error: {$this->mail->ErrorInfo}");
        }
    }

    /**
     * Send an email
     * 
     * @param string $toEmail Recipient email address
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $body Email content (HTML by default)
     * @param bool $isHtml Whether the body is HTML
     * @return bool True if sent, False if failed
     */
    public function sendEmail($toEmail, $toName, $subject, $body, $isHtml = true) {
        try {
            // Recipients
            $this->mail->clearAddresses(); // Clear previous addresses if used in a loop
            $this->mail->addAddress($toEmail, $toName);

            // Content
            $this->mail->isHTML($isHtml);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body); // Fallback plain text version

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}
?>