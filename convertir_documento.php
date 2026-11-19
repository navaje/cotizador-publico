<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/descuentos.php';
require_post_csrf();
require_once __DIR__ . '/app/recibos.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    die('ID no válido');
}

$id = (int) $_POST['id'];
$tipo = $_POST['tipo'] ?? '';

if (!in_array($tipo, ['nota_venta', 'nota_servicio'], true)) {
    die('Tipo de documento no válido');
}

$stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$origen = $stmt->fetch();

if (!$origen) {
    die('Cotización no encontrada');
}

$stmtItems = $pdo->prepare("
    SELECT id, concepto, cantidad, precio, importe
    FROM cotizacion_items
    WHERE cotizacion_id = :id
");
$stmtItems->execute([':id' => $id]);
$items = $stmtItems->fetchAll();
$metasDescuentos = ojd_cargar($pdo,$id);

require_once __DIR__ . '/folios.php';
$nuevoFolio = generarFolio($pdo, $tipo);

$pdo->beginTransaction();

try {
    ojr_verificar_motor($pdo);
    $cadenaOrigen = ojr_cadena($pdo, $id);
    $raizOrigen = (int)end($cadenaOrigen)['id'];
    $lockPagos = $pdo->prepare('SELECT id FROM cotizaciones WHERE id = ? FOR UPDATE');
    $lockPagos->execute([$raizOrigen]);
    $recibosOrigen = ojr_historial($pdo, $raizOrigen);
    $abonadoOrigen = ojr_acumulado($recibosOrigen);
    $estadoDerivado = $recibosOrigen
        ? ($abonadoOrigen === 0 ? 'pendiente' : ($abonadoOrigen >= ojr_centavos($origen['total']) ? 'pagado' : 'anticipo'))
        : 'pendiente';


    $stmtInsert = $pdo->prepare("
        INSERT INTO cotizaciones (
            folio, fecha,
            empresa_nombre, empresa_telefono, empresa_email, empresa_web, empresa_direccion,
            cliente_nombre, cliente_telefono, cliente_email, cliente_direccion,
            proyecto, vigencia, metodo_pago, tiempo_instalacion,
            descripcion, observaciones, condiciones,
            subtotal, iva, total,
            tipo_documento, documento_origen_id, estado_pago
        ) VALUES (
            :folio, NOW(),
            :empresa_nombre, :empresa_telefono, :empresa_email, :empresa_web, :empresa_direccion,
            :cliente_nombre, :cliente_telefono, :cliente_email, :cliente_direccion,
            :proyecto, :vigencia, :metodo_pago, :tiempo_instalacion,
            :descripcion, :observaciones, :condiciones,
            :subtotal, :iva, :total,
            :tipo_documento, :documento_origen_id, :estado_pago
        )
    ");

    $stmtInsert->execute([
        ':folio' => $nuevoFolio,
        ':empresa_nombre' => $origen['empresa_nombre'],
        ':empresa_telefono' => $origen['empresa_telefono'],
        ':empresa_email' => $origen['empresa_email'],
        ':empresa_web' => $origen['empresa_web'],
        ':empresa_direccion' => $origen['empresa_direccion'],
        ':cliente_nombre' => $origen['cliente_nombre'],
        ':cliente_telefono' => $origen['cliente_telefono'],
        ':cliente_email' => $origen['cliente_email'],
        ':cliente_direccion' => $origen['cliente_direccion'],
        ':proyecto' => $origen['proyecto'],
        ':vigencia' => $origen['vigencia'],
        ':metodo_pago' => $origen['metodo_pago'],
        ':tiempo_instalacion' => $origen['tiempo_instalacion'],
        ':descripcion' => $origen['descripcion'],
        ':observaciones' => $origen['observaciones'],
        ':condiciones' => $origen['condiciones'],
        ':subtotal' => $origen['subtotal'],
        ':iva' => $origen['iva'],
        ':total' => $origen['total'],
        ':tipo_documento' => $tipo,
        ':documento_origen_id' => $origen['id'],
        ':estado_pago' => $estadoDerivado
    ]);

    $nuevoId = $pdo->lastInsertId();

    $stmtItemInsert = $pdo->prepare("
        INSERT INTO cotizacion_items (
            cotizacion_id, concepto, cantidad, precio, importe
        ) VALUES (
            :cotizacion_id, :concepto, :cantidad, :precio, :importe
        )
    ");

    foreach ($items as $item) {
        $stmtItemInsert->execute([
            ':cotizacion_id' => $nuevoId,
            ':concepto' => $item['concepto'],
            ':cantidad' => $item['cantidad'],
            ':precio' => $item['precio'],
            ':importe' => $item['importe']
        ]);
        $meta = $metasDescuentos[(int)$item['id']] ?? null;
        if ($meta) ojd_guardar($pdo,(int)$pdo->lastInsertId(),(float)$meta['precio_original'],(float)$meta['porcentaje']);
    }

    $pdo->commit();

    header('Location: ver-cotizacion.php?id=' . $nuevoId);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die('Error: ' . $e->getMessage());
}
