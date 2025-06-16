<?php

require __DIR__ . '/vendor/autoload.php';

$kernel = new \App\Kernel('prod', true);
$kernel->boot();
$container = $kernel->getContainer();

if ($container->has('mailer')) {
    echo "Mailer service is registered!\n";
    var_dump($container->get('mailer'));
} else {
    echo "Mailer service is NOT registered!\n";
}