<?php
// Copia este archivo como db.php EN EL SERVIDOR y completa tus credenciales de cPanel.
// db.php esta en .gitignore: nunca subas credenciales reales al repositorio.
$host = 'localhost';
$db   = 'TU_BASE_DE_DATOS';
$user = 'TU_USUARIO_BD';
$pass = 'TU_CONTRASENA_BD';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Error de conexion a la base de datos: ' . $e->getMessage());
}
