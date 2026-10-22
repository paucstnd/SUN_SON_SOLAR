/* Sun Son Solar dashboards - small shared behaviours (no framework). */
(function () {
  var body = document.body;

  // Mobile sidebar
  var menuBtn = document.getElementById('menuBtn');
  var scrim = document.getElementById('scrim');
  if (menuBtn) menuBtn.addEventListener('click', function () { body.classList.toggle('menu-open'); });
  if (scrim) scrim.addEventListener('click', function () { body.classList.remove('menu-open'); });

  // Modals: [data-open-modal="id"] opens, [data-close-modal] or Esc or backdrop closes
  function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); }
  function closeModals() { document.querySelectorAll('.modal-overlay.active').forEach(function (m) { m.classList.remove('active'); }); }
  window.SSS = { openModal: openModal, closeModals: closeModals };
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-open-modal]');
    if (open) { e.preventDefault(); openModal(open.getAttribute('data-open-modal')); return; }
    if (e.target.closest('[data-close-modal]')) { closeModals(); return; }
    if (e.target.classList && e.target.classList.contains('modal-overlay')) closeModals();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModals(); });

  // Confirm dialogs: <form data-confirm="Are you sure?">
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Digits only: <input data-digits>
  document.querySelectorAll('input[data-digits]').forEach(function (el) {
    el.addEventListener('input', function () { this.value = this.value.replace(/[^0-9]/g, ''); });
  });

  // Show/hide password buttons
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-toggle-password'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
    });
  });

  // Auto-hide success toasts
  var toast = document.querySelector('.message.toast:not(.error)');
  if (toast) setTimeout(function () { toast.style.display = 'none'; }, 6000);
})();
