(() => {
 'use strict';
 const root=document.getElementById('banco-cobro');if(!root)return;
 const byId=id=>document.getElementById(id), search=byId('buscar-documento'), results=byId('documento-resultados');
 const createForm=byId('generar-recibo'),linkForm=byId('vincular-recibo');
 const money=cents=>new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'}).format(cents/100)+' MXN';
 let searchSequence=0,summarySequence=0,timer;
 const status=message=>{byId('busqueda-estado').textContent=message;};
 function clearSelection(){summarySequence++;byId('documento-resumen').hidden=true;createForm.elements.documento_id.value='';linkForm.elements.documento_id.value='';byId('crear-recibo').disabled=true;byId('guardar-vinculo').disabled=true;byId('recibo-existente').disabled=true;byId('recibo-existente').replaceChildren();byId('vinculo-estado').textContent='Selecciona primero un presupuesto o documento.';createForm.elements.confirmado.checked=false;linkForm.elements.confirmado_vinculo.checked=false;}
 async function request(parameters){
  const url=new URL('banco_documentos.php',window.location.href);url.searchParams.set('movimiento_id',root.dataset.movimiento);
  Object.entries(parameters).forEach(([key,value])=>url.searchParams.set(key,String(value)));
  const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
  let data;try{data=await response.json();}catch(error){throw new Error('La sesión venció o el servidor no respondió correctamente. Recarga la página.');}
  if(!response.ok || !data.ok)throw new Error(data.error || 'No se pudo consultar el documento.');return data;
 }
 async function loadResults(){
  const seq=++searchSequence;status('Buscando…');
  try{const data=await request({q:search.value.trim()});if(seq!==searchSequence)return;results.replaceChildren();
   data.documentos.forEach(doc=>{const button=document.createElement('button');button.type='button';button.className='documento-opcion';button.textContent=doc.folio+' · '+doc.cliente+' · '+doc.proyecto+' · '+money(doc.total)+' · '+doc.tipo;button.addEventListener('click',()=>selectDocument(doc.id));results.append(button);});
   status(data.documentos.length?'Selecciona un resultado. Se muestran hasta 30; escribe más datos para precisar la búsqueda.':'No se encontraron documentos. Prueba con otro nombre o folio.');
  }catch(error){if(seq===searchSequence){results.replaceChildren();status(error.message);}}
 }
 async function selectDocument(id){
  clearSelection();const seq=summarySequence;status('Consultando pagos y saldo…');
  try{const data=await request({documento_id:id});if(seq!==summarySequence)return;const d=data.documento;
   createForm.elements.documento_id.value=d.id;linkForm.elements.documento_id.value=d.id;
   byId('documento-titulo').textContent=d.tipo+' · '+d.folio;byId('documento-cliente').textContent='Cliente: '+d.cliente;byId('documento-proyecto').textContent='Proyecto: '+d.proyecto;
   [['cobro-total',d.total],['cobro-previo',d.previo],['cobro-saldo',d.saldo],['cobro-importe',d.deposito],['cobro-despues',d.saldo_despues]].forEach(([element,value])=>{byId(element).textContent=money(value);});
   byId('cobro-tipo').textContent='50% del total como referencia: '+money(d.referencia_50)+'. El importe recibido no está obligado a ser ese porcentaje.';
   let warning='El nuevo recibo será de '+d.titulo.toLowerCase()+'.';
   if(d.referencia_registrada)warning='Esta clave de rastreo ya tiene un recibo registrado. Revisa la opción de vincular; no generes otro pago.';
   else if(d.saldo_despues<0)warning='El depósito excede el saldo pendiente. Revisa el presupuesto y sus pagos antes de continuar.';
   else if(d.saldo<=0)warning='Este trabajo no tiene saldo pendiente. Revisa su historial.';
   byId('cobro-aviso').textContent=warning;byId('cobro-aviso').className=d.puede_aplicar?'success':'notice';
   byId('documento-historial').href='anticipos.php?id='+d.id;
   const history=byId('cobro-historial');history.replaceChildren();
   if(!d.recibos.length)history.textContent='Aún no hay pagos registrados.';
   d.recibos.forEach(receipt=>{const line=document.createElement('p'),a=document.createElement('a');a.href='recibo_pdf.php?id='+receipt.id;a.target='_blank';a.rel='noopener';a.textContent=receipt.folio;line.append(a,document.createTextNode(' · '+receipt.fecha+' · '+money(receipt.importe)+' · '+receipt.tipo+(receipt.anulado?' · ANULADO':'')));history.append(line);});
   for(const [name,value] of [['entrego',d.cliente],['concepto','Pago a cuenta del trabajo '+d.folio+': '+d.proyecto]]){const field=createForm.elements[name];if(!field.value.trim() || field.dataset.auto==='1'){field.value=value;field.dataset.auto='1';}}
   byId('crear-recibo').disabled=!d.puede_aplicar;
   const existing=byId('recibo-existente');existing.replaceChildren();
   d.vinculables.forEach(receipt=>{const option=document.createElement('option');option.value=receipt.id;option.textContent=receipt.folio+' · '+receipt.fecha+' · '+money(receipt.importe);existing.append(option);});
   existing.disabled=!d.vinculables.length;byId('guardar-vinculo').disabled=!d.vinculables.length;
   byId('vinculo-estado').textContent=d.vinculables.length?'Hay un recibo compatible. Puedes vincularlo sin modificar el saldo.':'No hay recibos compatibles sin vincular en este trabajo. Se exige coincidencia en importe, Banorte y clave de rastreo.';
   if(d.vinculables.length)byId('vinculo-panel').open=true;
   byId('documento-resumen').hidden=false;status('Documento seleccionado: '+d.folio);results.replaceChildren();
  }catch(error){if(seq===summarySequence){clearSelection();status(error.message);}}
 }
 ['entrego','concepto'].forEach(name=>createForm.elements[name].addEventListener('input',()=>{createForm.elements[name].dataset.auto='0';}));
 search.addEventListener('input',()=>{clearTimeout(timer);searchSequence++;clearSelection();results.replaceChildren();status('Buscando…');timer=setTimeout(loadResults,300);});
 [createForm,linkForm].forEach(form=>form.addEventListener('submit',event=>{if(!form.elements.documento_id.value){event.preventDefault();status('Selecciona primero un presupuesto o documento.');}}));
 if(Number(root.dataset.documento)>0)selectDocument(Number(root.dataset.documento));else loadResults();
})();
