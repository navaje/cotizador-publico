/* La preferencia de vista no concede ni elimina permisos. */
(function () {
  'use strict';
  var body = document.body;
  var key = 'oj_panel_modo_v1_' + body.dataset.workUser;
  var links = Array.from(document.querySelectorAll('.work-mode[data-mode]'));
  function apply(mode, remember) {
    if (mode !== 'campo' && mode !== 'oficina') mode = 'oficina';
    body.dataset.workMode = mode;
    document.querySelectorAll('[data-work-section]').forEach(function (section) {
      section.hidden = section.dataset.workSection !== mode;
    });
    links.forEach(function (link) {
      if (link.dataset.mode === mode) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
    document.getElementById('work-filter-mode').value = mode;
    document.getElementById('work-clear').href = 'dashboard.php?modo=' + mode;
    if (remember) { try { localStorage.setItem(key, mode); } catch (_) {} }
  }
  var initial = body.dataset.workMode;
  if (body.dataset.workExplicit !== '1') {
    try { initial = localStorage.getItem(key) || initial; } catch (_) {}
  }
  apply(initial, false);
  links.forEach(function (link) {
    link.addEventListener('click', function (event) {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
      // Los enlaces conservan navegación normal si el navegador no admite history.
      try { history.replaceState(null, '', link.href); } catch (_) { return; }
      event.preventDefault();
      apply(link.dataset.mode, true);
    });
  });
  window.addEventListener('popstate', function () {
    apply(new URL(location.href).searchParams.get('modo') || 'oficina', false);
  });
  // Si un enlace pide abrir visitas listas, su contenido debe quedar visible.
  function revealHash() {
    if (location.hash === '#visitas-listas') document.getElementById('visitas-listas').open = true;
  }
  document.querySelectorAll('a[href="#visitas-listas"]').forEach(function (link) {
    link.addEventListener('click', function () { document.getElementById('visitas-listas').open = true; });
  });
  window.addEventListener('hashchange', revealHash);
  revealHash();
})();
