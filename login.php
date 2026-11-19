<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = :usuario AND activo = 1 LIMIT 1");
    $stmt->execute([':usuario' => $usuario]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_rol'] = $user['rol'];
        $_SESSION['usuario_password_fingerprint'] = hash('sha256', $user['password_hash']);
        $_SESSION['ultima_actividad'] = time();

        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Ojo Jarocho</title>
<style>
body{
    font-family: Arial;
    box-sizing:border-box;
    margin:0;
    padding:15px;
    background:#0d3b66;
    display:flex;
    align-items:center;
    justify-content:center;
    height:100vh;
}
.box{
    background:#fff;
    padding:30px;
    border-radius:12px;
    width:100%;
    max-width:360px;
    box-sizing:border-box;
}
h2{
    margin-bottom:20px;
}
input{
    width:100%;
    box-sizing:border-box;
    padding:10px;
    margin-bottom:12px;
    border-radius:6px;
    border:1px solid #ccc;
}
button{
    width:100%;
    box-sizing:border-box;
    padding:10px;
    background:#0d3b66;
    color:#fff;
    border:none;
    border-radius:6px;
    font-weight:bold;
}
.error{
    color:red;
    margin-bottom:10px;
}
</style>
</head>
<body>
<div class="box">
    <h2>Ojo Jarocho Login</h2>

    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="text" name="usuario" placeholder="Usuario" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">Entrar</button>
    </form>
    <p><a href="recuperar_password.php">Olvidé mi contraseña</a></p>
</div>
</body>
</html>
