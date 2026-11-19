<?php
function oj_csv_texto($valor): string {
    $texto = (string)($valor ?? '');
    // Evitar que texto capturado se interprete como una fórmula al abrir CSV.
    if (preg_match('/^[\s\x00-\x20\x{FEFF}]*[=+\-@]|^[\t\r\n]/u', $texto)) return "'" . $texto;
    return $texto;
}
function oj_csv_documento(array $fila): array {
    $tipos = ['cotizacion' => 'Cotización', 'nota_venta' => 'Nota de venta', 'nota_servicio' => 'Nota de servicio'];
    $estados = ['pendiente' => 'Pendiente', 'anticipo' => 'Anticipo', 'pagado' => 'Pagado'];
    return [
        oj_csv_texto($fila['folio'] ?? ''),
        oj_csv_texto($tipos[$fila['tipo_documento'] ?? ''] ?? ($fila['tipo_documento'] ?? '')),
        oj_csv_texto($fila['fecha'] ?? ''),
        oj_csv_texto($fila['cliente_nombre'] ?? ''),
        oj_csv_texto($fila['cliente_telefono'] ?? ''),
        oj_csv_texto($fila['cliente_email'] ?? ''),
        oj_csv_texto($fila['proyecto'] ?? ''),
        number_format((float)($fila['subtotal'] ?? 0), 2, '.', ''),
        number_format((float)($fila['iva'] ?? 0), 2, '.', ''),
        number_format((float)($fila['total'] ?? 0), 2, '.', ''),
        oj_csv_texto($estados[$fila['estado_pago'] ?? ''] ?? ($fila['estado_pago'] ?? '')),
        'MXN'
    ];
}
