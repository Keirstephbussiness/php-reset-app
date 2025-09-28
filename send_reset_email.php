<?php
// Set CORS headers FIRST (before any output)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// Check for PHPMailer
if (!file_exists('vendor/autoload.php')) {
    $response['message'] = 'Server error: PHPMailer not found.';
    echo json_encode($response);
    exit;
}
require 'vendor/autoload.php';

$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email address.';
    echo json_encode($response);
    exit;
}

$token = bin2hex(random_bytes(32));
$resetLink = (getenv('APP_URL') ?: 'https://php-reset-app.onrender.com') . '/reset.php?email=' . urlencode($email) . '&token=' . $token;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USERNAME') ?: 'kiersteph@gmail.com';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);

    $mail->setFrom(getenv('FROM_EMAIL') ?: 'kiersteph@gmail.com', getenv('FROM_NAME') ?: 'Student Portal');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Password Reset Request';
    $mail->Body = "
        <h2>Password Reset</h2>
        <p>Click the link below to reset your password. This link expires in 1 hour.</p>
        <a href='$resetLink' style='background-color: #007bff; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a>
        <p>If you didn't request this, ignore this email.</p>
    ";
    $mail->AltBody = "Click here to reset your password: $resetLink (expires in 1 hour)";

    $mail->send();
    $response['success'] = true;
} catch (Exception $e) {
    $response['message'] = "Mailer Error: {$mail->ErrorInfo}";
}

echo json_encode($response);
?>
