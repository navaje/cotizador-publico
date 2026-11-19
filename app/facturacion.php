<?php
require_once __DIR__.'/recibos.php';
function ojf_admin(): void {if(($_SESSION['usuario_rol']??'')!=='admin'){http_response_code(403);exit('Solo el administrador puede facturar.');}}
function ojf_config(): array {$p=__DIR__.'/../facturacion/config.php';return is_file($p)?require $p:[];}
function ojf_schema(PDO $pdo): void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS oj_facturas_prueba (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, raiz_id INT NULL UNIQUE, documento_id INT NULL,
 estado VARCHAR(20) NOT NULL, source_hash CHAR(64) NOT NULL, solicitud LONGTEXT NOT NULL, respuesta LONGTEXT NULL,
 uuid VARCHAR(36) NULL, pdf_url TEXT NULL, xml LONGTEXT NULL, creado_por INT NOT NULL,
 creado_en DATETIME NOT NULL, enviado_en DATETIME NULL
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 // Permite facturas independientes; conserva IDs, índices y facturas existentes.
 $columns=$pdo->query('SHOW COLUMNS FROM oj_facturas_prueba')->fetchAll(PDO::FETCH_ASSOC);
 foreach($columns as $column){
  if(in_array($column['Field'],['raiz_id','documento_id'],true) && $column['Null']==='NO'){
   $pdo->exec('ALTER TABLE oj_facturas_prueba MODIFY '.$column['Field'].' INT NULL');
  }
 }
}
function ojf_request(string $endpoint,?array $payload=null): array {
 $config=ojf_config();$key=$config['api_key']??'';
 if(!$key || preg_match('/[\r\n]/',$key))throw new RuntimeException('Configura la clave API de pruebas.');
 if(!function_exists('curl_init'))throw new RuntimeException('El servidor necesita la extensión PHP cURL.');
 if(!in_array($endpoint,['account/balance','invoice/create'],true))throw new RuntimeException('Operación no admitida.');
 $c=curl_init('https://sandbox-app.facturadigital.com.mx/api/v5/'.$endpoint);
 $headers=['X-Api-Key: '.$key,'Accept: application/json'];$raw='';
 curl_setopt_array($c,[CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>50,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>$headers,CURLOPT_WRITEFUNCTION=>static function($c,$chunk)use(&$raw){if(strlen($raw)+strlen($chunk)>3000000)return 0;$raw.=$chunk;return strlen($chunk);}]);
 if($payload!==null){$headers[]='Content-Type: application/x-www-form-urlencoded';curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]),CURLOPT_HTTPHEADER=>$headers]);}
 $ok=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 if($ok===false)throw new RuntimeException('No se pudo confirmar la respuesta del proveedor.');
 try{$data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('Respuesta del proveedor no reconocida.');}
 if(!is_array($data))throw new RuntimeException('Respuesta inválida.');
 return ['http'=>$status,'data'=>$data];
}
function ojf_payload(array $doc,array $items,array $input): array {
 $rfc=ojf_upper(ojr_text($input,'rfc',13,true));$name=ojf_upper(ojr_text($input,'nombre',254,true));
 if(!preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/uD',$rfc) || in_array($rfc,['XAXX010101000','XEXX010101000'],true))throw new InvalidArgumentException('Esta primera versión requiere RFC individual del cliente; no admite factura global ni extranjeros.');
 $cp=ojr_text($input,'cp',5,true);$reg=ojr_text($input,'regimen',3,true);$use=ojf_upper(ojr_text($input,'uso',4,true));
 if(!preg_match('/^\d{5}$/D',$cp) || !preg_match('/^\d{3}$/D',$reg) || !preg_match('/^[A-Z][0-9]{2}$/D',$use))throw new InvalidArgumentException('Revisa código postal, régimen y uso CFDI del receptor.');
 $method=ojf_upper(ojr_text($input,'metodo',3,true));$form=ojr_text($input,'forma',2,true);
 if(!in_array($method,['PUE','PPD'],true) || !in_array($form,['01','02','03','04','05','06','08','12','13','14','15','17','23','24','25','26','27','28','29','30','31','99'],true))throw new InvalidArgumentException('Revisa método y forma de pago.');
 if(($method==='PPD' && $form!=='99') || ($method==='PUE' && ($form==='99' || ($input['pagado']??'')!=='1')))throw new InvalidArgumentException('PPD requiere forma 99. Para PUE confirma pago completo y la forma real de pago.');
 if(($input['sin_retenciones']??'')!=='1')throw new InvalidArgumentException('Confirma que esta operación no requiere retenciones ni impuestos distintos al IVA 16%.');
 if(!$items || count($items)>100)throw new InvalidArgumentException('El documento necesita entre 1 y 100 conceptos.');
 $concepts=[];$sum=0;$tax=0;
 foreach($items as $i=>$item){
  $key=trim((string)($input['clave'][$i]??''));$unit=ojf_upper(trim((string)($input['unidad'][$i]??'')));
  if(!preg_match('/^\d{8}$/D',$key) || $key==='01010101' || !preg_match('/^[A-Z0-9]{2,3}$/D',$unit))throw new InvalidArgumentException('Selecciona claves SAT específicas de producto/servicio y unidad para cada concepto.');
  $qty=rtrim(rtrim(number_format((float)$item['cantidad'],3,'.',''),'0'),'.');
  if(!preg_match('/^[0-9]{1,5}(?:\.[0-9]{1,3})?$/D',$qty) || (float)$qty<=0 || abs((float)$qty-(float)$item['cantidad'])>0.0000001)throw new InvalidArgumentException('Cantidad no compatible; máximo tres decimales.');
  $price=ojr_centavos($item['precio']);$base=(int)round((float)$qty*$price);
  if($base<=0 || $base!==ojr_centavos($item['importe']))throw new InvalidArgumentException('Los importes guardados no coinciden con cantidad por precio. Revisa el documento.');
  $vat=(int)round($base*16/100);$sum+=$base;$tax+=$vat;
  $concepts[]=['ClaveProdServ'=>$key,'ClaveUnidad'=>$unit,'Cantidad'=>$qty,'Descripcion'=>ojr_text($item,'concepto',1000,true),'ValorUnitario'=>ojr_decimal($price),'Importe'=>ojr_decimal($base),'ObjetoImp'=>'02','Impuestos'=>['Traslados'=>[['Base'=>ojr_decimal($base),'Impuesto'=>'002','TipoFactor'=>'Tasa','TasaOCuota'=>'0.160000','Importe'=>ojr_decimal($vat)]]]];
 }
 if($sum!==ojr_centavos($doc['subtotal']) || $tax!==ojr_centavos($doc['iva']) || $sum+$tax!==ojr_centavos($doc['total']))throw new InvalidArgumentException('Subtotal, IVA por concepto y total deben coincidir exactamente con el documento. Esta versión no admite descuentos ni otros impuestos.');
 return ['Version'=>'4.0','Serie'=>'OJPRUEBA','Fecha'=>'AUTO','FormaPago'=>$form,'SubTotal'=>ojr_decimal($sum),'Moneda'=>'MXN','Total'=>ojr_decimal($sum+$tax),'TipoDeComprobante'=>'I','MetodoPago'=>$method,'LugarExpedicion'=>'91963','Exportacion'=>'01','Emisor'=>['Rfc'=>'NAVJ710906LY0','Nombre'=>'JESUS NAVARRO VALENTIN','RegimenFiscal'=>'612'],'Receptor'=>['Rfc'=>$rfc,'Nombre'=>$name,'UsoCFDI'=>$use,'DomicilioFiscalReceptor'=>$cp,'RegimenFiscalReceptor'=>$reg],'Conceptos'=>$concepts,'Impuestos'=>['TotalImpuestosTrasladados'=>ojr_decimal($tax),'Traslados'=>[['Base'=>ojr_decimal($sum),'Impuesto'=>'002','TipoFactor'=>'Tasa','TasaOCuota'=>'0.160000','Importe'=>ojr_decimal($tax)]]]];
}
function ojf_result(array $data,array $payload): array {
 $cfdi=$data['cfdi']??[];$uuid=(string)($cfdi['UUID']??'');$xml=base64_decode((string)($cfdi['XmlBase64']??''),true);
 if(($data['code']??0)!=200 || !preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD',$uuid) || !$xml || strlen($xml)>2000000 || stripos($xml,'<!DOCTYPE')!==false || stripos($xml,'<!ENTITY')!==false)throw new RuntimeException('El proveedor no devolvió un CFDI verificable. Revisa su portal antes de intentar otra emisión.');
 if(!class_exists('DOMDocument'))throw new RuntimeException('Se requiere PHP DOM.');
 $dom=new DOMDocument();$old=libxml_use_internal_errors(true);try{$loaded=$dom->loadXML($xml,LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
 if(!$loaded)throw new RuntimeException('XML inválido.');
 $x=new DOMXPath($dom);$x->registerNamespace('c','http://www.sat.gob.mx/cfd/4');$x->registerNamespace('t','http://www.sat.gob.mx/TimbreFiscalDigital');
 $checks=['/c:Comprobante/@Version'=>'4.0','/c:Comprobante/@TipoDeComprobante'=>'I','/c:Comprobante/@Moneda'=>'MXN','/c:Comprobante/@Total'=>$payload['Total'],'/c:Comprobante/@Serie'=>$payload['Serie'],'/c:Comprobante/@Folio'=>$payload['Folio'],'/c:Comprobante/c:Emisor/@Rfc'=>'NAVJ710906LY0','/c:Comprobante/c:Emisor/@RegimenFiscal'=>'612','/c:Comprobante/c:Receptor/@Rfc'=>$payload['Receptor']['Rfc'],'/c:Comprobante/c:Complemento/t:TimbreFiscalDigital/@UUID'=>$uuid];
 foreach($checks as $path=>$expected)if(strcasecmp($x->evaluate('string('.$path.')'),$expected)!==0)throw new RuntimeException('Los datos del XML no coinciden con la solicitud. Revisa el portal del proveedor.');
 $pdf=(string)($cfdi['PDF']??'');$u=parse_url($pdf);
 if(($u['scheme']??'')!=='https' || ($u['host']??'')!=='sandbox-app.facturadigital.com.mx' || isset($u['user']) || isset($u['port']) || strcasecmp((string)($u['path']??''),'/docs/pdf/'.$uuid)!==0)$pdf='';
 return ['uuid'=>$uuid,'xml'=>$xml,'pdf'=>$pdf];
}

function ojf_source_hash(array $doc,array $items): string {
 return hash('sha256',json_encode([$doc['subtotal'],$doc['iva'],$doc['total'],$items],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}

function ojf_upper(string $value): string {return mb_strtoupper(trim($value),'UTF-8');}
function ojf_direct_document(array $input): array {
 $descriptions=$input['concepto']??[];
 if(!is_array($descriptions) || !$descriptions || count($descriptions)>100)throw new InvalidArgumentException('Captura entre 1 y 100 conceptos.');
 $items=[];$sum=0;$tax=0;
 foreach(array_values($descriptions) as $i=>$description){
  $qty=ojr_text(['cantidad'=>$input['cantidad'][$i]??''],'cantidad',10,true);
  if(!preg_match('/^[0-9]{1,5}(?:\.[0-9]{1,3})?$/D',$qty) || (float)$qty<=0)throw new InvalidArgumentException('La cantidad debe ser positiva y tener máximo tres decimales.');
  $priceText=ojr_text(['precio'=>$input['precio'][$i]??''],'precio',15,true);
  if(!preg_match('/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D',$priceText))throw new InvalidArgumentException('El precio debe tener máximo dos decimales, sin símbolos ni separadores de miles.');
  $price=ojr_centavos($priceText);$base=(int)round((float)$qty*$price);
  if($price<=0 || $base<=0)throw new InvalidArgumentException('El precio y el importe deben ser positivos.');
  $vat=(int)round($base*16/100);$sum+=$base;$tax+=$vat;
  $items[]=['concepto'=>ojr_text(['concepto'=>$description],'concepto',1000,true),'cantidad'=>$qty,'precio'=>ojr_decimal($price),'importe'=>ojr_decimal($base)];
 }
 return [['subtotal'=>ojr_decimal($sum),'iva'=>ojr_decimal($tax),'total'=>ojr_decimal($sum+$tax)],$items];
}
// Solo un rechazo fiscal explícito permite corregir y volver a enviar.
function ojf_rejection(?array $response): string {
 if(!$response || ($response['http']??0)!==400 || (int)($response['data']['code']??0)!==400 || !empty($response['data']['cfdi']['UUID']))return '';
 $message=$response['data']['message']??'';
 if(!is_string($message) || !preg_match('/^CFDI[0-9]{5}\b/',$message))return '';
 $key=ojf_config()['api_key']??'';
 if($key!=='')$message=str_replace($key,'[clave omitida]',$message);
 return mb_substr($message,0,1500,'UTF-8');
}
