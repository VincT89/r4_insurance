export function initBgCanvas(canvasId) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || canvas.dataset.initialized) return;
  canvas.dataset.initialized = 'true';

  const ctx = canvas.getContext('2d');
  let resizeTimeout;

  function draw() {
    const parent = canvas.parentElement;
    const W = canvas.width = parent === document.body ? window.innerWidth : parent.clientWidth;
    const H = canvas.height = parent === document.body ? window.innerHeight : parent.clientHeight;

    // Tinta unica (Grigio-Blu Medio per un contrasto bilanciato con i pannelli bianchi)
    ctx.fillStyle = '#f5f5f5';
    ctx.fillRect(0, 0, W, H);

    // Spotlight bianco puro e morbido dall'alto (illumina i pannelli sottostanti)
    const v = ctx.createRadialGradient(W * 0.5, 0, 0, W * 0.5, H * 0.7, Math.max(W, H));
    v.addColorStop(0, 'rgba(255, 255, 255, 0.6)');
    v.addColorStop(1, 'rgba(255, 255, 255, 0)');
    ctx.fillStyle = v;
    ctx.fillRect(0, 0, W, H);
  }

  // Ricalcoliamo solo quando l'utente ridimensiona la finestra (Nessuna animazione, massime performance)
  const resizeObserver = new ResizeObserver(() => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
      requestAnimationFrame(draw);
    }, 100);
  });

  resizeObserver.observe(document.body);
  draw();
}
