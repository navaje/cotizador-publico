/* Calculadora de almacenamiento CCTV: estima GB/dia, TB totales y disco recomendado. */
(() => {
 'use strict';
 const id = n => document.getElementById(n);
 if (!id('dCam')) return;
 const TAMANOS = [1, 2, 4, 6, 8, 10, 12, 16, 18, 20];
 function calcular() {
  const cam = Math.max(1, Math.min(256, id('dCam').valueAsNumber || 1));
  const res = Number(id('dRes').value) || 2;
  const fps = Math.max(1, Math.min(60, id('dFps').valueAsNumber || 15));
  const dias = Math.max(1, Math.min(3650, id('dDias').valueAsNumber || 30));
  const modo = Number(id('dModo').value) || 1;
  const mbps = res * fps * 0.055 * modo;
  const gbDia = mbps * 86400 / 8 / 1024 * cam;
  const tb = gbDia * dias / 1000;
  const necesario = tb * 1.2;
  const disco = TAMANOS.find(t => t >= necesario) || null;
  id('dGbDia').textContent = gbDia.toFixed(1);
  id('dTb').textContent = tb.toFixed(2);
  id('dDisco').textContent = disco ? disco + ' TB' : 'Más de 20 TB (proyecto a medida)';
  id('dExplicacion').textContent = 'Estimación: ' + cam + ' cámaras × ' + res + ' MP × ' + fps + ' fps × modo ' + Math.round(modo * 100) + '% ≈ ' + mbps.toFixed(1) + ' Mbps por cámara. Margen del 20% incluido.';
 }
 ['dCam', 'dRes', 'dFps', 'dDias', 'dModo', 'dPrecio'].forEach(n => id(n).addEventListener('input', calcular));
 id('dAgregar').addEventListener('click', () => {
  const cam = id('dCam').valueAsNumber || 1, res = id('dRes').value, fps = id('dFps').valueAsNumber || 15, dias = id('dDias').valueAsNumber || 30;
  const precio = Number(id('dPrecio').value) || 0;
  const disco = id('dDisco').textContent;
  const concepto = 'Disco duro surveillance ' + disco + ' — estimación ' + cam + ' cámaras ' + res + ' MP, ' + dias + ' días (' + fps + ' fps)';
  window.dispatchEvent(new CustomEvent('oj:catalogo-agregar', { detail: { concepto, cantidad: 1, precio } }));
  id('dExplicacion').textContent = 'Agregado: ' + concepto + '. Guarda el borrador para conservarlo.';
 });
 calcular();
})();
