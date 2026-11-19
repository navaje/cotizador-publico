<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/empresa.php';
oj_emp_upgrade($pdo);

$mensaje = '';
$error = '';

$empresa = oj_emp_leer($pdo);

if (!$empresa) {
    $pdo->exec("
        INSERT INTO empresa (nombre, telefono, email, web, direccion, logo, banco, cuenta, clabe)
        VALUES ('Ojo Jarocho', '', '', '', '', 'assets/logo.png', '', '', '')
    ");
    $empresa = oj_emp_leer($pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $web = trim($_POST['web'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $banco = trim($_POST['banco'] ?? '');
    $cuenta = trim($_POST['cuenta'] ?? '');
    $clabe = trim($_POST['clabe'] ?? '');
    if (strlen($banco) > 80 || strlen($cuenta) > 40 || strlen($clabe) > 18) $error = 'Revisa la longitud de los datos bancarios.';
    if ($error === '' && $cuenta !== '' && !preg_match('/^[0-9]{6,40}$/D', $cuenta)) $error = 'La cuenta solo admite dígitos (6 a 40).';
    if ($error === '' && $clabe !== '' && !preg_match('/^[0-9]{18}$/D', $clabe)) $error = 'La CLABE debe tener 18 dígitos.';
    $logoActual = $empresa['logo'] ?? 'assets/logo.png';
    $logoRuta = $logoActual;

    if (!empty($_FILES['logo']['name'])) {
        $permitidas = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

        if (($_FILES['logo']['size'] ?? 0) > 3 * 1024 * 1024) {
            $error = 'El logo no debe superar 3 MB.';
        }

        $mimeReal = '';
        if ($error === '' && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $infoImagen = @getimagesize($_FILES['logo']['tmp_name']);
            $mimeReal = $infoImagen['mime'] ?? '';
        }

        if ($error === '' && !isset($permitidas[$mimeReal])) {
            $error = 'Formato de logo no permitido. Usa PNG, JPG o WEBP.';
        } else {
            $extension = $permitidas[$mimeReal] ?? '';
            $nombreArchivo = 'logo_empresa_' . time() . '.' . $extension;
            $destinoDir = __DIR__ . '/assets';

            if (!is_dir($destinoDir)) {
                mkdir($destinoDir, 0775, true);
            }

            $destino = $destinoDir . '/' . $nombreArchivo;

            if ($error === '' && move_uploaded_file($_FILES['logo']['tmp_name'], $destino)) {
                $logoRuta = 'assets/' . $nombreArchivo;
            } else {
                $error = 'No se pudo subir el logo.';
            }
        }
    }

    if ($error === '') {
        $stmtUpdate = $pdo->prepare("
            UPDATE empresa
            SET nombre = :nombre,
                telefono = :telefono,
                email = :email,
                web = :web,
                direccion = :direccion,
                logo = :logo,
                banco = :banco,
                cuenta = :cuenta,
                clabe = :clabe
            WHERE id = :id
        ");

        $stmtUpdate->execute([
            ':nombre' => $nombre,
            ':telefono' => $telefono,
            ':email' => $email,
            ':web' => $web,
            ':direccion' => $direccion,
            ':logo' => $logoRuta,
            ':banco' => $banco,
            ':cuenta' => $cuenta,
            ':clabe' => $clabe,
            ':id' => $empresa['id']
        ]);

        $mensaje = 'Datos de la empresa actualizados correctamente.';

        $empresa = oj_emp_leer($pdo);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de empresa - Ojo Jarocho</title>
    <style>
        body{
            font-family: Arial, Helvetica, sans-serif;
            background:#eef3f8;
            margin:0;
            padding:24px;
            color:#1f2937;
        }
        .wrap{
            max-width:900px;
            margin:0 auto;
            background:#fff;
            border-radius:14px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
            overflow:hidden;
        }
        .topbar{
            background:#0d3b66;
            color:#fff;
            padding:22px 28px;
        }
        .topbar h1{
            margin:0;
            font-size:28px;
        }
        .content{
            padding:24px;
        }
        .grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:16px;
        }
        .full{
            grid-column:1 / -1;
        }
        label{
            display:block;
            font-weight:bold;
            margin-bottom:6px;
        }
        input, textarea{
            width:100%;
            padding:12px;
            border:1px solid #cfd8e3;
            border-radius:8px;
            font-size:14px;
            box-sizing:border-box;
        }
        textarea{
            min-height:100px;
            resize:vertical;
        }
        .actions{
            margin-top:20px;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .btn{
            display:inline-block;
            background:#0d3b66;
            color:#fff;
            text-decoration:none;
            padding:12px 16px;
            border:none;
            border-radius:8px;
            font-weight:bold;
            cursor:pointer;
        }
        .btn-secondary{
            background:#dbe7f3;
            color:#0d3b66;
        }
        .msg{
            padding:12px 14px;
            border-radius:8px;
            margin-bottom:18px;
        }
        .ok{
            background:#e8f7ec;
            color:#166534;
            border:1px solid #b7e4c7;
        }
        .bad{
            background:#fdecec;
            color:#991b1b;
            border:1px solid #f5c2c7;
        }
        .logo-box{
            margin-top:8px;
        }
        .logo-box img{
            max-height:90px;
            max-width:220px;
            display:block;
            padding:8px;
            background:#fff;
            border:1px solid #dbe3ec;
            border-radius:8px;
        }
        @media (max-width: 700px){
            .grid{
                grid-template-columns:1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <h1>Configuración de empresa</h1>
        </div>

        <div class="content">
            <?php if ($mensaje): ?>
                <div class="msg ok"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="msg bad"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="grid">
                    <div>
                        <label>Nombre</label>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($empresa['nombre'] ?? ''); ?>" required>
                    </div>

                    <div>
                        <label>Teléfono</label>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($empresa['telefono'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($empresa['email'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>Sitio web</label>
                        <input type="text" name="web" value="<?php echo htmlspecialchars($empresa['web'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>Banco (datos para pagos)</label>
                        <input type="text" name="banco" maxlength="80" value="<?php echo htmlspecialchars($empresa['banco'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>Cuenta bancaria</label>
                        <input type="text" name="cuenta" maxlength="40" inputmode="numeric" value="<?php echo htmlspecialchars($empresa['cuenta'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>CLABE interbancaria (18 dígitos)</label>
                        <input type="text" name="clabe" maxlength="18" inputmode="numeric" value="<?php echo htmlspecialchars($empresa['clabe'] ?? ''); ?>">
                    </div>

                    <div class="full">
                        <label>Dirección</label>
                        <textarea name="direccion"><?php echo htmlspecialchars($empresa['direccion'] ?? ''); ?></textarea>
                    </div>

                    <div class="full">
                        <label>Logo</label>
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp">
                        <div class="logo-box">
                            <?php if (!empty($empresa['logo']) && file_exists(__DIR__ . '/' . $empresa['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($empresa['logo']); ?>" alt="Logo actual">
                            <?php else: ?>
                                <span>No hay logo cargado.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Guardar cambios</button>
                    <a class="btn btn-secondary" href="listado.php">Ir a listado</a>
                    <a class="btn btn-secondary" href="index.php">Nueva cotización</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
