<?php
function oj_listado_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function oj_listado_filtros(array $entrada): array {
    $f = ['q' => '', 'tipo' => '', 'estado' => '', 'desde' => '', 'hasta' => '', 'pagina' => 1, 'where' => [], 'params' => [], 'errores' => []];
    foreach (['q','tipo','estado','desde','hasta'] as $campo) {
        if (isset($entrada[$campo]) && !is_scalar($entrada[$campo])) { $f['errores'][] = 'Revisa el filtro ' . $campo . '.'; continue; }
        $f[$campo] = trim((string)($entrada[$campo] ?? ''));
    }
    $longitud = function_exists('mb_strlen') ? mb_strlen($f['q'], 'UTF-8') : strlen($f['q']);
    if ($longitud > 100) { $f['errores'][] = 'La búsqueda admite hasta 100 caracteres.'; $f['q'] = ''; }
    if ($f['q'] !== '') {
        $patron = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $f['q']) . '%';
        $campos = ['folio','cliente_nombre','cliente_telefono','cliente_email','proyecto'];
        $partes = [];
        foreach ($campos as $i => $campo) { $partes[] = $campo . " LIKE :q" . $i . " ESCAPE '!'"; $f['params'][':q' . $i] = $patron; }
        $f['where'][] = '(' . implode(' OR ', $partes) . ')';
    }
    foreach (['tipo' => ['cotizacion','nota_venta','nota_servicio'], 'estado' => ['pendiente','anticipo','pagado']] as $campo => $validos) {
        if ($f[$campo] === '') continue;
        if (!in_array($f[$campo], $validos, true)) { $f['errores'][] = 'Selecciona un ' . $campo . ' válido.'; $f[$campo] = ''; continue; }
        $f['where'][] = ($campo === 'tipo' ? 'tipo_documento' : 'estado_pago') . ' = :' . $campo;
        $f['params'][':' . $campo] = $f[$campo];
    }
    $fechas = [];
    foreach (['desde','hasta'] as $campo) {
        if ($f[$campo] === '') continue;
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $f[$campo]) ? DateTimeImmutable::createFromFormat('!Y-m-d', $f[$campo]) : false;
        if (!$fecha || $fecha->format('Y-m-d') !== $f[$campo] || $f[$campo] < '1000-01-01' || $f[$campo] > '9998-12-31') { $f['errores'][] = 'La fecha ' . $campo . ' no es válida.'; $f[$campo] = ''; continue; }
        $fechas[$campo] = $fecha;
    }
    if (isset($fechas['desde'], $fechas['hasta']) && $fechas['desde'] > $fechas['hasta']) $f['errores'][] = 'La fecha Desde debe ser anterior o igual a Hasta.';
    if (isset($fechas['desde'])) { $f['where'][] = 'fecha >= :desde'; $f['params'][':desde'] = $fechas['desde']->format('Y-m-d 00:00:00'); }
    if (isset($fechas['hasta'])) { $f['where'][] = 'fecha < :hasta'; $f['params'][':hasta'] = $fechas['hasta']->modify('+1 day')->format('Y-m-d 00:00:00'); }
    if (isset($entrada['pagina'])) {
        $pagina = filter_var($entrada['pagina'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000000]]);
        if ($pagina !== false) $f['pagina'] = (int)$pagina;
    }
    return $f;
}
function oj_listado_url(array $f, int $pagina): string {
    return 'listado.php?' . http_build_query(['q' => $f['q'], 'tipo' => $f['tipo'], 'estado' => $f['estado'], 'desde' => $f['desde'], 'hasta' => $f['hasta'], 'pagina' => $pagina], '', '&', PHP_QUERY_RFC3986);
}
