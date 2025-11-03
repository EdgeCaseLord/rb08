<?php
// SMTP Test Script for Office 365
// Run with: php test-smtp.php

require_once 'vendor/autoload.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

// Configuration - Update these values
$config = [
    'host' => 'smtp.office365.com',
    'port' => 587,
    'username' => 'ifm_smtp@ifmmvz.onmicrosoft.com',
    'password' => 'YOUR_APP_PASSWORD_HERE', // Replace with actual app password
    'encryption' => 'tls'
];

try {
    echo "Testing SMTP connection to Office 365...\n";
    echo "Host: {$config['host']}\n";
    echo "Port: {$config['port']}\n";
    echo "Username: {$config['username']}\n";
    echo "Encryption: {$config['encryption']}\n\n";

    // Create transport
    $dsn = "smtp://{$config['username']}:{$config['password']}@{$config['host']}:{$config['port']}?encryption={$config['encryption']}";
    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);

    // Create test email
    $email = (new Email())
        ->from($config['username'])
        ->to('daniel@pixelhoch.de')
        ->subject('SMTP Test - ' . date('Y-m-d H:i:s'))
        ->text('This is a test email to verify SMTP configuration.');

    // Send email
    $mailer->send($email);

    echo "✅ SUCCESS: Email sent successfully!\n";
    echo "Your SMTP configuration is working correctly.\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting steps:\n";
    echo "1. Verify the app password is correct\n";
    echo "2. Check if SMTP AUTH is enabled in Office 365\n";
    echo "3. Ensure the user has permission to send emails\n";
    echo "4. Try using a different port (465 with SSL)\n";
}

