<?php
require_once __DIR__ . '/security.php';
// Formulario público: no requiere iniciar sesión.
$origenesValidos = ['web','catalogo','facebook','whatsapp','recomendacion','otro'];
$origen = strtolower(trim((string)($_GET['origen'] ?? 'web')));
if (!in_array($origen, $origenesValidos, true)) $origen = 'web';
$productoNombre = substr(trim((string)($_GET['producto'] ?? '')), 0, 255);
$productoSku = substr(trim((string)($_GET['sku'] ?? '')), 0, 100);
$productoUrl = trim((string)($_GET['producto_url'] ?? ''));
if (strpos($productoUrl, 'https://www.productos-info.com/tienda/ojojarocho') !== 0) $productoUrl = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitud de servicio - Ojo Jarocho</title>
<style>
*{ box-sizing:border-box; }
body{
  margin:0;
  font-family:Arial, Helvetica, sans-serif;
  background:#eef3f8;
  color:#1f2937;
  padding:24px;
}
.wrap{
  max-width:900px;
  margin:auto;
  background:#fff;
  border-radius:14px;
  box-shadow:0 10px 30px rgba(0,0,0,.08);
  overflow:hidden;
}
.topbar{
  background:#0d3b66;
  color:#fff;
  padding:24px;
}
.topbar h1{
  margin:0;
  font-size:28px;
}
.topbar p{
  margin:8px 0 0;
  opacity:.9;
}
.content{
  padding:24px;
}
.section{
  margin-bottom:22px;
  border:1px solid #e5e7eb;
  border-radius:12px;
  padding:20px;
  background:#fafcff;
}
.section h2{
  margin:0 0 16px;
  color:#0d3b66;
  font-size:20px;
}
.grid{
  display:grid;
  grid-template-columns:repeat(2,1fr);
  gap:14px;
}
.full{
  grid-column:1/-1;
}
label{
  display:block;
  font-weight:bold;
  margin-bottom:6px;
  font-size:14px;
}
input, textarea, select{
  width:100%;
  padding:11px;
  border:1px solid #cfd8e3;
  border-radius:8px;
  font-size:14px;
  background:#fff;
}
textarea{
  min-height:100px;
  resize:vertical;
}
.btn{
  background:#0d3b66;
  color:#fff;
  border:none;
  border-radius:8px;
  padding:13px 18px;
  font-weight:bold;
  cursor:pointer;
  font-size:15px;
}
.note{
  font-size:13px;
  color:#6b7280;
  margin-top:10px;
}
.btn-paste{
  width:100%;
  margin-top:7px;
  background:#e7f2cf;
  color:#345500;
  border:1px solid #b9d77b;
  padding:9px 12px;
}
.paste-status{display:block;margin-top:6px;font-size:12px;color:#556270;}
.privacy-check{display:flex;align-items:flex-start;gap:10px;margin:18px 0;color:#475569;font-size:13px;line-height:1.45;}
.privacy-check input{width:auto;margin-top:3px;flex:0 0 auto;}
.privacy-check a{color:#0d3b66;font-weight:bold;}
.website-field{ position:absolute !important; left:-9999px !important; }
@media(max-width:700px){
  body{ padding:12px; }
  .content{ padding:16px; }
  .grid{ grid-template-columns:1fr; }
}
</style>
</head>
<body>

<div class="wrap">
  <div class="topbar">
    <h1>Solicitud de servicio / mantenimiento</h1>
    <p>Completa este formulario para programar revisión, reparación o mantenimiento.</p>
  </div>

  <div class="content">
    <form method="POST" action="guardar_solicitud.php">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="origen" value="<?php echo htmlspecialchars($origen, ENT_QUOTES, 'UTF-8'); ?>">
      <div class="website-field" aria-hidden="true">
        <label>Sitio web</label>
        <input type="text" name="website" tabindex="-1" autocomplete="off">
      </div>

      <?php if ($origen === 'catalogo' || $productoNombre !== '' || $productoSku !== '' || $productoUrl !== ''): ?>
      <div class="section">
        <h2>Producto consultado</h2>
        <p class="note">Puedes copiar el nombre y enlace del catálogo. El modelo se captura más abajo en “Datos del equipo”.</p>
        <div class="grid">
          <input id="producto_sku" type="hidden" name="producto_sku" value="<?php echo htmlspecialchars($productoSku, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="full">
            <label>Nombre del producto</label>
            <input type="text" name="producto_nombre" maxlength="255" value="<?php echo htmlspecialchars($productoNombre, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. Kit de videovigilancia de 4 cámaras">
          </div>
          <div class="full">
            <label>Enlace del producto</label>
            <input type="url" name="producto_url" maxlength="500" value="<?php echo htmlspecialchars($productoUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.productos-info.com/tienda/ojojarocho/..."><br>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="section">
        <h2>Datos del cliente</h2>
        <div class="grid">
          <div>
            <label>Nombre completo *</label>
            <input type="text" name="cliente_nombre" required>
          </div>

          <div>
            <label>Teléfono / WhatsApp *</label>
            <input type="text" name="cliente_telefono" required>
          </div>

          <div>
            <label>Correo</label>
            <input type="email" name="cliente_email">
          </div>

          <div>
            <label>Persona que atenderá al técnico</label>
            <input type="text" name="persona_atiende">
          </div>

          <div class="full">
            <label>Dirección donde se realizará el servicio *</label>
            <textarea name="direccion" required></textarea>
          </div>
        </div>
      </div>

      <div class="section">
        <h2>Datos del equipo</h2>
        <div class="grid">
          <div>
            <label>Tipo de equipo</label>
            <select name="tipo_equipo">
              <option value="">Seleccionar</option>
              <option value="Cámara de seguridad">Cámara de seguridad</option>
              <option value="DVR/NVR">DVR/NVR</option>
              <option value="Sistema de videovigilancia">Sistema de videovigilancia</option>
              <option value="Control de acceso">Control de acceso</option>
              <option value="Alarma">Alarma</option>
              <option value="Red / Internet">Red / Internet</option>
              <option value="Otro">Otro</option>
            </select>
          </div>

          <div>
            <label>Marca</label>
            <input type="text" name="marca" placeholder="Ej. Hikvision, Dahua, Ezviz">
          </div>

          <div>
            <label>Modelo</label>
            <input id="modelo_equipo" type="text" name="modelo" value="<?php echo htmlspecialchars($productoSku, ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-paste" type="button" onclick="pegarModelo()">Pegar modelo copiado del catálogo</button>
            <span id="paste-status" class="paste-status" aria-live="polite"></span>
          </div>

          <div>
            <label>Número de serie</label>
            <input type="text" name="numero_serie">
          </div>

          <div>
            <label>Tipo de servicio</label>
            <select name="tipo_servicio">
              <option value="revision">Revisión</option>
              <option value="mantenimiento">Mantenimiento</option>
              <option value="reparacion">Reparación</option>
              <option value="instalacion">Instalación</option>
              <option value="otro">Otro</option>
            </select>
          </div>

          <div class="full">
            <label>Falla reportada / motivo del servicio *</label>
            <textarea name="falla_reportada" required placeholder="Describe qué está pasando, desde cuándo ocurre y si aparece algún mensaje o falla visible."></textarea>
          </div>
        </div>
      </div>

      <div class="section">
        <h2>Agenda sugerida</h2>
        <div class="grid">
          <div>
            <label>Fecha preferida</label>
            <input type="date" name="fecha_preferida">
          </div>

          <div>
            <label>Horario preferido</label>
            <input type="text" name="horario_preferido" placeholder="Ej. 10:00 a 13:00">
          </div>

          <div class="full">
            <label>Observaciones adicionales</label>
            <textarea name="observaciones" placeholder="Comentarios extra, referencias del domicilio, restricciones de acceso, etc."></textarea>
          </div>
        </div>
      </div>

      <label class="privacy-check">
        <input type="checkbox" name="acepta_privacidad" value="1" required>
        <span>He leído y acepto el <a href="https://ojojarocho.online/politica-de-privacidad/" target="_blank" rel="noopener">Aviso de privacidad</a> y autorizo a Ojo Jarocho a contactarme para atender esta solicitud.</span>
      </label>

      <button class="btn" type="submit">Enviar solicitud</button>
      <div class="note">Al enviar la solicitud, Ojo Jarocho revisará la información y se pondrá en contacto para confirmar la visita.</div>

    </form>
  </div>
</div>

<script>
async function pegarModelo() {
  const campo = document.getElementById('modelo_equipo');
  const sku = document.getElementById('producto_sku');
  const estado = document.getElementById('paste-status');
  try {
    if (!navigator.clipboard || !navigator.clipboard.readText) throw new Error('clipboard');
    const texto = (await navigator.clipboard.readText()).trim();
    if (!texto) {
      estado.textContent = 'El portapapeles está vacío. Copia primero el modelo desde el catálogo.';
      return;
    }
    campo.value = texto.substring(0, 100);
    if (sku) sku.value = texto.substring(0, 100);
    estado.textContent = 'Modelo pegado correctamente.';
  } catch (error) {
    campo.focus();
    estado.textContent = 'El navegador bloqueó el pegado automático. Presiona Ctrl + V en este campo.';
  }
}
</script>

</body>
</html>
