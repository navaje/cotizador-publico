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

$busqueda = trim((string)($_GET['q'] ?? ''));
$tipoFiltro = (string)($_GET['tipo'] ?? '');
$estadoFiltro = (string)($_GET['estado'] ?? '');
$desde = (string)($_GET['desde'] ?? '');
$hasta = (string)($_GET['hasta'] ?? '');

$tiposValidos = ['cotizacion', 'nota_venta', 'nota_servicio'];
$estadosValidos = ['pendiente', 'anticipo', 'pagado'];
$where = [];
$params = [];

if ($busqueda !== '') {
    $where[] = '(folio LIKE :busqueda OR cliente_nombre LIKE :busqueda OR cliente_telefono LIKE :busqueda OR cliente_email LIKE :busqueda OR proyecto LIKE :busqueda)';
    $params[':busqueda'] = '%' . substr($busqueda, 0, 100) . '%';
}
if (in_array($tipoFiltro, $tiposValidos, true)) {
    $where[] = 'tipo_documento = :tipo';
    $params[':tipo'] = $tipoFiltro;
} else {
    $tipoFiltro = '';
}
if (in_array($estadoFiltro, $estadosValidos, true)) {
    $where[] = 'estado_pago = :estado';
    $params[':estado'] = $estadoFiltro;
} else {
    $estadoFiltro = '';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $where[] = 'DATE(fecha) >= :desde';
    $params[':desde'] = $desde;
} else {
    $desde = '';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $where[] = 'DATE(fecha) <= :hasta';
    $params[':hasta'] = $hasta;
} else {
    $hasta = '';
}

$sqlDocumentos = "
    SELECT id, folio, tipo_documento, cliente_nombre, cliente_telefono, proyecto, total, estado_pago, fecha
    FROM cotizaciones
";
if ($where) {
    $sqlDocumentos .= ' WHERE ' . implode(' AND ', $where);
}
$sqlDocumentos .= ' ORDER BY id DESC LIMIT 50';

$stmtRecientes = $pdo->prepare($sqlDocumentos);
$stmtRecientes->execute($params);
$recientes = $stmtRecientes->fetchAll();

