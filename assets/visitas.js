/* Ojo Jarocho - captura móvil de visitas. Sin dependencias externas. */
(() => {
 'use strict';
 const state=JSON.parse(document.getElementById('visita-state').textContent);
 const schema=JSON.parse(document.getElementById('visita-schema').textContent);
 const editor=document.getElementById('visita-editor'),form=document.getElementById('visita-form');
 let dirty=document.getElementById('save-status').textContent.includes('sin guardar'),busy=false;
 const el=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
 const icons={
  Casa:'M3 11 12 3l9 8M5 10v11h14V10M9 21v-7h6v7',Departamento:'M5 21V3h14v18M9 7h1m4 0h1M9 11h1m4 0h1M9 15h1m4 0h1',Comercio:'M3 10V5h18v5M3 10c0 3 4 3 4 0 0 3 5 3 5 0 0 3 5 3 5 0 0 3 4 3 4 0M5 13v8h14v-8M9 21v-6h6v6',Oficina:'M4 21V3h16v18M8 7h8M8 11h8M8 15h8',Bodega:'M3 21V9l9-6 9 6v12M7 21V11h10v10M7 15h10M7 18h10',Industria:'M3 21V10l6 3V8l7 5V3h4v18ZM7 17h1m4 0h1m4 0h1',
  Bala:'M3 7h14l4 4-4 4H3ZM8 15v5h7M17 7v8',Domo:'M3 11h18M5 11a7 7 0 0 1 14 0M5 11a7 7 0 0 0 14 0M10 12a2 2 0 1 0 4 0', 'Turret / eyeball':'M3 19h18M5 19V9a7 7 0 0 1 14 0v10M8 11a4 4 0 1 0 8 0 4 4 0 0 0-8 0',PT:'M5 21h14M8 21v-4M16 21v-4M5 7h14v10H5ZM3 4l3-2m12 0 3 2',PTZ:'M5 21h14M8 21v-4M16 21v-4M5 7h14v10H5ZM9 12h6M12 9v6',
  'Wi-Fi':'M2 8a16 16 0 0 1 20 0M5 12a11 11 0 0 1 14 0M8 16a6 6 0 0 1 8 0M12 20h.01', '4G':'M5 21V3h14v18ZM9 17h6M9 7h6M9 11h6', 'IP cableada / PoE':'M6 3h12v10l-4 4v4h-4v-4l-4-4ZM9 3v5m3-5v5m3-5v5',Analógica:'M3 8h11v8H3ZM14 11h5v10M6 8V4h5v4',Pared:'M4 3v18M4 12h9M13 8h8v8h-8Z',Techo:'M3 3h18M12 3v7M7 10h10v8H7Z',Poste:'M10 3h4v18M14 8h7v6h-7',
  'Tubo PVC':'M3 5h13v5H8v11H3ZM16 4v7M2 21h7','Tubo metálico':'M3 5h13v5H8v11H3ZM2 6h6M3 17h5M16 4v7',Canaleta:'M3 5h18v5H8v11H3ZM3 7h16',Gabinete:'M5 3h14v18H5ZM14 11v4M8 7h8',Rack:'M4 3h16v18H4ZM4 9h16M4 15h16M7 6h1M7 12h1M7 18h1'};
 const iconFor=(value)=>{const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('aria-hidden','true');const path=document.createElementNS(svg.namespaceURI,'path');path.setAttribute('d',icons[value]||'M4 4h16v16H4ZM8 12h8M12 8v8');svg.append(path);return svg;};
 function changed(){dirty=true;document.getElementById('save-status').textContent='Hay cambios sin guardar.';document.querySelector('#convert-form button')?.setAttribute('disabled','disabled');summary();}
 function blank(section){return Object.fromEntries(Object.entries(schema[section].fields).map(([key,s])=>[key,s[2]?.[0]??'']));}
 const counters={};for(const [key,s] of Object.entries(schema))if(s.prefix)counters[key]=Math.max(0,...(state[key]||[]).map(x=>Number(String(x.id).split('-')[1])||0));
 function newId(section){return schema[section].prefix+'-'+String(++counters[section]).padStart(2,'0');}
 function visibleQuick(section,key){const quick={general:['proyecto','fecha','tecnico','cliente_nombre','cliente_telefono','cliente_email','cliente_direccion','inmueble','servicio','necesidad','modo'],camaras:['id','ubicacion','accion','objetivo','tecnologia','tipo','modelo','altura','luz','alimentacion','caja','notas'],infra:['grabador_accion','grabador','canales','grabacion','dias','disco_accion','disco','red','contacto','punto_nuevo','ups_accion','ups']};return !quick[section]||quick[section].includes(key);}
 function visibility(){for(const holder of editor.querySelectorAll('[data-field]')){const {section,index,field}=holder.dataset;const spec=schema[section].fields[field];const row=schema[section].prefix?state[section][Number(index)]:state[section];const wireless=spec[3]==='inalambrico';holder.hidden=(wireless&&!['Wi-Fi','4G'].includes(row.tecnologia))||(!wireless&&state.general.modo==='Rápido'&&!visibleQuick(section,field));}}
 function field(section,index,key,spec,row){
  const type=spec[1]||'text',val=row[key]??spec[2]?.[0]??'';
  const holder=el('div',undefined,'field'+(type==='area'||type==='tiles'?' wide':''));Object.assign(holder.dataset,{section,index:String(index),field:key});
  const id='ojv-'+section+'-'+index+'-'+key;
  const apply=(value)=>{row[key]=value;if(key==='modo'||key==='tecnologia')visibility();if(schema[section].prefix){const heading=holder.closest('.item')?.querySelector('.item-heading');if(heading)heading.textContent=labelRow(row);}changed();};
  if(type==='tiles'){
   const fs=el('fieldset',undefined,'tile-group');fs.append(el('legend',spec[0]));const tiles=el('div',undefined,'tiles');
   for(const [j,choice] of spec[2].entries()){const lab=el('label',undefined,'tile'),radio=el('input');radio.type='radio';radio.name=id;radio.value=choice;radio.checked=val===choice;radio.id=id+'-'+j;radio.addEventListener('change',()=>apply(radio.value));const face=el('span');face.append(iconFor(choice),el('b',choice));lab.append(radio,face);tiles.append(lab);}fs.append(tiles);holder.append(fs);
  } else {
   const label=el('label',spec[0]);label.htmlFor=id;
   let input;if(type==='select'){input=el('select');for(const choice of spec[2]){const op=el('option',choice);op.value=choice;input.append(op);}}
   else if(type==='area'){input=el('textarea');input.rows=3;input.maxLength=6000;}
   else {input=el('input');input.type=['num','int'].includes(type)?'number':type==='text'?'text':type;if(input.type==='number'){input.min='0';input.max='100000000';input.step=type==='int'?'1':'0.01';}else input.maxLength=['proyecto','cliente_nombre','tecnico'].includes(key)?180:500;}
   input.id=id;input.value=val;input.addEventListener(type==='select'?'change':'input',()=>apply(input.value));if(key==='id'){input.readOnly=true;input.setAttribute('aria-label','Identificador automático');}holder.append(label,input);
  }return holder;
 }
 const labelRow=row=>[row.id,row.ubicacion||row.destino||row.concepto||'Sin descripción'].filter(Boolean).join(' · ');
 function render(){editor.replaceChildren();for(const [key,s] of Object.entries(schema)){
  const section=el('details',undefined,'section');section.open=key==='general'||['camaras','tramos'].includes(key);section.append(el('summary',s.title));
  if(s.prefix){const help=key==='camaras'?'Una ficha por punto. PT gira; PTZ añade zoom óptico. La altura debe permitir el objetivo de identificación.':key==='tramos'?'Registra cada segmento compartido una sola vez. Cable total = (recorrido + reserva por cable) × número de cables.':'Agrega solo conceptos que no estén ya calculados en cámaras, tramos o infraestructura.';section.append(el('p',help,'muted'));
   (state[key]||[]).forEach((row,index)=>{const item=el('details',undefined,'item');item.open=false;const heading=el('summary');heading.append(iconFor(row.tipo||row.cable),el('span',labelRow(row),'item-heading'));item.append(heading);const grid=el('div',undefined,'grid');for(const [f,spec] of Object.entries(s.fields))grid.append(field(key,index,f,spec,row));item.append(grid);
    const actions=el('div',undefined,'actions');const duplicate=el('button','Duplicar');duplicate.type='button';duplicate.addEventListener('click',()=>{if(state[key].length>=s.limit)return alert('Alcanzaste el máximo de registros.');const copy=JSON.parse(JSON.stringify(row));copy.id=newId(key);state[key].push(copy);changed();render();openLast(key);});const remove=el('button','Quitar registro','danger');remove.type='button';remove.addEventListener('click',()=>{if(!confirm('¿Quitar '+row.id+' de esta versión?'))return;state[key].splice(index,1);changed();render();});actions.append(duplicate,remove);item.append(actions);section.append(item);
   });const add=el('button','+ Agregar '+(key==='camaras'?'cámara':key==='tramos'?'tramo':'material / trabajo'));add.type='button';add.addEventListener('click',()=>{if(state[key].length>=s.limit)return alert('Alcanzaste el máximo de registros.');const row=blank(key);row.id=newId(key);if(key==='tramos')row.cables='1';if(key==='materiales')row.cantidad='1';state[key].push(row);changed();render();openLast(key);});section.append(add);
  }else{const grid=el('div',undefined,'grid');for(const [f,spec] of Object.entries(s.fields))grid.append(field(key,0,f,spec,state[key]));section.append(grid);if(key==='general')section.append(el('p','Rápido muestra lo esencial. Detallado abre medidas y condiciones adicionales; cambiar de modo conserva lo capturado.','muted'));}
  section.dataset.sectionKey=key;editor.append(section);
 }visibility();summary();}
 function openLast(key){const item=editor.querySelector('[data-section-key="'+key+'"] .item:last-of-type');if(item){item.open=true;item.scrollIntoView({block:'start',behavior:'smooth'});}}
 function summary(){
  let cable=0,canal=0;for(const t of state.tramos){if(t.accion==='Instalar nuevo')cable+=(Number(t.metros)+Number(t.reserva))*Number(t.cables);if(t.canal_accion==='Instalar nuevo')canal+=Number(t.canal_metros);}
  const target=document.getElementById('visita-summary');target.replaceChildren();const metrics=el('div',undefined,'metrics');for(const [label,value] of [['Cámaras',state.camaras.length],['Cable nuevo (m)',cable.toFixed(2)],['Canalización nueva (m)',canal.toFixed(2)]]){const box=el('div',label,'metric');box.append(el('strong',String(value)));metrics.append(box);}target.append(metrics);
  if(Number(state.infra.bitrate)>0){const gb=Number(state.infra.bitrate)*10.8;target.append(el('p','Grabación continua estimada: '+gb.toFixed(1)+' GB/día; '+(gb*Number(state.infra.dias)/1000).toFixed(2)+' TB para los días indicados, antes de reservas y margen. No aplica directamente a grabación por eventos.'));}
  const pending=[];for(const [f,label]of Object.entries({cliente_nombre:'Nombre del cliente',proyecto:'Proyecto',fecha:'Fecha',cliente_direccion:'Dirección',necesidad:'Necesidad'}))if(!state.general[f])pending.push(label);
  if(!state.camaras.length)pending.push('Agrega al menos un punto de cámara.');
  for(const cam of state.camaras){for(const f of ['ubicacion','accion','objetivo','tecnologia','tipo','altura'])if(!cam[f]||cam[f]==='Por definir')pending.push(cam.id+': '+f+' pendiente.');if(['Wi-Fi','4G'].includes(cam.tecnologia)&&cam.wifi_medida!=='Medida')pending.push(cam.id+': prueba de señal pendiente.');}
  if(!state.cierre.recomendacion)pending.push('Recomendación técnica.');if(state.cierre.resueltos!=='Resueltos / no hay pendientes')pending.push('Resolver pendientes.');
  const out=document.getElementById('visita-pending');out.replaceChildren();if(pending.length){const ul=el('ul');for(const msg of pending)ul.append(el('li',msg));out.append(ul);}else out.append(el('p','Datos principales capturados. El servidor comprobará también los recorridos y materiales al guardar como listo.','ok'));
 }
 document.getElementById('client-search').addEventListener('click',async()=>{const target=document.getElementById('client-results');target.textContent='Buscando…';try{const response=await fetch('clientes_buscar.php?q='+encodeURIComponent(document.getElementById('client-query').value),{credentials:'same-origin'});const data=await response.json();if(!response.ok)throw Error(data.error||'No se pudo buscar.');target.replaceChildren();for(const client of data.items){const b=el('button',client.nombre+' · '+client.telefono);b.type='button';b.addEventListener('click',()=>{if(!confirm('¿Completar nombre, teléfono, correo y dirección con esta ficha?'))return;state.cliente_id=client.id;for(const f of ['nombre','telefono','email','direccion'])state.general['cliente_'+f]=client[f];changed();render();target.textContent='Cliente seleccionado. Puedes ajustar la dirección de esta visita.';});target.append(b);}if(!data.items.length)target.textContent='Sin coincidencias. Puedes capturar el cliente manualmente.';if(data.hay_mas)target.append(el('p','Hay más resultados. Acota la búsqueda.'));}catch(e){target.textContent=e.message;}});
 const fotoInput=document.getElementById('foto-multiple'),queueEl=document.getElementById('foto-queue'),btnFotos=document.getElementById('btn-fotos'),btnSubir=document.getElementById('btn-subir-fotos'),fotoStatus=document.getElementById('foto-status');
 const fotoQueue=[];
 async function comprimirFoto(file){
  if(!['image/jpeg','image/png'].includes(file.type))throw Error('Usa JPG o PNG.');
  if(!window.createImageBitmap){if(file.size>2097152)throw Error('Reduce la foto a menos de 2 MB.');return file;}
  const bmp=await createImageBitmap(file);
  const ratio=Math.min(1,1600/Math.max(bmp.width,bmp.height));
  const canvas=document.createElement('canvas');
  canvas.width=Math.max(1,Math.round(bmp.width*ratio));canvas.height=Math.max(1,Math.round(bmp.height*ratio));
  const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(bmp,0,0,canvas.width,canvas.height);bmp.close();
  const blob=await new Promise(r=>canvas.toBlob(r,'image/jpeg',.82));
  if(!blob||blob.size>2097152)throw Error('La foto sigue superando 2 MB. Redúcela antes de subirla.');
  return new File([blob],'evidencia.jpg',{type:'image/jpeg'});
 }
 function renderQueue(){
  if(!queueEl)return;
  queueEl.replaceChildren();
  fotoQueue.forEach((q,i)=>{
   const card=el('div',undefined,'foto-card');
   const img=el('img');img.src=q.url;img.alt='Vista previa';card.append(img);
   const punto=el('input');punto.placeholder='Punto (CAM-01)';punto.maxLength=20;
   const ubic=el('input');ubic.placeholder='Ubicación';ubic.maxLength=180;
   const desc=el('input');desc.placeholder='Descripción';desc.maxLength=500;
   q.inputs={punto,ubic,desc};
   const quitar=el('button','Quitar de la cola','danger');quitar.type='button';
   quitar.addEventListener('click',()=>{URL.revokeObjectURL(q.url);fotoQueue.splice(i,1);renderQueue();});
   card.append(punto,ubic,desc,quitar);
   queueEl.append(card);
  });
  if(btnSubir)btnSubir.disabled=!fotoQueue.length;
  if(fotoStatus)fotoStatus.textContent=fotoQueue.length?fotoQueue.length+' foto(s) listas para subir.':'';
 }
 btnFotos?.addEventListener('click',()=>fotoInput&&fotoInput.click());
 fotoInput?.addEventListener('change',async()=>{
  const files=[...fotoInput.files];fotoInput.value='';
  for(const f of files){
   if(fotoQueue.length>=10){alert('Máximo 10 fotos por carga; sube primero estas.');break;}
   try{fotoQueue.push({file:await comprimirFoto(f),url:URL.createObjectURL(f)});}catch(e){alert(e.message||'No se pudo preparar la foto.');}
  }
  renderQueue();
 });
 btnSubir?.addEventListener('click',async()=>{
  if(!fotoQueue.length)return;
  btnSubir.disabled=true;if(fotoStatus)fotoStatus.textContent='Subiendo…';
  try{
   const fd=new FormData();
   fd.append('csrf_token',form.querySelector('[name="csrf_token"]').value);
   fd.append('visita_id',document.getElementById('visita-id').value);
   fotoQueue.forEach((q,i)=>{fd.append('fotos[]',q.file,'foto'+i+'.jpg');fd.append('foto_punto[]',q.inputs.punto.value.trim());fd.append('foto_ubicacion[]',q.inputs.ubic.value.trim());fd.append('foto_descripcion[]',q.inputs.desc.value.trim());});
   const r=await fetch('visita_fotos_subir.php',{method:'POST',credentials:'same-origin',body:fd});
   const d=await r.json();
   if(!r.ok||!d.ok)throw Error(d.error||'No se pudieron subir las fotos.');
   location.reload();
  }catch(e){if(fotoStatus)fotoStatus.textContent=e.message||'Error al subir.';btnSubir.disabled=false;}
 });
 form.addEventListener('input',e=>{if(!editor.contains(e.target))changed();});
 form.addEventListener('change',e=>{if(!editor.contains(e.target))changed();});
 form.addEventListener('submit',async e=>{e.preventDefault();if(busy)return;if(!form.reportValidity())return;busy=true;const action=e.submitter?.value||'guardar';if(fotoQueue.length){e.preventDefault();busy=false;alert('Sube o quita las fotos pendientes antes de guardar.');return;}document.getElementById('visita-datos').value=JSON.stringify(state);const hidden=el('input');hidden.type='hidden';hidden.name='accion';hidden.value=action;form.append(hidden);dirty=false;for(const b of form.querySelectorAll('button'))b.disabled=true;HTMLFormElement.prototype.submit.call(form);});
 document.getElementById('convert-form')?.addEventListener('submit',e=>{if(dirty){e.preventDefault();alert('Guarda primero tus cambios como listos.');}else if(!confirm('Se abrirá un borrador con precios en cero. Revisa y completa sus importes antes de emitir.'))e.preventDefault();});
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
 render();
})();
