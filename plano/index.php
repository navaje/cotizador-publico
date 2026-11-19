<?php
require_once __DIR__ . '/../auth.php';
$csrfPlano = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Plano CCTV - Ojo Jarocho</title>

  <script src="../assets/vendor/fabric.min.js"></script>
  <script>if(!window.fabric){document.write('<script src="https:\/\/cdnjs.cloudflare.com\/ajax\/libs\/fabric.js\/5.3.1\/fabric.min.js"><\/script>');}</script>

  <style>
    body { margin:0; font-family:Arial,sans-serif; background:#f4f6f8; color:#333; }
    .topbar { background:#00235A; color:white; padding:15px; font-size:20px; font-weight:bold; }
    .layout { display:flex; height:calc(100vh - 55px); }
    .tools { width:260px; background:white; padding:15px; border-right:1px solid #ddd; box-sizing:border-box; }
    .tools button { width:100%; margin-bottom:10px; padding:12px; border:none; border-radius:8px; background:#00235A; color:white; font-weight:bold; cursor:pointer; }
    .tools button.green { background:#82B60B; }
    .tools button.gray { background:#555752; }
    .tools button.red { background:#b3261e; }
    .canvas-area { flex:1; display:flex; justify-content:center; align-items:center; overflow:auto; background:#e9edf2; padding:20px; box-sizing:border-box; }
    .canvas-container { background:white !important; border:2px solid #ccc; }
    .upper-canvas, .lower-canvas { background:transparent !important; }
    .info { font-size:13px; margin-top:15px; color:#555; line-height:1.4; }
  </style>
</head>

<body>

<div class="topbar">
  Diseñador de Plano CCTV - Ojo Jarocho
</div>

<div class="layout">
  <div class="tools">
    <button onclick="addWall()">Dibujar pared horizontal</button>
    <button onclick="addWallVertical()">Dibujar pared vertical</button>
    <button onclick="addCamera()">Agregar cámara</button>
    <button onclick="rotateSelected()">Girar seleccionado</button>
    <button onclick="addDVR()">Agregar DVR/NVR</button>
    <button onclick="addText()">Agregar texto</button>
    <button class="gray" onclick="deleteSelected()">Borrar seleccionado</button>
    <button class="red" onclick="clearCanvas()">Limpiar todo</button>
    <button class="green" onclick="exportPNG()">Guardar plano</button>

    <div class="info">
      <strong>Instrucciones:</strong><br>
      1. Agrega paredes.<br>
      2. Coloca cámaras y DVR.<br>
      3. Arrastra los objetos.<br>
      4. Usa “Girar” para orientar cámaras.<br>
      5. Guarda el plano para anexarlo al PDF.
    </div>
  </div>

  <div class="canvas-area">
    <canvas id="plano" width="1000" height="700"></canvas>
  </div>
</div>

<script>
  const urlParams = new URLSearchParams(window.location.search);
  const cotizacionID = urlParams.get('id') || '';
  const visitaID = urlParams.get('visita') || '';
  const planoID = urlParams.get('plano_id') || '';

  const canvas = new fabric.Canvas('plano', {
    backgroundColor: '#ffffff',
    selection: true,
    preserveObjectStacking: true
  });

  canvas.renderAll();

  function cargarPlanoGuardado() {
    if (!cotizacionID && !planoID) return;

    const rutaPlano = planoID ? ('ver.php?id=' + planoID + '&v=' + new Date().getTime()) : ('planos/plano_' + cotizacionID + '.png?v=' + new Date().getTime());

    fabric.Image.fromURL(rutaPlano, function(img) {
      if (!img) return;

      img.set({
        left: 0,
        top: 0,
        selectable: false,
        evented: false
      });

      img.scaleToWidth(canvas.width);
      canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
    });
  }

  cargarPlanoGuardado();

  function addWall() {
    const line = new fabric.Line([100, 100, 400, 100], {
      stroke: '#222',
      strokeWidth: 6,
      selectable: true
    });

    canvas.add(line);
    canvas.setActiveObject(line);
  }

  function addWallVertical() {
    const line = new fabric.Line([100, 100, 100, 400], {
      stroke: '#222',
      strokeWidth: 6,
      selectable: true
    });

    canvas.add(line);
    canvas.setActiveObject(line);
  }

  function addCamera() {
    const cone = new fabric.Triangle({
      width: 85,
      height: 110,
      fill: 'rgba(130, 182, 11, 0.25)',
      left: -20,
      top: 35,
      angle: 180
    });

    const body = new fabric.Circle({
      radius: 24,
      fill: '#00235A',
      left: 0,
      top: 0
    });

    const lens = new fabric.Circle({
      radius: 8,
      fill: '#82B60B',
      left: 16,
      top: 16
    });

    const label = new fabric.Text('CAM', {
      fontSize: 11,
      fill: '#ffffff',
      left: 9,
      top: 27,
      fontWeight: 'bold'
    });

    const camera = new fabric.Group([cone, body, lens, label], {
      left: 200,
      top: 200,
      angle: 0
    });

    canvas.add(camera);
    canvas.setActiveObject(camera);
  }

  function addDVR() {
    const rect = new fabric.Rect({
      width: 100,
      height: 60,
      fill: '#555752',
      rx: 8,
      ry: 8,
      left: 0,
      top: 0
    });

    const text = new fabric.Text('DVR', {
      fontSize: 22,
      fill: '#ffffff',
      left: 28,
      top: 18,
      fontWeight: 'bold'
    });

    const dvr = new fabric.Group([rect, text], {
      left: 450,
      top: 300
    });

    canvas.add(dvr);
    canvas.setActiveObject(dvr);
  }

  function addText() {
    const text = new fabric.IText('Escribe aquí', {
      left: 300,
      top: 150,
      fontSize: 20,
      fill: '#222'
    });

    canvas.add(text);
    canvas.setActiveObject(text);
  }

  function rotateSelected() {
    const activeObject = canvas.getActiveObject();

    if (!activeObject) {
      alert('Selecciona primero un objeto');
      return;
    }

    activeObject.rotate((activeObject.angle || 0) + 45);
    canvas.renderAll();
  }

  function deleteSelected() {
    const activeObject = canvas.getActiveObject();

    if (activeObject) {
      canvas.remove(activeObject);
      canvas.discardActiveObject();
      canvas.renderAll();
    }
  }

  function clearCanvas() {
    if (confirm('¿Seguro que deseas limpiar todo el plano?')) {
      canvas.clear();
      canvas.backgroundColor = '#ffffff';
      canvas.setBackgroundImage(null, canvas.renderAll.bind(canvas));
      canvas.renderAll();
    }
  }

  function exportPNG() {
    const dataURL = canvas.toDataURL({
      format: 'png',
      quality: 1,
      multiplier: 2
    });

    fetch('guardar.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'imagen=' + encodeURIComponent(dataURL) + '&id=' + encodeURIComponent(cotizacionID) + '&visita_id=' + encodeURIComponent(visitaID) + '&csrf_token=' + encodeURIComponent(<?php echo json_encode($csrfPlano); ?>)
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'ok') {
        alert('Plano guardado correctamente');
        window.open(data.archivo, '_blank');
      } else {
        alert('Error al guardar el plano');
      }
    })
    .catch(err => {
      console.error(err);
      alert('Error de conexión con guardar.php');
    });
  }
</script>

</body>
</html>
