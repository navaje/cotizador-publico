'use strict';
// Compara solamente los datos que guarda el borrador. No conserva datos en el navegador.
(() => {
  const form = document.getElementById('cotizadorForm');
  const estado = document.getElementById('propuestaGuardadoEstado');
  if (!form || !estado) return;
  const campos = new Set([
    'cliente_nombre','cliente_telefono','cliente_email','cliente_direccion',
    'proyecto','vigencia','metodo_pago','tiempo_instalacion','descripcion',
    'observaciones','condiciones','solicitud_id',
    'concepto[]','cantidad[]','precio[]','descuento[]'
  ]);
  const captura = () => JSON.stringify(Array.from(new FormData(form)).filter(([nombre]) => campos.has(nombre)));
  const inicial = captura();
  const propuestaNueva = form.dataset.propuestaSinGuardar === '1';
  const esBorrador = Boolean(form.elements.namedItem('borrador_id').value);
  let pendiente = false;
  let envioBorrador = null;
  function comprobar() {
    const cambiado = propuestaNueva || captura() !== inicial;
    if (cambiado !== pendiente || !estado.textContent) {
      pendiente = cambiado;
      estado.textContent = cambiado
        ? 'Cambios sin guardar en borrador. Usa Guardar borrador para conservar esta propuesta. Abrir un PDF no actualiza el borrador.'
        : (esBorrador ? 'Borrador cargado: sin cambios pendientes.' : 'Sin cambios pendientes desde que abriste el formulario.');
      estado.style.color = cambiado ? '#92400e' : '#555752';
    }
    return cambiado;
  }
  form.addEventListener('input', comprobar);
  form.addEventListener('change', comprobar);
  // Los botones pueden completar campos o agregar/eliminar filas sin emitir input.
  form.addEventListener('click', () => queueMicrotask(comprobar));
  const filas = document.getElementById('productosBody');
  if (filas) new MutationObserver(comprobar).observe(filas, {childList:true});
  form.addEventListener('submit', evento => {
    comprobar();
    envioBorrador = null;
    const boton = evento.submitter;
    if (!boton) return;
    const destino = new URL(boton.getAttribute('formaction') || form.action, window.location.href);
    const guardar = new URL('guardar_borrador.php', window.location.href);
    const target = boton.getAttribute('formtarget') || form.target;
    if (destino.href === guardar.href && target === '_self') {
      // Permite el envío normal; no da por guardado un POST que podría fallar.
      envioBorrador = evento;
      setTimeout(() => { if (envioBorrador === evento) envioBorrador = null; }, 1500);
    }
  });
  window.addEventListener('beforeunload', evento => {
    const envio = envioBorrador;
    envioBorrador = null;
    if (envio && !envio.defaultPrevented) return;
    if (!comprobar()) return;
    evento.preventDefault();
    evento.returnValue = '';
  });
  window.addEventListener('pageshow', () => { envioBorrador = null; comprobar(); });
  comprobar();
})();
