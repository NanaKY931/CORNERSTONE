<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Email Helper
 * 
 * Handles email sending with support for development and production modes
 */

if (!defined('CORNERSTONE_APP')) {
    require_once __DIR__ . '/config.php';
}

class EmailHelper {
    
    /**
     * Send verification email with access code
     * 
     * @param string $email Recipient email address
     * @param string $code Verification code
     * @param string $username Username
     * @return array ['success' => bool, 'code' => string (dev mode only), 'message' => string]
     */
    public static function sendVerificationEmail($email, $code, $username) {
        // Development mode - return code for display
        if (EMAIL_MODE === 'dev') {
            return [
                'success' => true,
                'code' => $code,
                'message' => 'Development mode: Code generated successfully'
            ];
        }
        
        // Production mode - send actual email
        $subject = 'Verify Your Cornerstone Account';
        $message = self::getVerificationEmailTemplate($code, $username);
        $headers = self::getEmailHeaders();
        
        $sent = mail($email, $subject, $message, $headers);
        
        return [
            'success' => $sent,
            'message' => $sent ? 'Verification email sent successfully' : 'Failed to send email'
        ];
    }
    
    /**
     * Get HTML email template for verification
     * 
     * @param string $code Verification code
     * @param string $username Username
     * @return string HTML email content
     */
    private static function getVerificationEmailTemplate($code, $username) {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .code-box {
            background: white;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #1e3a8a;
            font-family: 'Courier New', monospace;
        }
        .footer {
            background: #f3f4f6;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Cornerstone Inventory Tracker</h1>
        <p>Verify Your Account</p>
    </div>
    
    <div class="content">
        <p>Hello <strong>{$username}</strong>,</p>
        
        <p>Thank you for signing up! To complete your registration and access your dashboard, please use the verification code below:</p>
        
        <div class="code-box">
            <div class="code">{$code}</div>
        </div>
        
        <p>This code will expire in <strong>15 minutes</strong>.</p>
        
        <p>If you didn't request this verification, please ignore this email.</p>
        
        <p>Best regards,<br>The Cornerstone Team</p>
    </div>
    
    <div class="footer">
        <p>&copy; 2024 Cornerstone Inventory Tracker. All rights reserved.</p>
        <p>This is an automated message, please do not reply.</p>
    </div>
</body>
</html>
HTML;
        
        return $html;
    }
    
    /**
     * Get email headers for HTML emails
     * 
     * @return string Email headers
     */
    private static function getEmailHeaders() {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=utf-8';
        $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
        $headers[] = 'Reply-To: ' . FROM_EMAIL;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        return implode("\r\n", $headers);
    }
    
    /**
     * Generate random verification code
     * 
     * @param int $length Code length (default from config)
     * @return string Random alphanumeric code
     */
    public static function generateCode($length = VERIFICATION_CODE_LENGTH) {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude similar chars: I,O,0,1
        $code = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $max)];
        }
        
        return $code;
    }
    
    /**
     * Calculate expiration datetime
     * 
     * @param int $seconds Seconds from now (default from config)
     * @return string MySQL datetime format
     */
    public static function getExpirationTime($seconds = VERIFICATION_CODE_EXPIRY) {
        return date('Y-m-d H:i:s', time() + $seconds);
    }
}

?>
