<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/app/syscom_conexion.php';
header('Cache-Control: no-store');
$errores=[];$mensaje='';$resumen=null;$registro=null;$usuario=(int)$_SESSION['usuario_id'];$producto='240039';
if($_SERVER['REQUEST_METHOD']==='POST')require_post_csrf();
try{
    ojso_schema($pdo);$registro=ojso_registro($pdo,$usuario);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $accion=$_POST['accion']??null;
        if($accion==='guardar'){
            $client=$_POST['client_id']??null;$secret=$_POST['client_secret']??null;$version=$_POST['version']??null;
            if(!is_string($client)||!is_string($secret)||!is_string($version))throw new InvalidArgumentException('Revisa las credenciales y vuelve a intentarlo.');
            $client=trim($client);$secret=trim($secret);
            if($client===''||strlen($client)>512||preg_match('/[\x00-\x20\x7f]/',$client)||$secret===''||strlen($secret)>4096||preg_match('/[\x00-\x20\x7f]/',$secret))throw new InvalidArgumentException('Captura client_id y client_secret de tu aplicación SYSCOM, completos y sin espacios.');
            $v=filter_var($version,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);if($v===false)throw new InvalidArgumentException('Versión no válida. Recarga la página.');
            $token=ojso_nuevo_token($client,$secret);
            $categorias=ojso_http('categorias',[],$token['valor']);
            $hayConexiones=(bool)$pdo->query('SELECT usuario_id FROM cotizador_syscom_conexion LIMIT 1')->fetchColumn();
            $clave=ojso_clave(!$hayConexiones);
            ojso_guardar($pdo,$usuario,$v,ojso_cifrar(['id'=>$client,'secret'=>$secret],$clave,$usuario,'credenciales'),ojso_cifrar(['valor'=>$token['valor']],$clave,$usuario,'token'),$token['hasta']);
            unset($secret,$client,$token,$clave);
            header('Location: syscom_conexion.php?guardado=1',true,303);exit;
        }elseif($accion==='producto'){
            $raw=$_POST['producto']??null;
            if(!is_string($raw)||!preg_match('/^[1-9]\d{0,8}$/D',$raw))throw new InvalidArgumentException('Captura el ID numérico del producto en SYSCOM.');
            $producto=$raw;$token=ojso_token($pdo,$usuario);
            try{$r=ojso_http('productos/'.$producto,['moneda'=>'usd','iva'=>'false','financiero'=>'false','inventarios'=>'true'],$token);}
            catch(RuntimeException $e){if($e->getCode()!==401)throw $e;$token=ojso_token($pdo,$usuario,true);$r=ojso_http('productos/'.$producto,['moneda'=>'usd','iva'=>'false','financiero'=>'false','inventarios'=>'true'],$token);}
            $resumen=json_encode(ojso_resumen_producto($r),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
            $resumen=str_replace($token,'[TOKEN OCULTO]',$resumen);unset($token);
            if(strlen($resumen)>40000)$resumen=substr($resumen,0,40000)."\n… Respuesta truncada para revisión.";
            $mensaje='Producto consultado correctamente en SYSCOM. Revisa abajo los campos de precio y existencia recibidos.';
        }elseif($accion==='desconectar'){
            $v=filter_var($_POST['version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if($v===false)throw new InvalidArgumentException('Recarga antes de desconectar.');
            $stmt=$pdo->prepare('DELETE FROM cotizador_syscom_conexion WHERE usuario_id=? AND version=?');$stmt->execute([$usuario,$v]);
            if($stmt->rowCount()!==1)throw new RuntimeException('La conexión cambió en otra pestaña. Recarga antes de desconectar.',409);
            header('Location: syscom_conexion.php?desconectado=1',true,303);exit;
        }else throw new InvalidArgumentException('Acción no válida.');
        $registro=ojso_registro($pdo,$usuario);
    }
}catch(Throwable $e){
    http_response_code($e->getCode()===409?409:($e instanceof PDOException?500:422));
    if($e instanceof PDOException){error_log('Conexión SYSCOM: error de base de datos.');$errores[]='No se pudo acceder a la conexión. Revisa la instalación de la tabla en el hosting.';}
    elseif($e instanceof JsonException)$errores[]='La respuesta de SYSCOM no tiene un formato JSON válido.';
    else $errores[]=$e->getMessage();
}
if(!$errores&&$registro&&isset($_GET['guardado']))$mensaje='Conexión guardada: SYSCOM emitió un token de acceso y respondió a la consulta de categorías.';
if(!$errores&&!$registro&&isset($_GET['desconectado']))$mensaje='Conexión eliminada de este usuario. Si deseas revocar el token en SYSCOM, hazlo desde su panel de credenciales.';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conectar API SYSCOM · Ojo Jarocho</title><link rel="stylesheet" href="assets/syscom.css?v=20261018-512"><style>.ojso-exito{background:#dcfce7;padding:14px;border-radius:8px}.ojso-error{background:#fee2e2;padding:14px;border-radius:8px}.ojso-respuesta{max-height:600px;overflow:auto;white-space:pre-wrap;overflow-wrap:anywhere;background:#f1f5f9;padding:14px;border-radius:8px}.ojso-danger{background:#b91c1c}.ojso-form{margin-top:20px;border-top:1px solid #dbe7f3;padding-top:16px}</style></head>
<body class="syscom-pagina"><main class="syscom-panel"><h1>Conectar API SYSCOM</h1>
<nav class="syscom-acciones"><a class="syscom-btn" href="dashboard.php">Dashboard</a><a class="syscom-btn" href="index.php#syscomCotizador">Buscar productos</a><a class="syscom-btn" href="syscom.php">Catálogo CSV</a></nav>
<p>Conecta tu cuenta para comprobar el acceso al catálogo en línea. Esta primera etapa valida las credenciales y la respuesta de productos; el buscador del cotizador sigue utilizando tu CSV hasta completar la integración.</p>
<?php foreach($errores as $error):?><p class="ojso-error" role="alert"><?=ojso_e($error)?></p><?php endforeach;?>
<?php if($mensaje):?><p class="ojso-exito" role="status"><?=ojso_e($mensaje)?></p><?php endif;?>
<p><strong>Estado de este usuario: <?=$registro?'credenciales guardadas':'sin configurar'?>.</strong></p>
<?php if($registro):?><p class="syscom-ayuda">Último guardado (UTC): <?=ojso_e($registro['actualizado_en'])?>. Las credenciales no se vuelven a mostrar en pantalla.</p><?php endif;?>
<h2><?=$registro?'Cambiar credenciales':'Configurar credenciales'?></h2><p>Obtén client_id y client_secret creando una aplicación en <a href="https://developers.syscom.mx/guide" target="_blank" rel="noopener">SYSCOM Developers</a>. Usa acceso de catálogo con permiso <strong>ver-productos</strong>. Introduce ambos datos aquí; no uses tu contraseña de acceso a SYSCOM.</p>
<form method="post" action="syscom_conexion.php" autocomplete="off">
<?=csrf_field()?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="version" value="<?=$registro?(int)$registro['version']:0?>">
<label for="ojsoClient">Client ID</label><input type="text" name="client_id" id="ojsoClient" maxlength="512" autocomplete="off" spellcheck="false" required>
<label for="ojsoSecret">Client secret</label><input type="password" name="client_secret" id="ojsoSecret" maxlength="4096" autocomplete="new-password" spellcheck="false" required>
<p class="syscom-ayuda">Se guardan cifrados en el servidor para tu usuario. Mantén un respaldo completo de la cuenta de hosting: incluye la base de datos y la clave de cifrado que se crea fuera de public_html.</p>
<button class="syscom-btn" type="submit">Guardar y comprobar conexión</button></form>
<?php if($registro):?>
<form method="post" action="syscom_conexion.php" class="ojso-form">
<?=csrf_field()?><input type="hidden" name="accion" value="producto">
<h2>Comprobar un producto</h2><label for="ojsoProducto">ID numérico del producto</label><input id="ojsoProducto" type="text" name="producto" value="<?=ojso_e($producto)?>" pattern="[1-9][0-9]{0,8}" inputmode="numeric" required>
<p class="syscom-ayuda">Para el modelo IDS-7204HQHI-M1/T del archivo anterior, prueba el ID 240039. Se solicita precio USD sin IVA y existencias detalladas. El precio actual puede ser distinto al de tu descarga.</p>
<button class="syscom-btn" type="submit">Consultar producto en la API</button></form>
<?php endif;?>
<?php if($resumen!==null):?><h2>Respuesta de producto para revisión</h2><p>Esta respuesta permitirá identificar el campo exacto del costo y la existencia Veracruz antes de usarlos automáticamente al cotizar. Puedes compartir una captura de esta sección.</p><pre class="ojso-respuesta"><?=ojso_e($resumen)?></pre><?php endif;?>
<?php if($registro):?><form method="post" action="syscom_conexion.php" class="ojso-form"><?=csrf_field()?><input type="hidden" name="accion" value="desconectar"><input type="hidden" name="version" value="<?=(int)$registro['version']?>"><button class="syscom-btn ojso-danger" type="submit">Eliminar conexión de mi usuario</button><p class="syscom-ayuda">Conserva el catálogo CSV y tus documentos. El token se revoca por separado desde tu cuenta SYSCOM.</p></form><?php endif;?>
</main><script src="assets/syscom_conexion.js?v=20261019-514"></script></body></html>
