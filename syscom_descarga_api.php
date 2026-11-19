<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/app/syscom.php';
require_once __DIR__.'/app/syscom_conexion.php';
require_once __DIR__.'/app/syscom_descarga.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require_post_csrf();$usuario=(int)$_SESSION['usuario_id'];session_write_close();
$accion=$_POST['accion']??null;$version=null;
try{
    ojsc_schema($pdo);ojau_schema($pdo);
    if(!is_string($accion)||!in_array($accion,['estado','guardar','iniciar','procesar','cancelar'],true))throw new InvalidArgumentException('Acción no disponible.');
    if($accion!=='estado'){
        $raw=$_POST['version']??null;if(!is_string($raw))throw new InvalidArgumentException('Revisa el estado de la actualización.');
        $version=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);if($version===false)throw new InvalidArgumentException('Versión no válida. Recarga el estado.');
    }
    if($accion==='guardar')ojau_guardar($pdo,$usuario,$version,$_POST['enlace']??null);
    if($accion==='iniciar'){
        if(($_POST['confirmar']??null)!=='1')throw new InvalidArgumentException('Confirma que Su Precio está en USD sin IVA antes de actualizar.');
        @set_time_limit(75);ojau_descargar($pdo,$usuario,$version);
    }
    if($accion==='procesar')ojau_procesar($pdo,$usuario,$version);
    if($accion==='cancelar')ojau_cancelar($pdo,$usuario,$version);
    echo json_encode(ojau_publico($pdo,$usuario),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    $codigo=(int)$e->getCode();
    if($e instanceof PDOException){$codigo=500;$mensaje=(int)($e->errorInfo[1]??0)===1062?'El CSV repite un ID de producto. Cancela esta actualización y revisa el reporte.':'No se pudo guardar la actualización. Revisa las tablas y el registro PHP del hosting.';error_log('Descarga CSV SYSCOM: error de base de datos.');}
    elseif($e instanceof JsonException){$codigo=422;$mensaje='No se pudo interpretar la configuración del CSV. Cancela la actualización y revisa la descarga.';}
    elseif($e instanceof RuntimeException||$e instanceof InvalidArgumentException){$mensaje=$e->getMessage();$codigo=in_array($codigo,[409,429],true)?$codigo:422;}
    else{$codigo=500;$mensaje='No se pudo completar la actualización. Revisa el registro PHP del hosting.';error_log('Descarga CSV SYSCOM: error interno.');}
    if($accion==='procesar'&&is_int($version)&&$codigo!==409){try{ojau_pausar($pdo,$usuario,$version,$mensaje);}catch(Throwable $ignorado){error_log('CSV SYSCOM: no se pudo registrar la pausa.');}}
    $salida=['error'=>$mensaje];try{$salida+=ojau_publico($pdo,$usuario);}catch(Throwable $ignorado){}
    http_response_code($codigo);echo json_encode($salida,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}
