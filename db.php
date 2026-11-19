<?php
$host = 'localhost';
$db   = 'jesusna1_cotiza';
$user = 'jesusna1_ojcotiza';
$pass = 'Moroni710906';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Error de conexi¨®n a la base de datos: ' . $e->getMessage());
}