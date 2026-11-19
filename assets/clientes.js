'use strict';
function datosDesdeCliente(item) {
  if (!item || typeof item.nombre !== 'string' || !item.nombre.trim()) return null;
  return {cliente_nombre:item.nombre,cliente_telefono:typeof item.telefono === 'string' ? item.telefono : '',cliente_email:typeof item.email === 'string' ? item.email : '',cliente_direccion:typeof item.direccion === 'string' ? item.direccion : ''};
}
function aplicarClienteGuardado(datos, campos, solicitud, confirmar) {
  const nombres = Object.keys(datos);
  const diferentes = nombres.some(k => campos[k].value !== datos[k]);
  const hayDatos = nombres.some(k => campos[k].value.trim() !== '');
  const desvincular = diferentes && solicitud && solicitud.value !== '';
  if (diferentes && (hayDatos || desvincular)) {
    const aviso = '¿Sustituir los datos actuales del cliente por los seleccionados?' + (desvincular ? ' La cotización dejará de estar vinculada a la solicitud original.' : '');
    if (!confirmar(aviso)) return false;
  }
  nombres.forEach(k => { campos[k].value = datos[k]; });
  if (desvincular) solicitud.value = '';
  return true;
}
if (typeof module !== 'undefined') module.exports = {datosDesdeCliente,aplicarClienteGuardado};
if (typeof document !== 'undefined') {
  const porId = id => document.getElementById(id);
  const boton = porId('clientesBuscar'), estado = porId('clientesEstado'), lista = porId('clientesResultados');
  const form = porId('cotizadorForm');
  const guardar = porId('clienteGuardarDirecto');
  const estadoGuardado = porId('clienteGuardadoEstado');
  if (guardar && form) guardar.addEventListener('click', async () => {
    const datos = new URLSearchParams();
    const mapeo = {nombre:'cliente_nombre',telefono:'cliente_telefono',email:'cliente_email',direccion:'cliente_direccion'};
    Object.keys(mapeo).forEach(k => datos.set(k,form.elements.namedItem(mapeo[k]).value));
    datos.set('csrf_token',form.elements.namedItem('csrf_token').value);
    if (!datos.get('nombre').trim()) { estadoGuardado.textContent = 'Escribe el nombre antes de guardar al cliente.'; form.elements.namedItem('cliente_nombre').focus(); return; }
    const correo = form.elements.namedItem('cliente_email');
    if (!correo.checkValidity()) { estadoGuardado.textContent = 'Revisa el correo del cliente.'; correo.reportValidity(); return; }
    guardar.disabled = true; estadoGuardado.textContent = 'Guardando cliente…';
    try {
      const response = await fetch('clientes_guardar.php', {method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:datos});
      if (response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) throw new Error('La sesión pudo haber expirado. Conserva la captura y vuelve a iniciar sesión.');
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'No se pudo guardar el cliente.');
      if (data.estado === 'creado') estadoGuardado.textContent = 'Cliente guardado: ' + data.nombre + '. Ya puedes buscarlo en Mis clientes. Recuerda guardar también el borrador de la propuesta.';
      else if (data.estado === 'existente') estadoGuardado.textContent = 'Ya existe una ficha con ese nombre y el mismo teléfono o correo, o con los mismos datos sin contacto. No se creó otra. Recuerda guardar el borrador de la propuesta.';
      else if (data.estado === 'inactivo') estadoGuardado.textContent = 'Se encontró una ficha coincidente, pero está inactiva. Puedes activarla desde Administrar mis clientes.';
      else throw new Error('No se pudo confirmar el resultado del guardado. Revisa Mis clientes antes de repetirlo.');
    } catch (error) { estadoGuardado.textContent = error.message || 'No se pudo guardar el cliente.'; }
    finally { guardar.disabled = false; }
  });
  if (boton && form) {
    porId('clientesQuery').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); boton.click(); } });
    boton.addEventListener('click', async () => {
      boton.disabled = true; lista.replaceChildren(); estado.textContent = 'Buscando clientes…';
      try {
        const query = new URLSearchParams({q:porId('clientesQuery').value});
        const response = await fetch('clientes_buscar.php?' + query, {credentials:'same-origin',headers:{Accept:'application/json'}});
        if (response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) throw new Error('La sesión pudo haber expirado. Guarda tu captura si es posible y vuelve a iniciar sesión.');
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'No se pudo consultar el directorio.');
        if (!Array.isArray(data.items)) throw new Error('El directorio devolvió una respuesta no válida.');
        let visibles = 0;
        data.items.forEach(item => {
          const datos = datosDesdeCliente(item); if (!datos) return; visibles++;
          const fila = document.createElement('div'); fila.className = 'catalogo-fila';
          const texto = document.createElement('span'); texto.textContent = [item.nombre,item.telefono,item.email,item.direccion].filter(Boolean).join(' · ');
          const usar = document.createElement('button'); usar.type = 'button'; usar.className = 'btn btn-secondary'; usar.textContent = 'Usar cliente';
          usar.addEventListener('click', () => {
            const campos = {};
            Object.keys(datos).forEach(k => {campos[k] = form.elements.namedItem(k);});
            if (!aplicarClienteGuardado(datos,campos,form.elements.namedItem('solicitud_id'),aviso => window.confirm(aviso))) { estado.textContent = 'Selección cancelada. Se conservaron los datos actuales.'; return; }
            Object.values(campos).forEach(campo => campo.dispatchEvent(new Event('input',{bubbles:true})));
            estado.textContent = 'Datos cargados de ' + datos.cliente_nombre + '. Puedes ajustarlos para esta propuesta; guarda el borrador para conservar los cambios.';
          });
          fila.append(texto,usar); lista.append(fila);
        });
        estado.textContent = visibles ? visibles + ' resultado(s).' + (data.hay_mas ? ' Hay más; acota la búsqueda.' : '') : 'No hay clientes activos que coincidan. Registra o activa uno desde Mis clientes.';
      } catch (error) { estado.textContent = error.message || 'No se pudo consultar el directorio.'; }
      finally { boton.disabled = false; }
    });
  }
}
