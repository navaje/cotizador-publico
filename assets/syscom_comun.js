'use strict';
function syscomCalcular(costoUsd,tipoCambio,metodo,porcentaje) {
  const decimal=(valor,decimales,max) => {
    const s=String(valor).trim();
    if (!new RegExp('^\\d{1,10}(?:\\.\\d{1,'+decimales+'})?$').test(s)) return null;
    const [entero,fraccion='']=s.split('.');const n=BigInt(entero)*10n**BigInt(decimales)+BigInt(fraccion.padEnd(decimales,'0'));
    return n<=max?n:null;
  };
  const usd=decimal(costoUsd,4,99999999999999n),tc=decimal(tipoCambio,4,9999999n),pct=decimal(porcentaje,2,1000000n);
  if (usd===null || usd<=0n || tc===null || tc<=0n || pct===null || !['margen','recargo'].includes(metodo) || (metodo==='margen' && pct>=10000n)) return null;
  const costo=(usd*tc+500000n)/1000000n;
  if (costo<=0n || costo>9999999999n) return null;
  const venta=metodo==='margen' ? (2n*costo*10000n+(10000n-pct))/(2n*(10000n-pct)) : (costo*(10000n+pct)+5000n)/10000n;
  if (venta>9999999999n) return null;
  const iva=(venta*16n+50n)/100n;
  return {costo:Number(costo)/100,venta:Number(venta)/100,iva:Number(iva)/100,total:Number(venta+iva)/100,ganancia:Number(venta-costo)/100};
}
async function syscomPeticion(token,accion,datos={}) {
  const controller=new AbortController();const limite=setTimeout(()=>controller.abort(),45000);
  try {
    const r=await fetch('syscom_api.php',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:new URLSearchParams({csrf_token:token,accion,...datos}),signal:controller.signal});
    const error=(mensaje)=>{const e=new Error(mensaje);e.status=r.status;return e;};
    if(r.redirected)throw error('La solicitud fue redirigida (HTTP '+r.status+'). Abre de nuevo Importar catálogo e inicia sesión si se solicita.');
    const texto=await r.text();let data;
    try{data=JSON.parse(texto);}catch{
      const motivos={403:'El servidor rechazó la solicitud; puede ser la sesión/CSRF o una regla del hosting.',406:'El hosting rechazó el contenido de la solicitud.',413:'El hosting rechazó el tamaño de la solicitud.',429:'El hosting limitó la frecuencia de solicitudes.',500:'El servidor tuvo un error interno; revisa el registro de errores de PHP.',502:'El servidor intermediario no recibió una respuesta válida.',503:'El servicio está temporalmente no disponible.',504:'El hosting agotó el tiempo de respuesta.'};
      throw error('HTTP '+r.status+' al '+accion+': '+(motivos[r.status]||'El servidor respondió fuera del formato esperado.'));
    }
    if(!data || typeof data!=='object' || Array.isArray(data))throw error('HTTP '+r.status+': respuesta de catálogo no válida.');
    if(!r.ok)throw error('HTTP '+r.status+' al '+accion+': '+(data.error||'No se pudo completar la consulta.'));
    return data;
  }catch(e){
    if(e.name==='AbortError')throw new Error('Sin respuesta durante 45 segundos al '+accion+'. Puedes reintentar; el catálogo anterior se conserva hasta completar la carga.');
    throw e;
  }finally{clearTimeout(limite);}
}
function syscomCodificarFilas(filas) {
  const bytes=new TextEncoder().encode(JSON.stringify(filas));let texto='';
  for(let inicio=0;inicio<bytes.length;inicio+=16384)texto+=String.fromCharCode(...bytes.subarray(inicio,inicio+16384));
  return btoa(texto);
}
if (typeof module!=='undefined') module.exports={syscomCalcular};
