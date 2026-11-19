'use strict';
(() => {
 const root=document.getElementById('syscomDescarga');if(!root)return;
 const id=x=>document.getElementById(x),token=root.querySelector('[name="csrf_token"]').value;
 let tarea=null,cargado=false,ocupado=false,leidoEn=Date.now();
 const pendientes=()=>tarea&&['descargando','importando','pausado'].includes(tarea.estado);
 const espera=()=>tarea?Math.max(0,tarea.espera_segundos-Math.floor((Date.now()-leidoEn)/1000)):0;
 function controles(){
  id('ojauGuardar').disabled=ocupado||!cargado||Boolean(pendientes());id('ojauEnlace').disabled=ocupado||Boolean(pendientes());
  id('ojauIniciar').disabled=ocupado||!cargado||!tarea||Boolean(pendientes())||espera()>0;
  id('ojauContinuar').hidden=!tarea||!['importando','pausado'].includes(tarea.estado);id('ojauContinuar').disabled=ocupado;
  id('ojauCancelar').hidden=!pendientes();id('ojauCancelar').disabled=ocupado;id('ojauRevisar').disabled=ocupado;
  id('ojauEspera').textContent=espera()>0?'Nuevo intento de descarga disponible aproximadamente en '+Math.ceil(espera()/60)+' minuto(s). Puedes continuar una importación ya descargada.':'';
 }
 function pintar(d){
  tarea=d.tarea;cargado=true;leidoEn=Date.now();
  id('ojauActivo').textContent=d.activo?'Catálogo activo: '+d.activo.filas+' productos · '+d.activo.archivo+'.':'Todavía no hay un catálogo CSV activo para tu usuario.';
  id('ojauHistorial').textContent=tarea?('Enlace del reporte 1758 guardado. Último intento: '+(tarea.ultimo_intento||'ninguno')+'. Última actualización completada: '+(tarea.ultimo_exito||'ninguna')+(tarea.ultimo_exito?' · '+tarea.ultimas_filas+' productos':'')+'. Horarios de Ciudad de México.'):'Guarda el enlace del reporte 1758 para comenzar.';
  id('ojauProgreso').value=tarea&&tarea.bytes?Math.min(100,Math.floor(tarea.offset/tarea.bytes*100)):0;
  id('ojauEstado').textContent=tarea?(tarea.mensaje+' Productos confirmados en esta actualización: '+tarea.filas+'.'):'Sin configurar.';
  controles();
 }
 async function pedir(accion,datos={}){
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),accion==='iniciar'?95000:30000);
  try{
   const r=await fetch('syscom_descarga_api.php',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:new URLSearchParams({csrf_token:token,accion,version:String(tarea?tarea.version:0),...datos}),signal:controller.signal});
   if(r.redirected)throw new Error('La sesión pudo haber expirado. Inicia sesión y pulsa Revisar estado.');
   const texto=await r.text();let d;try{d=JSON.parse(texto);}catch{throw new Error('HTTP '+r.status+': no se recibió una respuesta válida. Pulsa Revisar estado antes de reintentar.');}
   if(!d||typeof d!=='object'||Array.isArray(d))throw new Error('La respuesta de actualización no es válida.');
   if(!r.ok){if(Object.prototype.hasOwnProperty.call(d,'tarea'))pintar(d);throw new Error(typeof d.error==='string'?d.error:'No se completó la actualización.');}
   if(!Object.prototype.hasOwnProperty.call(d,'tarea')||!Object.prototype.hasOwnProperty.call(d,'activo'))throw new Error('Falta el estado de la actualización en la respuesta.');
   pintar(d);return d;
  }catch(e){if(e.name==='AbortError')throw new Error('Se agotó el tiempo de espera. Pulsa Revisar estado; no descargues otra vez sin comprobar el resultado.');throw e;}
  finally{clearTimeout(timer);}
 }
 async function ejecutar(fn){if(ocupado)return;ocupado=true;controles();try{await fn();}catch(e){id('ojauEstado').textContent=e.message;}finally{ocupado=false;controles();}}
 async function lotes(){
  while(tarea&&tarea.estado==='importando'){
   await pedir('procesar');if(tarea&&tarea.estado==='importando')await new Promise(r=>setTimeout(r,150));
  }
 }
 id('ojauConfig').addEventListener('submit',e=>{e.preventDefault();return ejecutar(async()=>{
  await pedir('guardar',{enlace:id('ojauEnlace').value});id('ojauEnlace').value='';
 });});
 id('ojauIniciar').addEventListener('click',()=>ejecutar(async()=>{
  if(!id('ojauConfirmar').checked)throw new Error('Confirma que Su Precio está en USD sin IVA antes de actualizar.');
  if(!tarea||pendientes()||espera()>0)return;
  id('ojauEstado').textContent='Descargando el reporte al hosting. Puede tardar hasta un minuto…';
  await pedir('iniciar',{confirmar:'1'});await lotes();
 }));
 id('ojauContinuar').addEventListener('click',()=>ejecutar(async()=>{if(!tarea||!['importando','pausado'].includes(tarea.estado))return;await pedir('procesar');await lotes();}));
 id('ojauCancelar').addEventListener('click',()=>ejecutar(async()=>{if(pendientes())await pedir('cancelar');}));
 id('ojauRevisar').addEventListener('click',()=>ejecutar(()=>pedir('estado')));
 window.addEventListener('beforeunload',e=>{if(ocupado){e.preventDefault();e.returnValue='';}});
 setInterval(controles,1000);
 ejecutar(()=>pedir('estado'));
})();
