<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/app/facturacion.php';ojf_admin();header('Cache-Control: no-store');$error='';$notice='';
try {
 if($_SERVER['REQUEST_METHOD']==='POST'){
 require_post_csrf();$action=ojr_text($_POST,'accion',20,true);
 if($action==='guardar'){
  $key=ojr_text($_POST,'api_key',2048,true);if(preg_match('/[\r\n]/',$key))throw new InvalidArgumentException('Clave inválida.');
  if(!is_file(__DIR__.'/facturacion/.htaccess'))throw new RuntimeException('Falta la protección de la carpeta facturacion.');
  $path=__DIR__.'/facturacion/config.php';$tmp=tempnam(dirname($path),'ojf');if(!$tmp)throw new RuntimeException('No se pudo guardar.');
  try {chmod($tmp,0600);$content="<?php\nreturn ".var_export(['api_key'=>$key],true).";\n";if(file_put_contents($tmp,$content,LOCK_EX)!==strlen($content) || !rename($tmp,$path))throw new RuntimeException('No se pudo guardar la configuración.');}finally{if(is_file($tmp))unlink($tmp);}
  $notice='Clave de pruebas guardada. Puedes comprobar la conexión.';
 }elseif($action==='probar'){
  $r=ojf_request('account/balance');if($r['http']!==200 || ($r['data']['code']??0)!=200 || !isset($r['data']['balance']) || !is_numeric($r['data']['balance']))throw new RuntimeException('La conexión no devolvió un saldo válido. Revisa la clave del entorno de pruebas.');
  $notice='Conexión de pruebas correcta. Folios disponibles: '.(int)$r['data']['balance'].'.';
 }else throw new InvalidArgumentException('Acción inválida.');
 }
}catch(Throwable $e){$error=$e->getMessage();}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conexión de facturación</title><link rel="stylesheet" href="assets/bancos.css"></head><body><main><nav><a href="dashboard.php">Panel</a> · <a href="facturas.php">Facturas de prueba</a></nav><h1>Conectar Factura Digital — pruebas</h1><p>Este módulo solo se conecta al entorno de pruebas. No emite facturas fiscales reales.</p><p>Emisor: JESUS NAVARRO VALENTIN · NAVJ710906LY0 · Régimen 612 · CP 91963.</p>
<?php if($error):?><p class="error"><?=ojr_e($error)?></p><?php endif;?><?php if($notice):?><p><?=ojr_e($notice)?></p><?php endif;?>
<p>En el portal de pruebas de Factura Digital, ve a Configuración → Datos fiscales y copia la API Key. Configura el CSD directamente con el proveedor.</p><form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="guardar"><label>API Key del entorno de pruebas<input name="api_key" type="password" autocomplete="new-password" required maxlength="2048"></label><button>Guardar clave de pruebas</button></form><p><?=empty(ojf_config()['api_key'])?'Sin clave configurada.':'Clave configurada; no se muestra en pantalla.'?></p><form method="post"><?=csrf_field()?><input type="hidden" name="accion" value="probar"><button>Comprobar conexión y saldo</button></form></main></body></html>