// El modo organiza la vista; los permisos siguen en cada módulo.
$modo = (($_GET['modo'] ?? '') === 'campo') ? 'campo' : 'oficina';
$modoExplicito = isset($_GET['modo']);
function oj_panel_e($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function oj_panel_url($modo, $changes = []) {
    $query = array_merge(array_intersect_key($_GET, array_flip(['q','tipo','estado','desde','hasta'])), ['modo'=>$modo], $changes);
    return 'dashboard.php?' . http_build_query($query);
}
function oj_panel_link($url, $title, $description = '', $icon = '+') {
    echo '<a class="work-link" href="'.oj_panel_e($url).'"><span class="work-icon" aria-hidden="true">'.oj_panel_e($icon).'</span><span><strong>'.oj_panel_e($title).'</strong>';
    if ($description !== '') echo '<small>'.oj_panel_e($description).'</small>';
    echo '</span><span class="work-arrow" aria-hidden="true">›</span></a>';
}
$visitasRecientes = []; $visitasListas = []; $visitasProgramadas = []; $visitasDisponibles = true;
try {
    $qVisitas = $pdo->prepare('SELECT id,titulo,cliente_nombre,estado,actualizado_en FROM oj_visitas WHERE usuario_id=? ORDER BY actualizado_en DESC,id DESC LIMIT 5');
    $qVisitas->execute([(int)$_SESSION['usuario_id']]);
    $visitasRecientes = $qVisitas->fetchAll(PDO::FETCH_ASSOC);
    $qListas = $pdo->prepare("SELECT id,titulo,cliente_nombre,estado,actualizado_en FROM oj_visitas WHERE usuario_id=? AND estado='listo' ORDER BY actualizado_en DESC,id DESC LIMIT 5");
    $qListas->execute([(int)$_SESSION['usuario_id']]);
    $visitasListas = $qListas->fetchAll(PDO::FETCH_ASSOC);
    $qProg = $pdo->prepare("SELECT id,titulo,cliente_nombre,estado,actualizado_en, JSON_UNQUOTE(JSON_EXTRACT(datos,'$.general.fecha_deseada')) AS fecha FROM oj_visitas WHERE usuario_id=? AND estado='programada' AND JSON_UNQUOTE(JSON_EXTRACT(datos,'$.general.fecha_deseada'))<>'' ORDER BY fecha ASC, id DESC LIMIT 5");
    $qProg->execute([(int)$_SESSION['usuario_id']]);
    $visitasProgramadas = $qProg->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $visitasDisponibles = false; }
function oj_panel_visitas($rows, $available) {
    if (!$available) { echo '<p class="work-empty">No se pudo cargar el resumen de visitas. <a href="visitas.php">Abrir visitas técnicas</a>.</p>'; return; }
    if (!$rows) { echo '<p class="work-empty">No hay visitas para mostrar en esta sección.</p>'; return; }
    echo '<ul class="work-visits">';
    foreach ($rows as $v) {
        echo '<li><a href="visitas.php?id='.(int)$v['id'].'"><span><strong>'.oj_panel_e($v['cliente_nombre'] ?: 'Sin cliente').'</strong><small>'.oj_panel_e($v['titulo']).' · VT-'.str_pad((string)$v['id'],5,'0',STR_PAD_LEFT).'</small></span><span class="work-status">'.($v['estado']==='listo'?'Lista':($v['estado']==='programada'?'Programada':'Borrador')).'</span></a></li>';
    }
    echo '</ul>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel de trabajo - Ojo Jarocho</title>
<style>
body{
    font-family: Arial, Helvetica, sans-serif;
    background:#eef3f8;
    margin:0;
    padding:24px;
    color:#172033;
}
.wrap{
    max-width:1250px;
    margin:0 auto;
}
.topbar{
    background:linear-gradient(135deg,#00235A 0%,#06427f 72%,#82B60B 145%);
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
.topbar-inner{display:flex;align-items:center;justify-content:space-between;gap:20px;}
.brand-lockup{display:flex;align-items:center;gap:16px;}
.brand-lockup img{width:150px;max-height:56px;object-fit:contain;background:#fff;border-radius:9px;padding:5px;}
.topbar .eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:1.3px;color:#dbeafe;font-weight:bold;}
.inline-form{ display:inline-block; margin:2px; }
.inline-form button{ border:0; cursor:pointer; }
.btn-secondary{
    background:#dbe7f3;
    color:#0d3b66;
}
.btn:hover{filter:brightness(.96);transform:translateY(-1px);}
.btn-wa{background:#25D366;color:#073b1c;}
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
.filters{background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08);padding:18px;margin-bottom:20px;}
.filters h2{margin:0 0 14px;color:#00235A;font-size:20px;}
.filter-grid{display:grid;grid-template-columns:2fr repeat(4,1fr);gap:12px;align-items:end;}
.filter-field label{display:block;font-size:12px;font-weight:bold;color:#475569;margin-bottom:6px;}
.filter-field input,.filter-field select{width:100%;box-sizing:border-box;padding:11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;}
.filter-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}
.result-count{font-size:13px;color:#64748b;margin:-8px 0 14px;}
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
.btn-sm.btn-wa{background:#25D366;color:#073b1c;}
@media (max-width: 1000px){
    .cards{
        grid-template-columns:repeat(2, 1fr);
    }
    table{
        display:block;
        overflow:auto;
    }
    .filter-grid{grid-template-columns:repeat(2,1fr);}
}
@media (max-width: 600px){
    .cards{
        grid-template-columns:1fr;
    }
    body{
        padding:12px;
    }
    .topbar-inner,.brand-lockup{align-items:flex-start;}
    .topbar-inner{flex-direction:column;}
    .brand-lockup img{width:115px;}
    .filter-grid{grid-template-columns:1fr;}
}
/* La tabla conserva su ancho dentro de una zona desplazable. */
.panel{min-width:0;box-sizing:border-box;max-width:100%;}
.documentos-scroll{
    width:100%;
    max-width:100%;
    min-width:0;
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
    border:1px solid #e5e7eb;
    border-radius:8px;
    box-sizing:border-box;
}
.documentos-scroll:focus-visible{outline:2px solid #0d3b66;outline-offset:3px;}
.documentos-scroll table{display:table;min-width:1050px;width:100%;overflow:visible;}
.documentos-scroll td:nth-child(3),.documentos-scroll td:nth-child(4){min-width:150px;overflow-wrap:anywhere;}
.documentos-scroll td:nth-child(5),.documentos-scroll td:nth-child(7){white-space:nowrap;}
.documentos-scroll td:last-child{min-width:230px;}
.documentos-ayuda{font-size:13px;color:#64748b;margin:0 0 12px;line-height:1.5;}
</style>
<link rel="stylesheet" href="assets/dashboard_trabajo.css?v=1">
</head>
<body data-work-mode="<?=oj_panel_e($modo)?>" data-work-explicit="<?=$modoExplicito?'1':'0'?>" data-work-user="<?=(int)$_SESSION['usuario_id']?>">
<div class="wrap">
    <div class="topbar">
        <div class="topbar-inner">
            <div class="brand-lockup">
                <img src="assets/logo.png" alt="Ojo Jarocho">
                <div>
                    <div class="eyebrow">La mirada que cuida Veracruz</div>
                    <h1>Panel de trabajo</h1>
                    <p>Oficina y trabajo de campo, en un mismo lugar</p>
                </div>
            </div>
            <div>Hola, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></div>
        </div>
    </div>


    <nav class="work-modes" aria-label="Modo de trabajo">
        <a id="mode-oficina" class="work-mode" data-mode="oficina" href="<?=oj_panel_e(oj_panel_url('oficina'))?>" <?=$modo==='oficina'?'aria-current="page"':''?>>Oficina</a>
        <a id="mode-campo" class="work-mode" data-mode="campo" href="<?=oj_panel_e(oj_panel_url('campo'))?>" <?=$modo==='campo'?'aria-current="page"':''?>>Trabajo de campo</a>
    </nav>
    <section class="work-area" data-work-section="oficina" <?=$modo!=='oficina'?'hidden':''?> aria-labelledby="office-title">
        <div class="work-heading"><div><p class="work-kicker">OFICINA</p><h2 id="office-title">Prepara, cotiza y da seguimiento</h2><p>Los accesos de uso diario, primero.</p></div><a class="btn work-primary" href="index.php">+ Nueva cotización</a></div>
        <div class="work-grid">
        <?php oj_panel_link('clientes.php','Mis clientes','Datos de contacto y direcciones','01'); oj_panel_link('seguimiento.php','Seguimiento comercial','Oportunidades y próximos pasos','02'); oj_panel_link('dashboard.php?modo=oficina&estado=pendiente#documentos','Cobros y anticipos','Busca el documento y abre sus recibos','03'); ?>
        </div>
        <div class="work-continue"><span>Continuar trabajo</span><a href="borradores.php">Mis borradores</a><a href="#visitas-listas">Visitas listas para cotizar</a><a href="#documentos">Documentos emitidos</a><a href="solicitudes.php">Solicitudes</a></div>
        <details class="work-box" id="visitas-listas"><summary>Visitas listas para cotizar</summary><p class="work-hint">Últimas 5 con estado listo. Abre la visita para preparar o retomar su cotización.</p><?php oj_panel_visitas($visitasListas,$visitasDisponibles); ?></details>
        <details class="work-box" id="visitas-programadas"><summary>Próximas visitas programadas</summary><p class="work-hint">Últimas 5 con estado programada y fecha definida.</p><?php oj_panel_visitas($visitasProgramadas,$visitasDisponibles); ?></details>
    </section>
    <section class="work-area" data-work-section="campo" <?=$modo!=='campo'?'hidden':''?> aria-labelledby="field-title">
        <div class="work-heading"><div><p class="work-kicker">TRABAJO DE CAMPO</p><h2 id="field-title">Visita, registra y continúa</h2><p>Accesos amplios para trabajar desde el celular.</p></div><a class="btn work-primary" href="visitas.php?nueva=1">+ Nueva visita técnica</a></div>
        <div class="work-grid">
        <?php oj_panel_link('visitas.php','Mis levantamientos','Abrir, editar y preparar cotizaciones','01'); oj_panel_link('dashboard.php?modo=campo&tipo=nota_servicio#documentos','Notas de servicio','Buscar la nota y consultar su PDF','02'); oj_panel_link('dashboard.php?modo=campo&tipo=nota_servicio#documentos','Inspecciones CCTV / IP','Abre Checklist desde una nota de servicio','03'); ?>
        </div>
        <div class="work-continue"><span>Continuar trabajo</span><a href="#visitas-recientes">Visitas recientes</a><a href="clientes.php">Cliente y ubicación</a><a href="solicitudes.php">Solicitudes</a></div>
        <div class="work-box" id="visitas-recientes"><h3>Retomar una visita</h3><p class="work-hint">Tus últimas 5 visitas guardadas.</p><?php oj_panel_visitas($visitasRecientes,$visitasDisponibles); ?></div>
    </section>
    <section class="work-tools" aria-label="Más herramientas">
        <details class="work-box"><summary>Productos y herramientas</summary><div class="work-tool-grid">
        <?php oj_panel_link('index.php#syscomCotizador','Buscar productos SYSCOM'); oj_panel_link('catalogo.php','Mis conceptos'); oj_panel_link('https://www.productos-info.com/tienda/ojojarocho','Catálogo externo'); ?>
        </div></details>
        <details class="work-box"><summary>Finanzas y documentos</summary><div class="work-tool-grid">
        <?php oj_panel_link('listado.php','Listado completo'); oj_panel_link('dashboard.php?modo=oficina#documentos','Documentos y anticipos'); if (($_SESSION['usuario_rol']??'')==='admin') { oj_panel_link('banco_movimientos.php','Movimientos Banorte'); oj_panel_link('facturas.php','Facturas de prueba'); } ?>
        </div></details>
        <details class="work-box"><summary>Administración y cuenta</summary><div class="work-tool-grid">
        <?php oj_panel_link('config_empresa.php','Configurar empresa'); if (($_SESSION['usuario_rol']??'')==='admin') oj_panel_link('usuarios.php','Usuarios y acceso'); oj_panel_link('syscom.php','Importar catálogo SYSCOM'); oj_panel_link('syscom_descarga.php','Actualizar catálogo desde enlace'); oj_panel_link('syscom_conexion.php','Conectar API SYSCOM'); oj_panel_link('cambiar_password.php','Cambiar contraseña'); oj_panel_link('https://ojojarocho.online','Sitio Ojo Jarocho'); oj_panel_link('https://wa.me/5210000000000','WhatsApp Business'); oj_panel_link('logout.php','Cerrar sesión'); ?>
        </div></details>
    </section>
    <section data-work-section="oficina" <?=$modo!=='oficina'?'hidden':''?> aria-label="Resumen comercial">
    <h2 class="work-section-title">Resumen comercial</h2>
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

    </section>
    <div id="documentos" class="work-document-heading"><h2>Documentos y servicios</h2><p>Busca una cotización o nota. Las notas de servicio incluyen el acceso a Checklist CCTV / IP.</p></div>
    <form class="filters" method="get" action="dashboard.php">
        <h2>Buscar y filtrar documentos</h2>
        <input type="hidden" name="modo" id="work-filter-mode" value="<?=oj_panel_e($modo)?>">
        <div class="filter-grid">
            <div class="filter-field">
                <label for="q">Folio, cliente, teléfono o proyecto</label>
                <input id="q" type="search" name="q" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Ejemplo: COT-0042 o nombre del cliente">
            </div>
            <div class="filter-field">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo">
                    <option value="">Todos</option>
                    <option value="cotizacion" <?php echo $tipoFiltro === 'cotizacion' ? 'selected' : ''; ?>>Cotización</option>
                    <option value="nota_venta" <?php echo $tipoFiltro === 'nota_venta' ? 'selected' : ''; ?>>Nota de venta</option>
                    <option value="nota_servicio" <?php echo $tipoFiltro === 'nota_servicio' ? 'selected' : ''; ?>>Nota de servicio</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="estado">Estado de pago</label>
                <select id="estado" name="estado">
                    <option value="">Todos</option>
                    <option value="pendiente" <?php echo $estadoFiltro === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="anticipo" <?php echo $estadoFiltro === 'anticipo' ? 'selected' : ''; ?>>Anticipo</option>
                    <option value="pagado" <?php echo $estadoFiltro === 'pagado' ? 'selected' : ''; ?>>Pagado</option>
                </select>
            </div>
            <div class="filter-field"><label for="desde">Desde</label><input id="desde" type="date" name="desde" value="<?php echo htmlspecialchars($desde); ?>"></div>
            <div class="filter-field"><label for="hasta">Hasta</label><input id="hasta" type="date" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>"></div>
        </div>
        <div class="filter-actions">
            <button class="btn" type="submit">Aplicar filtros</button>
            <a class="btn btn-secondary" href="dashboard.php?modo=<?=oj_panel_e($modo)?>" id="work-clear">Limpiar</a>
        </div>
    </form>

    <div class="panel">
        <h2><?php echo ($busqueda || $tipoFiltro || $estadoFiltro || $desde || $hasta) ? 'Resultados' : 'Documentos recientes'; ?></h2>
        <div class="result-count"><?php echo count($recientes); ?> documento(s) mostrado(s), máximo 50</div>

        <p class="documentos-ayuda" id="documentosDesplazarAyuda">Si no ves todas las columnas, desliza la tabla hacia los lados. Con teclado, enfoca la tabla y usa las flechas.</p>
        <div class="documentos-scroll" role="region" aria-label="Tabla de documentos" aria-describedby="documentosDesplazarAyuda" tabindex="0">
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
                                <a class="btn-sm" href="index.php?duplicar_id=<?php echo (int)$row['id']; ?>">Preparar copia</a>
                                <a class="btn-sm" href="ver-cotizacion.php?id=<?php echo (int)$row['id']; ?>" target="_blank">Ver PDF</a>
                                <a class="btn-sm" href="anticipos.php?id=<?php echo (int)$row['id']; ?>">Recibos / Anticipos</a>
                                <?php if ($row['tipo_documento'] === 'nota_servicio'): ?>
                                <a class="btn-sm" href="inspecciones.php?documento=<?php echo (int)$row['id']; ?>">Checklist CCTV / IP</a>
                                <?php endif; ?>
                                <a class="btn-sm btn-wa" href="whatsapp.php?id=<?php echo (int)$row['id']; ?>" target="_blank" rel="noopener">WhatsApp</a>
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
</div>
<script src="assets/dashboard_trabajo.js?v=1" defer></script>
</body>
</html>
