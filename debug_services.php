<?php
// debug_services.php
require __DIR__ . '/vendor/autoload.php';

$kernel = new \App\Kernel('prod', true); // Cambia 'dev' a 'prod' si estás en producción
$kernel->boot();
$container = $kernel->getContainer();

if ($container->has('mailer')) {
    echo "Mailer service is registered!\n";
    var_dump($container->get('mailer'));
} else {
    echo "Mailer service is NOT registered!\n";
}