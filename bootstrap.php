<?php
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno desde .env
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

// Verificar que DATABASE_URL está definida
if (!isset($_ENV['DATABASE_URL'])) {
    die('Error: DATABASE_URL no está definida en .env');
}

// Configuración de Doctrine
$paths = [__DIR__ . '/src/Entity']; // Directorio donde está ProxyLogs.php
$isDevMode = true;

// Configuración de la base de datos desde DATABASE_URL
$dbParams = [
    'url' => $_ENV['DATABASE_URL'],
];

try {
    // Configuración de Doctrine ORM para anotaciones
    $config = ORMSetup::createConfiguration($isDevMode);
    $driver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver(
        new \Doctrine\Common\Annotations\AnnotationReader(),
        $paths
    );
    $config->setMetadataDriverImpl($driver);
    $entityManager = EntityManager::create($dbParams, $config);
} catch (\Exception $e) {
    die('Error al configurar Doctrine: ' . $e->getMessage());
}