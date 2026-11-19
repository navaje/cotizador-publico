<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_once __DIR__ . '/app/listado_filtros.php';
header('Cache-Control: no-store');
$filtros = oj_listado_filtros($_GET);
$cotizaciones = [];
$totalDocumentos = 0;
$porPagina = 25;
$totalPaginas = 1;
$pagina = 1;
$inicio = 0;
$tiposLabel = ['cotizacion' => 'Cotización', 'nota_venta' => 'Nota de venta', 'nota_servicio' => 'Nota de servicio'];
$estadosLabel = ['pendiente' => 'Pendiente', 'anticipo' => 'Anticipo', 'pagado' => 'Pagado'];
if (!$filtros['errores']) {
    try {
        $whereSql = $filtros['where'] ? ' WHERE ' . implode(' AND ', $filtros['where']) : '';
        $stmtConteo = $pdo->prepare('SELECT COUNT(*) FROM cotizaciones' . $whereSql);
        $stmtConteo->execute($filtros['params']);
        $totalDocumentos = (int)$stmtConteo->fetchColumn();
        $totalPaginas = max(1, (int)ceil($totalDocumentos / $porPagina));
        $pagina = min($filtros['pagina'], $totalPaginas);
        $offset = ($pagina - 1) * $porPagina;
        // LIMIT y OFFSET solo usan enteros calculados localmente.
        $stmt = $pdo->prepare('SELECT id, folio, fecha, cliente_nombre, proyecto, total, tipo_documento, estado_pago FROM cotizaciones' . $whereSql . ' ORDER BY id DESC LIMIT ' . $porPagina . ' OFFSET ' . $offset);
        $stmt->execute($filtros['params']);
        $cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $inicio = $totalDocumentos ? $offset + 1 : 0;
    } catch (Throwable $e) {
        error_log('Listado: ' . $e->getMessage());
        $filtros['errores'][] = 'No se pudo consultar el listado. Intenta de nuevo o revisa el registro de errores del hosting.';
        http_response_code(500);
    }
} else http_response_code(422);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Documentos - Ojo Jarocho</title>

