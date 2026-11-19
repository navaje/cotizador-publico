<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$busqueda = trim((string)($_GET['q'] ?? ''));
$estadoFiltro = (string)($_GET['estado'] ?? '');
$servicioFiltro = (string)($_GET['servicio'] ?? '');
$desde = (string)($_GET['desde'] ?? '');
$hasta = (string)($_GET['hasta'] ?? '');
$origenFiltro = (string)($_GET['origen'] ?? '');
$soloVencidos = ($_GET['vencidos'] ?? '') === '1';

$estadosValidos = ['nuevo','contactado','visita_programada','cotizado','aceptado','atendido','perdido'];
$serviciosValidos = ['mantenimiento','revision','reparacion','instalacion','otro'];
$origenesValidos = ['web','catalogo','facebook','whatsapp','recomendacion','otro'];
$where = [];
$params = [];

if ($busqueda !== '') {
    $where[] = '(folio LIKE :q OR cliente_nombre LIKE :q OR cliente_telefono LIKE :q OR cliente_email LIKE :q OR direccion LIKE :q OR tipo_equipo LIKE :q OR marca LIKE :q OR modelo LIKE :q OR producto_nombre LIKE :q OR producto_sku LIKE :q)';
    $params[':q'] = '%' . substr($busqueda, 0, 100) . '%';
}
if (in_array($estadoFiltro, $estadosValidos, true)) {
    $where[] = 'estado = :estado';
    $params[':estado'] = $estadoFiltro;
} else {
    $estadoFiltro = '';
}
if (in_array($servicioFiltro, $serviciosValidos, true)) {
    $where[] = 'tipo_servicio = :servicio';
    $params[':servicio'] = $servicioFiltro;
} else {
    $servicioFiltro = '';
}
if (in_array($origenFiltro, $origenesValidos, true)) {
    $where[] = 'origen = :origen';
    $params[':origen'] = $origenFiltro;
} else {
    $origenFiltro = '';
}
if ($soloVencidos) {
    $where[] = "fecha_seguimiento IS NOT NULL AND fecha_seguimiento < NOW() AND estado NOT IN ('atendido','perdido')";
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $where[] = 'DATE(created_at) >= :desde';
    $params[':desde'] = $desde;
} else {
    $desde = '';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $where[] = 'DATE(created_at) <= :hasta';
    $params[':hasta'] = $hasta;
} else {
    $hasta = '';
}

$sql = 'SELECT * FROM solicitudes_servicio';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

$resumenSolicitudes = array_fill_keys($estadosValidos, 0);
$stmtResumen = $pdo->query('SELECT estado, COUNT(*) AS total FROM solicitudes_servicio GROUP BY estado');
foreach ($stmtResumen->fetchAll() as $filaResumen) {
    if (array_key_exists($filaResumen['estado'], $resumenSolicitudes)) {
        $resumenSolicitudes[$filaResumen['estado']] = (int)$filaResumen['total'];
    }
}
$seguimientosVencidos = (int)$pdo->query("SELECT COUNT(*) FROM solicitudes_servicio WHERE fecha_seguimiento IS NOT NULL AND fecha_seguimiento < NOW() AND estado NOT IN ('atendido','perdido')")->fetchColumn();

function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function estadoClass($estado) {
    if ($estado === 'nuevo') return 'badge-blue';
    if ($estado === 'contactado') return 'badge-yellow';
    if ($estado === 'visita_programada') return 'badge-purple';
    if ($estado === 'cotizado') return 'badge-orange';
    if ($estado === 'aceptado') return 'badge-teal';
    if ($estado === 'atendido') return 'badge-green';
    if ($estado === 'perdido') return 'badge-red';
    return 'badge-gray';
}

