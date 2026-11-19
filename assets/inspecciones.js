(()=>{
'use strict';
const data=JSON.parse(document.getElementById('form-data').textContent),schema=JSON.parse(document.getElementById('schema').textContent),statuses=JSON.parse(document.getElementById('statuses').textContent),closed=window.ojcClosed;
const root=document.getElementById('editor');
const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
function field(parent,obj,key,label,type='text',options=null){
 const wrap=el('label',label),n=el(options?'select':type==='textarea'?'textarea':'input');
 if(options){Object.entries(options).forEach(([v,t])=>{const o=el('option',t);o.value=v;n.append(o);});if(obj[key]===undefined)obj[key]=Object.keys(options)[0];}
 else if(type!=='textarea')n.type=type;
 n.value=obj[key]??'';n.disabled=closed;n.setAttribute('aria-label',label);
 if(type==='number'){n.min='0';n.step='1';n.max=['instaladas','operativas'].includes(key)?'64':'10000';}
 const limits={tipo:100,marca:100,modelo:160,numero:30,canal:30,tecnico:160,motivo_visita:1000,inventario_notas:1000,observacion_inicial:1000,observacion_final:1000,mantenimiento:1500,accion:1500,hallazgo:1500,responsable:160,trabajos:2000,materiales:1500,recomendaciones:1500,receptor:160,motivo_sin_firma:500};
 if(!options)n.maxLength=limits[key]??(type==='textarea'?1500:200);
 n.addEventListener('input',()=>obj[key]=n.value);n.addEventListener('change',()=>obj[key]=n.value);wrap.append(n);parent.append(wrap);return n;
}
function action(parent,text,fn,danger=false){if(closed)return;const b=el('button',text,danger?'danger':'');b.type='button';b.addEventListener('click',fn);parent.append(b);}
function pairs(parent,obj,checks){obj.checks??={};Object.entries(checks).forEach(([key,label])=>{obj.checks[key]??={inicial:'pendiente',final:'pendiente'};const row=el('div',undefined,'checkrow');row.append(el('span',label));field(row,obj.checks[key],'inicial','Inicial','text',statuses);field(row,obj.checks[key],'final','Final','text',statuses);parent.append(row);});}
function heading(text){root.append(el('h2',text));}
heading('1. Datos de la visita');const general=el('div',undefined,'grid');root.append(general);
field(general,data,'fecha','Fecha de visita','date');field(general,data,'tecnico','Técnico responsable');
field(general,data,'sistema','Sistema','text',{analogico:'Analógico',ip:'IP',hibrido:'Híbrido'});
field(general,data,'visita','Motivo de visita','text',{inspeccion:'Inspección',preventivo:'Mantenimiento preventivo',falla:'Revisión de falla'});
field(general,data,'instaladas','Cámaras instaladas (hasta 64 fichas)','number');field(general,data,'operativas','Cámaras operativas','number');
field(general,data,'dias_requeridos','Días de grabación requeridos','number');field(general,data,'dias_disponibles','Días de grabación comprobados disponibles','number');
field(root,data,'motivo_visita','Descripción de la visita / falla reportada','textarea');
heading('2. Inventario del sistema');data.inventario??=[];const inventory=el('div');root.append(inventory);
function drawInventory(){inventory.replaceChildren();data.inventario.forEach((item,i)=>{const card=el('article',undefined,'card'),g=el('div',undefined,'grid');card.append(el('h3','Equipo '+(i+1)));card.append(g);field(g,item,'tipo','Tipo (DVR, NVR, disco, fuente, switch, UPS…)');field(g,item,'marca','Marca');field(g,item,'modelo','Modelo / capacidad');field(g,item,'ubicacion','Ubicación');field(g,item,'estado','Estado al cierre','text',{pendiente:'Pendiente de revisar',operativo:'Operativo',atencion:'Requiere atención',falla:'Con falla'});action(card,'Quitar equipo',()=>{data.inventario.splice(i,1);drawInventory();},true);inventory.append(card);});}
drawInventory();action(root,'Añadir equipo',()=>{if(data.inventario.length>=50)return alert('Máximo 50 equipos.');data.inventario.push({tipo:'',marca:'',modelo:'',ubicacion:'',estado:'pendiente'});drawInventory();});field(root,data,'inventario_notas','Observaciones del inventario','textarea');
heading('3. Revisión individual de cámaras');data.camaras??=[];const cameras=el('div');root.append(cameras);
function drawCameras(){cameras.replaceChildren();data.camaras.forEach((cam,i)=>{const card=el('details');card.open=data.camaras.length<=4;card.append(el('summary','Cámara '+(cam.numero||i+1)+' · '+(cam.ubicacion||'Ubicación por completar')));const g=el('div',undefined,'grid');card.append(g);field(g,cam,'numero','Número / identificación');field(g,cam,'canal','Canal / nombre del canal');field(g,cam,'ubicacion','Ubicación');field(g,cam,'marca_modelo','Marca y modelo');pairs(card,cam,schema.camera);field(card,cam,'observacion_inicial','Hallazgos iniciales','textarea');field(card,cam,'mantenimiento','Limpieza, ajustes, reparación o mantenimiento realizado','textarea');field(card,cam,'observacion_final','Resultado final / pendientes de esta cámara','textarea');action(card,'Quitar cámara',()=>{if(confirm('¿Quitar esta ficha de la versión nueva?')){data.camaras.splice(i,1);drawCameras();}},true);cameras.append(card);});}
drawCameras();action(root,'Añadir cámara',()=>{if(data.camaras.length>=64)return alert('Máximo 64 cámaras.');const bytes=crypto.getRandomValues(new Uint8Array(6));const uid='cam-'+Array.from(bytes,v=>v.toString(16).padStart(2,'0')).join('');data.camaras.push({uid,numero:String(data.camaras.length+1).padStart(2,'0'),canal:'',ubicacion:'',marca_modelo:'',checks:{}});drawCameras();});
heading('4. Revisión general del sistema');data.grupos??={};Object.entries(schema.groups).forEach(([key,group])=>{data.grupos[key]??={checks:{}};const block=data.grupos[key],card=el('details');card.open=true;card.append(el('summary',group.label));pairs(card,block,group.checks);field(card,block,'observacion_inicial','Observaciones iniciales','textarea');field(card,block,'accion','Mantenimiento o acción realizada','textarea');field(card,block,'observacion_final','Resultado final / pendientes','textarea');root.append(card);});
heading('5. Hallazgos y acciones correctivas');root.append(el('p','Registra cada falla con su equipo o ubicación. Los pendientes deben tener responsable y fecha objetivo.'));data.hallazgos??=[];const findings=el('div');root.append(findings);
function drawFindings(){findings.replaceChildren();data.hallazgos.forEach((item,i)=>{const card=el('article',undefined,'card'),g=el('div',undefined,'grid');card.append(el('h3','Hallazgo '+(i+1)));field(card,item,'equipo','Cámara, equipo o ubicación');field(card,item,'hallazgo','Hallazgo / falla encontrada','textarea');field(card,item,'accion','Acción realizada o propuesta','textarea');card.append(g);field(g,item,'prioridad','Prioridad','text',{critica:'Crítica',alta:'Alta',media:'Media',baja:'Baja'});field(g,item,'estado','Estado','text',{abierto:'Abierto',en_proceso:'En proceso',corregido:'Corregido'});field(g,item,'responsable','Responsable de la corrección');field(g,item,'fecha_objetivo','Fecha objetivo','date');action(card,'Quitar hallazgo',()=>{data.hallazgos.splice(i,1);drawFindings();},true);findings.append(card);});}
drawFindings();action(root,'Añadir hallazgo',()=>{if(data.hallazgos.length>=80)return alert('Máximo 80 hallazgos.');data.hallazgos.push({equipo:'',hallazgo:'',accion:'',prioridad:'media',estado:'abierto',responsable:'',fecha_objetivo:''});drawFindings();});
heading('6. Cierre técnico');field(root,data,'resultado','Resultado general','text',{pendiente:'Pendiente de concluir',operativo:'Operativo',con_pendientes:'Operativo con pendientes',critico:'Con fallas críticas'});field(root,data,'trabajos','Resumen de trabajos realizados','textarea');field(root,data,'materiales','Materiales utilizados o necesarios','textarea');field(root,data,'recomendaciones','Recomendaciones y pendientes','textarea');field(root,data,'proxima_revision','Próxima revisión propuesta','date');
const reception=document.getElementById('reception');reception.append(el('h2','Recepción del reporte'));field(reception,data,'recepcion','Recepción','text',{pendiente:'Firma de recepción pendiente',firmado:'Reporte recibido y firmado'});field(reception,data,'receptor','Nombre de quien recibe el reporte');field(reception,data,'motivo_sin_firma','Motivo si queda pendiente la firma','textarea');
reception.append(el('p','La firma acredita la recepción del reporte y sus pendientes. No sustituye el acta de aceptación del trabajo.'));
const canvas=el('canvas');canvas.width=640;canvas.height=220;canvas.setAttribute('aria-label','Área para firma de recepción');reception.append(canvas);const ctx=canvas.getContext('2d');
function clear(){ctx.fillStyle='#fff';ctx.fillRect(0,0,640,220);ctx.strokeStyle='#111';ctx.lineWidth=2;ctx.lineCap='round';}
clear();if(data.firma){const img=new Image();img.onload=()=>ctx.drawImage(img,0,0,640,220);img.src=data.firma;}
let drawing=false;const point=e=>{const r=canvas.getBoundingClientRect();return[(e.clientX-r.left)*640/r.width,(e.clientY-r.top)*220/r.height];};
if(!closed){canvas.addEventListener('pointerdown',e=>{drawing=true;canvas.setPointerCapture(e.pointerId);const [x,y]=point(e);ctx.beginPath();ctx.moveTo(x,y);});canvas.addEventListener('pointermove',e=>{if(!drawing)return;const [x,y]=point(e);ctx.lineTo(x,y);ctx.stroke();});const finish=()=>{if(drawing){drawing=false;data.firma=canvas.toDataURL('image/jpeg',.85);}};canvas.addEventListener('pointerup',finish);canvas.addEventListener('pointercancel',finish);action(reception,'Limpiar firma',()=>{clear();data.firma='';});}
const ack=el('label',undefined,'check'),box=el('input');box.type='checkbox';box.checked=!!data.recepcion_confirmada;box.disabled=closed;box.addEventListener('change',()=>data.recepcion_confirmada=box.checked);ack.append(box,document.createTextNode('Quien firma confirma: recibí el reporte y conozco los pendientes asentados.'));reception.append(ack);
// Compresión local: facilita adjuntar evidencia desde el celular.
let resizing=0;
document.querySelectorAll('.photo-upload').forEach(input=>input.addEventListener('change',async()=>{
 const file=input.files[0];if(!file)return;
 if(!['image/jpeg','image/png'].includes(file.type)){alert('Usa JPG o PNG; convierte HEIC antes de adjuntar.');input.value='';return;}
 if(typeof DataTransfer==='undefined')return;
 resizing++;try{const url=URL.createObjectURL(file),img=new Image();try{await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=reject;img.src=url;});const scale=Math.min(1,1600/Math.max(img.width,img.height)),c=document.createElement('canvas');c.width=Math.max(1,Math.round(img.width*scale));c.height=Math.max(1,Math.round(img.height*scale));const cx=c.getContext('2d');cx.fillStyle='#fff';cx.fillRect(0,0,c.width,c.height);cx.drawImage(img,0,0,c.width,c.height);const blob=await new Promise(resolve=>c.toBlob(resolve,'image/jpeg',.82));if(!blob)throw new Error('Imagen no procesada');const transfer=new DataTransfer();transfer.items.add(new File([blob],'evidencia.jpg',{type:'image/jpeg'}));input.files=transfer.files;}finally{URL.revokeObjectURL(url);}}catch(e){if(file.size>2097152){alert('Reduce esta imagen a menos de 2 MB antes de adjuntarla.');input.value='';}}finally{resizing--;}
}));
function closureHelp(){
 const issues=[],finalIssues=[],problem=v=>['atencion','no_cumple'].includes(v);
 const finalSelects=Array.from(root.querySelectorAll('.checkrow select[aria-label="Final"]'));
 finalSelects.forEach(n=>{if(problem(n.value)){const row=n.closest('.checkrow'),section=n.closest('details');finalIssues.push({text:section.querySelector('summary').textContent+' / '+row.querySelector('span').textContent+' / Final: '+statuses[n.value],target:n});}});
 data.inventario.forEach((eq,i)=>{if(['atencion','falla'].includes(eq.estado))finalIssues.push({text:'Inventario / Equipo '+(i+1)+' / '+eq.tipo+' / '+eq.ubicacion+' / '+(eq.estado==='falla'?'Con falla':'Requiere atención'),target:inventory.querySelectorAll('select')[i]});});
 if(Number(data.operativas)<Number(data.instaladas))finalIssues.push({text:'Cámaras operativas ('+data.operativas+') menores que instaladas ('+data.instaladas+')',target:general.querySelector('[aria-label="Cámaras operativas"]')});
 const open=data.hallazgos.some(h=>h.estado!=='corregido');
 if(finalIssues.length&&!open){
  issues.push({text:'Hay problemas al cierre y todos los hallazgos están corregidos, o no hay hallazgos. Si el problema continúa, añade o actualiza un hallazgo como Abierto o En proceso, con responsable y fecha objetivo.',target:findings});
  issues.push(...finalIssues);
 }
 if(!data.hallazgos.length&&!finalIssues.length&&Array.from(root.querySelectorAll('.checkrow select')).some(n=>problem(n.value)))issues.push({text:'Documenta en 5. Hallazgos la falla inicial y la acción realizada, aunque al final ya esté corregida.',target:findings});
 data.hallazgos.forEach((h,i)=>{
  const target=findings.children[i];
  if(!h.equipo?.trim()||!h.hallazgo?.trim()||!h.accion?.trim())issues.push({text:'Hallazgo '+(i+1)+': completa equipo, falla encontrada y acción realizada o propuesta.',target});
  if(h.estado!=='corregido'&&(!h.responsable?.trim()||!h.fecha_objetivo))issues.push({text:'Hallazgo '+(i+1)+': falta responsable de la corrección o fecha objetivo.',target});
 });
 if((finalIssues.length||open)&&data.resultado==='operativo')issues.push({text:'6. Cierre técnico: selecciona Operativo con pendientes o Con fallas críticas, según la situación real.',target:root.querySelector('[aria-label="Resultado general"]')});
 if(data.hallazgos.some(h=>h.estado!=='corregido'&&h.prioridad==='critica')&&data.resultado!=='critico')issues.push({text:'Hay un hallazgo crítico sin resolver: selecciona Con fallas críticas en Resultado general.',target:root.querySelector('[aria-label="Resultado general"]')});
 return issues;
}
function showClosureHelp(issues){
 document.getElementById('ojc-closure-help')?.remove();if(!issues.length)return;
 const box=el('div',undefined,'error');box.id='ojc-closure-help';box.setAttribute('role','alert');box.append(el('strong','Revisa estos datos para cerrar la inspección'));
 box.append(el('p','Si una falla ya fue resuelta, actualiza el resultado Final correspondiente. Si continúa, documéntala como hallazgo Abierto o En proceso. No cambies los resultados sin comprobarlos.'));
 const list=el('ul');issues.forEach(issue=>{const li=el('li',issue.text+' ');const b=el('button','Ir a este apartado');b.type='button';b.addEventListener('click',()=>{const target=issue.target;const details=target?.closest('details');if(details)details.open=true;if(target){target.scrollIntoView({behavior:'smooth',block:'center'});if(target.matches('input,select,textarea'))target.focus({preventScroll:true});}});li.append(b);list.append(li);});box.append(list);
 action(box,'Ir a Añadir hallazgo',()=>{const b=Array.from(root.querySelectorAll('button')).find(n=>n.textContent==='Añadir hallazgo');b?.scrollIntoView({behavior:'smooth',block:'center'});b?.focus({preventScroll:true});});root.prepend(box);
}
const updateStatus=document.getElementById('ojc-update-status');
if(updateStatus)updateStatus.textContent='Ayuda de cierre activa: se mostrarán los resultados pendientes y los problemas sin hallazgo abierto.';
if(!closed)showClosureHelp(closureHelp());
document.getElementById('checklist').addEventListener('submit',e=>{
 if(resizing){e.preventDefault();alert('Espera a que termine de reducirse la fotografía.');return;}
 if(e.submitter?.value==='cerrar'){
  document.getElementById('ojc-pending-list')?.remove();
  const selects=Array.from(root.querySelectorAll('.checkrow select'));
  selects.forEach(n=>n.classList.remove('ojc-pending'));
  const missing=selects.filter(n=>n.value==='pendiente');
  if(missing.length){
   e.preventDefault();const box=el('div',undefined,'error');box.id='ojc-pending-list';box.setAttribute('role','alert');
   box.append(el('strong','Faltan '+missing.length+' resultados Inicial/Final por revisar.'));
   box.append(el('p','Los campos pendientes están resaltados. Puedes guardar borrador para continuar después. Las fotos seleccionadas siguen en este formulario.'));
   const list=el('ul');missing.slice(0,8).forEach(n=>{const row=n.closest('.checkrow'),section=n.closest('details'),title=section?.querySelector('summary')?.textContent??'Revisión';list.append(el('li',title+' / '+row.querySelector('span').textContent+' / '+n.getAttribute('aria-label')));});box.append(list);
   if(missing.length>8)box.append(el('p','Al completar estos puntos, el aviso mostrará los siguientes.'));
   root.prepend(box);missing.forEach(n=>{n.classList.add('ojc-pending');const section=n.closest('details');if(section)section.open=true;n.addEventListener('change',()=>{n.classList.toggle('ojc-pending',n.value==='pendiente');},{once:true});});
   box.scrollIntoView({behavior:'smooth',block:'start'});return;
  }
  const issues=closureHelp();showClosureHelp(issues);if(issues.length){e.preventDefault();document.getElementById('ojc-closure-help').scrollIntoView({behavior:'smooth',block:'start'});return;}
  if(!confirm('¿Cerrar esta inspección? El reporte y sus evidencias quedarán conservados para consulta.')){e.preventDefault();return;}
 }
 document.getElementById('datos').value=JSON.stringify(data);
});
})();
