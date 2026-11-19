<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

function money($n) {
    return '$' . number_format((float)$n, 2, '.', ',');
}

function badgeClass($estado) {
    if ($estado === 'pagado') return 'badge-green';
    if ($estado === 'anticipo') return 'badge-yellow';
    return 'badge-red';
}

function tipoLabel($tipo) {
    if ($tipo === 'nota_venta') return 'Nota venta';
    if ($tipo === 'nota_servicio') return 'Nota servicio';
    return 'Cotización';
}

$estadisticas = [
    'total_documentos' => 0,
    'total_cotizaciones' => 0,
    'total_pendiente' => 0,
    'total_anticipo' => 0,
    'total_pagado' => 0,
    'monto_total' => 0,
    'monto_cotizado' => 0,
    'monto_pendiente' => 0,
    'monto_anticipo' => 0,
    'monto_pagado' => 0,
];

$stmtResumen = $pdo->query("
    SELECT 
        COUNT(*) AS total_documentos,
        COALESCE(SUM(CASE WHEN tipo_documento = 'cotizacion' THEN 1 ELSE 0 END), 0) AS total_cotizaciones,
        COALESCE(SUM(CASE WHEN estado_pago = 'pendiente' THEN 1 ELSE 0 END), 0) AS total_pendiente,
        COALESCE(SUM(CASE WHEN estado_pago = 'anticipo' THEN 1 ELSE 0 END), 0) AS total_anticipo,
        COALESCE(SUM(CASE WHEN estado_pago = 'pagado' THEN 1 ELSE 0 END), 0) AS total_pagado,

        COALESCE(SUM(CASE WHEN tipo_documento = 'cotizacion' THEN total ELSE 0 END), 0) AS monto_cotizado,
        COALESCE(SUM(CASE WHEN estado_pago = 'pendiente' THEN total ELSE 0 END), 0) AS monto_pendiente,
        COALESCE(SUM(CASE WHEN estado_pago = 'anticipo' THEN total ELSE 0 END), 0) AS monto_anticipo,
        COALESCE(SUM(CASE WHEN estado_pago = 'pagado' THEN total ELSE 0 END), 0) AS monto_pagado,
        COALESCE(SUM(total), 0) AS monto_total
    FROM cotizaciones
");

$resumen = $stmtResumen->fetch();

if ($resumen) {
    $estadisticas = array_merge($estadisticas, $resumen);
}

$stmtRecientes = $pdo->query("
    SELECT id, folio, tipo_documento, cliente_nombre, proyecto, total, estado_pago, fecha
    FROM cotizaciones
    ORDER BY id DESC
    LIMIT 15
");

$recientes = $stmtRecientes->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Ojo Jarocho</title>
<style>
body{
    font-family: Arial, Helvetica, sans-serif;
    background:#eef3f8;
    margin:0;
    padding:24px;
    color:#1f2937;
}
.wrap{
    max-width:1250px;
    margin:0 auto;
}
.topbar{
    background:#0d3b66;
    color:#fff;
    padding:22px 28px;
    border-radius:14px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    margin-bottom:20px;
}
.topbar h1{
    margin:0;
    font-size:30px;
}
.topbar p{
    margin:8px 0 0;
    opacity:.92;
}
.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:20px;
}
.btn{
    display:inline-block;
    background:#0d3b66;
    color:#fff;
    text-decoration:none;
    padding:11px 15px;
    border-radius:8px;
    font-weight:bold;
}
.inline-form{ display:inline-block; margin:2px; }
.inline-form button{ border:0; cursor:pointer; }
.btn-secondary{
    background:#dbe7f3;
    color:#0d3b66;
}
.cards{
    display:grid;
    grid-template-columns:repeat(5, 1fr);
    gap:16px;
    margin-bottom:20px;
}
.card{
    background:#fff;
    border-radius:14px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    padding:18px;
}
.card h3{
    margin:0 0 10px;
    font-size:15px;
    color:#4b5563;
}
.card .number{
    font-size:30px;
    font-weight:bold;
    margin-bottom:8px;
}
.card .amount{
    font-size:14px;
    color:#6b7280;
}
.card.red .number{ color:#b91c1c; }
.card.yellow .number{ color:#b45309; }
.card.green .number{ color:#15803d; }
.card.blue .number{ color:#0d3b66; }

.panel{
    background:#fff;
    border-radius:14px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    padding:20px;
}
.panel h2{
    margin:0 0 16px;
    color:#0d3b66;
}
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
.badge-red{
    background:#fee2e2;
    color:#b91c1c;
}
.badge-yellow{
    background:#fef3c7;
    color:#b45309;
}
.badge-green{
    background:#dcfce7;
    color:#15803d;
}
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
.btn-red{
    background:#fee2e2;
    color:#b91c1c;
}
.btn-yellow{
    background:#fef3c7;
    color:#b45309;
}
.btn-green{
    background:#dcfce7;
    color:#15803d;
}
@media (max-width: 1000px){
    .cards{
        grid-template-columns:repeat(2, 1fr);
    }
    table{
        display:block;
        overflow:auto;
    }
}
@media (max-width: 600px){
    .cards{
        grid-template-columns:1fr;
    }
    body{
        padding:12px;
    }
}
</style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <h1>Dashboard Ojo Jarocho</h1>
        <p>Resumen general de cotizaciones, notas y estados de pago</p>
    </div>

    <div class="actions">
        <a class="btn" href="index.php">+ Nueva cotización</a>
        <a class="btn btn-secondary" href="listado.php">Ver listado</a>
        <a class="btn btn-secondary" href="solicitudes.php">Solicitudes</a>
        <a class="btn btn-secondary" href="config_empresa.php">Configurar empresa</a>
        <a class="btn btn-secondary" href="cambiar_password.php">Cambiar contraseña</a>
        <a class="btn btn-secondary" href="https://ojojarocho.online" target="_blank" rel="noopener">Sitio Ojo Jarocho</a>
        <a class="btn btn-secondary" href="https://www.productos-info.com/tienda/ojojarocho" target="_blank" rel="noopener">Catálogo</a>
        <a class="btn btn-secondary" href="https://wa.me/529999185208" target="_blank" rel="noopener">WhatsApp Business</a>
        <a class="btn btn-secondary" href="logout.php">Cerrar sesión</a>
    </div>

    <div class="cards">
        <div class="card blue">
            <h3>Total documentos</h3>
            <div class="number"><?php echo (int)$estadisticas['total_documentos']; ?></div>
            <div class="amount">Monto global: <?php echo money($estadisticas['monto_total']); ?></div>
        </div>

        <div class="card blue">
            <h3>Cotizado</h3>
            <div class="number"><?php echo (int)$estadisticas['total_cotizaciones']; ?></div>
            <div class="amount">Monto cotizado: <?php echo money($estadisticas['monto_cotizado']); ?></div>
        </div>

        <div class="card red">
            <h3>Pendiente</h3>
            <div class="number"><?php echo (int)$estadisticas['total_pendiente']; ?></div>
            <div class="amount">Monto pendiente: <?php echo money($estadisticas['monto_pendiente']); ?></div>
        </div>

        <div class="card yellow">
            <h3>Anticipo</h3>
            <div class="number"><?php echo (int)$estadisticas['total_anticipo']; ?></div>
            <div class="amount">Monto anticipo: <?php echo money($estadisticas['monto_anticipo']); ?></div>
        </div>

        <div class="card green">
            <h3>Pagado</h3>
            <div class="number"><?php echo (int)$estadisticas['total_pagado']; ?></div>
            <div class="amount">Monto pagado: <?php echo money($estadisticas['monto_pagado']); ?></div>
        </div>
    </div>

    <div class="panel">
        <h2>Documentos recientes</h2>

        <table>
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Tipo</th>
                    <th>Cliente</th>
                    <th>Proyecto</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recientes): ?>
                    <?php foreach ($recientes as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['folio']); ?></td>
                            <td><?php echo htmlspecialchars(tipoLabel($row['tipo_documento'])); ?></td>
                            <td><?php echo htmlspecialchars($row['cliente_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($row['proyecto']); ?></td>
                            <td><?php echo money($row['total']); ?></td>
                            <td>
                                <span class="badge <?php echo badgeClass($row['estado_pago']); ?>">
                                    <?php echo strtoupper(htmlspecialchars($row['estado_pago'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['fecha']); ?></td>
                            <td>
                                <a class="btn-sm" href="ver-cotizacion.php?id=<?php echo (int)$row['id']; ?>" target="_blank">Ver PDF</a>
                                <?php foreach (['pendiente' => 'Pendiente', 'anticipo' => 'Anticipo', 'pagado' => 'Pagado'] as $estado => $etiqueta): ?>
                                <form class="inline-form" method="post" action="cambiar_estado.php">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                    <input type="hidden" name="estado" value="<?php echo $estado; ?>">
                                    <button class="btn-sm <?php echo $estado === 'pendiente' ? 'btn-red' : ($estado === 'anticipo' ? 'btn-yellow' : 'btn-green'); ?>" type="submit"><?php echo $etiqueta; ?></button>
                                </form>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No hay documentos registrados todavía.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
