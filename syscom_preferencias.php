<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/app/syscom_preferencias.php';
require_post_csrf();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    ojsp_schema($pdo);$usuario=(int)$_SESSION['usuario_id'];$accion=$_POST['accion']??'';
    if (!is_string($accion)||!in_array($accion,['leer','guardar'],true)) throw new InvalidArgumentException('Acción no válida.');
    if ($accion==='guardar') {
        $raw=$_POST['version']??null;
        if (!is_string($raw)) throw new InvalidArgumentException('Versión no válida.');
        $version=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        if ($version===false) throw new InvalidArgumentException('Versión no válida.');
        ojsp_guardar($pdo,$usuario,$version,ojsp_datos($_POST));
    }
    echo json_encode(['preferencias'=>ojsp_leer($pdo,$usuario)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof PDOException?500:($e->getCode()===409?409:422));
    if ($e instanceof PDOException) error_log('Parámetros SYSCOM: '.$e->getMessage());
    $mensaje=$e instanceof PDOException?'No se pudo acceder a tus parámetros. Revisa la tabla de preferencias o usa los campos manualmente.':$e->getMessage();
    echo json_encode(['error'=>$mensaje],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}
