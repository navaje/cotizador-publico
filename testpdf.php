<?php
require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();

$html = '
<h1>Prueba Ojo Jarocho</h1>
<p>Si ves este PDF, Dompdf ya quedó instalado correctamente.</p>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("prueba.pdf", ["Attachment" => false]);