<style>
body{
    font-family: Arial, Helvetica, sans-serif;
    background:#eef3f8;
    margin:0;
    padding:24px;
    color:#1f2937;
}
.wrap{
    max-width:1200px;
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
.actions{
    margin-bottom:20px;
}
.btn{
    display:inline-block;
    background:#0d3b66;
    color:#fff;
    text-decoration:none;
    padding:10px 14px;
    border-radius:8px;
    font-weight:bold;
}

/* TABLA */
table{
    width:100%;
    border-collapse:collapse;
}
thead th{
    background:#0d3b66;
    color:#fff;
    padding:12px 10px;
    text-align:left;
    font-size:14px;
}
tbody td{
    border:1px solid #e5e7eb;
    padding:10px;
    font-size:14px;
}
tbody tr:nth-child(even){
    background:#f8fafc;
}

/* BOTONES */
.btn-group{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
}
.inline-form{ display:inline-block; margin:0; }
.inline-form button{ border:0; cursor:pointer; }

.btn-sm{
    padding:7px 10px;
    border-radius:6px;
    font-size:12px;
    font-weight:bold;
    text-decoration:none;
}

/* COLORES */
.btn-pdf{ background:#0d3b66; color:#fff; }
.btn-wa{ background:#25D366; color:#fff; }
.btn-plano{ background:#82B60B; color:#fff; }
.btn-convert{ background:#dbe7f3; color:#0d3b66; }
.btn-firma{ background:#9333ea; color:#fff; }
.btn-delete{ background:#fee2e2; color:#b91c1c; }

.total{
    font-weight:bold;
}

.empty{
    padding:20px;
    background:#f9fafb;
    border:1px dashed #cbd5e1;
    border-radius:10px;
}

@media (max-width: 900px){
    table{
        display:block;
        overflow:auto;
    }
}
.filtros{padding:18px;background:#f8fafc;border:1px solid #cfd8e3;border-radius:10px;margin:20px 0}
.filtros-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px}.filtros label{display:block;font-size:14px;font-weight:bold}.filtros input,.filtros select{box-sizing:border-box;width:100%;padding:10px;margin-top:6px;font:inherit;border:1px solid #cfd8e3;border-radius:7px;background:white}
.filtro-actions,.paginacion,.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.filtro-actions{margin-top:14px}.filtro-actions button{border:0;cursor:pointer;font-size:14px}.filtro-actions a{color:#0d3b66}.paginacion{margin-top:20px}.paginacion a{padding:9px 12px;background:#dbe7f3;color:#0d3b66;border-radius:7px;text-decoration:none}.paginacion strong{padding:9px 12px;background:#0d3b66;color:white;border-radius:7px}.filtro-error{padding:12px;background:#fee2e2;color:#b91c1c;border-radius:8px}.resumen-listado{color:#555752;font-size:14px}.estado-doc{display:inline-block;padding:5px 8px;background:#eef3f8;border-radius:6px}
.filtros input:focus,.filtros select:focus{outline:2px solid #0d3b66;outline-offset:2px}
@media(max-width:700px){body{padding:10px}.content{padding:14px}.filtros-grid{grid-template-columns:1fr}}
</style>
</head>

<body>

<div class="wrap">
    <div class="topbar">
        <h1>Documentos</h1>
    </div>

    <div class="content">

        <div class="actions">
            <a class="btn" href="index.php">+ Nueva cotización</a>
        <a class="btn" href="borradores.php">Mis borradores</a>
        <a class="btn" href="catalogo.php">Mis conceptos</a>
        <a class="btn" href="clientes.php">Mis clientes</a>
        <a class="btn" href="seguimiento.php">Seguimiento comercial</a>
        </div>

        <form method="get" action="listado.php" class="filtros">
          <div class="filtros-grid">
            <label>Buscar documento<input type="search" name="q" maxlength="100" placeholder="Folio, cliente, teléfono, correo o proyecto" value="<?= oj_listado_e($filtros['q']); ?>"></label>
            <label>Tipo<select name="tipo"><option value="">Todos los tipos</option><?php foreach ($tiposLabel as $valor => $etiqueta): ?><option value="<?= $valor ?>" <?= $filtros['tipo'] === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option><?php endforeach; ?></select></label>
            <label>Estado de pago<select name="estado"><option value="">Todos los estados</option><?php foreach ($estadosLabel as $valor => $etiqueta): ?><option value="<?= $valor ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option><?php endforeach; ?></select></label>
            <label>Desde<input type="date" name="desde" min="1000-01-01" max="9998-12-31" value="<?= oj_listado_e($filtros['desde']); ?>"></label>
            <label>Hasta<input type="date" name="hasta" min="1000-01-01" max="9998-12-31" value="<?= oj_listado_e($filtros['hasta']); ?>"></label>
          </div>
          <div class="filtro-actions"><button class="btn" type="submit">Buscar / filtrar</button><button class="btn" type="submit" formaction="exportar_documentos.php">Exportar resultados a CSV</button><a href="listado.php">Limpiar filtros</a><a href="dashboard.php">Volver al Dashboard</a></div>
        </form>
        <p class="resumen-listado">La exportación incluye todas las coincidencias de los filtros elegidos, aunque ocupen varias páginas. Si cambias filtros antes de exportar, se usarán los valores que aparecen en el formulario.</p>
        <?php foreach ($filtros['errores'] as $errorFiltro): ?><p class="filtro-error" role="alert"><?= oj_listado_e($errorFiltro) ?></p><?php endforeach; ?>
        <?php if (!$filtros['errores']): ?><p class="resumen-listado" role="status"><?= $totalDocumentos ?> documento(s) encontrado(s). Mostrando <?= $inicio ?>–<?= $inicio ? $inicio + count($cotizaciones) - 1 : 0 ?>. Página <?= $pagina ?> de <?= $totalPaginas ?>.</p><?php endif; ?>
        <?php if (count($cotizaciones) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Tipo</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Proyecto</th>
                    <th>Total</th>
                    <th>Estado de pago</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($cotizaciones as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['folio']); ?></td>
                    <td><?= oj_listado_e($tiposLabel[$row['tipo_documento']] ?? $row['tipo_documento']); ?></td>
                    <td><?= htmlspecialchars($row['fecha']); ?></td>
                    <td><?= htmlspecialchars($row['cliente_nombre']); ?></td>
                    <td><?= htmlspecialchars($row['proyecto']); ?></td>
                    <td class="total">$<?= number_format($row['total'], 2, '.', ','); ?></td>
                    <td><span class="estado-doc"><?= oj_listado_e($estadosLabel[$row['estado_pago']] ?? $row['estado_pago']); ?></span></td>

                    <td>
                        <div class="btn-group">

                            <a class="btn-sm btn-convert" href="index.php?duplicar_id=<?= (int)$row['id']; ?>">Preparar copia</a>

                            <!-- PDF -->
                            <a class="btn-sm btn-pdf"
                               href="ver-cotizacion.php?id=<?= $row['id']; ?>" target="_blank">
                               PDF
                            </a>

                            <a class="btn-sm btn-pdf" href="anticipos.php?id=<?= (int)$row['id']; ?>">Recibos / Anticipos</a>
                            <?php if ($row['tipo_documento'] === 'nota_servicio'): ?>
                            <a class="btn-sm btn-plano" href="inspecciones.php?documento=<?= (int)$row['id']; ?>">Checklist CCTV / IP</a>
                            <?php endif; ?>
                            <!-- WhatsApp -->
                            <a class="btn-sm btn-wa"
                               href="whatsapp.php?id=<?= $row['id']; ?>" target="_blank">
                               WhatsApp
                            </a>

                            <!-- Plano -->
                            <a class="btn-sm btn-plano"
                               href="plano/?id=<?= $row['id']; ?>" target="_blank">
                               Plano
                            </a>

                            <!-- Convertir -->
                            <?php if ($row['tipo_documento'] === 'cotizacion'): ?>
                                <a class="btn-sm btn-convert" href="seguimiento.php?id=<?= (int)$row['id']; ?>">Seguimiento</a>
                                <?php foreach (['nota_venta' => 'Nota venta', 'nota_servicio' => 'Nota servicio'] as $tipo => $etiqueta): ?>
                                <form class="inline-form" method="post" action="convertir_documento.php">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= (int)$row['id']; ?>">
                                    <input type="hidden" name="tipo" value="<?= $tipo; ?>">
                                    <button class="btn-sm btn-convert" type="submit"><?= $etiqueta; ?></button>
                                </form>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Firma -->
                            <?php if (in_array($row['tipo_documento'], ['nota_venta','nota_servicio'])): ?>
                                <a class="btn-sm btn-firma"
                                   href="aceptacion.php?id=<?= $row['id']; ?>" target="_blank">
                                   Firma
                                </a>
                            <?php endif; ?>

                            <!-- Eliminar -->
                            <?php if (($_SESSION['usuario_rol'] ?? '') === 'admin'): ?>
                                <form class="inline-form" method="post" action="eliminar_documento.php" onsubmit="return confirm('¿Eliminar documento?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= (int)$row['id']; ?>">
                                    <button class="btn-sm btn-delete" type="submit">Eliminar</button>
                                </form>
                            <?php endif; ?>

                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php else: ?>
            <div class="empty">
                <?= $filtros['errores'] ? 'Corrige los filtros para consultar documentos.' : 'No hay documentos que coincidan. Prueba otra búsqueda o limpia los filtros.'; ?>
            </div>
        <?php endif; ?>

        <?php if (!$filtros['errores'] && $totalPaginas > 1): ?>
        <nav class="paginacion" aria-label="Páginas de documentos">
          <?php if ($pagina > 1): ?><a href="<?= oj_listado_e(oj_listado_url($filtros, 1)) ?>">Primera</a><a href="<?= oj_listado_e(oj_listado_url($filtros, $pagina - 1)) ?>">Anterior</a><?php endif; ?>
          <?php for ($p = max(1, $pagina - 2); $p <= min($totalPaginas, $pagina + 2); $p++): ?>
            <?php if ($p === $pagina): ?><strong aria-current="page"><?= $p ?></strong><?php else: ?><a href="<?= oj_listado_e(oj_listado_url($filtros, $p)) ?>"><?= $p ?></a><?php endif; ?>
          <?php endfor; ?>
          <?php if ($pagina < $totalPaginas): ?><a href="<?= oj_listado_e(oj_listado_url($filtros, $pagina + 1)) ?>">Siguiente</a><a href="<?= oj_listado_e(oj_listado_url($filtros, $totalPaginas)) ?>">Última</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
