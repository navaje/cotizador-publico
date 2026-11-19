<?php
function oj_clientes_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotizador_clientes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario_id BIGINT UNSIGNED NOT NULL,
        nombre VARCHAR(180) NOT NULL,
        telefono VARCHAR(60) NOT NULL DEFAULT '',
        email VARCHAR(254) NOT NULL DEFAULT '',
        direccion TEXT NOT NULL,
        activo TINYINT NOT NULL DEFAULT 1,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        creado_en DATETIME NOT NULL,
        actualizado_en DATETIME NOT NULL,
        INDEX idx_clientes_usuario (usuario_id, activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function oj_clientes_e($valor): string { return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'); }
function oj_clientes_texto(array $datos, string $campo, int $limite): string {
    $valor = $datos[$campo] ?? '';
    if (!is_scalar($valor)) throw new InvalidArgumentException('Revisa el campo ' . $campo . '.');
    $valor = trim((string)$valor);
    $n = function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : strlen($valor);
    if ($n > $limite) throw new InvalidArgumentException('El campo ' . $campo . ' es demasiado largo.');
    return $valor;
}
function oj_clientes_validar(array $datos): array {
    $nombre = oj_clientes_texto($datos, 'nombre', 180);
    $telefono = oj_clientes_texto($datos, 'telefono', 60);
    $email = oj_clientes_texto($datos, 'email', 254);
    $direccion = oj_clientes_texto($datos, 'direccion', 3000);
    if ($nombre === '') throw new InvalidArgumentException('Escribe el nombre del cliente.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Revisa el correo electrónico.');
    return [$nombre, $telefono, $email, $direccion];
}
function oj_clientes_id($valor): int {
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new InvalidArgumentException('Identificador de cliente no válido.');
    return (int)$id;
}
function oj_clientes_patron(string $q): string { return '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $q) . '%'; }

function oj_clientes_normalizar_texto(string $texto): string {
    $texto = trim(preg_replace('/\s+/u', ' ', $texto));
    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
}
function oj_clientes_normalizar_telefono(string $telefono): string {
    $digitos = preg_replace('/[^0-9]/', '', $telefono);
    if (strlen($digitos) === 12 && substr($digitos, 0, 2) === '52') $digitos = substr($digitos, 2);
    elseif (strlen($digitos) === 13 && substr($digitos, 0, 3) === '521') $digitos = substr($digitos, 3);
    return $digitos;
}
function oj_clientes_coinciden(array $a, array $b): bool {
    if (oj_clientes_normalizar_texto($a['nombre']) !== oj_clientes_normalizar_texto($b['nombre'])) return false;
    $telA = oj_clientes_normalizar_telefono($a['telefono']);
    $telB = oj_clientes_normalizar_telefono($b['telefono']);
    $mailA = oj_clientes_normalizar_texto($a['email']);
    $mailB = oj_clientes_normalizar_texto($b['email']);
    if ($telA !== '' && $telA === $telB) return true;
    if ($mailA !== '' && $mailA === $mailB) return true;
    // Sin contactos no se confunden homónimos con direcciones distintas.
    return $telA === '' && $telB === '' && $mailA === '' && $mailB === ''
        && oj_clientes_normalizar_texto($a['direccion']) === oj_clientes_normalizar_texto($b['direccion']);
}
function oj_clientes_guardar_unico(PDO $pdo, int $usuario, array $datos, ?int $id = null, ?int $version = null): array {
    $pdo->beginTransaction();
    try {
        // Compartido por ambas rutas: serializa las altas/ediciones de una cuenta.
        $lock = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE');
        $lock->execute([$usuario]);
        if (!$lock->fetchColumn()) throw new RuntimeException('El usuario ya no está disponible.');
        if ($id !== null) {
            $actual = $pdo->prepare('SELECT id, version, nombre, telefono, email, direccion FROM cotizador_clientes WHERE id = ? AND usuario_id = ? FOR UPDATE');
            $actual->execute([$id, $usuario]); $filaActual = $actual->fetch(PDO::FETCH_ASSOC);
            if (!$filaActual || (int)$filaActual['version'] !== $version) throw new RuntimeException('La ficha cambió en otra pestaña o no está disponible para tu usuario. Abre su edición actual para continuar.');
        }
        $candidato = ['nombre' => $datos[0], 'telefono' => $datos[1], 'email' => $datos[2], 'direccion' => $datos[3]];
        $stmt = $pdo->prepare('SELECT id, nombre, telefono, email, direccion, activo FROM cotizador_clientes WHERE usuario_id = ? ORDER BY activo DESC, id ASC');
        $stmt->execute([$usuario]);
        $coincidencia = null;
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($id !== null && (int)$fila['id'] === $id) continue;
            if (oj_clientes_coinciden($candidato, $fila)) {
                // Permitir corregir una ficha ya duplicada antes de este parche.
                if ($id !== null && oj_clientes_coinciden($filaActual, $fila)) continue;
                $coincidencia = $fila; break;
            }
        }
        $stmt->closeCursor();
        if ($coincidencia) {
            $pdo->commit();
            return ['estado' => (int)$coincidencia['activo'] ? 'existente' : 'inactivo', 'id' => (int)$coincidencia['id'], 'nombre' => $coincidencia['nombre']];
        }
        if ($id !== null) {
            $guardar = $pdo->prepare('UPDATE cotizador_clientes SET nombre = ?, telefono = ?, email = ?, direccion = ?, version = version + 1, actualizado_en = NOW() WHERE id = ? AND usuario_id = ? AND version = ?');
            $guardar->execute(array_merge($datos, [$id, $usuario, $version]));
            if ($guardar->rowCount() !== 1) throw new RuntimeException('No se pudo actualizar la ficha. Revisa su versión actual.');
            $estado = 'actualizado';
        } else {
            $guardar = $pdo->prepare('INSERT INTO cotizador_clientes (nombre, telefono, email, direccion, usuario_id, creado_en, actualizado_en) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
            $guardar->execute(array_merge($datos, [$usuario]));
            $id = (int)$pdo->lastInsertId(); $estado = 'creado';
        }
        $pdo->commit();
        return ['estado' => $estado, 'id' => $id, 'nombre' => $datos[0]];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
