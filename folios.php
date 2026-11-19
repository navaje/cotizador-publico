<?php
function generarFolio($pdo, $tipo) {

    $map = [
        'cotizacion' => 'COT',
        'nota_venta' => 'NV',
        'nota_servicio' => 'NS'
    ];

    if (!isset($map[$tipo])) {
        die('Tipo de folio inválido');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT consecutivo FROM folios WHERE tipo = :tipo FOR UPDATE");
        $stmt->execute([':tipo' => $tipo]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception("Tipo no encontrado en folios");
        }

        $nuevo = $row['consecutivo'] + 1;

        $stmtUpdate = $pdo->prepare("UPDATE folios SET consecutivo = :nuevo WHERE tipo = :tipo");
        $stmtUpdate->execute([
            ':nuevo' => $nuevo,
            ':tipo' => $tipo
        ]);

        $pdo->commit();

        $prefijo = $map[$tipo];

        return $prefijo . '-' . str_pad($nuevo, 4, '0', STR_PAD_LEFT);

    } catch (Exception $e) {
        $pdo->rollBack();
        die('Error generando folio: ' . $e->getMessage());
    }
}