<?php
function ojd_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotizacion_descuentos (
        item_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        precio_original DECIMAL(12,2) NOT NULL,
        porcentaje DECIMAL(5,2) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function ojd_linea(float $cantidad, float $original, float $porcentaje): array {
    if (!is_finite($cantidad) || !is_finite($original) || !is_finite($porcentaje)
        || $cantidad < 0 || $cantidad > 999999.99 || $original < 0 || $original > 99999999.99 || $porcentaje < 0 || $porcentaje > 100) throw new InvalidArgumentException('Revisa cantidad, precio y descuento (0 a 100%).');
    $q = (int)round($cantidad * 100); $p = (int)round($original * 100); $d = (int)round($porcentaje * 100);
    $neto = intdiv($p * (10000 - $d) + 5000, 10000);
    if ($q * $p > 9007199254740000) throw new InvalidArgumentException('El importe del concepto supera el límite de cálculo.');
    $bruto = intdiv($q * $p + 50, 100);
    $importe = intdiv($q * $neto + 50, 100);
    return ['precio' => $neto / 100, 'importe' => $importe / 100, 'bruto' => $bruto / 100, 'descuento' => ($bruto - $importe) / 100];
}
function ojd_cargar(PDO $pdo, int $documento): array {
    try {
        $stmt = $pdo->prepare('SELECT d.item_id, d.precio_original, d.porcentaje FROM cotizacion_descuentos d JOIN cotizacion_items i ON i.id = d.item_id WHERE i.cotizacion_id = ?');
        $stmt->execute([$documento]); $resultado = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) $resultado[(int)$fila['item_id']] = $fila;
        return $resultado;
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1146) return [];
        throw $e;
    }
}
function ojd_guardar(PDO $pdo, int $item, float $original, float $porcentaje): void {
    if ($porcentaje <= 0) return;
    $stmt = $pdo->prepare('INSERT INTO cotizacion_descuentos (item_id, precio_original, porcentaje) VALUES (?, ?, ?)');
    $stmt->execute([$item, number_format($original,2,'.',''), number_format($porcentaje,2,'.','')]);
}
function ojd_money(float $valor): string { return '$' . number_format($valor,2,'.',','); }
function ojd_pdf_fila(string $concepto, float $cantidad, float $precio, float $importe, ?array $meta, bool $mostrar): string {
    $c = htmlspecialchars($concepto, ENT_QUOTES, 'UTF-8');
    $q = rtrim(rtrim(number_format($cantidad,2,'.',''),'0'),'.');
    $extras = '';
    if ($mostrar) {
        $original = (float)($meta['precio_original'] ?? $precio);
        $porcentaje = (float)($meta['porcentaje'] ?? 0);
        $extras = '<td class="right">' . ojd_money($original) . '</td><td class="right">' . rtrim(rtrim(number_format($porcentaje,2,'.',''),'0'),'.') . '%</td>';
    }
    return '<tr><td>' . $c . '</td><td class="center">' . $q . '</td>' . $extras . '<td class="right">' . ojd_money($precio) . '</td><td class="right">' . ojd_money($importe) . '</td></tr>';
}
function ojd_pdf_cabecera(bool $mostrar): string {
    if (!$mostrar) return '<th style="width:50%;">Concepto</th><th style="width:12%;">Cantidad</th><th style="width:18%;">Precio sin IVA</th><th style="width:20%;">Importe</th>';
    return '<th style="width:36%;">Concepto</th><th style="width:9%;">Cant.</th><th style="width:15%;">Precio original</th><th style="width:10%;">Desc.</th><th style="width:15%;">Precio final</th><th style="width:15%;">Importe</th>';
}
function ojd_pdf_resumen(float $bruto, float $descuento, bool $mostrar): string {
    if (!$mostrar) return '';
    return '<tr><td>Importe original sin IVA</td><td class="right">' . ojd_money($bruto) . '</td></tr><tr><td>Descuento total</td><td class="right">' . ojd_money($descuento) . '</td></tr>';
}
function ojd_borrar(PDO $pdo, int $documento): void {
    try {
        $stmt = $pdo->prepare('DELETE FROM cotizacion_descuentos WHERE item_id IN (SELECT id FROM cotizacion_items WHERE cotizacion_id = ?)');
        $stmt->execute([$documento]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1146) throw $e;
    }
}
