<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/app/usuarios.php';
oj_admin($pdo);
header('Location: usuarios.php'); exit;
