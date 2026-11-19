'use strict';
(() => {
  const id=x=>document.getElementById(x),guardar=id('syscomGuardarPreferencias'),aplicar=id('syscomAplicarPreferencias'),estado=id('syscomPreferenciasEstado');
  if(!guardar||!aplicar||!estado)return;
  const campos={tipo_cambio:id('syscomTC'),metodo:id('syscomMetodo'),porcentaje:id('syscomPorcentaje')};
  const token=id('cotizadorForm').elements.namedItem('csrf_token').value;
  let preferencias=null,cargado=false,ocupado=false,editado=false;
  const valores=()=>Object.fromEntries(Object.entries(campos).map(([k,el])=>[k,el.value]));
  const inicial=JSON.stringify(valores());
  function controles(){guardar.disabled=ocupado||!cargado;aplicar.disabled=ocupado;}
  function describir(){
    if(!preferencias)return 'Todavía no tienes parámetros guardados. Captúralos y pulsa Guardar estos parámetros.';
    return 'Guardados para tu usuario: tipo de cambio '+preferencias.tipo_cambio+' MXN/USD; '+(preferencias.metodo==='margen'?'margen':'recargo')+' '+preferencias.porcentaje+'%. Último guardado: '+preferencias.fecha_local+' (Ciudad de México). Revisa el tipo de cambio antes de usarlo.';
  }
  function ponerGuardados(){
    if(!preferencias){estado.textContent=describir();return;}
    Object.entries(campos).forEach(([k,el])=>{el.value=preferencias[k];el.dispatchEvent(new Event('input',{bubbles:true}));});
    editado=false;estado.textContent=describir();
  }
  Object.values(campos).forEach(campo=>{
    const cambio=()=>{editado=true;if(cargado&&!ocupado)estado.textContent='Valores modificados en esta pantalla. Usa Guardar estos parámetros si quieres reutilizarlos en próximas cotizaciones. Los precios ya agregados se conservan.';};
    campo.addEventListener('input',cambio);campo.addEventListener('change',cambio);
  });
  async function peticion(accion,datos={}){
    const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
    try{
      const r=await fetch('syscom_preferencias.php',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:new URLSearchParams({csrf_token:token,accion,...datos}),signal:controller.signal});
      if(r.redirected)throw new Error('La sesión pudo haber expirado. Vuelve a iniciar sesión para guardar tus parámetros.');
      const texto=await r.text();let data;
      try{data=JSON.parse(texto);}catch{throw new Error('HTTP '+r.status+': no se pudieron consultar los parámetros. Puedes seguir cotizando con los campos manuales.');}
      if(!r.ok)throw new Error(data.error||'No se pudieron guardar los parámetros.');
      if(!data||typeof data!=='object'||!Object.prototype.hasOwnProperty.call(data,'preferencias'))throw new Error('La respuesta de parámetros no es válida.');
      return data.preferencias;
    }catch(e){if(e.name==='AbortError')throw new Error('La consulta de parámetros agotó el tiempo de espera. Usa Aplicar guardados para reintentar o sigue con los campos manuales.');throw e;}
    finally{clearTimeout(timer);}
  }
  async function leer(explicito){
    const antes=JSON.stringify(valores());
    ocupado=true;controles();
    try{
      preferencias=await peticion('leer');cargado=true;
      if(JSON.stringify(valores())===antes&&(explicito||(!editado&&antes===inicial)))ponerGuardados();
      else estado.textContent=describir()+' Se conservaron los valores que empezaste a capturar; usa Aplicar guardados si deseas sustituirlos.';
    }catch(e){estado.textContent=e.message;}
    finally{ocupado=false;controles();}
  }
  aplicar.addEventListener('click',()=>{if(!ocupado)leer(true);});
  guardar.addEventListener('click',async()=>{
    if(ocupado||!cargado)return;
    for(const campo of [campos.tipo_cambio,campos.porcentaje]){
      if(!campo.value||!campo.checkValidity()){campo.reportValidity();estado.textContent='Captura un tipo de cambio y porcentaje válidos antes de guardar.';return;}
    }
    if(campos.metodo.value==='margen'&&campos.porcentaje.valueAsNumber>=100){estado.textContent='El margen debe ser menor a 100%.';return;}
    ocupado=true;controles();estado.textContent='Guardando parámetros para tu usuario…';
    try{
      preferencias=await peticion('guardar',{...valores(),version:String(preferencias?preferencias.version:0)});
      // No reemplaza los campos: podrías haberlos editado mientras respondía el servidor.
      const mismos=preferencias&&Number(campos.tipo_cambio.value)===Number(preferencias.tipo_cambio)&&campos.metodo.value===preferencias.metodo&&Number(campos.porcentaje.value)===Number(preferencias.porcentaje);
      editado=!mismos;estado.textContent='Parámetros guardados correctamente. '+describir()+(mismos?'':' Hay valores más recientes en pantalla que aún no se guardaron.');
    }catch(e){estado.textContent=e.message;}
    finally{ocupado=false;controles();}
  });
  leer(false);
})();
