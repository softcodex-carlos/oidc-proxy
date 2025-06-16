<?php

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

require __DIR__ . '/vendor/autoload.php';

$dsn = 'smtp://carlos@softcodex.ch:D!685174168020ab@smtp.office365.com:587?encryption=starttls';
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

$email = (new Email())
    ->from('carlos@softcodex.ch')
    ->to('carlos@softcodex.ch')
    ->subject('Test Email')
    ->text('This is a test email.');

try {
    $mailer->send($email);
    echo "Email sent successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}