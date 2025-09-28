<?php
// send_reset_email.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email address.';
        echo json_encode($response);
        exit;
    }

    // Generate a unique token (in a real app, store in DB with user ID and expiration)
    $token = bin2hex(random_bytes(32));
    $expiration = time() + 3600; // 1 hour expiration (enforce in reset script)

    // In a real app, store token and expiration in database associated with user
    // For demo, we'll just generate and send (assume DB logic here)
    // Example: $db->query("INSERT INTO reset_tokens (email, token, expiration) VALUES (?, ?, ?)", [$email, $token, $expiration]);

    // Reset link (replace with your actual reset page URL)
    $resetLink = "https://your-app.onrender.com/reset.php?email=" . urlencode($email) . "&token=" . $token;
    // Note: In reset.php, validate token, check expiration, and allow password reset if valid.

    // Send email using PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Server settings (configure for your SMTP server)
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.example.com'; // e.g., smtp.gmail.com
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'] ?? 'your-email@example.com'; // Your SMTP username
        $mail->Password = $_ENV['SMTP_PASSWORD'] ?? 'your-smtp-password'; // Your SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // or ENCRYPTION_SMTPS
        $mail->Port = (int)($_ENV['SMTP_PORT'] ?? 587); // or 465 for SSL

        // Recipients
        $mail->setFrom($_ENV['FROM_EMAIL'] ?? 'no-reply@yourdomain.com', 'Student Portal');
        $mail->addAddress($email);

        // Content
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
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>
