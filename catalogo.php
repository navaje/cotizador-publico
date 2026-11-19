<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app/catalogo.php';
header('Cache-Control: no-store');
$usuario = (int)$_SESSION['usuario_id'];
$mensaje = '';
$error = '';
$estadoHttp = 200;
$editar = ['id' => '', 'version' => '', 'codigo' => '', 'nombre' => '', 'categoria' => 'servicio', 'unidad' => 'servicio', 'precio' => ''];
try {
    oj_catalogo_schema($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_post_csrf();
        if (($_POST['accion'] ?? '') === 'estado') {
            $id = oj_catalogo_id($_POST['id'] ?? null);
            $version = oj_catalogo_id($_POST['version'] ?? null);
            $activo = $_POST['activo'] ?? null;
            if (!in_array($activo, ['0','1'], true)) throw new InvalidArgumentException('Estado no válido.');
            $stmt = $pdo->prepare('UPDATE cotizador_catalogo SET activo = ?, version = version + 1, actualizado_en = NOW() WHERE id = ? AND usuario_id = ? AND version = ?');
            $stmt->execute([(int)$activo, $id, $usuario, $version]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('El concepto cambió en otra pestaña. Recarga el catálogo para revisar su estado actual.');
        } elseif (($_POST['accion'] ?? '') === 'guardar') {
            foreach ($editar as $k => $v) $editar[$k] = is_scalar($_POST[$k] ?? '') ? (string)($_POST[$k] ?? '') : '';
            $datos = oj_catalogo_validar($_POST);
            $idEdicion = ($_POST['id'] ?? '') !== '' ? oj_catalogo_id($_POST['id']) : null;
            $versionEdicion = $idEdicion !== null ? oj_catalogo_id($_POST['version'] ?? null) : null;
            $resultadoConcepto = oj_catalogo_guardar_unico($pdo,$usuario,$datos,$idEdicion,$versionEdicion);
            if (in_array($resultadoConcepto['estado'],['existente','inactivo'],true)) {
                throw new RuntimeException('Ya existe un concepto coincidente por código/modelo o por nombre, tipo y unidad si falta código. No se guardó otra ficha ni se sobrescribió su precio. Revisa el concepto existente en la lista.');
            }
        } else throw new InvalidArgumentException('Acción no válida.');
        header('Location: catalogo.php?guardado=1', true, 303); exit;
    }
    if (isset($_GET['editar'])) {
        $stmt = $pdo->prepare('SELECT id, version, codigo, nombre, categoria, unidad, precio FROM cotizador_catalogo WHERE id = ? AND usuario_id = ?');
        $stmt->execute([oj_catalogo_id($_GET['editar']), $usuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) throw new RuntimeException('No se encontró el concepto para tu usuario.');
        $editar = $fila;
    }
} catch (Throwable $e) {
    $estadoHttp = 400;
    if ($e instanceof PDOException) { $estadoHttp = 500; error_log('Catálogo: ' . $e->getMessage()); $error = 'No se pudo guardar o cargar el catálogo. Revisa la instalación de su tabla.'; }
    else $error = $e->getMessage();
}
$q = ''; $items = []; $hayMas = false;
try {
    $q = oj_catalogo_texto($_GET, 'q', 100);
    $sql = 'SELECT * FROM cotizador_catalogo WHERE usuario_id = ?'; $args = [$usuario];
    if ($q !== '') {
        $patron = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $q) . '%';
        $sql .= " AND (nombre LIKE ? ESCAPE '!' OR codigo LIKE ? ESCAPE '!')";
        $args[] = $patron; $args[] = $patron;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY activo DESC, nombre ASC, id ASC LIMIT 101');
    $stmt->execute($args); $items = $stmt->fetchAll(PDO::FETCH_ASSOC); $hayMas = count($items) > 100; $items = array_slice($items, 0, 100);
} catch (Throwable $e) { if (!$error) $error = 'No se pudo consultar el catálogo. Revisa la instalación o acorta la búsqueda.'; $estadoHttp = 500; }
http_response_code($estadoHttp);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mis conceptos · Ojo Jarocho</title><link rel="stylesheet" href="assets/catalogo.css?v=20261016"></head><body><main class="catalogo-wrap"><h1>Mis conceptos frecuentes</h1><p>Guarda productos y servicios para agregarlos a tus cotizaciones. Este catálogo es privado de tu usuario. Todos los precios son de venta <strong>sin IVA</strong>.</p>
<nav class="catalogo-actions"><a href="index.php">Cotizador</a><a href="dashboard.php">Dashboard</a><a href="borradores.php">Mis borradores</a></nav>
<?php if ($error): ?><p class="catalogo-error" role="alert"><?= oj_catalogo_e($error) ?></p><?php elseif (isset($_GET['guardado'])): ?><p class="catalogo-ok" role="status">Cambio guardado correctamente.</p><?php endif; ?>
<section class="catalogo-panel"><h2><?= $editar['id'] ? 'Editar concepto' : 'Nuevo concepto' ?></h2><form method="post" action="catalogo.php">
<?= csrf_field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?= oj_catalogo_e($editar['id']) ?>"><input type="hidden" name="version" value="<?= oj_catalogo_e($editar['version']) ?>">
<div class="catalogo-grid"><label>Código / modelo (opcional)<input name="codigo" maxlength="80" value="<?= oj_catalogo_e($editar['codigo']) ?>"></label><label>Nombre del concepto<input name="nombre" maxlength="300" required value="<?= oj_catalogo_e($editar['nombre']) ?>"></label><label>Tipo<select name="categoria"><?php foreach (['producto' => 'Producto', 'servicio' => 'Servicio'] as $v => $t): ?><option value="<?= $v ?>" <?= $editar['categoria'] === $v ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label><label>Unidad<select name="unidad"><?php foreach (['pieza','metro','servicio','visita','hora','jornada','lote'] as $v): ?><option value="<?= $v ?>" <?= $editar['unidad'] === $v ? 'selected' : '' ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></label><label>Precio de venta unitario sin IVA (MXN)<input name="precio" type="number" min="0" max="99999999.99" step="0.01" required value="<?= oj_catalogo_e($editar['precio']) ?>"></label></div><div class="catalogo-actions"><button type="submit">Guardar concepto</button><?php if ($editar['id']): ?><a href="catalogo.php">Cancelar edición</a><?php endif; ?></div></form></section>
<section class="catalogo-panel"><h2>Conceptos guardados</h2><p>Se comprueban coincidencias por código/modelo o por nombre, tipo y unidad si falta código. Para cambiar un precio usa Editar.</p><form method="get" action="catalogo.php" class="catalogo-actions"><label>Buscar nombre o modelo<input name="q" maxlength="100" value="<?= oj_catalogo_e($q) ?>"></label><button type="submit">Buscar</button><a href="catalogo.php">Ver todos</a></form><p>Editar un precio afecta las próximas selecciones. Los documentos y borradores que ya lo contienen conservan su precio capturado.</p>
<?php if (!$items): ?><p>Aún no hay conceptos que coincidan. Puedes registrar una visita técnica, instalación, mantenimiento, cámara o cable por metro.</p><?php endif; ?>
<?php foreach ($items as $item): ?><article class="catalogo-fila"><div><strong><?= oj_catalogo_e($item['nombre']) ?></strong><p><?= oj_catalogo_e($item['codigo']) ?> · <?= oj_catalogo_e(ucfirst($item['categoria'])) ?> · <?= oj_catalogo_e($item['unidad']) ?></p><p>$<?= number_format((float)$item['precio'], 2, '.', ',') ?> sin IVA / unidad · <?= (int)$item['activo'] ? 'Activo' : 'Inactivo' ?></p></div><div class="catalogo-actions"><a href="catalogo.php?editar=<?= (int)$item['id'] ?>">Editar</a><form method="post" action="catalogo.php"><?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="version" value="<?= (int)$item['version'] ?>"><input type="hidden" name="activo" value="<?= (int)$item['activo'] ? '0' : '1' ?>"><button type="submit"><?= (int)$item['activo'] ? 'Desactivar' : 'Activar' ?></button></form></div></article><?php endforeach; ?>
<?php if ($hayMas): ?><p>Se muestran los primeros 100 resultados. Usa la búsqueda para encontrar otros conceptos.</p><?php endif; ?></section></main></body></html>
