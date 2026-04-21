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
            $appConfig = file_exists(__DIR__ . '/../config.php') ? require __DIR__ . '/../config.php' : [];
            $appName = $appConfig['app_name'] ?? 'Bathyal';
            $smtpConfig = $appConfig['smtp'] ?? [];

            // Fetch from Environment Variables (SetEnv in .htaccess) first, then config.php, then defaults
            $smtpUser = getenv('SMTP_USER') ?: ($smtpConfig['username'] ?? '');
            $smtpPass = getenv('SMTP_PASS') ?: ($smtpConfig['password'] ?? '');
            $smtpHost = getenv('SMTP_HOST') ?: ($smtpConfig['host'] ?? 'smtp.gmail.com');
            $smtpPort = getenv('SMTP_PORT') ?: ($smtpConfig['port'] ?? 587);
            $smtpSecure = getenv('SMTP_SECURE') ?: ($smtpConfig['secure'] ?? 'tls');
            $smtpFromEmail = getenv('SMTP_FROM_EMAIL') ?: ($smtpConfig['from_email'] ?? $smtpUser);
            $smtpFromName = getenv('SMTP_FROM_NAME') ?: ($smtpConfig['from_name'] ?? ($appName . ' System'));

            // Server settings
            if (!empty($smtpUser) && $smtpUser !== 'YOUR_EMAIL@gmail.com') {
                $this->mail->isSMTP();
                $this->mail->Host = $smtpHost;
                $this->mail->SMTPAuth = true;
                $this->mail->Username = $smtpUser;
                $this->mail->Password = $smtpPass;
                $this->mail->SMTPSecure = ($smtpSecure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $this->mail->Port = $smtpPort;
                $this->mail->setFrom($smtpFromEmail, $smtpFromName);
            } else {
                // Fallback to local mail() if SMTP is not configured
                $this->mail->isMail();
                $this->mail->setFrom('no-reply@' . strtolower(str_replace(' ', '', $appName)) . '.local', $appName . ' System');
            }

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
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Email send error: {$this->mail->ErrorInfo}");
            return ['success' => false, 'error' => $this->mail->ErrorInfo];
        }
    }
}