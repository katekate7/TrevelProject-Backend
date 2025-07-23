<?php
// Simple email test script
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env.local');

echo "=== Email Configuration Test ===\n";
echo "MAILER_DSN: " . ($_ENV['MAILER_DSN'] ?? 'Not set') . "\n";
echo "MAILER_FROM_EMAIL: " . ($_ENV['MAILER_FROM_EMAIL'] ?? 'Not set') . "\n";
echo "MAILER_FROM_NAME: " . ($_ENV['MAILER_FROM_NAME'] ?? 'Not set') . "\n\n";

// Test if we can create the transport
try {
    $transport = Transport::fromDsn($_ENV['MAILER_DSN']);
    $mailer = new Mailer($transport);
    echo "✅ Transport created successfully\n";
} catch (Exception $e) {
    echo "❌ Failed to create transport: " . $e->getMessage() . "\n";
    exit(1);
}

// Create and send a test email
try {
    $testEmail = (new Email())
        ->from(new Address($_ENV['MAILER_FROM_EMAIL'], $_ENV['MAILER_FROM_NAME']))
        ->to('katekr500@gmail.com') // Use your test email
        ->subject('Test Email from Travel App')
        ->text('This is a test email to verify email delivery is working.')
        ->html('<h1>Test Email</h1><p>This is a test email to verify email delivery is working.</p>');

    $mailer->send($testEmail);
    echo "✅ Test email sent successfully!\n";
    echo "📧 Check your inbox (and spam folder) for the test email\n";
} catch (Exception $e) {
    echo "❌ Failed to send test email: " . $e->getMessage() . "\n";
}

echo "\n=== SendGrid Troubleshooting Tips ===\n";
echo "1. Verify sender authentication in SendGrid dashboard\n";
echo "2. Check if your domain is authenticated\n";
echo "3. Verify the API key has proper permissions\n";
echo "4. Check spam folder in recipient email\n";
echo "5. Review SendGrid activity logs\n";
?>
