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
            $appConfig = file_exists(__DIR__ . '/../config.php') ? require __DIR__ . '/../config.php' : ['app_name' => 'Bathyal'];
            $appName = $appConfig['app_name'] ?? 'Bathyal';

            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = 'smtp.gmail.com'; 
            $this->mail->SMTPAuth = true;
            $this->mail->Username = 'YOUR_EMAIL@gmail.com'; 
            $this->mail->Password = 'YOUR_APP_PASSWORD'; 
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = 587;

            // Sender
            $this->mail->setFrom('YOUR_EMAIL@gmail.com', $appName . ' System');
            $this->mail->isHTML(true);
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