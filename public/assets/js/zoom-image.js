/* zoom-image.js
 * Zoom sur #main-photo : clic pour activer/désactiver, molette pour +/-,
 * glisser pour déplacer quand zoomé. Fonctionne desktop et touch basique.
 */
(function () {
  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

  document.addEventListener('DOMContentLoaded', () => {
    const img = document.getElementById('main-photo');
    if (!img) return;

    const container = img.closest('.image-principale') || img.parentElement;
    if (!container) return;

    // styles requis sur le conteneur
    container.style.overflow = 'hidden';
    container.style.position = container.style.position || 'relative';

    let scale = 1;           // niveau de zoom
    const minScale = 1;
    const maxScale = 5;
    let isPanning = false;
    let startX = 0, startY = 0;
    let imgX = 0, imgY = 0;  // translation courante

    // appliquer transform
    function applyTransform() {
      img.style.transform = `translate(${imgX}px, ${imgY}px) scale(${scale})`;
      img.style.transformOrigin = '0 0';
      img.style.willChange = 'transform';
    }

    // bornes de déplacement selon échelle
    function bounds() {
      const rect = container.getBoundingClientRect();
      const w = img.naturalWidth || img.width;
      const h = img.naturalHeight || img.height;
      const scaledW = (w * scale) * (img.width / (img.naturalWidth || img.width));
      const scaledH = (h * scale) * (img.height / (img.naturalHeight || img.height));
      const minX = Math.min(0, rect.width - scaledW);
      const minY = Math.min(0, rect.height - scaledH);
      const maxX = 0;
      const maxY = 0;
      return { minX, minY, maxX, maxY };
    }

    function setScale(next, centerX, centerY) {
      const rect = img.getBoundingClientRect();
      const cx = centerX - rect.left;
      const cy = centerY - rect.top;

      const prevScale = scale;
      scale = clamp(next, minScale, maxScale);

      // zoom vers le point du curseur: ajuster la translation
      imgX = (imgX - cx) * (scale / prevScale) + cx;
      imgY = (imgY - cy) * (scale / prevScale) + cy;

      // clamp dans les bornes
      const b = bounds();
      imgX = clamp(imgX, b.minX, b.maxX);
      imgY = clamp(imgY, b.minY, b.maxY);

      applyTransform();
      container.classList.toggle('is-zoomed', scale > 1);
      container.style.cursor = scale > 1 ? 'grab' : 'zoom-in';
    }

    // clic pour activer/désactiver
    container.addEventListener('click', (e) => {
      if (scale === 1) {
        setScale(2, e.clientX, e.clientY);
      } else {
        scale = 1; imgX = 0; imgY = 0; applyTransform();
        container.classList.remove('is-zoomed');
        container.style.cursor = 'zoom-in';
      }
    });

    // molette pour ajuster
    container.addEventListener('wheel', (e) => {
      if (!e.ctrlKey) e.preventDefault();
      const delta = e.deltaY > 0 ? -0.2 : 0.2;
      setScale(scale + delta, e.clientX, e.clientY);
    }, { passive: false });

    // pan (drag) quand zoomé
    container.addEventListener('mousedown', (e) => {
      if (scale === 1) return;
      isPanning = true; startX = e.clientX - imgX; startY = e.clientY - imgY;
      container.style.cursor = 'grabbing';
    });
    window.addEventListener('mousemove', (e) => {
      if (!isPanning) return;
      imgX = e.clientX - startX; imgY = e.clientY - startY;
      const b = bounds();
      imgX = clamp(imgX, b.minX, b.maxX);
      imgY = clamp(imgY, b.minY, b.maxY);
      applyTransform();
    });
    window.addEventListener('mouseup', () => {
      isPanning = false;
      if (scale > 1) container.style.cursor = 'grab';
    });

    // touch (simple) : drag
    container.addEventListener('touchstart', (e) => {
      if (scale === 1) return;
      if (e.touches.length !== 1) return;
      const t = e.touches[0];
      isPanning = true; startX = t.clientX - imgX; startY = t.clientY - imgY;
    }, { passive: true });
    container.addEventListener('touchmove', (e) => {
      if (!isPanning || e.touches.length !== 1) return;
      const t = e.touches[0];
      imgX = t.clientX - startX; imgY = t.clientY - startY;
      const b = bounds();
      imgX = clamp(imgX, b.minX, b.maxX);
      imgY = clamp(imgY, b.minY, b.maxY);
      applyTransform();
    }, { passive: true });
    container.addEventListener('touchend', () => { isPanning = false; }, { passive: true });

    // init
    img.style.transition = 'transform 120ms ease';
    container.style.cursor = 'zoom-in';
    applyTransform();
  });
})();
