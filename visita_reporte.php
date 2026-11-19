<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/app/visitas.php';
header('Cache-Control: private, no-store');
try{
    $v=ojv_load($pdo,ojv_id($_GET['id']??null),(int)$_SESSION['usuario_id'],ojv_id($_GET['version']??null,true));$client=($_GET['cliente']??'')==='1';$pdf=($_GET['pdf']??'')==='1';
    if($pdf&&!$client){
        foreach($v['form']['fotos'] as &$f){
            $q=$pdo->prepare('SELECT archivo,mime FROM oj_visita_fotos WHERE id=? AND visita_id=? AND usuario_id=?');$q->execute([$f['id'],$v['id'],(int)$_SESSION['usuario_id']]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if($row&&preg_match('/^[a-f0-9]{48}\.bin$/D',$row['archivo'])){$path=__DIR__.'/app/visitas_fotos/'.$row['archivo'];if(is_file($path))$f['src']='data:'.$row['mime'].';base64,'.base64_encode(file_get_contents($path));}
        }unset($f);
    }
    $html=ojv_report($v,$client,$pdf);
    if(!$pdf){header('Content-Type: text/html; charset=utf-8');echo $html;exit;}
    require_once __DIR__.'/dompdf/autoload.inc.php';$options=new \Dompdf\Options();$options->set('isRemoteEnabled',false);$options->set('isPhpEnabled',false);$pdfDoc=new \Dompdf\Dompdf($options);$pdfDoc->loadHtml($html,'UTF-8');$pdfDoc->setPaper('A4');$pdfDoc->render();
    $pdfDoc->stream('VT-'.(int)$v['id'].'-v'.(int)$v['version'].($client?'-cliente':'-interno').'.pdf',['Attachment'=>false]);
}catch(InvalidArgumentException $e){http_response_code(404);exit(ojv_e($e->getMessage()));}
catch(Throwable $e){error_log('Reporte visita: '.$e->getMessage());http_response_code(500);exit('No se pudo generar el informe. Los datos guardados se conservan. Revisa el registro del servidor.');}
