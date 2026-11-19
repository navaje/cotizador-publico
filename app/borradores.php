<?php
// Archivo interno: los puntos de entrada exigen auth.php y CSRF.
function oj_borradores_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotizador_borradores (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario_id BIGINT UNSIGNED NOT NULL,
        titulo VARCHAR(180) NOT NULL,
        datos LONGTEXT NOT NULL,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        documento_id BIGINT UNSIGNED NULL,
        creado_en DATETIME NOT NULL,
        actualizado_en DATETIME NOT NULL,
        INDEX idx_borrador_usuario (usuario_id, actualizado_en)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function oj_borrador_id($valor): ?int {
    if ($valor === null || $valor === '') return null;
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new InvalidArgumentException('Identificador de borrador no válido.');
    return (int)$id;
}
function oj_borrador_leer(PDO $pdo, int $id, int $usuario, bool $bloquear = false): array {
    $stmt = $pdo->prepare('SELECT * FROM cotizador_borradores WHERE id = ? AND usuario_id = ?' . ($bloquear ? ' FOR UPDATE' : ''));
    $stmt->execute([$id, $usuario]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fila) throw new RuntimeException('No se encontró un borrador disponible para tu usuario.');
    return $fila;
}
function oj_borrador_datos(array $entrada): array {
    $salida = [];
    foreach (['cliente_nombre','cliente_telefono','cliente_email','cliente_direccion','proyecto','vigencia','metodo_pago','tiempo_instalacion','descripcion','observaciones','condiciones','solicitud_id'] as $campo) {
        $valor = $entrada[$campo] ?? '';
        if (!is_scalar($valor) || strlen((string)$valor) > 50000) throw new InvalidArgumentException('Uno de los textos no es válido o es demasiado largo.');
        $salida[$campo] = (string)$valor;
    }
    $salida['items'] = [];
    $conceptos = $entrada['concepto'] ?? [];
    $cantidades = $entrada['cantidad'] ?? [];
    $precios = $entrada['precio'] ?? [];
    $descuentos = $entrada['descuento'] ?? [];
    if (!is_array($conceptos) || !is_array($cantidades) || !is_array($precios) || !is_array($descuentos) || count($conceptos) > 300) throw new InvalidArgumentException('Revisa la lista de conceptos (máximo 300).');
    $cantidades = array_values($cantidades);
    $precios = array_values($precios);
    $descuentos = array_values($descuentos);
    foreach (array_values($conceptos) as $i => $concepto) {
        $cantidad = $cantidades[$i] ?? '';
        $precio = $precios[$i] ?? '';
        $descuento = $descuentos[$i] ?? '0';
        foreach ([$concepto, $cantidad, $precio, $descuento] as $valor) {
            if (!is_scalar($valor) || strlen((string)$valor) > 50000) throw new InvalidArgumentException('Uno de los conceptos no es válido.');
        }
        $salida['items'][] = ['concepto' => (string)$concepto, 'cantidad' => (string)$cantidad, 'precio' => (string)$precio, 'descuento' => (string)$descuento];
    }
    return $salida;
}
function oj_borrador_error(Throwable $e, int $codigo = 400): void {
    http_response_code($codigo);
    if ($e instanceof PDOException) {
        error_log('Borradores: ' . $e->getMessage());
        $mensaje = 'No se pudo acceder a los borradores. Revisa la instalación de la tabla o contacta al administrador.';
    } else $mensaje = $e->getMessage();
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Borradores</title><body style="font-family:Arial;padding:24px"><h1>No se completó la operación</h1><p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p><p>Si estabas capturando datos, vuelve con el botón Atrás del navegador para revisarlos.</p><a href="borradores.php">Ver borradores</a></body></html>';
    exit;
}
