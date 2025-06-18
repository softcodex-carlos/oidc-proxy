<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $connection = $entityManager->getConnection();
    $connection->connect();
    echo "Conexión exitosa a la base de datos.\n";
} catch (\Exception $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
}