<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app/seguimiento.php';
header('Cache-Control: no-store');
$errores = []; $editar = null; $filas = []; $total = 0; $paginas = 1; $pagina = 1; $id = 0;
$f = ['q'=>'','estado'=>'','agenda'=>'','pagina'=>1];
$esPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($esPost) require_post_csrf();
try {
    ojs_schema($pdo);
    if ($esPost) {
        $id = ojs_entero($_POST['id'] ?? null);
        $version = ojs_entero($_POST['version'] ?? null, true);
        $datos = ojs_datos($_POST);
        ojs_guardar($pdo,$id,$version,(int)$_SESSION['usuario_id'],$datos);
        header('Location: seguimiento.php?id=' . $id . '&guardado=1',true,303);
        exit;
    }
    $id = isset($_GET['id']) ? ojs_entero($_GET['id']) : 0;
} catch (Throwable $e) {
    http_response_code($e instanceof PDOException ? 500 : ($e->getCode() === 409 ? 409 : 422));
    if ($e instanceof PDOException) { error_log('Seguimiento: '.$e->getMessage()); $errores[] = 'No se pudo guardar o consultar el seguimiento. Revisa la instalación de la tabla e inténtalo de nuevo.'; }
    else $errores[] = $e->getMessage();
}
try {
    if ($id) {
        $stmt = $pdo->prepare("SELECT c.id,c.folio,c.cliente_nombre,c.proyecto,c.total,s.estado,s.proximo_contacto,s.nota,s.version,s.actualizado_en,u.nombre AS editor FROM cotizaciones c LEFT JOIN cotizador_seguimiento s ON s.documento_id=c.id LEFT JOIN usuarios u ON u.id=s.actualizado_por WHERE c.id=? AND c.tipo_documento='cotizacion'");
        $stmt->execute([$id]); $editar = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editar) { http_response_code(404); $errores[] = 'No se encontró una cotización disponible para dar seguimiento.'; }
    }
    $f = ojs_filtros($_GET);
    $from = ' FROM cotizaciones c LEFT JOIN cotizador_seguimiento s ON s.documento_id=c.id WHERE ' . $f['where'];
    $stmt = $pdo->prepare('SELECT COUNT(*)' . $from); $stmt->execute($f['params']); $total = (int)$stmt->fetchColumn();
    $paginas = max(1,(int)ceil($total/25)); $pagina = min($f['pagina'],$paginas);
    $stmt = $pdo->prepare('SELECT c.id,c.folio,c.cliente_nombre,c.proyecto,c.total,s.estado,s.proximo_contacto,s.nota' . $from . " ORDER BY CASE WHEN s.estado IN ('enviada','en_seguimiento') AND s.proximo_contacto IS NOT NULL THEN 0 ELSE 1 END, s.proximo_contacto ASC,c.id DESC LIMIT 25 OFFSET " . (($pagina-1)*25));
    $stmt->execute($f['params']); $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code($e instanceof PDOException ? 500 : 422);
    if ($e instanceof PDOException) { error_log('Seguimiento consulta: '.$e->getMessage()); $errores[] = 'No se pudo consultar la lista de seguimiento. Revisa el registro de errores del hosting.'; }
    else $errores[] = $e->getMessage();
}
$estados = ojs_estados();
$valor = static function(string $campo, $defecto = '') use ($esPost,$editar) {
    if ($esPost && isset($_POST[$campo]) && is_string($_POST[$campo])) return $_POST[$campo];
    return $editar[$campo] ?? $defecto;
};
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Seguimiento comercial · Ojo Jarocho</title><style>
*{box-sizing:border-box}body{margin:0;padding:20px;background:#eef3f8;color:#1f2937;font-family:Arial,Helvetica,sans-serif}.wrap{max-width:1100px;margin:auto}h1,h2{color:#0d3b66}.panel{background:white;border:1px solid #dbe7f3;border-radius:12px;padding:20px;margin:18px 0;min-width:0}.acciones{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 14px;border:0;border-radius:8px;background:#0d3b66;color:white;text-decoration:none;font:inherit;cursor:pointer}.secundario{background:#dbe7f3;color:#0d3b66}.grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px}label{display:block;font-weight:bold;margin-bottom:8px}input,select,textarea{font:inherit;width:100%;min-height:44px;border:1px solid #cfd8e3;border-radius:8px;padding:10px;margin:6px 0 10px;background:white}textarea{min-height:110px}.ayuda{font-size:13px;line-height:1.5;color:#555752}.error{padding:14px;border:1px solid #b91c1c;background:#fef2f2;border-radius:8px}.exito{padding:14px;background:#dcfce7;border-radius:8px}.documento{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;border:1px solid #cfd8e3;background:white;border-radius:10px;padding:18px;margin:14px 0}.documento h2{font-size:18px;margin:0 0 8px}.documento p{margin:8px 0;overflow-wrap:anywhere}.estado{display:inline-block;border-radius:8px;padding:6px 10px;background:#dbe7f3}.vencido{color:#b91c1c;font-weight:bold}.nota{white-space:pre-wrap}.campo{min-width:0}a{color:#0d3b66}:focus-visible{outline:2px solid #0d3b66;outline-offset:3px}@media(max-width:640px){body{padding:10px}.panel,.documento{padding:14px}.grid,.documento{grid-template-columns:1fr}input,select,textarea{font-size:16px}}
</style></head><body><main class="wrap"><h1>Seguimiento comercial</h1><p>Control de cotizaciones enviadas y próximas llamadas. El estado comercial no registra pagos, convierte documentos ni sustituye la aceptación firmada.</p>
<nav class="acciones"><a class="btn secundario" href="dashboard.php">Dashboard</a><a class="btn secundario" href="listado.php">Documentos</a><a class="btn secundario" href="index.php">Nueva cotización</a></nav>
<?php foreach ($errores as $error): ?><p class="error" role="alert"><?= ojs_e($error) ?></p><?php endforeach; ?>
<?php if (!$errores && $editar && isset($_GET['guardado'])): ?><p class="exito" role="status">Seguimiento guardado correctamente.</p><?php endif; ?>
<?php if ($editar): ?>
<section class="panel"><h2><?= ojs_e($editar['folio']) ?> · <?= ojs_e($editar['cliente_nombre']) ?></h2><p><?= ojs_e($editar['proyecto']) ?> · Total: $<?= number_format((float)$editar['total'],2,'.',',') ?> MXN</p>
<form method="post" action="seguimiento.php">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$editar['id'] ?>"><input type="hidden" name="version" value="<?= ojs_e($valor('version','0')) ?>">
<div class="grid"><div class="campo"><label>Estado comercial<select name="estado" required><option value="">Selecciona</option><?php foreach ($estados as $clave=>$label): ?><option value="<?= ojs_e($clave) ?>" <?= $valor('estado') === $clave ? 'selected' : '' ?>><?= ojs_e($label) ?></option><?php endforeach; ?></select></label></div>
<div class="campo"><label>Próximo contacto<input type="date" name="proximo_contacto" value="<?= ojs_e($valor('proximo_contacto')) ?>"></label></div></div>
<p class="ayuda">La fecha es opcional para Enviada y En seguimiento. Déjala vacía al marcar Aceptada o Rechazada. Puedes registrar una fecha pasada para ver el contacto vencido.</p>
<label>Nota de seguimiento<textarea name="nota" maxlength="2000" placeholder="Ejemplo: llamar el viernes; el cliente está revisando la propuesta."><?= ojs_e($valor('nota')) ?></textarea></label>
<div class="acciones"><button class="btn" type="submit">Guardar seguimiento</button><a class="btn secundario" href="seguimiento.php?id=<?= (int)$editar['id'] ?>">Recargar versión actual</a><a class="btn secundario" href="ver-cotizacion.php?id=<?= (int)$editar['id'] ?>" target="_blank" rel="noopener">Ver PDF</a></div>
<p class="ayuda">Seguimiento compartido por los usuarios con acceso al cotizador. Se conserva una nota editable por cotización; no es un historial de llamadas ni envía recordatorios automáticos.</p>
<?php if ($editar['actualizado_en']): ?><p class="ayuda">Último cambio: <?= ojs_e($editar['actualizado_en']) ?> · <?= ojs_e($editar['editor'] ?: 'Usuario') ?></p><?php endif; ?>
</form></section>
<?php endif; ?>
<section class="panel"><h2>Buscar cotizaciones</h2><form method="get" action="seguimiento.php"><div class="grid">
<div class="campo"><label>Folio, cliente o proyecto<input type="search" name="q" maxlength="100" value="<?= ojs_e($f['q']) ?>"></label></div>
<div class="campo"><label>Estado<select name="estado"><option value="">Todos</option><option value="sin_seguimiento" <?= $f['estado']==='sin_seguimiento' ? 'selected' : '' ?>>Sin seguimiento</option><?php foreach ($estados as $clave=>$label): ?><option value="<?= ojs_e($clave) ?>" <?= $f['estado']===$clave ? 'selected' : '' ?>><?= ojs_e($label) ?></option><?php endforeach; ?></select></label></div>
<div class="campo"><label>Próximo contacto<select name="agenda"><?php foreach ([''=>'Todos','hoy'=>'Hoy','vencidos'=>'Vencidos','programados'=>'Con fecha programada'] as $clave=>$label): ?><option value="<?= ojs_e($clave) ?>" <?= $f['agenda']===$clave ? 'selected' : '' ?>><?= ojs_e($label) ?></option><?php endforeach; ?></select></label></div></div>
<div class="acciones"><button class="btn" type="submit">Buscar / filtrar</button><a class="btn secundario" href="seguimiento.php">Limpiar</a></div></form>
<p class="ayuda">Hoy: <?= ojs_e(ojs_hoy()) ?> · Hora de referencia: Ciudad de México. Los filtros se combinan. Los contactos de cotizaciones aceptadas o rechazadas quedan fuera de la agenda.</p>
</section>
<p role="status"><?= $total ?> cotización(es). Página <?= $pagina ?> de <?= $paginas ?>.</p>
<?php if (!$filas && !$errores): ?><p class="panel">No hay cotizaciones para estos filtros.</p><?php endif; ?>
<?php foreach ($filas as $fila): $activa = in_array($fila['estado'],['enviada','en_seguimiento'],true); ?>
<article class="documento"><div><h2><?= ojs_e($fila['folio']) ?> · <?= ojs_e($fila['cliente_nombre']) ?></h2><p><?= ojs_e($fila['proyecto']) ?></p><p>Total: $<?= number_format((float)$fila['total'],2,'.',',') ?> MXN · <span class="estado"><?= ojs_e($estados[$fila['estado']] ?? 'Sin seguimiento') ?></span></p>
<?php if ($activa && $fila['proximo_contacto']): ?><p class="<?= $fila['proximo_contacto'] < ojs_hoy() ? 'vencido' : '' ?>">Próximo contacto: <?= ojs_e($fila['proximo_contacto']) ?><?= $fila['proximo_contacto'] < ojs_hoy() ? ' · Vencido' : ($fila['proximo_contacto']===ojs_hoy() ? ' · Hoy' : '') ?></p><?php endif; ?>
<?php if ($fila['nota']): ?><p class="nota ayuda"><?= ojs_e($fila['nota']) ?></p><?php endif; ?></div>
<div class="acciones"><a class="btn" href="seguimiento.php?id=<?= (int)$fila['id'] ?>">Actualizar seguimiento</a><a class="btn secundario" href="ver-cotizacion.php?id=<?= (int)$fila['id'] ?>" target="_blank" rel="noopener">Ver PDF</a></div></article>
<?php endforeach; ?>
<nav class="acciones" aria-label="Páginas de seguimiento"><?php if ($pagina>1): ?><a class="btn secundario" href="<?= ojs_e(ojs_url($f,$pagina-1)) ?>">Anterior</a><?php endif; ?><?php if ($pagina<$paginas): ?><a class="btn secundario" href="<?= ojs_e(ojs_url($f,$pagina+1)) ?>">Siguiente</a><?php endif; ?></nav>
</main></body></html>
