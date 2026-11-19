// Ejecutar desde tu-correo@gmail.com. Requiere el servicio avanzado Gmail (v1).
// Usa permiso de solo lectura: no modifica ni envía correos.
function sincronizarBanorte() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) return;
  try {
    const props = PropertiesService.getScriptProperties();
    const url = props.getProperty('OJ_URL');
    const secret = props.getProperty('OJ_SECRETO');
    const expected = 'tu-correo@gmail.com';
    if (url !== 'https://ojojarocho.online/cotizador/banco_receptor.php' || !/^[a-f0-9]{64}$/.test(secret || '')) throw new Error('Configura OJ_URL y OJ_SECRETO en las propiedades del proyecto.');
    if (Gmail.Users.getProfile('me').emailAddress.toLowerCase() !== expected) throw new Error('Autoriza este proyecto desde ' + expected);
    const started = Date.now();
    const last = Number(props.getProperty('OJ_ULTIMA_REVISION') || 0);
    // Primera ejecución: últimos 30 días. Las siguientes solapan 48 horas;
    // si se interrumpe el servicio, se retoma desde la última revisión exitosa.
    const after = Math.floor((last ? last - 48*3600000 : started - 30*86400000) / 1000);
    const query = '{from:tu-cuenta@banorte.com from:tu-cuenta@banorte.com} after:' + after;
    let pageToken, failures = 0, count = 0, deferred = false;
    let done = JSON.parse(props.getProperty('OJ_PROCESADOS') || '[]');
    const known = new Set(done);
    do {
      const options = {q:query,maxResults:50};
      if (pageToken) options.pageToken = pageToken;
      const page = Gmail.Users.Messages.list('me',options);
      for (const item of (page.messages || [])) {
        if (known.has(item.id)) continue;
        if (Date.now()-started > 240000) { deferred = true; break; }
        const msg = Gmail.Users.Messages.get('me',item.id,{format:'full'});
        const headers = msg.payload.headers || [];
        const header = name => (headers.find(h => h.name.toLowerCase() === name.toLowerCase()) || {}).value || '';
        const subject = header('Subject');
        if (!/SPEI RECIBIDO|Compra en Comercio|Transferencia a Otros Bancos Nacionales/i.test(subject)) continue;
        // Solo aceptar el resultado de autenticación agregado por Gmail.
        const auth = headers.filter(h => h.name.toLowerCase()==='authentication-results')
          .map(h => h.value).find(v => /^mx\.google\.com\s*;/i.test(v.trim())) || '';
        if (!/dkim=pass\s+header\.i=@banorte\.com(?:\s|;)/i.test(auth) || !/dmarc=pass\b[^;]*header\.from=banorte\.com(?:\s|;|$)/i.test(auth)) {
          console.warn('Aviso omitido: autenticación bancaria no verificada. ID ' + item.id); failures++; continue;
        }
        const texts = {html:'',plain:''};
        function collect(part) {
          const attachment = (part.headers || []).some(h => h.name.toLowerCase()==='content-disposition' && /^attachment/i.test(h.value));
          if (attachment) return;
          if (part.body && part.body.data && /^text\/(html|plain)$/.test(part.mimeType)) {
            const ct = (part.headers || []).find(h => h.name.toLowerCase()==='content-type');
            const charsetMatch = ct && /charset=["']?([^;"'\s]+)/i.exec(ct.value);
            const charset = charsetMatch ? charsetMatch[1] : 'UTF-8';
            const text = Utilities.newBlob(Utilities.base64DecodeWebSafe(part.body.data)).getDataAsString(charset);
            if (part.mimeType==='text/html') texts.html += text; else texts.plain += text;
          }
          (part.parts || []).forEach(collect);
        }
        collect(msg.payload);
        const payload = JSON.stringify({correo:expected,id:item.id,from:header('From'),subject:subject,body:texts.html || texts.plain});
        const ts = String(Math.floor(Date.now()/1000));
        const sig = Utilities.computeHmacSha256Signature(ts+'.'+payload,secret,Utilities.Charset.UTF_8)
          .map(b => ('0'+((b+256)%256).toString(16)).slice(-2)).join('');
        const response = UrlFetchApp.fetch(url,{method:'post',contentType:'application/json',payload:payload,headers:{'X-OJ-Timestamp':ts,'X-OJ-Signature':sig},muteHttpExceptions:true,followRedirects:false});
        let result = {};try {result = JSON.parse(response.getContentText());} catch(e) {}
        if (response.getResponseCode()===200 && result.ok===true) {known.add(item.id);done.push(item.id);count++;}
        else {failures++;console.warn('No se importó el aviso '+item.id+'. HTTP '+response.getResponseCode());}
      }
      pageToken = page.nextPageToken;
      if (deferred) break;
    } while (pageToken);
    // Guarda solo IDs recientes. El servidor comprueba también duplicados bancarios.
    done = done.slice(-300); props.setProperty('OJ_PROCESADOS',JSON.stringify(done));
    if (!failures && !deferred) props.setProperty('OJ_ULTIMA_REVISION',String(started));
    console.log('Avisos procesados: '+count+'. Errores: '+failures+'. Revisión parcial: '+deferred);
    if (failures) throw new Error('Hay avisos pendientes. Revisa Ejecuciones y el módulo de movimientos; la siguiente revisión los reintentará.');
  } finally { lock.releaseLock(); }
}
function activarRevisionCadaCincoMinutos() {
  sincronizarBanorte();
  desactivarRevision();
  ScriptApp.newTrigger('sincronizarBanorte').timeBased().everyMinutes(5).create();
}
function desactivarRevision() {
  ScriptApp.getProjectTriggers().filter(t => t.getHandlerFunction()==='sincronizarBanorte').forEach(t => ScriptApp.deleteTrigger(t));
}
