<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$id = $_GET['id'] ?? 0;

if (!is_numeric($id) || (int)$id <= 0) {
    die('ID inválido');
}

$id = (int)$id;

$stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    die('Documento no encontrado');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acta de aceptación</title>
<style>
body{
    font-family: Arial, Helvetica, sans-serif;
    background:#eef3f8;
    margin:0;
    padding:24px;
    color:#1f2937;
}
.wrap{
    max-width:900px;
    margin:0 auto;
    background:#fff;
    border-radius:14px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    overflow:hidden;
}
.topbar{
    background:#0d3b66;
    color:#fff;
    padding:22px 28px;
}
.topbar h1{
    margin:0;
    font-size:28px;
}
.content{
    padding:24px;
}
.info{
    background:#f8fafc;
    border:1px solid #d6dee8;
    border-radius:10px;
    padding:16px;
    margin-bottom:18px;
}
label{
    display:block;
    font-weight:bold;
    margin-bottom:6px;
}
input, textarea{
    width:100%;
    padding:10px;
    border:1px solid #cfd8e3;
    border-radius:8px;
    box-sizing:border-box;
    margin-bottom:14px;
}
canvas{
    width:100%;
    max-width:700px;
    height:220px;
    border:1px solid #111;
    border-radius:8px;
    background:#fff;
    touch-action:none;
}
.actions{
    margin-top:16px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.btn{
    display:inline-block;
    background:#0d3b66;
    color:#fff;
    text-decoration:none;
    padding:11px 15px;
    border:none;
    border-radius:8px;
    font-weight:bold;
    cursor:pointer;
}
.btn-secondary{
    background:#dbe7f3;
    color:#0d3b66;
}
</style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <h1>Acta de aceptación</h1>
    </div>

    <div class="content">
        <div class="info">
            <p><strong>Folio:</strong> <?php echo htmlspecialchars($data['folio']); ?></p>
            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($data['cliente_nombre']); ?></p>
            <p><strong>Proyecto:</strong> <?php echo htmlspecialchars($data['proyecto']); ?></p>
            <p><strong>Total:</strong> $<?php echo number_format((float)$data['total'], 2, '.', ','); ?></p>
        </div>

        <form method="POST" action="guardar_aceptacion.php" onsubmit="guardarFirma()">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="cotizacion_id" value="<?php echo $data['id']; ?>">
            <input type="hidden" name="firma" id="firmaInput">

            <label>Nombre completo de quien recibe</label>
            <input type="text" name="nombre" required>

            <label>Puesto / cargo</label>
            <input type="text" name="puesto">

            <label>Observaciones de aceptación</label>
            <textarea name="observacion" placeholder="Ejemplo: Se recibió e instaló correctamente el servicio."></textarea>

            <label>Firma</label>
            <canvas id="firmaCanvas" width="700" height="220"></canvas>

            <div class="actions">
                <button type="button" class="btn btn-secondary" id="btnLimpiar">Limpiar firma</button>
                <button type="submit" class="btn">Generar acta</button>
                <a href="listado.php" class="btn btn-secondary">Volver</a>
            </div>
        </form>
    </div>
</div>

<script>
const canvas = document.getElementById('firmaCanvas');
const ctx = canvas.getContext('2d');
const btnLimpiar = document.getElementById('btnLimpiar');

ctx.lineWidth = 2;
ctx.lineCap = 'round';
ctx.strokeStyle = '#111';

let dibujando = false;

function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    if (e.touches && e.touches.length > 0) {
        return {
            x: e.touches[0].clientX - rect.left,
            y: e.touches[0].clientY - rect.top
        };
    }
    return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
    };
}

function iniciarDibujo(e) {
    dibujando = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
}

function dibujar(e) {
    if (!dibujando) return;
    e.preventDefault();
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
}

function terminarDibujo() {
    dibujando = false;
}

function limpiarFirma() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function guardarFirma() {
    document.getElementById('firmaInput').value = canvas.toDataURL('image/png');
}

canvas.addEventListener('mousedown', iniciarDibujo);
canvas.addEventListener('mousemove', dibujar);
canvas.addEventListener('mouseup', terminarDibujo);
canvas.addEventListener('mouseleave', terminarDibujo);

canvas.addEventListener('touchstart', iniciarDibujo, { passive: false });
canvas.addEventListener('touchmove', dibujar, { passive: false });
canvas.addEventListener('touchend', terminarDibujo);

btnLimpiar.addEventListener('click', limpiarFirma);
</script>
</body>
</html>
