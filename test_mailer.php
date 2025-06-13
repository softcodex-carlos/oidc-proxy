<?php
// test_mailer.php
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

// Ajusta la ruta al autoloader
require __DIR__ . '/vendor/autoload.php';

$dsn = 'smtp://carlos@softcodex.ch:D!685174168020ab@smtp.office365.com:587?encryption=starttls';
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

$email = (new Email())
    ->from('carlos@softcodex.ch')
    ->to('test@example.com') // Cambia a un correo real para pruebas
    ->subject('Test Email')
    ->text('This is a test email.');

try {
    $mailer->send($email);
    echo "Email sent successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}