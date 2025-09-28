<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set CORS headers immediately
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Initialize response
$response = ['success' => false, 'message' => ''];

// Log request for debugging
file_put_contents('/var/log/php83/app.log', date('Y-m-d H:i:s') . " Request: " . json_encode($_SERVER) . "\n", FILE_APPEND);

// Check for vendor/autoload.php
if (!file_exists('vendor/autoload.php')) {
    http_response_code(500);
    $response['message'] = 'Server error: PHPMailer not found';
    echo json_encode($response);
    exit;
}
require 'vendor/autoload.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Validate email
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    $response['message'] = 'Invalid email address';
    echo json_encode($response);
    exit;
}

// Generate reset token and link
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
    http_response_code(200);
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = "Mailer Error: {$mail->ErrorInfo}";
}

echo json_encode($response);
?>
