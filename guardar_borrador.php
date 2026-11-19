<?php
require_once __DIR__ . '/auth.php';
require_post_csrf();
require_once __DIR__ . '/app/borradores.php';
try {
    oj_borradores_schema($pdo);
    $id = oj_borrador_id($_POST['borrador_id'] ?? null);
    $datos = oj_borrador_datos($_POST);
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $titulo = trim($datos['proyecto']) ?: (trim($datos['cliente_nombre']) ?: 'Propuesta sin título');
    $titulo = function_exists('mb_substr') ? mb_substr($titulo, 0, 180, 'UTF-8') : substr($titulo, 0, 180);
    $usuario = (int)$_SESSION['usuario_id'];
    if ($id) {
        $version = oj_borrador_id($_POST['borrador_version'] ?? null);
        $stmt = $pdo->prepare('UPDATE cotizador_borradores SET titulo = ?, datos = ?, version = version + 1, actualizado_en = NOW() WHERE id = ? AND usuario_id = ? AND version = ? AND documento_id IS NULL');
        $stmt->execute([$titulo, $json, $id, $usuario, $version]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('El borrador cambió en otra pestaña o ya generó un documento. Abre su versión actual desde Borradores antes de continuar.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO cotizador_borradores (usuario_id, titulo, datos, creado_en, actualizado_en) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([$usuario, $titulo, $json]);
        $id = (int)$pdo->lastInsertId();
    }
    header('Location: index.php?borrador_id=' . $id . '&guardado=1', true, 303);
    exit;
} catch (Throwable $e) { oj_borrador_error($e); }
