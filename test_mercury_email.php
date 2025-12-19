<?php
/**
 * Mercury Email Test Script
 * 
 * This script tests if Mercury mail server is configured correctly
 * and can send emails via PHP's mail() function.
 */

// Test email configuration
$to = 'test@localhost';
$subject = 'CORNERSTONE - Mercury Test Email';
$message = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 20px;
            border: 1px solid #e5e7eb;
        }
        .code-box {
            background: white;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin: 15px 0;
        }
        .code {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 6px;
            color: #1e3a8a;
            font-family: "Courier New", monospace;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Mercury Test Email</h1>
        <p>CORNERSTONE Inventory Tracker</p>
    </div>
    
    <div class="content">
        <p><strong>Congratulations!</strong></p>
        
        <p>If you are reading this email, Mercury mail server is configured correctly and working!</p>
        
        <div class="code-box">
            <div class="code">TEST123</div>
        </div>
        
        <p>This is a test verification code format. Your actual verification codes will look like this.</p>
        
        <p><strong>Mercury Configuration:</strong></p>
        <ul>
            <li>SMTP Host: localhost</li>
            <li>SMTP Port: 25</li>
            <li>From: noreply@localhost</li>
        </ul>
        
        <p>You can find this email in Mercury\'s mail directory:<br>
        <code>C:\\xampp\\MercuryMail\\MAIL\\</code></p>
    </div>
</body>
</html>
';

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/html; charset=utf-8';
$headers[] = 'From: Cornerstone Inventory <noreply@localhost>';
$headers[] = 'X-Mailer: PHP/' . phpversion();

$headersString = implode("\r\n", $headers);

// Attempt to send email
$result = mail($to, $subject, $message, $headersString);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercury Email Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        h1 {
            color: #1e3a8a;
        }
        .btn {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        .btn:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Mercury Email Test</h1>
        
        <?php if ($result): ?>
            <div class="success">
                <h3>✅ Email Sent Successfully!</h3>
                <p>The PHP mail() function executed successfully. Mercury should have received the email.</p>
            </div>
            
            <div class="info">
                <h4>📧 Next Steps:</h4>
                <ol>
                    <li>Open Windows Explorer</li>
                    <li>Navigate to: <code>C:\xampp\MercuryMail\MAIL\</code></li>
                    <li>Look for the newest <code>.MER</code> file</li>
                    <li>Open it with a text editor (Notepad, VS Code, etc.)</li>
                    <li>You should see the HTML email content with "TEST123" code</li>
                </ol>
            </div>
            
            <div class="info">
                <h4>📋 Email Details:</h4>
                <ul>
                    <li><strong>To:</strong> test@localhost</li>
                    <li><strong>From:</strong> noreply@localhost</li>
                    <li><strong>Subject:</strong> CORNERSTONE - Mercury Test Email</li>
                    <li><strong>Format:</strong> HTML</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="error">
                <h3>❌ Email Failed to Send</h3>
                <p>The PHP mail() function returned false. This could mean:</p>
                <ul>
                    <li>Mercury is not running in XAMPP</li>
                    <li>PHP is not configured to use Mercury</li>
                    <li>There's a configuration issue</li>
                </ul>
            </div>
            
            <div class="info">
                <h4>🔍 Troubleshooting Steps:</h4>
                <ol>
                    <li>Open XAMPP Control Panel</li>
                    <li>Check if Mercury is running (should have green indicator)</li>
                    <li>If not running, click "Start" next to Mercury</li>
                    <li>Check <code>php.ini</code> for mail configuration:
                        <ul>
                            <li><code>SMTP = localhost</code></li>
                            <li><code>smtp_port = 25</code></li>
                        </ul>
                    </li>
                    <li>Restart Apache after any changes</li>
                    <li>Try running this test again</li>
                </ol>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <a href="signup.php" class="btn">← Back to Signup</a>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn" style="background: #6b7280; margin-left: 10px;">🔄 Run Test Again</a>
        </div>
    </div>
</body>
</html>
