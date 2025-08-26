/* public/assets/js/filtre-avance.js
   - Sliders année/prix synchronisés
   - Auto-apply des filtres (AJAX)
   - Remplace .catalogue-grid + met à jour l'URL
*/
(function () {
  const form = document.getElementById('filters');
  if (!form) return;

  const y1 = form.querySelector('#year_min');
  const y2 = form.querySelector('#year_max');
  const p1 = form.querySelector('#price_min');
  const p2 = form.querySelector('#price_max');
  const lblY = form.querySelector('#lblYears');
  const lblP = form.querySelector('#lblPrices');
  const autos = form.querySelectorAll('[data-autosubmit]');

  function clamp(a, b) {
    if (!a || !b) return;
    const av = +a.value, bv = +b.value;
    if (av > bv) { a.value = bv; b.value = av; }
  }

  function syncYears() {
    clamp(y1, y2);
    if (lblY) lblY.textContent = `${y1.value} - ${y2.value}`;
    schedule();
  }

  function syncPrices() {
    clamp(p1, p2);
    if (lblP) {
      const fmt = n => Number(n).toLocaleString();
      lblP.textContent = `${fmt(p1.value)}$ - ${fmt(p2.value)}$`;
    }
    schedule();
  }

  if (y1 && y2) { y1.addEventListener('input', syncYears); y2.addEventListener('input', syncYears); }
  if (p1 && p2) { p1.addEventListener('input', syncPrices); p2.addEventListener('input', syncPrices); }
  autos.forEach(el => el.addEventListener('change', schedule));

  let debounceId = null, ctrl = null;
  function schedule() {
    clearTimeout(debounceId);
    debounceId = setTimeout(applyFilters, 250);
  }

  async function applyFilters() {
    const params = new URLSearchParams(new FormData(form));
    const url = form.action + '?' + params.toString();

    try {
      if (ctrl) ctrl.abort();
      ctrl = new AbortController();
      const res = await fetch(url, { signal: ctrl.signal, headers: { 'X-Requested-With': 'fetch' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const newGrid = doc.querySelector('.catalogue-grid');
      if (newGrid) {
        const grid = document.querySelector('.catalogue-grid');
        if (grid) grid.replaceWith(newGrid);
        history.replaceState(null, '', url);
      } else {
        form.submit(); // fallback si la grille n'est pas trouvée
      }
    } catch (e) {
      // console.warn('Filtre AJAX: fallback', e);
    }
  }

  // init labels
  if (y1 && y2) syncYears();
  if (p1 && p2) syncPrices();
})();
