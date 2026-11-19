<?php require_once __DIR__.'/auth.php'; ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Importar SYSCOM · Ojo Jarocho</title><link rel="stylesheet" href="assets/syscom.css?v=20261018-512"></head><body class="syscom-pagina"><main class="syscom-panel" id="syscomImportacion">
<h1>Importar catálogo SYSCOM</h1><p>Selecciona el CSV original de tu descarga. Se lee por partes y sólo se envían productos, costos y existencia Veracruz. Las descripciones HTML y los enlaces privados se descartan.</p>
<nav class="syscom-acciones"><a class="syscom-btn" href="index.php">Cotizador</a><a class="syscom-btn" href="dashboard.php">Dashboard</a><a class="syscom-btn" href="syscom_descarga.php">Actualizar desde enlace SYSCOM</a></nav>
<?= csrf_field() ?>
<p><strong>Precios de origen: USD sin IVA, columna “Su Precio”.</strong> Esta carga es una copia del archivo, no una conexión en tiempo real con SYSCOM.</p>
<p id="syscomActual" class="syscom-ayuda" role="status">Consultando catálogo…</p>
<label for="syscomArchivo">Archivo CSV de SYSCOM</label><input id="syscomArchivo" type="file" accept=".csv,text/csv">
<label class="syscom-confirmar"><input type="checkbox" id="syscomConfirmar"> Confirmo que “Su Precio” está en dólares estadounidenses y no incluye IVA.</label>
<div class="syscom-acciones"><button type="button" class="syscom-btn" id="syscomIniciar">Importar archivo</button><button type="button" class="syscom-btn syscom-secundario" id="syscomCancelar" hidden>Cancelar carga pendiente</button></div>
<progress id="syscomProgreso" max="100" value="0" aria-label="Progreso de lectura del archivo"></progress><p id="syscomEstadoImportacion" role="status" aria-live="polite"></p>
<p class="syscom-ayuda">Mantén esta página abierta mientras importa. Puedes trabajar en el cotizador en otra pestaña. Si se interrumpe, el catálogo anterior sigue disponible: cancela la carga pendiente y vuelve a seleccionar el archivo desde el principio.</p>
<p class="syscom-ayuda">Al terminar se reemplaza únicamente tu catálogo importado de SYSCOM. Los documentos, borradores y Mis conceptos conservan sus precios. Otros usuarios deben importar su propio archivo.</p>
<noscript>Activa JavaScript para importar este archivo por lotes.</noscript>
</main><script src="assets/syscom_comun.js?v=20261018-5121"></script><script src="assets/syscom_csv.js?v=20261018-512"></script><script src="assets/syscom_importar.js?v=20261018-5121"></script></body></html>
