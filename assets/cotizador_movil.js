'use strict';
(() => {
  const body = document.getElementById('productosBody');
  if (!body) return;
  const etiquetas = ['Concepto','Cantidad','Precio original sin IVA','Descuento %','Precio final sin IVA','Importe sin IVA','Acciones'];
  function preparar(fila) {
    Array.from(fila.children).forEach((celda, i) => {
      celda.dataset.label = etiquetas[i] || '';
      const campo = celda.querySelector('input');
      if (campo) {
        campo.setAttribute('aria-label', etiquetas[i]);
        if (campo.type === 'number') campo.setAttribute('inputmode', 'decimal');
      }
    });
  }
  Array.from(body.children).forEach(preparar);
  new MutationObserver(cambios => {
    cambios.forEach(cambio => cambio.addedNodes.forEach(nodo => {
      if (nodo.nodeType === 1 && nodo.tagName === 'TR') preparar(nodo);
    }));
  }).observe(body, {childList:true});
})();
