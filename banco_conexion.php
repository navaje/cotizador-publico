<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/app/bancos.php';ojb_admin();$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
 require_post_csrf();
 try {
  $path=__DIR__.'/bancos/config.php';
  if(is_file($path))throw new InvalidArgumentException('La conexión ya tiene clave. Para cambiarla, modifica config.php y las propiedades del proyecto Google.');
  if(!is_file(__DIR__.'/bancos/.htaccess'))throw new RuntimeException('Falta la protección de la carpeta bancos.');
  $config=['secreto'=>bin2hex(random_bytes(32)),'correo'=>'tu-correo@gmail.com','cuenta'=>'0000'];
  $handle=fopen($path,'x');if(!$handle)throw new RuntimeException('No se pudo crear bancos/config.php. Usa config.ejemplo.php y una clave aleatoria de 64 caracteres hexadecimales.');
  chmod($path,0600);$content="<?php\nreturn ".var_export($config,true).";\n";
  if(fwrite($handle,$content)!==strlen($content)){fclose($handle);unlink($path);throw new RuntimeException('No se pudo guardar la configuración.');}fclose($handle);
  header('Location: banco_conexion.php');exit;
 } catch(Throwable $e){$error=$e->getMessage();}
}
$config=ojb_config();header('Cache-Control: no-store');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conectar Gmail — Ojo Jarocho</title><link rel="stylesheet" href="assets/bancos.css"></head><body><main><nav><a href="banco_movimientos.php">Movimientos</a> · <a href="dashboard.php">Panel</a></nav><h1>Conectar avisos de Gmail</h1><p>Cuenta tu-correo@gmail.com · Banorte terminación 0000.</p><?php if($error):?><p class="error"><?=ojr_e($error)?></p><?php endif;?>
<?php if(empty($config['secreto'])):?><form method="post"><?=csrf_field()?><p>Genera la clave privada que conectará tu proyecto Google con el cotizador.</p><button>Crear clave de conexión</button></form><?php else:?>
<p>En las propiedades del proyecto de Google Apps Script agrega estos dos valores. La clave da acceso al receptor de movimientos; consérvala en privado.</p><label>OJ_URL<input readonly value="https://ojojarocho.online/cotizador/banco_receptor.php"></label><label>OJ_SECRETO<input readonly value="<?=ojr_e($config['secreto'])?>"></label><p>Sigue los pasos de INSTALAR_BANCOS.md. La clave creada no activa la lectura: también debes autorizar el proyecto en Google y crear su activador.</p>
<?php endif;?></main></body></html>
