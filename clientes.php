<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app/clientes.php';
header('Cache-Control: no-store');
$usuario = (int)$_SESSION['usuario_id'];
$error = ''; $estadoHttp = 200;
$editar = ['id' => '', 'version' => '', 'nombre' => '', 'telefono' => '', 'email' => '', 'direccion' => ''];
try {
    oj_clientes_schema($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_post_csrf();
        if (($_POST['accion'] ?? '') === 'estado') {
            $id = oj_clientes_id($_POST['id'] ?? null);
            $version = oj_clientes_id($_POST['version'] ?? null);
            $activo = $_POST['activo'] ?? null;
            if (!in_array($activo, ['0','1'], true)) throw new InvalidArgumentException('Estado no válido.');
            $stmt = $pdo->prepare('UPDATE cotizador_clientes SET activo = ?, version = version + 1, actualizado_en = NOW() WHERE id = ? AND usuario_id = ? AND version = ?');
            $stmt->execute([(int)$activo, $id, $usuario, $version]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('El cliente cambió en otra pestaña o no está disponible para tu usuario. Recarga para consultar su estado.');
        } elseif (($_POST['accion'] ?? '') === 'guardar') {
            foreach ($editar as $k => $v) $editar[$k] = is_scalar($_POST[$k] ?? '') ? (string)($_POST[$k] ?? '') : '';
            $datos = oj_clientes_validar($_POST);
            $idEdicion = ($_POST['id'] ?? '') !== '' ? oj_clientes_id($_POST['id']) : null;
            $versionEdicion = $idEdicion !== null ? oj_clientes_id($_POST['version'] ?? null) : null;
            $resultadoCliente = oj_clientes_guardar_unico($pdo, $usuario, $datos, $idEdicion, $versionEdicion);
            if (in_array($resultadoCliente['estado'], ['existente','inactivo'], true)) {
                throw new RuntimeException('No se guardó otra ficha: ya existe un cliente con ese nombre y el mismo teléfono o correo, o con los mismos datos sin contacto. Revisa la ficha existente en la lista; no fue sobrescrita.');
            }
        } else throw new InvalidArgumentException('Acción no válida.');
        header('Location: clientes.php?guardado=1', true, 303); exit;
    }
    if (isset($_GET['editar'])) {
        $stmt = $pdo->prepare('SELECT id, version, nombre, telefono, email, direccion FROM cotizador_clientes WHERE id = ? AND usuario_id = ?');
        $stmt->execute([oj_clientes_id($_GET['editar']), $usuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) throw new RuntimeException('No se encontró el cliente para tu usuario.');
        $editar = $fila;
    }
} catch (Throwable $e) {
    $estadoHttp = 400;
    if ($e instanceof PDOException) { $estadoHttp = 500; error_log('Clientes: ' . $e->getMessage()); $error = 'No se pudo guardar o cargar el directorio. Revisa la instalación de la tabla de clientes.'; }
    else $error = $e->getMessage();
}
$q = ''; $items = []; $hayMas = false;
try {
    $q = oj_clientes_texto($_GET, 'q', 100);
    $sql = 'SELECT * FROM cotizador_clientes WHERE usuario_id = ?'; $args = [$usuario];
    if ($q !== '') {
        $patron = oj_clientes_patron($q);
        $sql .= " AND (nombre LIKE ? ESCAPE '!' OR telefono LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";
        $args[] = $patron; $args[] = $patron; $args[] = $patron;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY activo DESC, nombre ASC, id ASC LIMIT 101');
    $stmt->execute($args); $items = $stmt->fetchAll(PDO::FETCH_ASSOC); $hayMas = count($items) > 100; $items = array_slice($items, 0, 100);
} catch (Throwable $e) { if (!$error) $error = 'No se pudo consultar el directorio. Revisa la instalación o acorta la búsqueda.'; $estadoHttp = 500; }
http_response_code($estadoHttp);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mis clientes · Ojo Jarocho</title><link rel="stylesheet" href="assets/catalogo.css?v=20261016-5"></head><body><main class="catalogo-wrap"><h1>Mis clientes</h1><p>Guarda nombre, teléfono, correo y dirección para reutilizarlos. Este directorio es privado de tu usuario.</p><nav class="catalogo-actions"><a href="index.php">Cotizador</a><a href="dashboard.php">Dashboard</a><a href="borradores.php">Mis borradores</a><a href="catalogo.php">Mis conceptos</a></nav>
<?php if ($error): ?><p class="catalogo-error" role="alert"><?= oj_clientes_e($error) ?></p><?php elseif (isset($_GET['guardado'])): ?><p class="catalogo-ok" role="status">Cambio guardado correctamente.</p><?php endif; ?>
<section class="catalogo-panel"><h2><?= $editar['id'] ? 'Editar cliente' : 'Nuevo cliente' ?></h2><form method="post" action="clientes.php"><?= csrf_field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?= oj_clientes_e($editar['id']) ?>"><input type="hidden" name="version" value="<?= oj_clientes_e($editar['version']) ?>"><div class="catalogo-grid"><label>Nombre<input name="nombre" maxlength="180" required value="<?= oj_clientes_e($editar['nombre']) ?>"></label><label>Teléfono<input type="tel" name="telefono" maxlength="60" value="<?= oj_clientes_e($editar['telefono']) ?>"></label><label>Correo electrónico<input type="email" name="email" maxlength="254" value="<?= oj_clientes_e($editar['email']) ?>"></label><label>Dirección<textarea name="direccion" maxlength="3000" rows="4"><?= oj_clientes_e($editar['direccion']) ?></textarea></label></div><div class="catalogo-actions"><button type="submit">Guardar cliente</button><?php if ($editar['id']): ?><a href="clientes.php">Cancelar edición</a><?php endif; ?></div></form></section>
<section class="catalogo-panel"><h2>Clientes guardados</h2><p>Se comprueba nombre y teléfono o correo para evitar nuevas fichas repetidas, aunque cambien otros datos. Las fichas duplicadas anteriores permanecen: conserva la correcta y desactiva las sobrantes.</p><form method="get" action="clientes.php" class="catalogo-actions"><label>Buscar nombre, teléfono o correo<input name="q" maxlength="100" value="<?= oj_clientes_e($q) ?>"></label><button type="submit">Buscar</button><a href="clientes.php">Ver todos</a></form><p>Los cambios del directorio se aplican a nuevas selecciones. Los documentos y borradores conservan sus datos capturados.</p>
<?php if (!$items): ?><p>Aún no hay clientes que coincidan con tu búsqueda. Registra uno en el formulario de arriba.</p><?php endif; ?>
<?php foreach ($items as $item): ?><article class="catalogo-fila"><div><strong><?= oj_clientes_e($item['nombre']) ?></strong><p><?= oj_clientes_e($item['telefono']) ?> · <?= oj_clientes_e($item['email']) ?></p><p style="white-space:pre-line"><?= oj_clientes_e($item['direccion']) ?></p><p><?= (int)$item['activo'] ? 'Activo' : 'Inactivo' ?></p></div><div class="catalogo-actions"><a href="clientes.php?editar=<?= (int)$item['id'] ?>">Editar</a><?php if ((int)$item['activo']): ?><a href="index.php?cliente_id=<?= (int)$item['id'] ?>">Nueva cotización</a><?php endif; ?><form method="post" action="clientes.php"><?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="version" value="<?= (int)$item['version'] ?>"><input type="hidden" name="activo" value="<?= (int)$item['activo'] ? '0' : '1' ?>"><button type="submit"><?= (int)$item['activo'] ? 'Desactivar' : 'Activar' ?></button></form></div></article><?php endforeach; ?><?php if ($hayMas): ?><p>Se muestran las primeras 100 coincidencias. Acota la búsqueda para encontrar otras.</p><?php endif; ?></section></main></body></html>
