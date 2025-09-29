<?php
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
    $resetLink = (getenv('APP_URL') ?: 'https://php-reset-app.onrender.com') . '/reset.php?email=' . urlencode($email) . '&token=' . $token;

    // Email content
    $subject = 'Password Reset Request - Student Portal';
    $message = "
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
</html>";

    // Email headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Student Portal <noreply@' . parse_url($_SERVER['HTTP_HOST'] ?? 'localhost', PHP_URL_HOST) . '>',
        'Reply-To: noreply@' . parse_url($_SERVER['HTTP_HOST'] ?? 'localhost', PHP_URL_HOST),
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 3',
        'X-MSMail-Priority: Normal'
    ];

    // Send email using PHP's built-in mail() function
    if (mail($email, $subject, $message, implode("\r\n", $headers))) {
        $response['success'] = true;
        $response['message'] = 'Reset email sent successfully.';
        
        // Log successful email (optional)
        error_log("Password reset email sent to: " . $email);
    } else {
        throw new Exception('Failed to send email. Mail server may not be configured.');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Email Error: " . $e->getMessage());
} catch (Throwable $e) {
    $response['message']
