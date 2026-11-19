'use strict';
document.querySelectorAll('form').forEach(form=>{
  let enviando=false;
  const botones=Array.from(form.querySelectorAll('button[type="submit"]'));
  const etiquetas=botones.map(b=>b.textContent);
  form.addEventListener('submit',e=>{
    if(enviando){e.preventDefault();return;}
    enviando=true;botones.forEach(b=>{b.disabled=true;b.textContent='Procesando…';});
  });
  window.addEventListener('pageshow',()=>{enviando=false;botones.forEach((b,i)=>{b.disabled=false;b.textContent=etiquetas[i];});});
});
