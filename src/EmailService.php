<?php

namespace ClaimIt;

/**
 * Email service for sending notifications via SMTP
 */
class EmailService
{
    private array $config;
    private AwsService $awsService;
    
    public function __construct(AwsService $awsService)
    {
        $this->awsService = $awsService;
        $this->loadConfig();
    }
    
    /**
     * Load SMTP configuration
     */
    private function loadConfig(): void
    {
        $configFile = __DIR__ . '/../config/smtp-config.php';
        
        if (!file_exists($configFile)) {
            throw new \Exception(
                "SMTP configuration file not found. Please copy 'smtp-config.example.php' to 'smtp-config.php' and configure your SMTP settings."
            );
        }
        
        $this->config = require $configFile;
        
        // Validate required configuration
        if (empty($this->config['host'])) {
            throw new \Exception('SMTP configuration is incomplete. Please check host.');
        }
        
        // Check if authentication is configured
        $this->config['auth_enabled'] = !empty($this->config['username']) && !empty($this->config['password']);
    }
    
    /**
     * Send email notification when an item is claimed
     */
    public function sendItemClaimedNotification(array $itemOwner, array $claimedItem, array $claimer): bool
    {
        try {
            // Check if user has email notifications enabled
            if (!$this->isEmailNotificationsEnabled($itemOwner['id'])) {
                return true; // Not an error, just not sending
            }
            
            // Prepare email content
            $subject = 'Your item has been claimed!';
            $htmlBody = $this->generateItemClaimedHtml($claimedItem, $claimer);
            $textBody = $this->generateItemClaimedText($claimedItem, $claimer);
            
            // Send email via SMTP
            $result = $this->sendSmtpEmail(
                $itemOwner['email'],
                $subject,
                $htmlBody,
                $textBody
            );
            
            if ($result) {
                error_log("Email sent successfully to {$itemOwner['email']} for claimed item {$claimedItem['tracking_number']}");
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Email Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send test email to administrator
     */
    public function sendTestEmail(array $user): bool
    {
        try {
            error_log("SMTP Debug: Starting test email to {$user['email']}");
            error_log("SMTP Debug: Using config - Host: {$this->config['host']}, Port: {$this->config['port']}, Encryption: " . ($this->config['encryption'] ?? 'None'));
            error_log("SMTP Debug: Authentication: " . ($this->config['auth_enabled'] ? 'Enabled' : 'Disabled'));
            if ($this->config['auth_enabled']) {
                error_log("SMTP Debug: Username: {$this->config['username']}, From: {$this->config['from_email']}");
            } else {
                error_log("SMTP Debug: From: {$this->config['from_email']} (no authentication)");
            }
            
            $subject = 'ClaimIt Test Email';
            $htmlBody = $this->generateTestEmailHtml($user);
            $textBody = $this->generateTestEmailText($user);
            
            // Send email via SMTP
            $result = $this->sendSmtpEmail(
                $user['email'],
                $subject,
                $htmlBody,
                $textBody
            );
            
            if ($result) {
                error_log("Test email sent successfully to {$user['email']}");
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Test Email Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send new listing notification to all users who have it enabled
     */
    public function sendNewListingNotifications(array $newItem, array $itemOwner): bool
    {
        try {
            // Get all users who have new listing notifications enabled
            $usersToNotify = $this->getUsersWithNewListingNotifications();
            
            if (empty($usersToNotify)) {
                error_log("No users have new listing notifications enabled");
                return true; // Not an error, just no one to notify
            }
            
            $successCount = 0;
            $totalCount = count($usersToNotify);
            
            foreach ($usersToNotify as $user) {
                // Don't notify the person who posted the item
                if ($user['id'] === $itemOwner['id']) {
                    continue;
                }
                
                try {
                    $subject = 'New item posted on ClaimIt!';
                    $htmlBody = $this->generateNewListingHtml($newItem, $itemOwner, $user);
                    $textBody = $this->generateNewListingText($newItem, $itemOwner, $user);
                    
                    $result = $this->sendSmtpEmail(
                        $user['email'],
                        $subject,
                        $htmlBody,
                        $textBody
                    );
                    
                    if ($result) {
                        $successCount++;
                        error_log("New listing notification sent to {$user['email']}");
                    }
                    
                } catch (\Exception $e) {
                    error_log("Failed to send new listing notification to {$user['email']}: " . $e->getMessage());
                }
            }
            
            error_log("New listing notifications sent: $successCount/$totalCount");
            return $successCount > 0;
            
        } catch (\Exception $e) {
            error_log("New Listing Notifications Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email via SMTP
     */
    private function sendSmtpEmail(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        // Create boundary for multipart message
        $boundary = md5(uniqid(time()));
        
        // Build email headers
        $headers = [
            'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>',
            'Reply-To: ' . $this->config['from_email'],
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: ClaimIt Email Service'
        ];
        
        // Build email body
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";
        
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        
        $body .= "--$boundary--\r\n";
        
        // Send email using PHP's mail() function with SMTP
        return $this->sendViaSmtp($to, $subject, $body, $headers);
    }
    
    /**
     * Send email via SMTP connection
     */
    private function sendViaSmtp(string $to, string $subject, string $body, array $headers): bool
    {
        try {
            error_log("SMTP Debug: Attempting to connect to {$this->config['host']}:{$this->config['port']}");
            
            // Create socket connection with SSL context if needed
            $context = null;
            if (isset($this->config['verify_ssl_cert']) && !$this->config['verify_ssl_cert']) {
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ]);
                error_log("SMTP Debug: SSL verification disabled for connection");
            }
            
            // Create socket connection
            if ($context) {
                $socket = stream_socket_client(
                    "tcp://{$this->config['host']}:{$this->config['port']}",
                    $errno,
                    $errstr,
                    $this->config['timeout'] ?? 30,
                    STREAM_CLIENT_CONNECT,
                    $context
                );
            } else {
                $socket = fsockopen($this->config['host'], $this->config['port'], $errno, $errstr, $this->config['timeout'] ?? 30);
            }
            
            if (!$socket) {
                error_log("SMTP Debug: Connection failed - $errstr ($errno)");
                throw new \Exception("Failed to connect to SMTP server: $errstr ($errno)");
            }
            
            error_log("SMTP Debug: Socket connection established");
            
            // Read initial response
            $response = fgets($socket, 512);
            error_log("SMTP Debug: Initial server response: " . trim($response));
            
            if (substr($response, 0, 3) !== '220') {
                error_log("SMTP Debug: Invalid initial response - expected 220, got: " . substr($response, 0, 3));
                throw new \Exception("SMTP server error: $response");
            }
            
            // Send EHLO command
            $heloHostname = $this->config['helo_hostname'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $ehloCommand = "EHLO " . $heloHostname . "\r\n";
            error_log("SMTP Debug: Sending EHLO command: " . trim($ehloCommand));
            fputs($socket, $ehloCommand);
            
            // Read all EHLO response lines (server capabilities)
            $ehloResponses = [];
            do {
                $response = fgets($socket, 512);
                $ehloResponses[] = trim($response);
                error_log("SMTP Debug: EHLO response: " . trim($response));
            } while (substr($response, 3, 1) === '-'); // Continue reading while response code has continuation mark
            
            // Start TLS if required
            if ($this->config['encryption'] === 'tls') {
                // Check if server supports STARTTLS
                $supportsStartTls = false;
                foreach ($ehloResponses as $ehloResponse) {
                    if (stripos($ehloResponse, 'STARTTLS') !== false) {
                        $supportsStartTls = true;
                        break;
                    }
                }
                
                if (!$supportsStartTls) {
                    error_log("SMTP Debug: Server does not support STARTTLS, skipping TLS");
                } else {
                    error_log("SMTP Debug: Server supports STARTTLS, attempting TLS encryption");
                    fputs($socket, "STARTTLS\r\n");
                    $response = fgets($socket, 512);
                    error_log("SMTP Debug: STARTTLS response: " . trim($response));
                    
                    if (substr($response, 0, 3) !== '220') {
                        error_log("SMTP Debug: STARTTLS failed - expected 220, got: " . substr($response, 0, 3));
                        error_log("SMTP Debug: Continuing without TLS encryption");
                    } else {
                        // Enable crypto
                        error_log("SMTP Debug: Enabling TLS crypto");
                        
                        // Apply SSL context to the socket if SSL verification is disabled
                        if (isset($this->config['verify_ssl_cert']) && !$this->config['verify_ssl_cert']) {
                            error_log("SMTP Debug: Applying SSL context with verification disabled");
                            stream_context_set_option($socket, 'ssl', 'verify_peer', false);
                            stream_context_set_option($socket, 'ssl', 'verify_peer_name', false);
                            stream_context_set_option($socket, 'ssl', 'allow_self_signed', true);
                        }
                        
                        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                            error_log("SMTP Debug: TLS crypto enable failed, continuing without TLS");
                        } else {
                            error_log("SMTP Debug: TLS crypto enabled successfully");
                            
                            // Send EHLO again after TLS
                            fputs($socket, $ehloCommand);
                            // Read all post-TLS EHLO response lines
                            do {
                                $response = fgets($socket, 512);
                                error_log("SMTP Debug: Post-TLS EHLO response: " . trim($response));
                            } while (substr($response, 3, 1) === '-'); // Continue reading while response code has continuation mark
                        }
                    }
                }
            }
            
            // Authenticate
            // Authenticate only if credentials are provided
            if ($this->config['auth_enabled']) {
                error_log("SMTP Debug: Starting authentication");
                fputs($socket, "AUTH LOGIN\r\n");
                $response = fgets($socket, 512);
                error_log("SMTP Debug: AUTH LOGIN response: " . trim($response));
                
                if (substr($response, 0, 3) !== '334') {
                    error_log("SMTP Debug: AUTH LOGIN failed - expected 334, got: " . substr($response, 0, 3));
                    throw new \Exception("AUTH LOGIN failed: $response");
                }
                
                // Send username
                error_log("SMTP Debug: Sending username: " . $this->config['username']);
                fputs($socket, base64_encode($this->config['username']) . "\r\n");
                $response = fgets($socket, 512);
                error_log("SMTP Debug: Username response: " . trim($response));
                
                if (substr($response, 0, 3) !== '334') {
                    error_log("SMTP Debug: Username auth failed - expected 334, got: " . substr($response, 0, 3));
                    throw new \Exception("Username authentication failed: $response");
                }
                
                // Send password
                error_log("SMTP Debug: Sending password (hidden)");
                fputs($socket, base64_encode($this->config['password']) . "\r\n");
                $response = fgets($socket, 512);
                error_log("SMTP Debug: Password response: " . trim($response));
                
                if (substr($response, 0, 3) !== '235') {
                    error_log("SMTP Debug: Password auth failed - expected 235, got: " . substr($response, 0, 3));
                    throw new \Exception("Password authentication failed: $response");
                }
                error_log("SMTP Debug: Authentication successful");
            } else {
                error_log("SMTP Debug: Skipping authentication (no credentials provided)");
            }
            
            // Send MAIL FROM
            error_log("SMTP Debug: Sending MAIL FROM: " . $this->config['from_email']);
            fputs($socket, "MAIL FROM: <" . $this->config['from_email'] . ">\r\n");
            $response = fgets($socket, 512);
            error_log("SMTP Debug: MAIL FROM response: " . trim($response));
            
            if (substr($response, 0, 3) !== '250') {
                error_log("SMTP Debug: MAIL FROM failed - expected 250, got: " . substr($response, 0, 3));
                throw new \Exception("MAIL FROM failed: $response");
            }
            
            // Send RCPT TO
            error_log("SMTP Debug: Sending RCPT TO: " . $to);
            fputs($socket, "RCPT TO: <$to>\r\n");
            $response = fgets($socket, 512);
            error_log("SMTP Debug: RCPT TO response: " . trim($response));
            
            if (substr($response, 0, 3) !== '250') {
                error_log("SMTP Debug: RCPT TO failed - expected 250, got: " . substr($response, 0, 3));
                throw new \Exception("RCPT TO failed: $response");
            }
            
            // Send DATA
            error_log("SMTP Debug: Sending DATA command");
            fputs($socket, "DATA\r\n");
            $response = fgets($socket, 512);
            error_log("SMTP Debug: DATA response: " . trim($response));
            
            if (substr($response, 0, 3) !== '354') {
                error_log("SMTP Debug: DATA command failed - expected 354, got: " . substr($response, 0, 3));
                throw new \Exception("DATA command failed: $response");
            }
            
            // Send email headers and body
            error_log("SMTP Debug: Sending email content");
            fputs($socket, "To: $to\r\n");
            fputs($socket, "Subject: $subject\r\n");
            foreach ($headers as $header) {
                fputs($socket, "$header\r\n");
            }
            fputs($socket, "\r\n");
            fputs($socket, $body);
            fputs($socket, "\r\n.\r\n");
            
            $response = fgets($socket, 512);
            error_log("SMTP Debug: Email send response: " . trim($response));
            
            if (substr($response, 0, 3) !== '250') {
                error_log("SMTP Debug: Email sending failed - expected 250, got: " . substr($response, 0, 3));
                throw new \Exception("Email sending failed: $response");
            }
            
            // Send QUIT
            error_log("SMTP Debug: Sending QUIT command");
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            error_log("SMTP Debug: Email sent successfully");
            
            return true;
            
        } catch (\Exception $e) {
            if (isset($socket)) {
                fclose($socket);
            }
            throw $e;
        }
    }
    
    /**
     * Check if user has email notifications enabled
     */
    private function isEmailNotificationsEnabled(string $userId): bool
    {
        try {
            $yamlKey = 'users/' . $userId . '.yaml';
            
            if (!$this->awsService->objectExists($yamlKey)) {
                return false; // Default to no notifications
            }
            
            $yamlObject = $this->awsService->getObject($yamlKey);
            $userSettings = parseSimpleYaml($yamlObject['content']);
            
            return isset($userSettings['email_notifications']) && $userSettings['email_notifications'] === 'yes';
            
        } catch (\Exception $e) {
            error_log("Error checking email notifications for user $userId: " . $e->getMessage());
            return false; // Default to no notifications on error
        }
    }
    
    /**
     * Generate HTML email content for item claimed notification
     */
    private function generateItemClaimedHtml(array $item, array $claimer): string
    {
        $itemUrl = $this->getItemUrl($item['tracking_number']);
        $itemImage = $this->getItemImageUrl($item);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Your item has been claimed!</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; }
                .content { padding: 20px 0; }
                .item-card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; }
                .item-image { max-width: 200px; height: auto; border-radius: 4px; }
                .button { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
                .footer { text-align: center; color: #666; font-size: 14px; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Your item has been claimed!</h1>
                </div>
                
                <div class='content'>
                    <p>Great news! Someone has claimed your item:</p>
                    
                    <div class='item-card'>
                        <h3>{$this->escapeHtml($item['title'])}</h3>
                        <p><strong>Description:</strong> {$this->escapeHtml($item['description'])}</p>
                        <p><strong>Type:</strong> {$this->escapeHtml($item['type'])}</p>
                        <p><strong>Posted:</strong> {$this->formatDate($item['created_at'])}</p>
                        " . ($itemImage ? "<img src='{$itemImage}' alt='Item image' class='item-image'>" : "") . "
                    </div>
                    
                    <p><strong>Claimed by:</strong> {$this->escapeHtml($claimer['name'])}</p>
                    
                    <p>You can view the full details of your item and manage it from your dashboard.</p>
                    
                    <a href='{$itemUrl}' class='button'>View Item Details</a>
                </div>
                
                <div class='footer'>
                    <p>This email was sent because you have email notifications enabled in your ClaimIt settings.</p>
                    <p>You can disable these notifications anytime in your account settings.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generate plain text email content for item claimed notification
     */
    private function generateItemClaimedText(array $item, array $claimer): string
    {
        $itemUrl = $this->getItemUrl($item['tracking_number']);
        
        return "
Your item has been claimed!

Great news! Someone has claimed your item:

Title: {$item['title']}
Description: {$item['description']}
Type: {$item['type']}
Posted: {$this->formatDate($item['created_at'])}

Claimed by: {$claimer['name']}

You can view the full details of your item and manage it from your dashboard:
{$itemUrl}

This email was sent because you have email notifications enabled in your ClaimIt settings.
You can disable these notifications anytime in your account settings.
        ";
    }
    
    /**
     * Get item URL
     */
    private function getItemUrl(string $trackingNumber): string
    {
        $isLocalhost = isset($_SERVER['HTTP_HOST']) && (
            $_SERVER['HTTP_HOST'] === 'localhost:8000' || 
            $_SERVER['HTTP_HOST'] === '127.0.0.1:8000'
        );
        
        $baseUrl = $isLocalhost 
            ? 'http://localhost:8000/'
            : 'https://claimit.stonekeep.com/';
            
        return $baseUrl . '?page=item&id=' . urlencode($trackingNumber);
    }
    
    /**
     * Get item image URL
     */
    private function getItemImageUrl(array $item): ?string
    {
        if (empty($item['image_key'])) {
            return null;
        }
        
        try {
            return $this->awsService->getPresignedUrl($item['image_key'], 86400); // 24 hours
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Escape HTML
     */
    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Format date
     */
    private function formatDate(string $date): string
    {
        return date('F j, Y \a\t g:i A', strtotime($date));
    }
    
    /**
     * Generate HTML email content for test email
     */
    private function generateTestEmailHtml(array $user): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>ClaimIt Test Email</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; }
                .content { padding: 20px 0; }
                .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0; }
                .footer { text-align: center; color: #666; font-size: 14px; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📧 ClaimIt Email Test</h1>
                </div>
                
                <div class='content'>
                    <div class='success'>
                        <strong>✅ Email Configuration Working!</strong>
                    </div>
                    
                    <p>Hello {$this->escapeHtml($user['name'])},</p>
                    
                    <p>This is a test email from ClaimIt to verify that your SMTP email configuration is working correctly.</p>
                    
                    <h3>Test Details:</h3>
                    <ul>
                        <li><strong>Recipient:</strong> {$this->escapeHtml($user['email'])}</li>
                        <li><strong>Sent at:</strong> {$this->formatDate(date('Y-m-d H:i:s'))}</li>
                        <li><strong>SMTP Server:</strong> {$this->config['host']}:{$this->config['port']}</li>
                        <li><strong>Encryption:</strong> " . ($this->config['encryption'] ?? 'None') . "</li>
                    </ul>
                    
                    <p>If you received this email, your SMTP configuration is working properly and ClaimIt can send email notifications when items are claimed.</p>
                    
                    <p>You can now disable email notifications in your settings if you don't want to receive them, or leave them enabled to get notified when someone claims your items.</p>
                </div>
                
                <div class='footer'>
                    <p>This test email was sent from ClaimIt Email Service</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generate plain text email content for test email
     */
    private function generateTestEmailText(array $user): string
    {
        return "
ClaimIt Email Test

✅ Email Configuration Working!

Hello {$user['name']},

This is a test email from ClaimIt to verify that your SMTP email configuration is working correctly.

Test Details:
- Recipient: {$user['email']}
- Sent at: {$this->formatDate(date('Y-m-d H:i:s'))}
- SMTP Server: {$this->config['host']}:{$this->config['port']}
- Encryption: " . ($this->config['encryption'] ?? 'None') . "

If you received this email, your SMTP configuration is working properly and ClaimIt can send email notifications when items are claimed.

You can now disable email notifications in your settings if you don't want to receive them, or leave them enabled to get notified when someone claims your items.

This test email was sent from ClaimIt Email Service
        ";
    }
    
    /**
     * Get all users who have new listing notifications enabled
     */
    private function getUsersWithNewListingNotifications(): array
    {
        try {
            $awsService = getAwsService();
            if (!$awsService) {
                return [];
            }
            
            // Get all user files from S3
            $result = $awsService->listObjects('users/');
            $userFiles = $result['objects'] ?? [];
            $usersToNotify = [];
            
            foreach ($userFiles as $file) {
                if (strpos($file['key'], '.yaml') === false) {
                    continue; // Skip non-YAML files
                }
                
                try {
                    $yamlObject = $awsService->getObject($file['key']);
                    $yamlContent = $yamlObject['content'];
                    $userSettings = parseSimpleYaml($yamlContent);
                    
                    // Check if user has new listing notifications enabled
                    if (isset($userSettings['new_listing_notifications']) && 
                        $userSettings['new_listing_notifications'] === 'yes' &&
                        !empty($userSettings['email'])) {
                        
                        $usersToNotify[] = [
                            'id' => $userSettings['user_id'] ?? '',
                            'name' => $userSettings['display_name'] ?? $userSettings['google_name'] ?? 'Unknown',
                            'email' => $userSettings['email']
                        ];
                    }
                    
                } catch (\Exception $e) {
                    error_log("Error reading user settings from {$file['key']}: " . $e->getMessage());
                    continue;
                }
            }
            
            return $usersToNotify;
            
        } catch (\Exception $e) {
            error_log("Error getting users with new listing notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate new listing notification HTML content
     */
    private function generateNewListingHtml(array $newItem, array $itemOwner, array $recipient): string
    {
        $siteUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'claimit.stonekeep.com');
        $itemUrl = $siteUrl . '/?page=item&id=' . $newItem['tracking_number'];
        $imageUrl = !empty($newItem['image_key']) ? getCloudFrontUrl($newItem['image_key']) : null;
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>New Item on ClaimIt</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; font-weight: 300; }
                .content { padding: 30px; }
                .content h2 { color: #667eea; margin-top: 0; }
                .content p { margin-bottom: 20px; }
                .item-card { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin: 20px 0; background: #f9f9f9; }
                .item-title { font-size: 20px; font-weight: bold; color: #333; margin-bottom: 10px; }
                .item-description { color: #666; margin-bottom: 15px; }
                .item-meta { color: #888; font-size: 14px; margin-bottom: 15px; }
                .item-image { max-width: 100%; height: auto; border-radius: 5px; margin: 10px 0; }
                .cta-button { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 15px 0; }
                .cta-button:hover { background: #5a6fd8; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 14px; }
                .footer a { color: #667eea; text-decoration: none; }
                .footer a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🆕 New Item on ClaimIt!</h1>
                </div>
                <div class='content'>
                    <h2>Someone just posted a new item!</h2>
                    <p>Hello {$recipient['name']},</p>
                    <p>A new item has been posted on ClaimIt that you might be interested in:</p>
                    
                    <div class='item-card'>
                        <div class='item-title'>{$newItem['title']}</div>
                        <div class='item-description'>{$newItem['description']}</div>
                        <div class='item-meta'>
                            <strong>Posted by:</strong> {$itemOwner['name']}<br>
                            <strong>Price:</strong> $" . number_format($newItem['price'], 2) . "<br>
                            <strong>Tracking #:</strong> {$newItem['tracking_number']}
                        </div>";
        
        if ($imageUrl) {
            $html .= "<img src='{$imageUrl}' alt='Item image' class='item-image'>";
        }
        
        $html .= "
                        <a href='{$itemUrl}' class='cta-button'>View Item Details</a>
                    </div>
                    
                    <p>Don't miss out! Check out this item and others on ClaimIt.</p>
                    <p>Best regards,<br>The ClaimIt Team</p>
                </div>
                <div class='footer'>
                    <p>This email was sent from <a href='{$siteUrl}'>ClaimIt</a></p>
                    <p>You're receiving this because you have new listing notifications enabled in your settings.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Generate new listing notification text content
     */
    private function generateNewListingText(array $newItem, array $itemOwner, array $recipient): string
    {
        $siteUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'claimit.stonekeep.com');
        $itemUrl = $siteUrl . '/?page=item&id=' . $newItem['tracking_number'];
        
        return "New Item on ClaimIt!

Hello {$recipient['name']},

A new item has been posted on ClaimIt that you might be interested in:

{$newItem['title']}

{$newItem['description']}

Posted by: {$itemOwner['name']}
Price: $" . number_format($newItem['price'], 2) . "
Tracking #: {$newItem['tracking_number']}

View this item: {$itemUrl}

Don't miss out! Check out this item and others on ClaimIt.

Best regards,
The ClaimIt Team

---
This email was sent from {$siteUrl}
You're receiving this because you have new listing notifications enabled in your settings.";
    }
}
