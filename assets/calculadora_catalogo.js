'use strict';
function conceptoParaGuardarCalculo(campos, calcular) {
  const nombre = campos.nombre.trim();
  if (!nombre) throw new Error('Escribe el concepto en la calculadora antes de guardarlo.');
  const resultado = calcular(campos.costo,campos.incluido,campos.metodo,campos.porcentaje);
  if (!resultado || !Number.isFinite(resultado.venta) || resultado.venta < 0) throw new Error('Revisa el costo y el porcentaje de la calculadora.');
  if (resultado.venta > 99999999.99) throw new Error('El precio calculado supera el máximo admitido por el catálogo.');
  return {codigo:campos.codigo.trim(),nombre,categoria:campos.categoria,unidad:campos.unidad,precio:resultado.venta.toFixed(2)};
}
if (typeof module !== 'undefined') module.exports = {conceptoParaGuardarCalculo};
if (typeof document !== 'undefined') {
  const id = nombre => document.getElementById(nombre);
  const boton = id('calcGuardarCatalogo'), estado = id('calcCatalogoEstado');
  if (boton) boton.addEventListener('click',async () => {
    let datos;
    try {
      if (['calcCosto','calcPorcentaje'].some(nombre => !id(nombre).value || !id(nombre).checkValidity())) throw new Error('Revisa el costo y el porcentaje antes de guardar.');
      datos = conceptoParaGuardarCalculo({nombre:id('calcConcepto').value,codigo:id('calcCodigoCatalogo').value,categoria:id('calcTipoCatalogo').value,unidad:id('calcUnidadCatalogo').value,costo:id('calcCosto').valueAsNumber,incluido:id('calcIva').value === 'incluido',metodo:id('calcMetodo').value,porcentaje:id('calcPorcentaje').valueAsNumber},calcularVenta);
    } catch (error) { estado.textContent = error.message; return; }
    const parametros = new URLSearchParams(datos);
    parametros.set('csrf_token',id('cotizadorForm').elements.namedItem('csrf_token').value);
    boton.disabled = true; estado.textContent = 'Guardando concepto…';
    try {
      const response = await fetch('catalogo_guardar.php',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:parametros});
      if (response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) throw new Error('La sesión pudo haber expirado. Conserva la captura y vuelve a iniciar sesión.');
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'No se pudo guardar el concepto.');
      if (data.estado === 'creado') estado.textContent = 'Guardado: ' + data.nombre + ' a $' + datos.precio + ' sin IVA por unidad. Ya puedes buscarlo en Mis conceptos.';
      else if (data.estado === 'existente') estado.textContent = 'Ya existe un concepto coincidente. No se creó otro ni se modificó su precio. Usa Editar en Mis conceptos para actualizarlo.';
      else if (data.estado === 'inactivo') estado.textContent = 'Existe un concepto coincidente inactivo. Revísalo y actívalo desde Mis conceptos si corresponde.';
      else throw new Error('No se pudo confirmar el guardado. Revisa Mis conceptos antes de repetirlo.');
    } catch (error) { estado.textContent = error.message || 'No se pudo guardar el concepto.'; }
    finally { boton.disabled = false; }
  });
}
