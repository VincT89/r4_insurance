// resources/js/app-shell.js

export function initShell() {

  // --- Sidebar state ora gestito interamente via Alpine in layouts/app.blade.php ---
  // --- Toast system tipizzato ---
  window.toast = function (msg, type = 'success') {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.dataset.type = type; // permette stili CSS: #toast[data-type="error"] { ... }
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2600);
  };

  document.querySelectorAll('[data-flash-toast]:not([data-toast-shown])').forEach(flash => {
    flash.dataset.toastShown = 'true';
    window.toast(flash.textContent.trim(), flash.dataset.flashToast);
  });

  // --- Listener globali: solo una volta ---
  if (window._shellListenersAdded) return;
  window._shellListenersAdded = true;

  // --- Chiusura overlay (.overlay.open) con Escape ---
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.overlay.open').forEach(o => o.classList.remove('open'));
    }
  });

  // --- Chiusura overlay cliccando fuori ---
  document.addEventListener('click', e => {
    document.querySelectorAll('.overlay.open').forEach(o => {
      if (e.target === o) o.classList.remove('open');
    });
  });
}
