'use strict';
(() => {
  const form = document.getElementById('factura-form');
  if (!form) return;
  const uppercase = field => {if (field.matches('[data-uppercase]')) field.value = field.value.toLocaleUpperCase('es-MX');};
  form.addEventListener('change', event => uppercase(event.target));
  form.addEventListener('focusout', event => uppercase(event.target));
  form.addEventListener('submit', () => form.querySelectorAll('[data-uppercase]').forEach(uppercase));
  const container = document.getElementById('factura-conceptos');
  const add = document.getElementById('agregar-concepto');
  if (!container || !add) return;
  const renumber = () => container.querySelectorAll('.factura-concepto').forEach((row, index) => {
    row.querySelectorAll('input[name]').forEach(field => {field.name = field.name.replace(/\[\d+\]$/, '[' + index + ']');});
  });
  add.addEventListener('click', () => {
    if (container.children.length >= 100) return;
    const row = container.firstElementChild.cloneNode(true);
    row.querySelectorAll('input').forEach(field => {field.value = field.name.startsWith('cantidad[') ? '1' : '';});
    container.append(row);renumber();row.querySelector('input').focus();
  });
  container.addEventListener('click', event => {
    if (!event.target.matches('[data-remove-concept]') || container.children.length <= 1) return;
    event.target.closest('.factura-concepto').remove();renumber();
  });
})();
