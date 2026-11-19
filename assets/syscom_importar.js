'use strict';
(() => {
  const root=document.getElementById('syscomImportacion');if(!root)return;
  const id=x=>document.getElementById(x),token=root.querySelector('[name="csrf_token"]').value;
  let pendiente=null,ocupado=false,cancelado=false,apiLista=false;
  function controles(){id('syscomIniciar').disabled=ocupado||Boolean(pendiente);id('syscomArchivo').disabled=ocupado;id('syscomCancelar').hidden=!pendiente;}
  async function estado(){const d=await syscomPeticion(token,'estado');
    apiLista=d.api_version==='5.12.1';if(!apiLista)throw new Error('Falta actualizar syscom_api.php a V5.12.1. Sube todos los archivos del parche y recarga con Ctrl+F5.');
    pendiente=d.pendiente;
    id('syscomActual').textContent=d.activa ? ('Catálogo activo: '+d.activa.filas+' productos; '+d.activa.sin_precio+' sin precio utilizable. Archivo: '+d.activa.archivo+'. Importado: '+d.activa.actualizado_en+'.') : 'Todavía no tienes un catálogo SYSCOM importado.';
    if(pendiente)id('syscomEstadoImportacion').textContent='Hay una carga pendiente con '+pendiente.filas+' productos. Puedes cancelarla y comenzar de nuevo.';
    controles();
  }
  id('syscomCancelar').addEventListener('click',async()=>{
    if(!pendiente)return;
    cancelado=true;id('syscomCancelar').disabled=true;
    try{await syscomPeticion(token,'cancelar',{id:String(pendiente.id)});pendiente=null;id('syscomEstadoImportacion').textContent='Carga cancelada. Se conserva el catálogo anterior.';}
    catch(e){id('syscomEstadoImportacion').textContent=e.message;}
    finally{id('syscomCancelar').disabled=false;controles();}
  });
  id('syscomIniciar').addEventListener('click',async()=>{
    if(!apiLista){try{await estado();}catch(e){id('syscomEstadoImportacion').textContent=e.message;return;}}
    if(ocupado||pendiente)return;
    const archivo=id('syscomArchivo').files[0];
    if(!archivo||!archivo.name.toLowerCase().endsWith('.csv')){id('syscomEstadoImportacion').textContent='Selecciona el CSV original de SYSCOM.';return;}
    if(!id('syscomConfirmar').checked){id('syscomEstadoImportacion').textContent='Confirma la moneda USD y que los costos no incluyen IVA.';return;}
    if(archivo.size>400*1024*1024){id('syscomEstadoImportacion').textContent='El archivo supera 400 MB. Descarga un catálogo más pequeño.';return;}
    ocupado=true;cancelado=false;controles();id('syscomProgreso').value=0;
    let total=0,numero=0,confirmados=0,lote=[];
    try{
      const r=await syscomPeticion(token,'iniciar',{archivo:archivo.name});pendiente={id:r.id,filas:0};controles();
      async function enviar(){
        if(cancelado)throw new Error('Carga cancelada.');
        numero++;const datos={id:String(r.id),numero:String(numero),filas_b64:syscomCodificarFilas(lote)};
        let respuesta;
        for(let intento=0;intento<2;intento++){
          try{respuesta=await syscomPeticion(token,'lote',datos);break;}
          catch(e){if(intento||(e.status&&!([429,502,503,504].includes(e.status)))||cancelado)throw e;await new Promise(resolve=>setTimeout(resolve,2000));}
        }
        confirmados=respuesta.filas;lote=[];id('syscomEstadoImportacion').textContent='Importando: '+confirmados+' productos confirmados. Lote '+numero+' completado…';
        await new Promise(resolve=>setTimeout(resolve,250));
      }
      const fragmentos=syscomFragmentosArchivo(archivo,bytes=>{id('syscomProgreso').value=Math.floor(bytes/archivo.size*100);});
      for await(const producto of syscomLeerCsv(fragmentos)){
        if(cancelado)throw new Error('Carga cancelada.');
        total++;if(total>100000)throw new Error('El archivo supera 100000 productos.');
        lote.push(producto);if(lote.length===100)await enviar();
      }
      if(lote.length)await enviar();if(cancelado)throw new Error('Carga cancelada.');
      if(!total)throw new Error('El CSV no contiene productos.');
      await syscomPeticion(token,'finalizar',{id:String(r.id),esperados:String(total)});
      pendiente=null;id('syscomProgreso').value=100;await estado();
      id('syscomEstadoImportacion').textContent='Importación completada: '+total+' productos. Ya puedes buscarlos desde el cotizador.';
    }catch(e){id('syscomEstadoImportacion').textContent='Carga detenida: '+confirmados+' productos confirmados; lote '+numero+'. '+e.message+(cancelado?'':' Cancela la carga pendiente antes de volver a importar el archivo.');}
    finally{ocupado=false;controles();}
  });
  window.addEventListener('beforeunload',e=>{if(ocupado){e.preventDefault();e.returnValue='';}});
  estado().catch(e=>{id('syscomEstadoImportacion').textContent=e.message;});
})();
