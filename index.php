<?php
// Install Symfony Mailer via Composer: composer require symfony/mailer

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

require 'vendor/autoload.php'; // Load Composer autoloader

// CRITICAL: Set CORS headers IMMEDIATELY
header('Access-Control-Allow-Origin: http://127.0.0.1:5500');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Validate email
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address.');
    }

    $token = bin2hex(random_bytes(32));
    // Use APP_URL from environment, fallback to production Render URL
    $baseUrl = getenv('APP_URL') ?: 'https://php-reset-app.onrender.com';
    $resetLink = $baseUrl . '/reset.php?email=' . urlencode($email) . '&token=' . $token;

    // Configure Symfony Mailer with Gmail SMTP
    $dsn = sprintf('smtp://%s:%s@smtp.gmail.com:587?encryption=starttls', 
        urlencode(getenv('GMAIL_USERNAME')), 
        urlencode(getenv('GMAIL_APP_PASSWORD'))
    );
    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);

    // Create email
    $emailMessage = (new Email())
        ->from(getenv('GMAIL_USERNAME'))
        ->to($email)
        ->replyTo(getenv('GMAIL_USERNAME'), 'Student Portal')
        ->subject('Password Reset Request - Student Portal')
        ->html("
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Password Reset</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #007bff;'>Password Reset Request</h2>
        <p>You have requested to reset your password for Student Portal.</p>
        <p>Click the button below to reset your password. This link will expire in 1 hour.</p>
        <div style='text-align: center; margin: 30px 0;'>
            <a href='$resetLink' style='background-color: #007bff; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Reset Password</a>
        </div>
        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p style='word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 4px;'>$resetLink</p>
        <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
        <p style='font-size: 14px; color: #666;'>If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
        <p style='font-size: 14px; color: #666;'>This is an automated message, please do not reply to this email.</p>
    </div>
</body>
</html>")
        ->text("Password Reset Request\n\nYou have requested to reset your password.\n\nReset link: $resetLink\n\nThis link will expire in 1 hour.");

    // Send email
    $mailer->send($emailMessage);

    $response['success'] = true;
    $response['message'] = 'Reset email sent successfully.';
    error_log("Password reset email sent to: " . $email);

} catch (Exception $e) {
    $response['message'] = 'Email could not be sent. Error: ' . $e->getMessage();
    error_log("Symfony Mailer Error: " . $e->getMessage());
} catch (Throwable $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
    error_log("General Error: " . $e->getMessage());
}

echo json_encode($response);
?>
