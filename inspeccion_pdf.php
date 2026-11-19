<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/db.php';require_once __DIR__.'/app/inspecciones.php';require_once __DIR__.'/dompdf/autoload.inc.php';
date_default_timezone_set('America/Mexico_City');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
try{
    $loaded=ojc_load($pdo,(int)($_GET['id']??0),(int)($_GET['version']??0));
    $options=new \Dompdf\Options();$options->set('isRemoteEnabled',false);
    $pdf=new \Dompdf\Dompdf($options);$pdf->loadHtml(ojc_html($loaded),'UTF-8');$pdf->setPaper('A4','portrait');$pdf->render();
    $pdf->getCanvas()->page_text(500,817,'Página {PAGE_NUM} / {PAGE_COUNT}',null,8,[0.35,0.35,0.35]);
    $name=preg_replace('/[^A-Za-z0-9_-]/','_',$loaded['registro']['folio']).'-v'.(int)$loaded['revision']['version'].'.pdf';
    header('Content-Type: application/pdf');header('Content-Disposition: '.(($_GET['descargar']??'')==='1'?'attachment':'inline').'; filename="'.$name.'"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');echo $pdf->output();
}catch(InvalidArgumentException $e){http_response_code(404);exit(ojc_e($e->getMessage()));}
catch(Throwable $e){error_log('OJ inspeccion PDF: '.$e->getMessage());http_response_code(500);exit('No se pudo generar el reporte. Los datos guardados se conservan. Revisa el registro de errores.');}
