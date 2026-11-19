<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/recibos.php';
require_once __DIR__ . '/dompdf/autoload.inc.php';
date_default_timezone_set('America/Mexico_City');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$id = (int)($_GET['id'] ?? 0);
try {
    $q = $pdo->prepare('SELECT * FROM oj_recibos WHERE id = ?'); $q->execute([$id]);
    $receipt = $q->fetch(PDO::FETCH_ASSOC);
    if (!$receipt) { http_response_code(404); exit('Recibo no encontrado.'); }
    $options = new \Dompdf\Options(); $options->set('isRemoteEnabled', false);
    $pdf = new \Dompdf\Dompdf($options);
    $pdf->loadHtml(ojr_html($receipt), 'UTF-8'); $pdf->setPaper('A4', 'portrait'); $pdf->render();
    $bytes = $pdf->output();
    // Copia privada regenerable. La base de datos es el registro del recibo.
    $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $receipt['folio']).'.pdf';
    $dir = __DIR__.'/storage/recibos';
    if (is_dir($dir) && is_writable($dir) && is_file($dir.'/.htaccess')) {
        $temp = tempnam($dir, 'tmp_');
        if ($temp !== false) {
            if (file_put_contents($temp, $bytes) !== false) {
                if (!rename($temp, $dir.'/'.$name)) { unlink($temp); error_log('OJ recibos: no se pudo conservar copia PDF.'); }
            } else { unlink($temp); error_log('OJ recibos: error al guardar copia PDF.'); }
        }
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: '.(($_GET['descargar'] ?? '') === '1' ? 'attachment' : 'inline').'; filename="'.$name.'"');
    header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff');
    echo $bytes; exit;
} catch (Throwable $e) { error_log('OJ recibo PDF: '.$e->getMessage()); http_response_code(500); exit('No se pudo generar el PDF. El recibo guardado se conserva; revisa el registro de errores.'); }
