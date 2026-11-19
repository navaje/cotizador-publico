'use strict';
// Lee por fragmentos; descarta HTML e información no usada antes de enviar.
async function* syscomLeerCsv(fragmentos) {
  const requeridas={'ID Producto':'id','Modelo':'modelo','Marca':'marca','Título':'titulo','Su Precio':'costo_usd','Stock Veracruz':'veracruz','Menu Nvl 1':'categoria','Menu Nvl 2':'subcategoria','Menu Nvl 3':'familia'};
  let cabecera=null,columna=0,valor='',campos=[],enComillas=false,comillaPendiente=false,trasComillas=false,omitirLF=false,hayContenido=false,inicioCampo=true,filaNumero=0;
  const conservar=()=>!cabecera || Boolean(requeridas[cabecera[columna]]);
  function agregar(c) {hayContenido=true;inicioCampo=false;if (conservar()) {valor+=c;if(valor.length>5000)throw new Error('Un campo del catálogo es demasiado largo.');}}
  function cerrarCampo(){campos.push(conservar()?valor:'');valor='';columna++;trasComillas=false;inicioCampo=true;}
  function cerrarFila(){
    if (!hayContenido && columna===0 && valor==='')return null;
    cerrarCampo();filaNumero++;
    const fila=campos;campos=[];columna=0;hayContenido=false;
    if (!cabecera) {
      cabecera=fila.map(x=>x.replace(/^\uFEFF/,'').trim());
      if (new Set(cabecera).size!==cabecera.length)throw new Error('El CSV repite columnas.');
      for(const nombre of Object.keys(requeridas))if(!cabecera.includes(nombre))throw new Error('Falta la columna '+nombre+'. Selecciona la descarga original de SYSCOM.');
      return null;
    }
    if(fila.length!==cabecera.length)throw new Error('La fila '+filaNumero+' tiene un número de columnas diferente. No se activará esta carga.');
    const p={};cabecera.forEach((nombre,i)=>{if(requeridas[nombre])p[requeridas[nombre]]=fila[i].trim();});return p;
  }
  for await(const texto of fragmentos) {
    for(let i=0;i<texto.length;i++) {
      const c=texto[i];
      if(omitirLF){omitirLF=false;if(c==='\n')continue;}
      if(enComillas) {
        if(comillaPendiente) {
          comillaPendiente=false;
          if(c==='"'){agregar('"');continue;}
          enComillas=false;trasComillas=true;
        } else {if(c==='"')comillaPendiente=true;else agregar(c);continue;}
      }
      if(c===','){hayContenido=true;cerrarCampo();continue;}
      if(c==='\n'||c==='\r'){const fila=cerrarFila();if(c==='\r')omitirLF=true;if(fila)yield fila;continue;}
      if(trasComillas)throw new Error('Hay contenido inesperado después de una comilla en el CSV.');
      if(c==='"'&&inicioCampo){enComillas=true;hayContenido=true;inicioCampo=false;continue;}
      agregar(c);
    }
  }
  if(enComillas&&!comillaPendiente)throw new Error('El CSV termina dentro de un campo entre comillas.');
  if(enComillas&&comillaPendiente){enComillas=false;comillaPendiente=false;}
  const fila=cerrarFila();if(fila)yield fila;
  if(!cabecera)throw new Error('El archivo está vacío.');
}
async function* syscomFragmentosArchivo(archivo,progreso) {
  const decoder=new TextDecoder('utf-8',{fatal:true});
  for(let inicio=0;inicio<archivo.size;inicio+=262144){const datos=await archivo.slice(inicio,inicio+262144).arrayBuffer();yield decoder.decode(datos,{stream:true});progreso(Math.min(inicio+datos.byteLength,archivo.size));}
  const resto=decoder.decode();if(resto)yield resto;
}
if(typeof module!=='undefined')module.exports={syscomLeerCsv};
