'use strict';
const redondear = n => Math.round((n + Number.EPSILON) * 100) / 100;
// La base del escalón conserva decimales escritos manualmente y permite
// que las flechas avancen exactamente una unidad desde ese valor.
function minimoCantidad(valor) {
  if (!Number.isFinite(valor) || valor <= 0) return 1;
  const fraccion = redondear(valor - Math.floor(valor));
  return fraccion > 0 && fraccion < 1 ? fraccion : 1;
}
function calcularVenta(costo, incluido, metodo, porcentaje) {
  if (![costo, porcentaje].every(Number.isFinite) || costo < 0 || porcentaje < 0 || (metodo === 'margen' && porcentaje >= 100)) return null;
  const base = incluido ? costo / 1.16 : costo;
  const venta = redondear(metodo === 'margen' ? base / (1 - porcentaje / 100) : base * (1 + porcentaje / 100));
  return { base: redondear(base), venta, final: redondear(venta + redondear(venta * .16)), ganancia: redondear(venta - base) };
}
function calcularLineaDescuento(cantidad, precio, descuento=0) {
  if (![cantidad,precio,descuento].every(Number.isFinite) || cantidad < 0 || cantidad > 999999.99 || precio < 0 || precio > 99999999.99 || descuento < 0 || descuento > 100) return null;
  const q = Math.round(cantidad * 100), p = Math.round(precio * 100), d = Math.round(descuento * 100);
  const final = Math.floor((p * (10000-d) + 5000) / 10000);
  if (q*p > 9007199254740000) return null;
  const bruto = Math.floor((q*p+50)/100), importe = Math.floor((q*final+50)/100);
  return {precio:final/100,importe:importe/100,bruto:bruto/100,descuento:(bruto-importe)/100};
}
function calcularTotales(items) {
  const lineas = items.map(item => calcularLineaDescuento(item.cantidad,item.precio,item.descuento ?? 0));
  if (lineas.some(x => !x)) return null;
  const sumar = clave => lineas.reduce((n,x) => n + Math.round(x[clave]*100),0);
  const subtotalC = sumar('importe'), brutoC = sumar('bruto');
  if (!Number.isSafeInteger(subtotalC) || !Number.isSafeInteger(brutoC) || brutoC > 9007199254740000) return null;
  const ivaC = Math.floor((subtotalC*16+50)/100);
  if (subtotalC*16+50 > 9007199254740000 || !Number.isSafeInteger(subtotalC+ivaC)) return null;
  return {importes:lineas.map(x => x.importe),preciosFinales:lineas.map(x => x.precio),subtotal:subtotalC/100,iva:ivaC/100,total:(subtotalC+ivaC)/100,bruto:brutoC/100,descuentoTotal:(brutoC-subtotalC)/100};
}
if (typeof module !== 'undefined') module.exports = {calcularVenta, calcularTotales, redondear, minimoCantidad, calcularLineaDescuento};
if (typeof document !== 'undefined') {
  const porId = id => document.getElementById(id);
  const body = porId('productosBody');
  const moneda = n => new Intl.NumberFormat('es-MX', {style:'currency', currency:'MXN'}).format(n);
  const numero = el => Number.isFinite(el.valueAsNumber) ? el.valueAsNumber : 0;
  const configurarCantidad = input => { input.step = '1'; input.min = String(minimoCantidad(input.valueAsNumber)); };
  configurarCantidad(porId('catalogoCantidad'));
  porId('catalogoCantidad').addEventListener('input', () => configurarCantidad(porId('catalogoCantidad')));
  window.setDoc = (tipo, modo) => { porId('tipo_documento').value = tipo; porId('modo_pdf').value = modo; };
  function recalcular() {
    const filas = Array.from(body.querySelectorAll('tr'));
    filas.forEach(tr => configurarCantidad(tr.querySelector('.cantidad')));
    const t = calcularTotales(filas.map(tr => ({cantidad:Math.max(0,numero(tr.querySelector('.cantidad'))),precio:Math.max(0,numero(tr.querySelector('.precio'))),descuento:numero(tr.querySelector('.descuento'))})));
    porId('descuentosEstado').textContent = t ? '' : 'Revisa cantidades, precios y descuentos (0 a 100%).';
    if (!t) {
      filas.forEach(tr => {tr.querySelector('.importe').textContent = '—';tr.querySelector('.precio-final').textContent = '—';});
      ['subtotalVista','ivaVista','totalVista','brutoVista','descuentoVista','anticipoVista','saldoVista'].forEach(id => porId(id).textContent = '—');
      return;
    }
    filas.forEach((tr,i) => {tr.querySelector('.importe').textContent = moneda(t.importes[i]);tr.querySelector('.precio-final').textContent = moneda(t.preciosFinales[i]);});
    ['subtotal','iva','total'].forEach(k => porId(k+'Vista').textContent = moneda(t[k]));
    porId('brutoVista').textContent = moneda(t.bruto);
    porId('descuentoVista').textContent = moneda(t.descuentoTotal);
    const anticipo = redondear(t.total * Math.min(100,Math.max(0,numero(porId('anticipoPorcentaje')))) / 100);
    porId('anticipoVista').textContent = moneda(anticipo);
    porId('saldoVista').textContent = moneda(redondear(t.total-anticipo));
  }
  function agregarFila(concepto='', cantidad='', precio='', descuento=0) {
    let tr = body.querySelector('tr');
    if (!tr || tr.querySelector('[name="concepto[]"]').value.trim() || tr.querySelector('.cantidad').value || tr.querySelector('.precio').value) {
      tr = document.createElement('tr');
      tr.innerHTML = '<td><input type="text" name="concepto[]" required></td><td><input type="number" name="cantidad[]" class="cantidad" min="1" step="1" required></td><td><input type="number" name="precio[]" class="precio" min="0" step="0.01" required></td><td><input type="number" name="descuento[]" class="descuento" min="0" max="100" step="0.01" value="0" aria-label="Porcentaje de descuento"></td><td class="precio-final">$0.00</td><td class="importe">$0.00</td><td><button type="button" class="btn btn-secondary btn-duplicar">Duplicar</button> <button type="button" class="btn btn-danger btn-eliminar">Eliminar</button></td>';
      body.appendChild(tr);
    }
    tr.querySelector('[name="concepto[]"]').value = concepto;
    tr.querySelector('.cantidad').value = cantidad;
    tr.querySelector('.precio').value = precio;
    tr.querySelector('.descuento').value = descuento;
    recalcular();
    return tr;
  }
  window.addEventListener('oj:catalogo-agregar', e => {
    const d = e.detail;
    if (!d || typeof d.concepto !== 'string' || !Number.isFinite(d.cantidad) || d.cantidad <= 0 || !Number.isFinite(d.precio) || d.precio < 0) return;
    agregarFila(d.concepto, d.cantidad, d.precio.toFixed(2));
  });
  porId('agregar').addEventListener('click', () => agregarFila().querySelector('input').focus());
  body.addEventListener('input', recalcular);
  body.addEventListener('click', e => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    if (e.target.classList.contains('btn-duplicar')) agregarFila(tr.querySelector('[name="concepto[]"]').value, tr.querySelector('.cantidad').value, tr.querySelector('.precio').value,tr.querySelector('.descuento').value);
    if (e.target.classList.contains('btn-eliminar')) {
      if (body.children.length <= 1) { alert('Debe haber al menos un concepto.'); return; }
      tr.remove(); recalcular();
    }
  });
  porId('anticipoPorcentaje').addEventListener('input', recalcular);
  function actualizarCalculadora() {
    configurarCantidad(porId('calcCantidad'));
    const metodo = porId('calcMetodo').value;
    porId('calcPorcentaje').max = metodo === 'margen' ? '99.99' : '';
    porId('calcExplicacion').textContent = metodo === 'margen' ? 'Margen: venta = costo sin IVA ÷ (1 − porcentaje). Ejemplo: costo $100 y margen 30% → venta $142.86 sin IVA.' : 'Recargo: venta = costo sin IVA × (1 + porcentaje). Ejemplo: costo $100 y recargo 30% → venta $130.00 sin IVA.';
    const validos = ['calcCosto','calcPorcentaje','calcCantidad'].every(id => porId(id).value !== '' && porId(id).checkValidity());
    const resultado = validos ? calcularVenta(numero(porId('calcCosto')), porId('calcIva').value === 'incluido', metodo, numero(porId('calcPorcentaje'))) : null;
    const ids = {base:'calcBase',venta:'calcVenta',final:'calcFinal',ganancia:'calcGanancia'};
    Object.keys(ids).forEach(k => porId(ids[k]).textContent = resultado ? moneda(resultado[k]) : '—');
    porId('calcAgregar').disabled = !resultado;
    porId('calcEstado').textContent = resultado ? '' : 'Revisa los valores. El margen debe ser menor a 100% y la cantidad mayor que cero.';
    return resultado;
  }
  ['calcCosto','calcPorcentaje','calcCantidad','calcIva','calcMetodo'].forEach(id => porId(id).addEventListener('input', actualizarCalculadora));
  document.querySelectorAll('[data-porcentaje]').forEach(btn => btn.addEventListener('click', () => { porId('calcPorcentaje').value = btn.dataset.porcentaje; actualizarCalculadora(); }));
  porId('calcAgregar').addEventListener('click', () => {
    const r = actualizarCalculadora();
    if (!r) return;
    const concepto = porId('calcConcepto').value.trim();
    if (!concepto) { porId('calcEstado').textContent = 'Escribe el concepto antes de agregarlo.'; porId('calcConcepto').focus(); return; }
    agregarFila(concepto, numero(porId('calcCantidad')), r.venta.toFixed(2));
    porId('calcEstado').textContent = 'Concepto agregado con precio sin IVA. Puedes editarlo en la tabla.';
  });
  // Los botones de documento conservan su acción; Guardar borrador usa su propia ruta.
  porId('catalogoQuery').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); porId('catalogoBuscar').click(); }
  });
  porId('cotizadorForm').addEventListener('submit', e => {
    const boton = e.submitter;
    if (!boton || !boton.hasAttribute('onclick')) window.setDoc('cotizacion','ver');
  });
  actualizarCalculadora(); recalcular();
}
