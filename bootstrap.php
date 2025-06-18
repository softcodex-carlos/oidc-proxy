<?php
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno desde .env
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

// Configuración de Doctrine
$paths = [__DIR__ . '/src/Entity']; // Directorio donde está ProxyLogs.php
$isDevMode = true;

// Configuración de la base de datos desde DATABASE_URL
$dbParams = [
    'url' => $_ENV['DATABASE_URL'],
];

// Configuración de Doctrine ORM
$config = ORMSetup::createAnnotationMetadataConfiguration($paths, $isDevMode, null, null, false);
$entityManager = EntityManager::create($dbParams, $config);