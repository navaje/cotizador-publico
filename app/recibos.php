<?php
// Funciones del módulo. Los controladores deben exigir auth.php y CSRF.
function ojr_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function ojr_centavos($value): int {
    $value = trim((string)$value);
    if (!preg_match('/^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
        throw new InvalidArgumentException('Importe inválido. Escribe números sin comas, hasta dos decimales; ejemplo: 1500.00.');
    }
    return (int)$m[1] * 100 + (int)str_pad($m[2] ?? '', 2, '0');
}
function ojr_decimal(int $cents): string {
    return intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
}
function ojr_money(int $cents): string {
    return '$' . number_format($cents / 100, 2, '.', ',') . ' MXN';
}
function ojr_text(array $data, string $key, int $max, bool $required = false): string {
    if (isset($data[$key]) && !is_scalar($data[$key])) throw new InvalidArgumentException('Campo inválido: ' . $key);
    $value = trim((string)($data[$key] ?? ''));
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max) {
        throw new InvalidArgumentException('Revisa el campo ' . $key . '. Máximo ' . $max . ' caracteres.');
    }
    return $value;
}
function ojr_tipo(string $tipo): string {
    return ['cotizacion'=>'Cotización', 'nota_venta'=>'Nota de venta', 'nota_servicio'=>'Nota de servicio'][$tipo] ?? 'Documento';
}
function ojr_verificar_motor(PDO $pdo): void {
    // La exclusión entre cobros concurrentes requiere tablas transaccionales.
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return;
    $q = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('cotizaciones','oj_recibos')");
    $tables = $q->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['cotizaciones','oj_recibos'] as $name) {
        if (strcasecmp((string)($tables[$name] ?? ''), 'InnoDB') !== 0) {
            throw new RuntimeException('Antes de registrar pagos, verifica que las tablas cotizaciones y oj_recibos existan y usen InnoDB en phpMyAdmin.');
        }
    }
}
function ojr_cadena(PDO $pdo, int $id): array {
    $chain = []; $seen = [];
    $q = $pdo->prepare('SELECT * FROM cotizaciones WHERE id = ? LIMIT 1');
    while ($id > 0) {
        if (isset($seen[$id]) || count($chain) >= 50) throw new RuntimeException('La relación entre documentos necesita revisión.');
        $seen[$id] = true; $q->execute([$id]); $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('No se encontró el documento o su origen. Revisa los documentos relacionados.');
        $chain[] = $row; $id = (int)($row['documento_origen_id'] ?? 0);
    }
    if (!$chain) throw new RuntimeException('Selecciona un documento válido.');
    return $chain;
}
function ojr_historial(PDO $pdo, int $root): array {
    $q = $pdo->prepare('SELECT * FROM oj_recibos WHERE raiz_id = ? ORDER BY id DESC');
    $q->execute([$root]); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function ojr_acumulado(array $rows): int {
    $sum = 0;
    foreach ($rows as $row) if ($row['anulado_en'] === null) $sum += ojr_centavos($row['importe']);
    return $sum;
}
function ojr_estado(PDO $pdo, array $chain, int $paid): void {
    $q = $pdo->prepare('UPDATE cotizaciones SET estado_pago = ? WHERE id = ?');
    $root = end($chain); $pending = [$root]; $seen = [];
    $children = $pdo->prepare('SELECT id, total FROM cotizaciones WHERE documento_origen_id = ?');
    while ($pending) {
        $doc = array_pop($pending);
        if (isset($seen[$doc['id']])) continue;
        if (count($seen) >= 500) throw new RuntimeException('Demasiados documentos relacionados; revisa la estructura del trabajo.');
        $seen[$doc['id']] = true;
        $total = ojr_centavos($doc['total']);
        $state = $paid === 0 ? 'pendiente' : ($paid >= $total ? 'pagado' : 'anticipo');
        $q->execute([$state, $doc['id']]);
        $children->execute([$doc['id']]);
        foreach ($children->fetchAll(PDO::FETCH_ASSOC) as $child) $pending[] = $child;
    }
}
function ojr_guardar(PDO $pdo, int $documentId, array $input, array $session): int {
    ojr_verificar_motor($pdo);
    $token = ojr_text($input, 'solicitud_token', 64, true);
    if (!preg_match('/^[a-f0-9]{64}$/D', $token) || (int)($session['ojr_tokens'][$token] ?? 0) !== $documentId) {
        throw new InvalidArgumentException('El formulario venció. Recarga la página.');
    }
    $lookup = $pdo->prepare('SELECT id, documento_id FROM oj_recibos WHERE solicitud_token = ?');
    $lookup->execute([$token]); $existing = $lookup->fetch(PDO::FETCH_ASSOC);
    if ($existing && (int)$existing['documento_id'] === $documentId) return (int)$existing['id'];
    if ($existing) throw new InvalidArgumentException('El formulario corresponde a otro documento. Recarga la página.');
    if (($input['confirmado'] ?? '') !== '1') throw new InvalidArgumentException('Confirma que el dinero fue recibido y que registraste los pagos anteriores.');
    $amount = ojr_centavos(ojr_text($input, 'importe', 20, true));
    if ($amount <= 0) throw new InvalidArgumentException('El importe debe ser mayor que cero.');
    $method = ojr_text($input, 'forma_pago', 20, true);
    if (!in_array($method, ['efectivo','transferencia'], true)) throw new InvalidArgumentException('Selecciona efectivo o transferencia.');
    $bank = ojr_text($input, 'banco', 100, $method === 'transferencia');
    $reference = ojr_text($input, 'referencia', 160, $method === 'transferencia');
    if ($method === 'efectivo') { $bank = ''; $reference = ''; }
    $receiver = ojr_text($input, 'recibio', 160, true);
    $payer = ojr_text($input, 'entrego', 160, true);
    $concept = ojr_text($input, 'concepto', 1000, true);
    $notes = ojr_text($input, 'observaciones', 1000);
    $dateText = ojr_text($input, 'fecha_pago', 16, true);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dateText, new DateTimeZone('America/Mexico_City'));
    if (!$date || $date->format('Y-m-d\TH:i') !== $dateText || $date > new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'))) {
        throw new InvalidArgumentException('La fecha de recepción debe ser válida y no estar en el futuro.');
    }
    $chain = ojr_cadena($pdo, $documentId); $root = (int)end($chain)['id'];
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        // Serializa pagos de todas las notas derivadas de la misma cotización.
        $lock = $pdo->prepare('SELECT id FROM cotizaciones WHERE id = ? FOR UPDATE');
        $lock->execute([$root]);
        $lookup->execute([$token]); $existing = $lookup->fetch(PDO::FETCH_ASSOC);
        if ($existing) { if ($ownsTransaction) $pdo->commit(); return (int)$existing['id']; }
        $chain = ojr_cadena($pdo, $documentId);
        if ((int)end($chain)['id'] !== $root) throw new RuntimeException('La relación del documento cambió. Recarga la página.');
        $doc = $chain[0]; $total = ojr_centavos($doc['total']);
        $history = ojr_historial($pdo, $root);
        // Una misma referencia bancaria vigente no debe cobrarse dos veces.
        if ($method === 'transferencia') {
            foreach ($history as $old) {
                if ($old['anulado_en'] !== null || $old['forma_pago'] !== 'transferencia') continue;
                $oldData = json_decode($old['snapshot'], true, 512, JSON_THROW_ON_ERROR);
                if (mb_strtolower(trim($oldData['referencia']), 'UTF-8') === mb_strtolower($reference, 'UTF-8')) {
                    throw new InvalidArgumentException('Esta referencia bancaria ya está registrada en '.$old['folio'].'. Revisa el historial antes de repetir el pago.');
                }
            }
        }
        $previous = ojr_acumulado($history);
        if ($amount > $total - $previous) throw new InvalidArgumentException('El pago excede el saldo pendiente. Revisa el importe y los recibos anteriores.');
        $paid = $previous + $amount; $balance = $total - $paid;
        $kind = $balance === 0 ? 'liquidacion' : ($previous === 0 ? 'anticipo' : 'abono');
        $documents = []; $acceptances = [];
        $a = $pdo->prepare('SELECT id, cliente_nombre, fecha FROM aceptaciones WHERE cotizacion_id = ? ORDER BY id DESC LIMIT 1');
        foreach (array_reverse($chain) as $related) {
            $documents[] = ['id'=>(int)$related['id'], 'folio'=>$related['folio'], 'tipo'=>ojr_tipo($related['tipo_documento'] ?? 'cotizacion'), 'archivo'=>$related['folio'].'.pdf'];
            $a->execute([$related['id']]); $acceptance = $a->fetch(PDO::FETCH_ASSOC);
            if ($acceptance) $acceptances[] = ['id'=>(int)$acceptance['id'], 'folio_documento'=>$related['folio'], 'archivo'=>'ACEPTACION-'.preg_replace('/[^A-Za-z0-9_-]/','_', $related['folio']).'.pdf', 'nombre'=>$acceptance['cliente_nombre'], 'fecha'=>$acceptance['fecha']];
        }
        $snapshot = [
            'empresa'=>['nombre'=>$doc['empresa_nombre'], 'telefono'=>$doc['empresa_telefono'], 'email'=>$doc['empresa_email'], 'web'=>$doc['empresa_web'], 'direccion'=>$doc['empresa_direccion']],
            'cliente'=>['nombre'=>$doc['cliente_nombre'], 'telefono'=>$doc['cliente_telefono'], 'email'=>$doc['cliente_email'], 'direccion'=>$doc['cliente_direccion']],
            'proyecto'=>$doc['proyecto'], 'documentos'=>$documents, 'aceptaciones'=>$acceptances,
            'total'=>$total, 'previo'=>$previous, 'importe'=>$amount, 'acumulado'=>$paid, 'saldo'=>$balance,
            'banco'=>$bank, 'referencia'=>$reference, 'recibio'=>$receiver, 'entrego'=>$payer, 'concepto'=>$concept, 'observaciones'=>$notes
        ];
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $q = $pdo->prepare('INSERT INTO oj_recibos (raiz_id, documento_id, solicitud_token, importe, tipo, forma_pago, fecha_pago, creado_en, creado_por, snapshot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $q->execute([$root, $documentId, $token, ojr_decimal($amount), $kind, $method, $date->format('Y-m-d H:i:s'), date('Y-m-d H:i:s'), (string)($session['usuario_nombre'] ?? 'Usuario').' (#'.(int)$session['usuario_id'].')', $json]);
        $id = (int)$pdo->lastInsertId();
        $q = $pdo->prepare('UPDATE oj_recibos SET folio = ? WHERE id = ?');
        $q->execute(['RA-'.str_pad((string)$id, 6, '0', STR_PAD_LEFT), $id]);
        ojr_estado($pdo, $chain, $paid);
        if ($ownsTransaction) $pdo->commit(); return $id;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
function ojr_anular(PDO $pdo, int $documentId, int $receiptId, string $reason, array $session): void {
    ojr_verificar_motor($pdo);
    if (($session['usuario_rol'] ?? '') !== 'admin') throw new InvalidArgumentException('Solo el administrador puede anular recibos.');
    if ($reason === '' || mb_strlen($reason, 'UTF-8') > 500) throw new InvalidArgumentException('Indica un motivo de anulación, hasta 500 caracteres.');
    $chain = ojr_cadena($pdo, $documentId); $root = (int)end($chain)['id'];
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT id FROM cotizaciones WHERE id = ? FOR UPDATE'); $q->execute([$root]);
        $q = $pdo->prepare('SELECT documento_id FROM oj_recibos WHERE id = ? AND raiz_id = ? AND anulado_en IS NULL');
        $q->execute([$receiptId, $root]); $receipt = $q->fetch(PDO::FETCH_ASSOC);
        if (!$receipt) throw new InvalidArgumentException('El recibo no existe, ya está anulado o corresponde a otro trabajo.');
        $q = $pdo->prepare('UPDATE oj_recibos SET anulado_en = ?, anulado_por = ?, motivo_anulacion = ? WHERE id = ?');
        $q->execute([date('Y-m-d H:i:s'), (string)($session['usuario_nombre'] ?? 'Administrador').' (#'.(int)$session['usuario_id'].')', $reason, $receiptId]);
        $paid = ojr_acumulado(ojr_historial($pdo, $root));
        ojr_estado($pdo, $chain, $paid);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function ojr_html(array $receipt): string {
    $s = json_decode($receipt['snapshot'], true, 512, JSON_THROW_ON_ERROR);
    $titles = ['anticipo'=>'RECIBO DE ANTICIPO', 'abono'=>'RECIBO DE ABONO', 'liquidacion'=>'RECIBO DE LIQUIDACIÓN'];
    $title = $titles[$receipt['tipo']] ?? 'RECIBO DE PAGO';
    ob_start();
    include __DIR__ . '/recibo_template.php';
    return ob_get_clean();
}
