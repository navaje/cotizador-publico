'use strict';
(() => {
  const id=x=>document.getElementById(x);if(!id('syscomCotizador'))return;
  const token=id('cotizadorForm').elements.namedItem('csrf_token').value;
  const moneda=n=>new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'}).format(n);
  const actualizar=id('syscomActualizarAPI'),usarCSV=id('syscomUsarCSV'),estadoAPI=id('syscomEnLineaEstado');
  let seleccionado=null,productoCSV=null,seleccion=0,actualizando=false,revisionPendiente=false,apiController=null;
  let buscando=false,busquedaNumero=0,busquedaController=null,ultimaBusqueda=null,pagina=1,hayMas=false,catalogo=null,errorCatalogo='';
  function fuente(){
    id('syscomFuente').textContent=id('syscomOrigen').value==='api'
      ? 'Origen: API SYSCOM de tu usuario. No requiere CSV. Consulta USD sin IVA y existencia nueva Veracruz. Las categorías del CSV no se aplican a esta búsqueda.'
      : (catalogo?'Archivo: '+catalogo.archivo+' · Importado: '+catalogo.actualizado_en+' · '+catalogo.filas+' productos. Existencias y costos de esa descarga.':(errorCatalogo||'Importa un archivo CSV o elige SYSCOM en línea (API).'));
  }
  function cancelarAPI(){if(apiController)apiController.abort();apiController=null;actualizando=false;revisionPendiente=false;actualizar.disabled=false;actualizar.textContent='Actualizar precio y existencia (API)';usarCSV.hidden=true;estadoAPI.textContent='';}
  function limpiarSeleccion(){++seleccion;cancelarAPI();seleccionado=null;productoCSV=null;id('syscomSeleccion').hidden=true;calcular();}
  function invalidarBusqueda(){
    ++busquedaNumero;if(busquedaController)busquedaController.abort();busquedaController=null;buscando=false;id('syscomBuscar').disabled=false;
    ultimaBusqueda=null;id('syscomPaginacion').hidden=true;id('syscomResultados').replaceChildren();limpiarSeleccion();id('syscomEstado').textContent='Pulsa Buscar para consultar con estos filtros.';
  }
  function mostrarProducto(){
    if(!seleccionado)return;const p=seleccionado,enLinea=p.fuente==='api';
    id('syscomProductoTitulo').textContent=p.modelo+' · '+p.marca;
    id('syscomProductoDatos').textContent='Costo de tu cuenta USD sin IVA: '+p.costo_usd+' · Existencia nueva Veracruz '+(enLinea?'(API): ':'del archivo: ')+(p.veracruz===null?'no informada':p.veracruz)+' · '+p.titulo;
    if(!actualizando)usarCSV.hidden=!productoCSV||(!enLinea&&!revisionPendiente);
  }
  function resumenAPI(){
    const p=seleccionado;
    return 'Origen: API SYSCOM · Consulta: '+p.fecha_local+' (Ciudad de México).'+(productoCSV?' Costo del CSV: '+productoCSV.costo_usd+' USD.':'')+' Se calculó la venta con tus parámetros actuales.'+(p.veracruz===null?' SYSCOM no informó la existencia Veracruz.':'')+' Confirma disponibilidad antes de comprar.';
  }
  function calcular(){
    const r=seleccionado?syscomCalcular(seleccionado.costo_usd,id('syscomTC').value,id('syscomMetodo').value,id('syscomPorcentaje').value):null;
    id('syscomCosto').textContent=r?moneda(r.costo):'—';id('syscomVenta').textContent=r?moneda(r.venta):'—';id('syscomFinal').textContent=r?moneda(r.total):'—';
    id('syscomAgregar').disabled=!r||actualizando||revisionPendiente;
    id('syscomCalculoEstado').textContent=r?('Ganancia unitaria antes de gastos: '+moneda(r.ganancia)+'. El IVA se calcula sobre el precio de venta.'):'Captura un tipo de cambio mayor que cero y un porcentaje válido. El margen debe ser menor a 100%. Los productos con costo cero no se pueden agregar automáticamente.';
    if(actualizando)id('syscomCalculoEstado').textContent='Consultando SYSCOM. Espera el resultado antes de agregar.';
    else if(revisionPendiente)id('syscomCalculoEstado').textContent='No se actualizó el producto. Reintenta la API'+(productoCSV?' o pulsa Usar datos del CSV para continuar con esa descarga.':' o vuelve a buscar y seleccionar el producto.');
    return r;
  }
  function productoValido(p,esperado=null){
    return p&&/^[1-9]\d{0,8}$/.test(String(p.id))&&(esperado===null||String(p.id)===esperado)&&p.fuente==='api'&&typeof p.modelo==='string'&&p.modelo!==''&&typeof p.marca==='string'&&typeof p.titulo==='string'&&/^\d{1,10}(?:\.\d{1,4})?$/.test(String(p.costo_usd))&&Number(p.costo_usd)>0&&(p.veracruz===null||(Number.isInteger(p.veracruz)&&p.veracruz>=0))&&typeof p.fecha_local==='string';
  }
  async function peticionAPI(ruta,datos,controller){
    const timer=setTimeout(()=>controller.abort(),110000);
    try{
      const r=await fetch(ruta,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:new URLSearchParams({csrf_token:token,...datos}),signal:controller.signal});
      if(r.redirected)throw new Error('La sesión pudo haber expirado. Vuelve a iniciar sesión y reintenta.');
      const texto=await r.text();let d;try{d=JSON.parse(texto);}catch{throw new Error('HTTP '+r.status+': el hosting no devolvió una respuesta válida para la consulta en línea.');}
      if(!d||typeof d!=='object'||Array.isArray(d))throw new Error('Respuesta en línea no válida.');
      if(!r.ok)throw new Error(typeof d.error==='string'?d.error:'No se pudo consultar SYSCOM en línea.');
      return d;
    }catch(e){if(e.name==='AbortError')throw new Error('La consulta en línea se interrumpió o agotó su tiempo de espera.');throw e;}
    finally{clearTimeout(timer);}
  }
  function ponerSeleccion(p,csv){
    productoCSV=csv;seleccionado={...p};mostrarProducto();
    const concepto=p.modelo+(p.titulo?' · '+p.titulo:'');id('syscomConcepto').value=Array.from(concepto).slice(0,255).join('');
    estadoAPI.textContent=p.fuente==='api'?resumenAPI():'Datos del CSV. Usa Actualizar precio y existencia (API) para consultar tu cuenta SYSCOM.';
    id('syscomSeleccion').hidden=false;calcular();id('syscomTC').focus();
  }
  function pintar(items,d,origen){
    items.forEach(p=>{
      const fila=document.createElement('div');fila.className='syscom-producto';
      const texto=document.createElement('div'),titulo=document.createElement('strong'),detalle=document.createElement('p');
      titulo.textContent=p.modelo+' · '+(p.titulo||'Sin título');
      detalle.textContent=p.marca+' · Costo USD sin IVA: '+p.costo_usd+' · Existencia nueva Veracruz '+(origen==='api'?'(API): ':'del archivo: ')+(p.veracruz===null?'no informada':p.veracruz);
      detalle.className='syscom-ayuda';texto.append(titulo,detalle);
      const elegir=document.createElement('button');elegir.type='button';elegir.className='syscom-btn';elegir.textContent=Number(p.costo_usd)>0?'Calcular precio de venta':'Seleccionar y consultar API';
      elegir.addEventListener('click',async()=>{
        if(elegir.disabled)return;
        limpiarSeleccion();const turno=seleccion;elegir.disabled=true;
        try{
          if(origen==='api'){
            const controller=new AbortController();apiController=controller;id('syscomEstado').textContent='Confirmando precio y existencia del producto seleccionado…';
            const r=await peticionAPI('syscom_en_linea.php',{producto:String(p.id)},controller);
            if(turno!==seleccion)return;
            if(!productoValido(r.producto,String(p.id)))throw new Error('La respuesta no contiene el producto y costo esperados.');
            ponerSeleccion(r.producto,null);id('syscomEstado').textContent='Producto seleccionado desde API SYSCOM.';
          }else{
            const r=await syscomPeticion(token,'producto',{producto:String(p.id),carga:String(d.activa.id)});
            if(turno!==seleccion)return;
            catalogo=r.activa;fuente();const csv={...r.producto,fuente:'csv'};ponerSeleccion(csv,csv);
          }
        }catch(e){if(turno===seleccion)id('syscomEstado').textContent=e.message;}
        finally{if(turno===seleccion)apiController=null;elegir.disabled=false;}
      });
      fila.append(texto,elegir);id('syscomResultados').append(fila);
    });
  }
  function leerFiltros(){return {origen:id('syscomOrigen').value,q:id('syscomQuery').value,categoria:id('syscomCategoria').value,veracruz:id('syscomVeracruz').checked?'1':'0',tipo:id('syscomTipoBusqueda').value};}
  async function buscar(filtros,n=1){
    if(buscando)return;
    const turno=++busquedaNumero;buscando=true;id('syscomBuscar').disabled=true;id('syscomPaginacion').hidden=true;id('syscomEstado').textContent='Buscando…';
    id('syscomResultados').replaceChildren();limpiarSeleccion();
    try{
      let d;
      if(filtros.origen==='api'){
        const controller=new AbortController();busquedaController=controller;
        d=await peticionAPI('syscom_busqueda.php',{q:filtros.q,tipo:filtros.tipo,veracruz:filtros.veracruz,pagina:String(n)},controller);
        if(!Array.isArray(d.items)||!d.items.every(p=>productoValido(p))||d.fuente!=='api'||d.pagina!==n||typeof d.hay_mas!=='boolean')throw new Error('La búsqueda no devolvió una lista de productos válida.');
      }else d=await syscomPeticion(token,'buscar',{q:filtros.q,categoria:filtros.categoria,veracruz:filtros.veracruz});
      if(turno!==busquedaNumero)return;
      if(filtros.origen==='csv')catalogo=d.activa;
      fuente();pintar(d.items,d,filtros.origen);ultimaBusqueda={...filtros};pagina=n;hayMas=d.hay_mas;
      let mensaje=d.items.length?d.items.length+' resultado(s) en '+(filtros.origen==='api'?'API SYSCOM, página '+pagina:'el catálogo CSV')+'.':'No hay coincidencias utilizables en esta página con estos filtros.';
      if(filtros.origen==='api'){
        if(d.omitidos)mensaje+=' Se omitieron '+d.omitidos+' producto(s) sin costo o datos utilizables.';
        if(d.sin_stock)mensaje+=' Se omitieron '+d.sin_stock+' producto(s) sin existencia nueva Veracruz confirmada.';
        id('syscomPaginacion').hidden=pagina===1&&!hayMas;id('syscomPaginaAnterior').disabled=pagina<=1;id('syscomPaginaSiguiente').disabled=!hayMas;id('syscomPaginaActual').textContent='Página '+pagina;
        if(hayMas)mensaje+=' Puedes ver la siguiente página.';
      }else if(hayMas)mensaje+=' Hay más: especifica mejor el modelo o la categoría.';
      id('syscomEstado').textContent=mensaje;
    }catch(e){if(turno===busquedaNumero){ultimaBusqueda=null;id('syscomEstado').textContent=e.message+' Puedes elegir Mi catálogo CSV para buscar en tu descarga.';}}
    finally{if(turno===busquedaNumero){buscando=false;busquedaController=null;id('syscomBuscar').disabled=false;}}
  }
  id('syscomBuscar').addEventListener('click',()=>buscar(leerFiltros()));
  id('syscomPaginaAnterior').addEventListener('click',()=>{if(ultimaBusqueda&&pagina>1)return buscar(ultimaBusqueda,pagina-1);});
  id('syscomPaginaSiguiente').addEventListener('click',()=>{if(ultimaBusqueda&&hayMas)return buscar(ultimaBusqueda,pagina+1);});
  id('syscomOrigen').addEventListener('change',()=>{invalidarBusqueda();configurarOrigen();});
  function configurarOrigen(){const api=id('syscomOrigen').value==='api';id('syscomCategoria').disabled=api;id('syscomTipoZona').hidden=!api;id('syscomQuery').maxLength=api?120:100;id('syscomBuscar').textContent=api?'Buscar en SYSCOM en línea':'Buscar en catálogo CSV';fuente();}
  id('syscomQuery').addEventListener('input',invalidarBusqueda);
  ['syscomCategoria','syscomVeracruz','syscomTipoBusqueda'].forEach(k=>id(k).addEventListener('change',invalidarBusqueda));
  id('syscomQuery').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();id('syscomBuscar').click();}});
  ['syscomTC','syscomMetodo','syscomPorcentaje'].forEach(k=>id(k).addEventListener('input',calcular));id('syscomMetodo').addEventListener('change',calcular);
  const cantidad=id('syscomCantidad');cantidad.addEventListener('input',()=>{cantidad.step='1';cantidad.min=String(minimoCantidad(cantidad.valueAsNumber));});
  actualizar.addEventListener('click',async()=>{
    if(!seleccionado||actualizando)return;
    const turno=seleccion,idProducto=String(seleccionado.id),controller=new AbortController();apiController=controller;
    actualizando=true;revisionPendiente=true;actualizar.disabled=true;usarCSV.hidden=true;actualizar.textContent='Consultando SYSCOM…';estadoAPI.textContent='Solicitando costo USD sin IVA y existencia nueva Veracruz…';calcular();
    try{
      const d=await peticionAPI('syscom_en_linea.php',{producto:idProducto},controller);
      if(turno!==seleccion)return;
      if(!productoValido(d.producto,idProducto))throw new Error('La respuesta no contiene el producto y costo esperados.');
      seleccionado={...seleccionado,...d.producto};revisionPendiente=false;mostrarProducto();estadoAPI.textContent=resumenAPI();
    }catch(e){if(turno===seleccion)estadoAPI.textContent=e.message+' No se cambió el costo. Reintenta'+(productoCSV?' o elige Usar datos del CSV.':'.');}
    finally{if(turno===seleccion){apiController=null;actualizando=false;actualizar.disabled=false;actualizar.textContent='Actualizar precio y existencia (API)';mostrarProducto();calcular();}}
  });
  usarCSV.addEventListener('click',()=>{if(actualizando||!productoCSV)return;seleccionado={...productoCSV};revisionPendiente=false;mostrarProducto();calcular();estadoAPI.textContent='Usando el costo y la existencia del CSV por tu elección. Revisa la fecha de importación.';});
  id('syscomAgregar').addEventListener('click',()=>{
    if(actualizando||revisionPendiente)return;const r=calcular(),concepto=id('syscomConcepto').value.trim();if(!r||!seleccionado)return;
    if(!concepto){id('syscomCalculoEstado').textContent='Escribe la descripción para el documento.';id('syscomConcepto').focus();return;}
    if(!cantidad.value||!cantidad.checkValidity()||!Number.isFinite(cantidad.valueAsNumber)||cantidad.valueAsNumber<=0||cantidad.valueAsNumber>999999.99){cantidad.reportValidity();id('syscomCalculoEstado').textContent='Revisa la cantidad antes de agregar.';return;}
    window.dispatchEvent(new CustomEvent('oj:catalogo-agregar',{detail:{concepto,cantidad:cantidad.valueAsNumber,precio:r.venta}}));
    id('syscomCalculoEstado').textContent='Agregado: '+concepto+' a '+moneda(r.venta)+' MXN sin IVA por unidad. Guarda el borrador para conservarlo.';
  });
  configurarOrigen();
  syscomPeticion(token,'estado').then(d=>{catalogo=d.activa;d.categorias.forEach(c=>{const o=document.createElement('option');o.value=c;o.textContent=c;id('syscomCategoria').append(o);});fuente();}).catch(e=>{errorCatalogo=e.message;fuente();});
})();
