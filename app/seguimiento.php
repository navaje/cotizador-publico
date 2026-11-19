<?php
// Los puntos de entrada exigen autenticación y CSRF para las escrituras.
function ojs_e($valor): string { return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'); }
function ojs_estados(): array {
    return ['enviada'=>'Enviada','en_seguimiento'=>'En seguimiento','aceptada'=>'Aceptada','rechazada'=>'Rechazada'];
}
function ojs_hoy(): string { return (new DateTimeImmutable('today', new DateTimeZone('America/Mexico_City')))->format('Y-m-d'); }
function ojs_schema(PDO $pdo): void {
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/MIGRACION_SEGUIMIENTO.sql'));
}
function ojs_texto(array $entrada, string $clave, int $max): string {
    $valor = $entrada[$clave] ?? '';
    if (!is_string($valor) || strlen($valor) > $max * 4 || preg_match('//u', $valor) !== 1) throw new InvalidArgumentException('Revisa el campo ' . $clave . '.');
    $valor = trim($valor);
    $longitud = function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : preg_match_all('/./us', $valor);
    if ($longitud > $max) throw new InvalidArgumentException('El campo ' . $clave . ' es demasiado largo.');
    return $valor;
}
function ojs_entero($valor, bool $permitirCero = false): int {
    if (!is_scalar($valor)) throw new InvalidArgumentException('Identificador no válido.');
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options'=>['min_range'=>$permitirCero ? 0 : 1]]);
    if ($id === false) throw new InvalidArgumentException('Identificador no válido.');
    return $id;
}
function ojs_fecha(string $valor): ?string {
    if ($valor === '') return null;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    if (!$fecha || $fecha->format('Y-m-d') !== $valor || $valor < '1000-01-01' || $valor > '9999-12-31') throw new InvalidArgumentException('La fecha de próximo contacto no es válida.');
    return $valor;
}
function ojs_datos(array $entrada): array {
    $estado = ojs_texto($entrada, 'estado', 24);
    if (!isset(ojs_estados()[$estado])) throw new InvalidArgumentException('Selecciona un estado comercial válido.');
    $fecha = ojs_fecha(ojs_texto($entrada, 'proximo_contacto', 10));
    if ($fecha !== null && in_array($estado, ['aceptada','rechazada'], true)) throw new InvalidArgumentException('Quita la fecha de próximo contacto para cerrar como aceptada o rechazada.');
    return ['estado'=>$estado,'proximo_contacto'=>$fecha,'nota'=>ojs_texto($entrada,'nota',2000)];
}
function ojs_filtros(array $entrada): array {
    $q = ojs_texto($entrada,'q',100);
    $estado = ojs_texto($entrada,'estado',24);
    $agenda = ojs_texto($entrada,'agenda',12);
    if ($estado !== '' && $estado !== 'sin_seguimiento' && !isset(ojs_estados()[$estado])) throw new InvalidArgumentException('Filtro de estado no válido.');
    if (!in_array($agenda,['','hoy','vencidos','programados'],true)) throw new InvalidArgumentException('Filtro de contacto no válido.');
    $pagina = isset($entrada['pagina']) ? ojs_entero($entrada['pagina']) : 1;
    $where = ["c.tipo_documento = 'cotizacion'"];
    $params = [];
    if ($q !== '') {
        $patron = '%' . strtr($q, ['!'=>'!!','%'=>'!%','_'=>'!_']) . '%';
        $partes = [];
        foreach (['c.folio','c.cliente_nombre','c.proyecto'] as $i=>$campo) {
            $partes[] = $campo . " LIKE :q" . $i . " ESCAPE '!'";
            $params[':q'.$i] = $patron;
        }
        $where[] = '(' . implode(' OR ', $partes) . ')';
    }
    if ($estado === 'sin_seguimiento') $where[] = 's.documento_id IS NULL';
    elseif ($estado !== '') { $where[] = 's.estado = :estado'; $params[':estado'] = $estado; }
    if ($agenda !== '') {
        $where[] = "s.estado IN ('enviada','en_seguimiento')";
        if ($agenda === 'programados') $where[] = 's.proximo_contacto IS NOT NULL';
        else { $where[] = 's.proximo_contacto ' . ($agenda === 'hoy' ? '=' : '<') . ' :hoy'; $params[':hoy'] = ojs_hoy(); }
    }
    return ['q'=>$q,'estado'=>$estado,'agenda'=>$agenda,'pagina'=>$pagina,'where'=>implode(' AND ',$where),'params'=>$params];
}
function ojs_url(array $f, int $pagina): string {
    return 'seguimiento.php?' . http_build_query(['q'=>$f['q'],'estado'=>$f['estado'],'agenda'=>$f['agenda'],'pagina'=>$pagina]);
}
function ojs_guardar(PDO $pdo, int $id, int $version, int $usuario, array $datos): void {
    $pdo->beginTransaction();
    try {
        // Serializa altas simultáneas y comprueba que siga siendo una cotización.
        $stmt = $pdo->prepare('SELECT tipo_documento FROM cotizaciones WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() !== 'cotizacion') throw new InvalidArgumentException('No se encontró una cotización disponible.');
        $stmt = $pdo->prepare('SELECT version FROM cotizador_seguimiento WHERE documento_id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $actual = $stmt->fetchColumn();
        if (($actual === false && $version !== 0) || ($actual !== false && (int)$actual !== $version)) throw new RuntimeException('Este seguimiento cambió en otra pestaña o por otro usuario. Revisa la versión actual antes de guardar.',409);
        $valores = [$datos['estado'],$datos['proximo_contacto'],$datos['nota'],$usuario];
        if ($actual === false) {
            $stmt = $pdo->prepare('INSERT INTO cotizador_seguimiento (estado, proximo_contacto, nota, actualizado_por, documento_id, version, actualizado_en) VALUES (?, ?, ?, ?, ?, 1, NOW())');
            $stmt->execute(array_merge($valores,[$id]));
        } else {
            $stmt = $pdo->prepare('UPDATE cotizador_seguimiento SET estado = ?, proximo_contacto = ?, nota = ?, actualizado_por = ?, version = version + 1, actualizado_en = NOW() WHERE documento_id = ? AND version = ?');
            $stmt->execute(array_merge($valores,[$id,$version]));
            if ($stmt->rowCount() !== 1) throw new RuntimeException('El seguimiento cambió. Abre su versión actual antes de continuar.',409);
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
