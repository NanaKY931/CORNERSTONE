<?php
/**
 * Simple SMTP Email Sender
 * 
 * Lightweight SMTP client for sending emails via Gmail
 */

class SimpleSMTP {
    private $host;
    private $port;
    private $username;
    private $password;
    private $socket;
    
    public function __construct($host, $port, $username, $password) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }
    
    public function send($to, $subject, $htmlBody, $fromEmail, $fromName) {
        try {
            // Connect to SMTP server
            $this->connect();
            
            // Send SMTP commands
            $this->command("EHLO " . $_SERVER['SERVER_NAME']);
            $this->command("STARTTLS");
            
            // Upgrade to TLS
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Re-send EHLO after STARTTLS
            $this->command("EHLO " . $_SERVER['SERVER_NAME']);
            
            // Authenticate
            $this->command("AUTH LOGIN");
            $this->command(base64_encode($this->username));
            $this->command(base64_encode($this->password));
            
            // Send email
            $this->command("MAIL FROM: <{$fromEmail}>");
            $this->command("RCPT TO: <{$to}>");
            $this->command("DATA");
            
            // Email headers and body
            $headers = "From: {$fromName} <{$fromEmail}>\r\n";
            $headers .= "To: <{$to}>\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "\r\n";
            
            $message = $headers . $htmlBody . "\r\n.\r\n";
            $this->send_data($message);
            
            // Quit
            $this->command("QUIT");
            
            // Close connection
            fclose($this->socket);
            
            return true;
        } catch (Exception $e) {
            if ($this->socket) {
                fclose($this->socket);
            }
            throw $e;
        }
    }
    
    private function connect() {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 30);
        if (!$this->socket) {
            throw new Exception("Failed to connect to SMTP server: $errstr ($errno)");
        }
        $this->get_response();
    }
    
    private function command($cmd) {
        fputs($this->socket, $cmd . "\r\n");
        return $this->get_response();
    }
    
    private function send_data($data) {
        fputs($this->socket, $data);
        return $this->get_response();
    }
    
    private function get_response() {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return $response;
    }
}