function estadoLabel($estado) {
    $labels = [
        'nuevo' => 'Nuevo', 'contactado' => 'Contactado',
        'visita_programada' => 'Visita programada', 'cotizado' => 'Cotizado',
        'aceptado' => 'Aceptado', 'atendido' => 'Atendido', 'perdido' => 'Perdido'
    ];
    return $labels[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitudes de servicio - Ojo Jarocho</title>
<style>
body{
  font-family:Arial, Helvetica, sans-serif;
  background:#eef3f8;
  margin:0;
  padding:24px;
  color:#1f2937;
}
.wrap{
  max-width:1250px;
  margin:0 auto;
  background:#fff;
  border-radius:14px;
  box-shadow:0 10px 30px rgba(0,0,0,.08);
  overflow:hidden;
}
.topbar{
  background:linear-gradient(135deg,#00235A,#06427f);
  color:#fff;
  padding:22px 28px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  flex-wrap:wrap;
}
.topbar h1{
  margin:0;
  font-size:28px;
}
.content{
  padding:24px;
}
.actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-bottom:18px;
}
.btn{
  display:inline-block;
  background:#0d3b66;
  color:#fff;
  text-decoration:none;
  padding:10px 14px;
  border-radius:8px;
  font-weight:bold;
  font-size:14px;
}
.btn-secondary{
  background:#dbe7f3;
  color:#0d3b66;
}
table{
  width:100%;
  border-collapse:collapse;
  background:#fff;
}
thead th{
  background:#0d3b66;
  color:#fff;
  padding:12px 10px;
  text-align:left;
  font-size:13px;
}
tbody td{
  border:1px solid #e5e7eb;
  padding:10px;
  font-size:13px;
  vertical-align:top;
}
tbody tr:nth-child(even){
  background:#f8fafc;
}
.badge{
  display:inline-block;
  padding:6px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:bold;
}
.badge-blue{ background:#dbeafe; color:#1d4ed8; }
.badge-yellow{ background:#fef3c7; color:#b45309; }
.badge-purple{ background:#ede9fe; color:#6d28d9; }
.badge-orange{ background:#ffedd5; color:#c2410c; }
.badge-teal{ background:#ccfbf1; color:#0f766e; }
.badge-green{ background:#dcfce7; color:#15803d; }
.badge-red{ background:#fee2e2; color:#b91c1c; }
.badge-gray{ background:#e5e7eb; color:#374151; }

.btn-sm{
  display:inline-block;
  padding:7px 10px;
  border-radius:6px;
  text-decoration:none;
  font-size:12px;
  font-weight:bold;
  background:#dbe7f3;
  color:#0d3b66;
  margin:2px;
}
.empty{
  padding:20px;
  background:#f9fafb;
  border:1px dashed #cbd5e1;
  border-radius:10px;
}
.summary{display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:18px;}
.summary-card{border:1px solid #e2e8f0;border-radius:10px;padding:12px;background:#f8fafc;}
.summary-card strong{display:block;font-size:24px;color:#00235A;margin-top:5px;}
.filters{border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:18px;background:#f8fafc;}
.filter-grid{display:grid;grid-template-columns:2fr repeat(5,1fr);gap:12px;align-items:end;}
.field label{display:block;font-size:12px;font-weight:bold;color:#475569;margin-bottom:6px;}
.field input,.field select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;}
.filter-actions{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;}
.btn-wa{background:#25D366;color:#073b1c;}
.overdue{background:#fff1f2;border:1px solid #fecdd3;color:#9f1239;padding:14px;border-radius:10px;margin-bottom:18px;}
.overdue a{color:#9f1239;font-weight:bold;}
.date-overdue{color:#b91c1c;font-weight:bold;}
@media(max-width:1000px){
  table{
    display:block;
    overflow:auto;
  }
  .summary{grid-template-columns:repeat(3,1fr);}
  .filter-grid{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:600px){.summary,.filter-grid{grid-template-columns:1fr}.content{padding:14px}body{padding:10px}}
</style>
</head>
<body>

<div class="wrap">
  <div class="topbar">
    <h1>Solicitudes de servicio</h1>
    <div>
      <a class="btn btn-secondary" href="dashboard.php">Dashboard</a>
      <a class="btn btn-secondary" href="listado.php">Cotizaciones / notas</a>
      <a class="btn btn-secondary" href="logout.php">Salir</a>
    </div>
  </div>

  <div class="content">
    <div class="actions">
      <a class="btn" href="solicitud_servicio.php" target="_blank">+ Nueva solicitud pública</a>
    </div>

    <div class="summary">
      <?php foreach (['nuevo','contactado','visita_programada','cotizado','aceptado','atendido','perdido'] as $estadoResumen): ?>
      <div class="summary-card"><?php echo e(estadoLabel($estadoResumen)); ?><strong><?php echo (int)$resumenSolicitudes[$estadoResumen]; ?></strong></div>
      <?php endforeach; ?>
    </div>

    <?php if ($seguimientosVencidos > 0): ?><div class="overdue">Hay <strong><?php echo $seguimientosVencidos; ?></strong> seguimiento(s) vencido(s). <a href="solicitudes.php?vencidos=1">Ver pendientes</a></div><?php endif; ?>

    <form class="filters" method="get" action="solicitudes.php">
      <div class="filter-grid">
        <div class="field"><label for="q">Folio, cliente, teléfono, equipo o marca</label><input id="q" type="search" name="q" value="<?php echo e($busqueda); ?>" placeholder="Buscar solicitud"></div>
        <div class="field"><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option><?php foreach ($estadosValidos as $estado): ?><option value="<?php echo e($estado); ?>" <?php echo $estadoFiltro === $estado ? 'selected' : ''; ?>><?php echo e(estadoLabel($estado)); ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="servicio">Servicio</label><select id="servicio" name="servicio"><option value="">Todos</option><?php foreach ($serviciosValidos as $servicio): ?><option value="<?php echo e($servicio); ?>" <?php echo $servicioFiltro === $servicio ? 'selected' : ''; ?>><?php echo e(ucfirst($servicio)); ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="origen">Origen</label><select id="origen" name="origen"><option value="">Todos</option><?php foreach ($origenesValidos as $origen): ?><option value="<?php echo e($origen); ?>" <?php echo $origenFiltro === $origen ? 'selected' : ''; ?>><?php echo e(ucfirst($origen)); ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="desde">Desde</label><input id="desde" type="date" name="desde" value="<?php echo e($desde); ?>"></div>
        <div class="field"><label for="hasta">Hasta</label><input id="hasta" type="date" name="hasta" value="<?php echo e($hasta); ?>"></div>
      </div>
      <div class="filter-actions"><?php if ($soloVencidos): ?><input type="hidden" name="vencidos" value="1"><?php endif; ?><button class="btn" type="submit">Aplicar filtros</button><a class="btn btn-secondary" href="solicitudes.php">Limpiar</a></div>
    </form>

    <?php if ($solicitudes): ?>
      <table>
        <thead>
          <tr>
            <th>Folio</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Teléfono</th>
            <th>Servicio</th>
            <th>Equipo</th>
            <th>Fecha preferida</th>
            <th>Seguimiento</th>
            <th>Estado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($solicitudes as $row): ?>
            <tr>
              <td><?php echo e($row['folio']); ?></td>
              <td><?php echo e($row['created_at']); ?></td>
              <td>
                <strong><?php echo e($row['cliente_nombre']); ?></strong><br>
                <?php echo e($row['direccion']); ?>
              </td>
              <td><?php echo e($row['cliente_telefono']); ?></td>
              <td><?php echo e($row['tipo_servicio']); ?></td>
              <td>
                <?php echo e($row['tipo_equipo']); ?><br>
                <?php echo e($row['marca']); ?> <?php echo e($row['modelo']); ?>
              </td>
              <td>
                <?php echo e($row['fecha_preferida']); ?><br>
                <?php echo e($row['horario_preferido']); ?>
              </td>
              <td>
                <strong><?php echo e(ucfirst($row['origen'] ?? 'web')); ?></strong><br>
                <?php if (!empty($row['fecha_seguimiento'])): ?><span class="<?php echo strtotime($row['fecha_seguimiento']) < time() && !in_array($row['estado'], ['atendido','perdido'], true) ? 'date-overdue' : ''; ?>"><?php echo e($row['fecha_seguimiento']); ?></span><br><?php endif; ?>
                <?php echo e($row['proxima_accion'] ?? ''); ?>
              </td>
              <td>
                <span class="badge <?php echo estadoClass($row['estado']); ?>">
                  <?php echo e(estadoLabel($row['estado'])); ?>
                </span>
              </td>
              <td>
                <a class="btn-sm" href="ver_solicitud.php?id=<?php echo (int)$row['id']; ?>">Ver</a>
                <a class="btn-sm btn-wa" href="whatsapp_solicitud.php?id=<?php echo (int)$row['id']; ?>" target="_blank" rel="noopener">WhatsApp</a>
                <a class="btn-sm" href="index.php?solicitud_id=<?php echo (int)$row['id']; ?>">Cotizar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty">No hay solicitudes registradas todavía.</div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
