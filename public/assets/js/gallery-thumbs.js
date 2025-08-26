/* gallery-thumbs.js
 * Remplace l'image principale (#main-photo) quand on clique une miniature.
 * Utilise l'attribut data-full sur chaque <img> miniature pour l'URL grande.
 */
(function () {
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $all(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

  document.addEventListener('DOMContentLoaded', () => {
    const main = $('#main-photo');
    const thumbs = $all('.miniatures img');

    if (!main || thumbs.length === 0) return;

    // marquer la miniature active
    function setActive(thumb) {
      thumbs.forEach(t => {
        t.classList.toggle('is-active', t === thumb);
        t.setAttribute('aria-selected', t === thumb ? 'true' : 'false');
      });
    }

    // précharger une image
    function preload(src) {
      const img = new Image();
      img.src = src;
    }

    // handler commun
    function showFromThumb(thumb) {
      const full = thumb.dataset.full || thumb.src;
      const alt  = thumb.getAttribute('alt') || main.getAttribute('alt') || '';

      // si l’URL ne change pas, ne rien faire
      if (main.src === full) { setActive(thumb); return; }

      // transition douce (optionnel)
      main.style.opacity = '0';
      preload(full);
      setTimeout(() => {
        main.src = full;
        main.alt = alt;
        main.addEventListener('load', () => { main.style.opacity = '1'; }, { once: true });
      }, 80);

      setActive(thumb);
    }

    thumbs.forEach((thumb, i) => {
      thumb.tabIndex = 0;
      thumb.setAttribute('role', 'option');
      if (i === 0) setActive(thumb);

      thumb.addEventListener('click', () => showFromThumb(thumb));
      thumb.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          showFromThumb(thumb);
        }
      });
    });
  });
})();
