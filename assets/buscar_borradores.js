'use strict';
function normalizarBusquedaBorrador(texto) {
  return String(texto).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es').trim().replace(/\s+/g,' ');
}
function coincideBorrador(texto, consulta, estado, filtro) {
  return (!filtro || estado === filtro) && normalizarBusquedaBorrador(consulta).split(' ').every(palabra => normalizarBusquedaBorrador(texto).includes(palabra));
}
if (typeof module !== 'undefined') module.exports = {normalizarBusquedaBorrador,coincideBorrador};
if (typeof document !== 'undefined') {
  const buscar = document.getElementById('buscarBorradores');
  const estado = document.getElementById('estadoBorradores');
  const filas = Array.from(document.querySelectorAll('[data-borrador-estado]'));
  if (buscar && estado) {
    const datos = filas.map(fila => ({fila,texto:fila.textContent,estado:fila.dataset.borradorEstado}));
    function filtrar() {
      let visibles = 0;
      datos.forEach(item => {
        const mostrar = coincideBorrador(item.texto,buscar.value,item.estado,estado.value);
        item.fila.hidden = !mostrar;
        if (mostrar) visibles++;
      });
      document.getElementById('cuentaBorradores').textContent = visibles + ' de ' + filas.length + ' borrador(es) de esta lista.';
      document.getElementById('sinCoincidenciasBorradores').hidden = visibles > 0 || filas.length === 0;
    }
    document.getElementById('filtrosBorradores').hidden = false;
    buscar.addEventListener('input',filtrar);
    estado.addEventListener('change',filtrar);
    document.getElementById('limpiarBorradores').addEventListener('click',() => {buscar.value='';estado.value='';filtrar();buscar.focus();});
    filtrar();
  }
}
