<?php
function oj_catalogo_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotizador_catalogo (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario_id BIGINT UNSIGNED NOT NULL,
        codigo VARCHAR(80) NOT NULL DEFAULT '',
        nombre VARCHAR(300) NOT NULL,
        categoria VARCHAR(20) NOT NULL,
        unidad VARCHAR(20) NOT NULL,
        precio DECIMAL(12,2) NOT NULL,
        activo TINYINT NOT NULL DEFAULT 1,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        creado_en DATETIME NOT NULL,
        actualizado_en DATETIME NOT NULL,
        INDEX idx_catalogo_usuario (usuario_id, activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function oj_catalogo_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function oj_catalogo_texto(array $datos, string $campo, int $limite): string {
    $v = $datos[$campo] ?? '';
    if (!is_scalar($v)) throw new InvalidArgumentException('Revisa el campo ' . $campo . '.');
    $v = trim((string)$v);
    $longitud = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
    if ($longitud > $limite) throw new InvalidArgumentException('El campo ' . $campo . ' es demasiado largo.');
    return $v;
}
function oj_catalogo_validar(array $datos): array {
    $codigo = oj_catalogo_texto($datos, 'codigo', 80);
    $nombre = oj_catalogo_texto($datos, 'nombre', 300);
    $categoria = oj_catalogo_texto($datos, 'categoria', 20);
    $unidad = oj_catalogo_texto($datos, 'unidad', 20);
    $precio = oj_catalogo_texto($datos, 'precio', 20);
    if ($nombre === '') throw new InvalidArgumentException('Escribe el nombre del concepto.');
    if (!in_array($categoria, ['producto','servicio'], true)) throw new InvalidArgumentException('Selecciona producto o servicio.');
    if (!in_array($unidad, ['pieza','metro','servicio','visita','hora','jornada','lote'], true)) throw new InvalidArgumentException('Selecciona una unidad válida.');
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $precio)) throw new InvalidArgumentException('Escribe un precio sin IVA de 0 a 99999999.99, con hasta dos decimales y punto decimal.');
    return [$codigo, $nombre, $categoria, $unidad, number_format((float)$precio, 2, '.', '')];
}
function oj_catalogo_id($valor): int {
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new InvalidArgumentException('Identificador no válido.');
    return (int)$id;
}

function oj_catalogo_normalizar(string $texto): string {
    $texto = trim(preg_replace('/\s+/u', ' ', $texto));
    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
}
function oj_catalogo_coinciden(array $a, array $b): bool {
    $codigoA = oj_catalogo_normalizar($a['codigo']); $codigoB = oj_catalogo_normalizar($b['codigo']);
    if ($codigoA !== '' && $codigoB !== '') return $codigoA === $codigoB;
    return oj_catalogo_normalizar($a['nombre']) === oj_catalogo_normalizar($b['nombre'])
        && $a['categoria'] === $b['categoria'] && $a['unidad'] === $b['unidad'];
}
function oj_catalogo_guardar_unico(PDO $pdo, int $usuario, array $datos, ?int $id = null, ?int $version = null): array {
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE');
        $lock->execute([$usuario]);
        if (!$lock->fetchColumn()) throw new RuntimeException('El usuario ya no está disponible.');
        if ($id !== null) {
            $actual = $pdo->prepare('SELECT id, version, codigo, nombre, categoria, unidad FROM cotizador_catalogo WHERE id = ? AND usuario_id = ? FOR UPDATE');
            $actual->execute([$id,$usuario]); $filaActual = $actual->fetch(PDO::FETCH_ASSOC);
            if (!$filaActual || (int)$filaActual['version'] !== $version) throw new RuntimeException('El concepto cambió en otra pestaña o no está disponible para tu usuario. Abre su edición actual para continuar.');
        }
        $candidato = ['codigo' => $datos[0], 'nombre' => $datos[1], 'categoria' => $datos[2], 'unidad' => $datos[3]];
        $stmt = $pdo->prepare('SELECT id, codigo, nombre, categoria, unidad, precio, activo FROM cotizador_catalogo WHERE usuario_id = ? ORDER BY activo DESC, id ASC');
        $stmt->execute([$usuario]); $coincidencia = null;
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($id !== null && (int)$fila['id'] === $id) continue;
            if (oj_catalogo_coinciden($candidato,$fila)) {
                if ($id !== null && oj_catalogo_coinciden($filaActual,$fila)) continue;
                $coincidencia = $fila; break;
            }
        }
        $stmt->closeCursor();
        if ($coincidencia) {
            $pdo->commit();
            return ['estado' => (int)$coincidencia['activo'] ? 'existente' : 'inactivo', 'nombre' => $coincidencia['nombre'], 'precio' => $coincidencia['precio']];
        }
        if ($id !== null) {
            $stmt = $pdo->prepare('UPDATE cotizador_catalogo SET codigo = ?, nombre = ?, categoria = ?, unidad = ?, precio = ?, version = version + 1, actualizado_en = NOW() WHERE id = ? AND usuario_id = ? AND version = ?');
            $stmt->execute(array_merge($datos,[$id,$usuario,$version]));
            if ($stmt->rowCount() !== 1) throw new RuntimeException('No se pudo actualizar el concepto. Revisa su versión actual.');
            $estado = 'actualizado';
        } else {
            $stmt = $pdo->prepare('INSERT INTO cotizador_catalogo (codigo, nombre, categoria, unidad, precio, usuario_id, creado_en, actualizado_en) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute(array_merge($datos,[$usuario])); $estado = 'creado';
        }
        $pdo->commit();
        return ['estado' => $estado, 'nombre' => $datos[1], 'precio' => $datos[4]];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
