<?php
// Archivo temporal de diagnóstico. Bórralo al terminar.
require_once __DIR__ . '/auth.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
function linea($titulo, $valor) {
    echo '<tr><th>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</th><td>'
        . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '</td></tr>';
}
echo '<!doctype html><meta charset="utf-8"><title>Diagnóstico del acta</title>';
echo '<style>body{font:16px Arial;max-width:850px;margin:30px auto;padding:15px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #bbb;padding:10px;text-align:left}th{width:45%}</style>';
echo '<h1>Diagnóstico del acta de aceptación</h1><table>';
linea('PHP', PHP_VERSION);
linea('Carpeta pdfs', is_dir(__DIR__ . '/pdfs') ?
    (is_writable(__DIR__ . '/pdfs') ? 'Existe y permite escritura' : 'Existe, pero PHP no puede escribir') :
    (is_writable(__DIR__) ? 'No existe; PHP puede intentar crearla' : 'No existe y la carpeta cotizador no permite crearla'));
linea('Dompdf / autoload.inc.php', is_file(__DIR__ . '/dompdf/autoload.inc.php') ? 'Existe' : 'FALTA');
try {
    if (is_file(__DIR__ . '/dompdf/autoload.inc.php')) {
        require_once __DIR__ . '/dompdf/autoload.inc.php';
        linea('Clase Dompdf', class_exists('Dompdf\\Dompdf') ? 'Disponible' : 'NO disponible');
    }
} catch (Throwable $e) {
    linea('Clase Dompdf', 'ERROR al cargarla; consulta el registro de errores de PHP');
    error_log('Diagnóstico acta, Dompdf: ' . $e);
}
try {
    require_once __DIR__ . '/db.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        linea('Conexión PDO', 'NO disponible en db.php');
    } else {
        linea('Conexión PDO', 'Disponible');
        foreach (array('cotizaciones', 'empresa', 'aceptaciones') as $tabla) {
            try {
                $columnas = $pdo->query('SHOW COLUMNS FROM `' . $tabla . '`')->fetchAll(PDO::FETCH_COLUMN);
                linea('Tabla ' . $tabla, 'Existe; columnas: ' . implode(', ', $columnas));
            } catch (Throwable $e) {
                linea('Tabla ' . $tabla, 'FALTA o no se puede consultar');
                error_log('Diagnóstico acta, tabla ' . $tabla . ': ' . $e);
            }
        }
        try {
            $q = $pdo->prepare('SELECT COUNT(*) FROM cotizaciones WHERE id = ?');
            $q->execute(array(48));
            linea('Cotización ID 48', (int)$q->fetchColumn() > 0 ? 'Existe' : 'No existe en esta base');
        } catch (Throwable $e) {
            linea('Cotización ID 48', 'No se pudo consultar');
            error_log('Diagnóstico acta, cotización: ' . $e);
        }
    }
} catch (Throwable $e) {
    linea('Base de datos', 'ERROR al cargar db.php; consulta el registro de errores de PHP');
    error_log('Diagnóstico acta, db.php: ' . $e);
}
echo '</table><p>Al terminar, borra <strong>diagnostico_aceptacion.php</strong> del servidor.</p>';
