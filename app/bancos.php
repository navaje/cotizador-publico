<?php
require_once __DIR__.'/recibos.php';
function ojb_admin(): void {
    if (($_SESSION['usuario_rol'] ?? '') !== 'admin') { http_response_code(403); exit('Solo el administrador puede consultar la cuenta mixta.'); }
}
function ojb_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS oj_banco_eventos (
      huella CHAR(64) PRIMARY KEY, datos TEXT NOT NULL, correo_id VARCHAR(190) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS oj_banco_movimientos (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      huella CHAR(64) NOT NULL UNIQUE, correo_id VARCHAR(190) NOT NULL,
      tipo VARCHAR(12) NOT NULL, operacion VARCHAR(30) NOT NULL,
      cuenta VARCHAR(4) NOT NULL, importe DECIMAL(12,2) NOT NULL,
      comision DECIMAL(12,2) NOT NULL DEFAULT 0, fecha DATETIME NOT NULL,
      fecha_aplicacion DATE NULL, descripcion VARCHAR(500) NOT NULL,
      referencia VARCHAR(160) NOT NULL, clasificacion VARCHAR(12) NOT NULL DEFAULT 'pendiente',
      categoria VARCHAR(100) NOT NULL DEFAULT '', notas VARCHAR(1000) NOT NULL DEFAULT '',
      documento_id INT NULL, recibo_id INT NULL UNIQUE,
      origen VARCHAR(20) NOT NULL, creado_en DATETIME NOT NULL,
      actualizado_en DATETIME NULL, actualizado_por VARCHAR(190) NULL,
      INDEX(fecha), INDEX(clasificacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS oj_banco_auditoria (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, movimiento_id BIGINT UNSIGNED NOT NULL,
      accion VARCHAR(30) NOT NULL, detalle TEXT NOT NULL, autor VARCHAR(190) NOT NULL,
      fecha DATETIME NOT NULL, INDEX(movimiento_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function ojb_audit(PDO $pdo,int $id,string $action,array $detail,string $author): void {
    $q=$pdo->prepare('INSERT INTO oj_banco_auditoria (movimiento_id,accion,detalle,autor,fecha) VALUES (?,?,?,?,?)');
    $q->execute([$id,$action,json_encode($detail,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$author,date('Y-m-d H:i:s')]);
}
function ojb_plain(string $body): string {
    $body=preg_replace('~<(style|script)\b[^>]*>.*?</\1>~is',' ',$body);
    $body=preg_replace('~<[^>]+>~s',' ',$body);
    $body=html_entity_decode($body,ENT_QUOTES|ENT_HTML5,'UTF-8');
    return trim(preg_replace('/\s+/u',' ',str_replace("\xc2\xa0",' ',$body)));
}
function ojb_date(string $day,string $month,string $year,string $h='00',string $m='00',string $s='00'): string {
    $months=['ENE'=>1,'JAN'=>1,'FEB'=>2,'MAR'=>3,'ABR'=>4,'APR'=>4,'MAY'=>5,'JUN'=>6,'JUL'=>7,'AGO'=>8,'AUG'=>8,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DIC'=>12,'DEC'=>12];
    $mon=$months[strtoupper($month)]??0;
    if(!checkdate($mon,(int)$day,(int)$year) || (int)$h>23 || (int)$m>59 || (int)$s>59) throw new InvalidArgumentException('Fecha bancaria inválida.');
    return sprintf('%04d-%02d-%02d %02d:%02d:%02d',(int)$year,$mon,(int)$day,(int)$h,(int)$m,(int)$s);
}
function ojb_money(string $amount): string { return ojr_decimal(ojr_centavos(str_replace(',','',$amount))); }
function ojb_match(string $pattern,string $text): array {
    if(!preg_match($pattern,$text,$m)) throw new InvalidArgumentException('El formato no incluye todos los datos necesarios. No se importó.');
    return $m;
}
function ojb_parse(string $from,string $subject,string $body): array {
    if(!preg_match('/(?:^|<)(notificacionesbanorte@banorte\.com|notificaciones@banorte\.com)>?$/i',trim($from))) throw new InvalidArgumentException('Remitente no admitido.');
    $t=ojb_plain($body); $r=['comision'=>'0.00','fecha_aplicacion'=>null];
    if(stripos($subject,'Eventos por Cuenta')!==false) {
        $a=ojb_match('/Operacion:\s*([A-Za-z0-9-]+)\s*Fecha de Operacion:\s*(\d{1,2})\/([A-Za-z]{3})\/(\d{4})\s*Hora de Operacion:\s*(\d{1,2}):(\d{2}):(\d{2})\s*horas\s*Cuenta Origen:\s*\*+(\d{4})\s*Importe:\s*\$\s*([\d,]+\.\d{2})\s*MN\s*Fecha de Aplicacion:\s*(\d{4}-\d{2}-\d{2})/i',$t);
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$a[10]);
        if(!$date || $date->format('Y-m-d')!==$a[10])throw new InvalidArgumentException('Fecha de aplicación inválida.');
        $r+=['tipo'=>'evento','operacion'=>'evento_cuenta','importe'=>ojb_money($a[9]),'fecha'=>ojb_date($a[2],$a[3],$a[4],$a[5],$a[6],$a[7]),'cuenta'=>$a[8],'referencia'=>$a[1],'descripcion'=>'Evento para completar fechas; no constituye un ingreso independiente'];
        $r['fecha_aplicacion']=$a[10];
    } elseif(stripos($subject,'SPEI RECIBIDO')!==false) {
        $m=ojb_match('/ABONO SPEI de\s*\$\s*([\d,]+\.\d{2})\s*MN el\s*(\d{1,2})\/([A-Za-z]{3})\/(\d{4})\s*a\s*las\s*(\d{1,2})\s*:\s*(\d{2})\s*:\s*(\d{2})\s*horas a la cuenta\s*\*+(\d{4})\s*Clave de Rastreo\s*([A-Za-z0-9-]+)/i',$t);
        $r+=['tipo'=>'ingreso','operacion'=>'spei_recibido','importe'=>ojb_money($m[1]),'fecha'=>ojb_date($m[2],$m[3],$m[4],$m[5],$m[6],$m[7]),'cuenta'=>$m[8],'referencia'=>$m[9],'descripcion'=>'SPEI recibido; cliente por identificar'];
    } elseif(stripos($subject,'Compra en Comercio')!==false) {
        $c=ojb_match('/cargo a su cuenta\s*\*+(\d{4})/i',$t);
        $d=ojb_match('/Operacion:\s*(.*?)\s*Fecha y hora de la operacion:\s*(\d{1,2})\/([A-Za-z]{3})\s*(\d{1,2}):(\d{2}):(\d{2})\s*hrs\.\s*Fecha de Aplicacion:\s*(\d{1,2})\/([A-Za-z]{3})\/(\d{4})/i',$t);
        $a=ojb_match('/Importe:\s*\$\s*([\d,]+\.\d{2})\s*MN.*?Movimiento en su cuenta\/autorizacion:\s*([A-Za-z0-9-]+)/i',$t);
        $year=(int)$d[9]; if(in_array(strtoupper($d[3]),['DIC','DEC'],true) && in_array(strtoupper($d[8]),['ENE','JAN'],true)) $year--;
        $r+=['tipo'=>'egreso','operacion'=>'compra','importe'=>ojb_money($a[1]),'fecha'=>ojb_date($d[2],$d[3],(string)$year,$d[4],$d[5],$d[6]),'cuenta'=>$c[1],'referencia'=>$a[2],'descripcion'=>$d[1]];
        $r['fecha_aplicacion']=substr(ojb_date($d[7],$d[8],$d[9]),0,10);
    } elseif(stripos($subject,'Transferencia a Otros Bancos Nacionales')!==false && preg_match('/transferencia fue exitosa/i',$t)) {
        $a=ojb_match('/Enviaste\s*\$\s*([\d,]+\.\d{2})\s*MN\s*Fecha y hora:\s*(\d{1,2})\/([A-Za-z]{3})\/(\d{4})\s*a las\s*(\d{1,2}):(\d{2}):(\d{2})\s*horas/i',$t);
        $c=ojb_match('/Cuenta origen terminaci[oó]n:\s*.*?\*+(\d{4})\s*N[uú]mero de referencia:\s*([A-Za-z0-9-]+)/iu',$t);
        $b=ojb_match('/Banco receptor:\s*(.*?)\s*Nombre del destinatario:\s*(.*?)\s*Cuenta origen/iu',$t);
        $fee=ojb_match('/Comisi[oó]n m[aá]s IVA:\s*\$\s*([\d,]+\.\d{2})\s*MN\s*Concepto:\s*(.*?)(?:\s*¿|\s*Cualquier duda|$)/iu',$t);
        $r+=['tipo'=>'egreso','operacion'=>'spei_enviado','importe'=>ojb_money($a[1]),'fecha'=>ojb_date($a[2],$a[3],$a[4],$a[5],$a[6],$a[7]),'cuenta'=>$c[1],'referencia'=>$c[2],'descripcion'=>'Transferencia a '.$b[2].' / '.$b[1].' / '.$fee[2]];
        $r['comision']=ojb_money($fee[1]);
    } else throw new InvalidArgumentException('Aviso no compatible. Los pedidos pendientes de pago no son ingresos.');
    if(ojr_centavos($r['importe'])<=0) throw new InvalidArgumentException('Importe inválido.');
    $r['descripcion']=mb_substr($r['descripcion'],0,500,'UTF-8');
    $identity=in_array($r['operacion'],['spei_recibido','evento_cuenta'],true)?[$r['cuenta'],'spei_recibido',$r['referencia']]:[$r['cuenta'],$r['operacion'],$r['fecha'],$r['importe'],$r['referencia']];
    $r['huella']=hash('sha256',implode('|',$identity));
    return $r;
}
function ojb_import(PDO $pdo,array $r,string $mailId,string $origin,string $author): array {
    try {
        $pdo->beginTransaction();
        // El evento nunca genera un movimiento financiero sin un SPEI confirmado.
        $q=$pdo->prepare('SELECT datos FROM oj_banco_eventos WHERE huella=? FOR UPDATE');$q->execute([$r['huella']]);
        $stored=$q->fetchColumn();$event=$stored?json_decode($stored,true,32,JSON_THROW_ON_ERROR):null;
        if($r['operacion']==='evento_cuenta') {
            if($event && ($event['importe']!==$r['importe'] || $event['fecha']!==$r['fecha'] || $event['fecha_aplicacion']!==$r['fecha_aplicacion']))throw new InvalidArgumentException('Los avisos de evento con la misma referencia tienen datos distintos. Revisa el banco.');
            if(!$event){$q=$pdo->prepare('INSERT INTO oj_banco_eventos (huella,datos,correo_id) VALUES (?,?,?)');$q->execute([$r['huella'],json_encode($r,JSON_THROW_ON_ERROR),substr($mailId,0,190)]);}
            $event=$r;
        }
        $q=$pdo->prepare('SELECT * FROM oj_banco_movimientos WHERE huella=? FOR UPDATE');$q->execute([$r['huella']]);$bank=$q->fetch();$new=false;
        if(!$bank && $r['operacion']!=='evento_cuenta') {
            if($event && $event['importe']!==$r['importe'])throw new InvalidArgumentException('El importe del evento no coincide con el SPEI. Revisa el banco.');
            if($event){$r['fecha']=$event['fecha'];$r['fecha_aplicacion']=$event['fecha_aplicacion'];}
            $q=$pdo->prepare('INSERT INTO oj_banco_movimientos (huella,correo_id,tipo,operacion,cuenta,importe,comision,fecha,fecha_aplicacion,descripcion,referencia,origen,creado_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute([$r['huella'],substr($mailId,0,190),$r['tipo'],$r['operacion'],$r['cuenta'],$r['importe'],$r['comision'],$r['fecha'],$r['fecha_aplicacion'],$r['descripcion'],$r['referencia'],$origin,date('Y-m-d H:i:s')]);
            $id=(int)$pdo->lastInsertId();$new=true;ojb_audit($pdo,$id,'importacion',['origen'=>$origin,'fechas_completadas'=>(bool)$event],$author);
        } else $id=$bank?(int)$bank['id']:null;
        $updated=false;
        if($bank) {
            if(ojr_centavos($bank['importe'])!==ojr_centavos($r['importe']))throw new InvalidArgumentException('La referencia ya está registrada con otro importe. Revisa el banco.');
            if($event && $bank['operacion']==='spei_recibido') {
                if(ojr_centavos($bank['importe'])!==ojr_centavos($event['importe']))throw new InvalidArgumentException('El importe del evento no coincide con el SPEI.');
                if($bank['fecha']!==$event['fecha'] || $bank['fecha_aplicacion']!==$event['fecha_aplicacion']) {
                    $q=$pdo->prepare('UPDATE oj_banco_movimientos SET fecha=?,fecha_aplicacion=?,actualizado_en=?,actualizado_por=? WHERE id=?');
                    $q->execute([$event['fecha'],$event['fecha_aplicacion'],date('Y-m-d H:i:s'),$author,$id]);$updated=true;
                    ojb_audit($pdo,$id,'fechas_bancarias',['antes'=>[$bank['fecha'],$bank['fecha_aplicacion']],'despues'=>[$event['fecha'],$event['fecha_aplicacion']],'recibo_conservado'=>$bank['recibo_id']],$author);
                }
            }
        }
        $pdo->commit();return ['id'=>$id,'nuevo'=>$new,'actualizado'=>$updated,'evento_pendiente'=>$id===null];
    } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
// Lee únicamente la parte textual; nunca ejecuta HTML ni guarda el correo completo.
function ojb_eml(string $raw,int $depth=0): array {
    if($depth>8 || strlen($raw)>1048576) throw new InvalidArgumentException('Correo demasiado grande o complejo.');
    $parts=preg_split('/\r?\n\r?\n/',$raw,2);$head=preg_replace('/\r?\n[ \t]+/',' ',$parts[0]);$body=$parts[1]??'';$headers=[];
    foreach(preg_split('/\r?\n/',$head) as $line) { if(strpos($line,':')===false)continue;[$k,$v]=explode(':',$line,2);$headers[strtolower($k)]=trim($v); }
    $type=$headers['content-type']??'text/plain';
    if(stripos($headers['content-disposition']??'','attachment')!==false)return ['headers'=>$headers,'body'=>''];
    if(stripos($type,'multipart/')!==false && preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/i',$type,$m)) {
        $boundary=$m[1]?:$m[2];$html='';$plain='';
        foreach(explode('--'.$boundary,$body) as $i=>$chunk) {if($i===0 || trim($chunk)==='--')continue;$p=ojb_eml(ltrim($chunk,"\r\n"),$depth+1);if(!$p['body'])continue;if(stripos($p['headers']['content-type']??'','text/plain')!==false)$plain=$p['body'];else $html=$p['body'];}
        return ['headers'=>$headers,'body'=>$html?:$plain];
    }
    if(!preg_match('~^text/(plain|html)\b~i',$type))return ['headers'=>$headers,'body'=>''];
    $encoding=strtolower($headers['content-transfer-encoding']??'');
    if($encoding==='quoted-printable')$body=quoted_printable_decode($body);
    if($encoding==='base64') { $decoded=base64_decode(preg_replace('/\s+/','',$body),true); if($decoded===false)throw new InvalidArgumentException('Correo Base64 inválido.');$body=$decoded; }
    if(preg_match('/charset="?([^;"\s]+)/i',$type,$m) && strcasecmp($m[1],'utf-8')!==0) $body=mb_convert_encoding($body,'UTF-8',$m[1]);
    return ['headers'=>$headers,'body'=>$body];
}
function ojb_config(): array {
    $path=__DIR__.'/../bancos/config.php';
    if(!is_file($path))return [];
    $config=require $path; return is_array($config)?$config:[];
}

function ojb_apply_locked(PDO $pdo,array $r,int $docId,array $post,array $session,string $actor): int {
    if(!$pdo->inTransaction())throw new RuntimeException("Se requiere una transacción activa.");
    ojb_payment_engine($pdo);
    $mid=(int)$r["id"];
    if($r['tipo']!=='ingreso' || $r['clasificacion']!=='negocio')throw new InvalidArgumentException('Clasifica el depósito como Negocio para aplicarlo.');
    if($r['recibo_id'])throw new InvalidArgumentException('Este depósito ya tiene un recibo asociado.');
    $input=$post;$input['importe']=$r['importe'];$input['forma_pago']='transferencia';$input['banco']='Banorte';$input['referencia']=$r['referencia'];$input['fecha_pago']=str_replace(' ','T',substr($r['fecha'],0,16));
    $bt=ojr_text($post,'banco_token',64,true);
    if(!preg_match('/^[a-f0-9]{64}$/D',$bt) || (int)($session['ojb_tokens'][$bt]??0)!==$mid)throw new InvalidArgumentException('El formulario venció. Recarga el movimiento.');
    $input['solicitud_token']=hash('sha256','movimiento-banco-'.$r['huella']);
    $receiptSession=$session;$receiptSession['ojr_tokens'][$input['solicitud_token']]=$docId;
    // Detecta recibos anteriores de la misma transferencia, aun en otro trabajo.
    $prior=$pdo->query("SELECT folio,snapshot FROM oj_recibos WHERE forma_pago='transferencia' AND anulado_en IS NULL");
    foreach($prior->fetchAll(PDO::FETCH_ASSOC) as $old) {
      $oldData=json_decode($old['snapshot'],true,512,JSON_THROW_ON_ERROR);
      if(strcasecmp(trim((string)($oldData['referencia']??'')),trim($r['referencia']))===0)throw new InvalidArgumentException('Esta transferencia ya está registrada en '.$old['folio'].'. No vuelvas a aplicar el pago.');
    }
    $receipt=ojr_guardar($pdo,$docId,$input,$receiptSession);
    $q=$pdo->prepare('UPDATE oj_banco_movimientos SET documento_id=?,recibo_id=?,actualizado_en=?,actualizado_por=? WHERE id=?');$q->execute([$docId,$receipt,date('Y-m-d H:i:s'),$actor,$mid]);
    ojb_audit($pdo,$mid,'aplicacion',['documento_id'=>$docId,'recibo_id'=>$receipt],$actor);    return $receipt;
}

function ojb_payment_engine(PDO $pdo): void {
    ojr_verificar_motor($pdo);
    if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')return;
    $q=$pdo->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('oj_banco_movimientos','oj_banco_auditoria')");
    $tables=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach(['oj_banco_movimientos','oj_banco_auditoria'] as $table)if(strcasecmp((string)($tables[$table]??''),'InnoDB')!==0)throw new RuntimeException('Las tablas de movimientos y auditoría deben usar InnoDB antes de registrar o vincular pagos.');
}
function ojb_token(array $post,array $session,int $mid): void {
    $token=ojr_text($post,'banco_token',64,true);
    if(!preg_match('/^[a-f0-9]{64}$/D',$token) || (int)($session['ojb_tokens'][$token]??0)!==$mid)throw new InvalidArgumentException('El formulario venció. Recarga el movimiento.');
}
function ojb_same_transfer(array $movement,array $receipt): bool {
    if($receipt['anulado_en']!==null || $receipt['forma_pago']!=='transferencia')return false;
    $snapshot=json_decode($receipt['snapshot'],true,512,JSON_THROW_ON_ERROR);
    return ojr_centavos($receipt['importe'])===ojr_centavos($movement['importe'])
      && strcasecmp(trim((string)($snapshot['referencia']??'')),trim($movement['referencia']))===0
      && stripos(trim((string)($snapshot['banco']??'')),'banorte')!==false;
}
function ojb_link_locked(PDO $pdo,array $movement,int $docId,int $receiptId,array $post,array $session,string $actor): int {
    if(!$pdo->inTransaction())throw new RuntimeException('Se requiere una transacción activa.');
    if($movement['tipo']!=='ingreso' || $movement['clasificacion']!=='negocio' || $movement['recibo_id'])throw new InvalidArgumentException('Selecciona un ingreso de negocio sin recibo asociado.');
    ojb_token($post,$session,(int)$movement['id']);
    if(($post['confirmado_vinculo']??'')!=='1')throw new InvalidArgumentException('Confirma que el recibo corresponde a este depósito.');
    ojb_payment_engine($pdo);
    $chain=ojr_cadena($pdo,$docId);$root=(int)end($chain)['id'];
    $q=$pdo->prepare('SELECT id FROM cotizaciones WHERE id=? FOR UPDATE');$q->execute([$root]);
    $chain=ojr_cadena($pdo,$docId);if((int)end($chain)['id']!==$root)throw new InvalidArgumentException('El documento cambió. Recarga la página.');
    $q=$pdo->prepare('SELECT * FROM oj_recibos WHERE id=? FOR UPDATE');$q->execute([$receiptId]);$receipt=$q->fetch(PDO::FETCH_ASSOC);
    if(!$receipt || (int)$receipt['raiz_id']!==$root || !ojb_same_transfer($movement,$receipt))throw new InvalidArgumentException('El recibo debe estar vigente, pertenecer al mismo trabajo y coincidir en importe, banco Banorte y clave de rastreo.');
    $q=$pdo->prepare('SELECT id FROM oj_banco_movimientos WHERE recibo_id=? LIMIT 1');$q->execute([$receiptId]);
    if($q->fetchColumn())throw new InvalidArgumentException('Ese recibo ya está vinculado a otro movimiento.');
    $q=$pdo->prepare('UPDATE oj_banco_movimientos SET documento_id=?,recibo_id=?,actualizado_en=?,actualizado_por=? WHERE id=?');
    $q->execute([(int)$receipt['documento_id'],$receiptId,date('Y-m-d H:i:s'),$actor,(int)$movement['id']]);
    ojb_audit($pdo,(int)$movement['id'],'vincular_recibo',['documento_id'=>(int)$receipt['documento_id'],'recibo_id'=>$receiptId],$actor);
    return $receiptId;
}
function ojb_document_summary(PDO $pdo,int $docId,array $movement): array {
    $chain=ojr_cadena($pdo,$docId);$doc=$chain[0];$root=(int)end($chain)['id'];
    $history=ojr_historial($pdo,$root);$total=ojr_centavos($doc['total']);$paid=ojr_acumulado($history);$amount=ojr_centavos($movement['importe']);$balance=$total-$paid;
    $candidates=[];$registeredReference=false;
    // También identifica referencias registradas en otro trabajo para evitar un segundo cobro.
    $q=$pdo->prepare("SELECT r.*, m.id vinculo_id FROM oj_recibos r LEFT JOIN oj_banco_movimientos m ON m.recibo_id=r.id WHERE r.forma_pago='transferencia' AND r.anulado_en IS NULL");$q->execute();
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $receipt) {
        $snapshot=json_decode($receipt['snapshot'],true,512,JSON_THROW_ON_ERROR);
        if(strcasecmp(trim((string)($snapshot['referencia']??'')),trim($movement['referencia']))!==0)continue;
        $registeredReference=true;
        if((int)$receipt['raiz_id']===$root && !$receipt['vinculo_id'] && ojb_same_transfer($movement,$receipt))$candidates[]=['id'=>(int)$receipt['id'],'folio'=>$receipt['folio'],'fecha'=>$receipt['fecha_pago'],'importe'=>ojr_centavos($receipt['importe'])];
    }
    $after=$balance-$amount;$kind=$after===0?'Liquidación':($paid===0?'Anticipo':'Abono');
    $records=[];foreach($history as $receipt)$records[]=['id'=>(int)$receipt['id'],'folio'=>$receipt['folio'],'fecha'=>$receipt['fecha_pago'],'importe'=>ojr_centavos($receipt['importe']),'tipo'=>$receipt['tipo'],'anulado'=>$receipt['anulado_en']!==null];
    return ['id'=>(int)$doc['id'],'folio'=>$doc['folio'],'cliente'=>$doc['cliente_nombre'],'proyecto'=>$doc['proyecto'],'tipo'=>ojr_tipo($doc['tipo_documento']??'cotizacion'),'total'=>$total,'previo'=>$paid,'saldo'=>$balance,'deposito'=>$amount,'saldo_despues'=>$after,'referencia_50'=>(int)round($total/2),'titulo'=>$kind,'puede_aplicar'=>$balance>0 && $amount>0 && $amount<=$balance && !$registeredReference,'referencia_registrada'=>$registeredReference,'recibos'=>$records,'vinculables'=>$candidates];
}
