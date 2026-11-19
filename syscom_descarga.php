<?php require_once __DIR__.'/auth.php';header('Cache-Control: no-store'); ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Actualizar catálogo desde SYSCOM · Ojo Jarocho</title><link rel="stylesheet" href="assets/syscom.css?v=20261018-512"></head>
<body class="syscom-pagina"><main class="syscom-panel" id="syscomDescarga">
<h1>Actualizar catálogo desde SYSCOM</h1>
<nav class="syscom-acciones"><a class="syscom-btn" href="dashboard.php">Dashboard</a><a class="syscom-btn" href="index.php#syscomCotizador">Buscar productos</a><a class="syscom-btn" href="syscom.php">Importar archivo manualmente</a><a class="syscom-btn" href="syscom_conexion.php">Conexión API</a></nav>
<p>Descarga el reporte general <strong>1758</strong> directamente al hosting e importa sus productos por lotes. El catálogo actual sigue activo hasta completar la nueva carga.</p>
<p>Esta pantalla actualiza al pulsar el botón. La ejecución periódica todavía no está programada.</p>
<?=csrf_field()?>
<form id="ojauConfig" autocomplete="off"><h2>Enlace del catálogo general</h2><label for="ojauEnlace">Enlace de descarga automática del reporte 1758</label><input type="password" id="ojauEnlace" maxlength="250" autocomplete="new-password" spellcheck="false" required>
<p class="syscom-ayuda">Pégalo una sola vez. Se guarda cifrado para tu usuario y no se vuelve a mostrar. La conexión API ya debe estar configurada para utilizar su clave privada de cifrado.</p>
<button type="submit" class="syscom-btn" id="ojauGuardar">Guardar enlace</button></form>
<h2>Actualización del catálogo</h2><p id="ojauActivo" class="syscom-ayuda"></p><p id="ojauHistorial" class="syscom-ayuda"></p>
<label><input type="checkbox" id="ojauConfirmar"> Confirmo que la columna “Su Precio” del reporte está en USD y no incluye IVA.</label>
<div class="syscom-acciones"><button type="button" class="syscom-btn" id="ojauIniciar" disabled>Actualizar catálogo ahora</button><button type="button" class="syscom-btn" id="ojauContinuar" hidden>Continuar importación</button><button type="button" class="syscom-btn syscom-secundario" id="ojauRevisar">Revisar estado</button><button type="button" class="syscom-btn syscom-secundario" id="ojauCancelar" hidden>Cancelar actualización pendiente</button></div>
<p id="ojauEspera" class="syscom-ayuda"></p><progress id="ojauProgreso" max="100" value="0" aria-label="Progreso de lectura del CSV"></progress><p id="ojauEstado" role="status" aria-live="polite">Consultando estado…</p>
<p class="syscom-ayuda">Mantén abierta esta página mientras importa. Puedes cotizar en otra pestaña. Si se interrumpe, pulsa Revisar estado y después Continuar importación, sin volver a descargar. Una descarga pendiente que no termine puede cancelarse desde aquí.</p>
<p class="syscom-ayuda">Se permite un nuevo intento de descarga por enlace cada hora, incluso si el intento falla. No se reintenta automáticamente. Los lotes de un archivo ya descargado pueden continuarse durante ese plazo.</p>
<p class="syscom-ayuda">Sólo actualiza tu catálogo CSV. Documentos, borradores, precios ya agregados y catálogos de otros usuarios se conservan. El archivo temporal se guarda fuera del sitio público y se elimina al completar o cancelar.</p>
<noscript>Activa JavaScript para actualizar el catálogo desde esta pantalla.</noscript>
</main><script src="assets/syscom_descarga.js?v=20261020-517"></script></body></html>
