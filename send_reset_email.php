<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Set CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow all origins for testing
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email address.';
    echo json_encode($response);
    exit;
}

// Generate a unique token (in a real app, store in DB with user ID and expiration)
$token = bin2hex(random_bytes(32));
$expiration = time() + 3600; // 1 hour expiration (enforce in reset script)

// In a real app, store token in database
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
