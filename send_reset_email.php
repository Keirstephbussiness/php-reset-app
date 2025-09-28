<?php
// CRITICAL: Set CORS headers IMMEDIATELY - before ANY other code
header('Access-Control-Allow-Origin: http://127.0.0.1:5500'); // Specific origin for security
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Enable error reporting to catch issues
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Check for PHPMailer BEFORE requiring it
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        throw new Exception('Server configuration error: Dependencies not installed.');
    }

    // Wrap require in try-catch
    try {
        require_once __DIR__ . '/vendor/autoload.php';
    } catch (Throwable $e) {
        throw new Exception('Server configuration error: Failed to load dependencies.');
    }

    // Validate email
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address.');
    }

    // Check required environment variables
    $smtpPassword = getenv('SMTP_PASSWORD');
    if (empty($smtpPassword)) {
        throw new Exception('Server configuration error: SMTP not configured.');
    }

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception as PHPMailerException;

    $token = bin2hex(random_bytes(32));
    $resetLink = (getenv('APP_URL') ?: 'https://php-reset-app.onrender.com') . '/reset.php?email=' . urlencode($email) . '&token=' . $token;

    $mail = new PHPMailer(true);
    
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USERNAME') ?: 'kiersteph@gmail.com';
    $mail->Password = $smtpPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
    
    // Email settings
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
    $response['message'] = 'Reset email sent successfully.';

} catch (PHPMailerException $e) {
    $response['message'] = 'Failed to send email. Please try again later.';
    error_log("PHPMailer Error: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("General Error: " . $e->getMessage());
} catch (Throwable $e) {
    $response['message'] = 'Server error. Please try again later.';
    error_log("Fatal Error: " . $e->getMessage());
}

echo json_encode($response);
?>
