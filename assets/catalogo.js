'use strict';
function conceptoDesdeCatalogo(item) {
  const precio = Number(item.precio);
  if (typeof item.nombre !== 'string' || !item.nombre.trim() || !Number.isFinite(precio) || precio < 0) return null;
  const codigo = typeof item.codigo === 'string' ? item.codigo.trim() : '';
  const unidad = typeof item.unidad === 'string' ? item.unidad.trim() : '';
  return {concepto: (codigo ? codigo + ' · ' : '') + item.nombre + (unidad ? ' (' + unidad + ')' : ''), precio};
}
if (typeof module !== 'undefined') module.exports = {conceptoDesdeCatalogo};
if (typeof document !== 'undefined') {
  const boton = document.getElementById('catalogoBuscar');
  const estado = document.getElementById('catalogoEstado');
  const lista = document.getElementById('catalogoResultados');
  const cantidad = document.getElementById('catalogoCantidad');
  const monedaCatalogo = n => new Intl.NumberFormat('es-MX', {style:'currency',currency:'MXN'}).format(n);
  if (boton) boton.addEventListener('click', async () => {
    boton.disabled = true; lista.replaceChildren(); estado.textContent = 'Buscando…';
    const query = new URLSearchParams({q:document.getElementById('catalogoQuery').value, categoria:document.getElementById('catalogoCategoria').value});
    try {
      const response = await fetch('catalogo_buscar.php?' + query, {credentials:'same-origin',headers:{Accept:'application/json'}});
      if (response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) throw new Error('La sesión pudo haber expirado. Guarda tu captura si es posible y vuelve a iniciar sesión.');
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'No se pudo consultar el catálogo.');
      if (!Array.isArray(data.items)) throw new Error('El catálogo devolvió una respuesta no válida.');
      let visibles = 0;
      data.items.forEach(item => {
        const valor = conceptoDesdeCatalogo(item);
        if (!valor) return;
        visibles++;
        const fila = document.createElement('div'); fila.className = 'catalogo-fila';
        const texto = document.createElement('span'); texto.textContent = valor.concepto + ' · ' + monedaCatalogo(valor.precio) + ' sin IVA';
        const agregar = document.createElement('button'); agregar.type = 'button'; agregar.className = 'btn btn-secondary'; agregar.textContent = 'Agregar';
        agregar.addEventListener('click', () => {
          const q = cantidad.valueAsNumber;
          if (!cantidad.checkValidity() || !Number.isFinite(q) || q <= 0) { cantidad.reportValidity(); estado.textContent = 'Revisa la cantidad antes de agregar.'; return; }
          window.dispatchEvent(new CustomEvent('oj:catalogo-agregar', {detail:{concepto:valor.concepto,cantidad:q,precio:valor.precio}}));
          estado.textContent = 'Agregado: ' + valor.concepto + '. Puedes editar cantidad y precio en la tabla.';
        });
        fila.append(texto, agregar); lista.append(fila);
      });
      estado.textContent = visibles ? (visibles + ' resultado(s).' + (data.hay_mas ? ' Hay más; acota la búsqueda.' : '')) : 'No hay coincidencias activas. Registra o activa tus conceptos desde Mis conceptos.';
    } catch (error) { estado.textContent = error.message || 'No se pudo consultar el catálogo.'; }
    finally { boton.disabled = false; }
  });
}
