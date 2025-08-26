document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('images');
  const container = document.getElementById('image-previews');
  if (!input || !container) return;

  // --- Helpers ---
  function clearPreviews(){
    while (container.firstChild) container.removeChild(container.firstChild);
  }

  // Si aucune option n'est cochée, on met required=true pour forcer le choix à l'envoi.
  // Si au moins une est cochée, on enlève required pour ne pas bloquer pendant qu'on change d'avis.
  function updateRequired(){
    const radios = container.querySelectorAll('input[type="radio"][name="primary_index"]');
    const anyChecked = Array.from(radios).some(r => r.checked);
    radios.forEach(r => r.required = !anyChecked);
  }

  // Permettre de DÉCOCHER un radio en recliquant dessus
  let downOn = null;
  container.addEventListener('mousedown', (e) => {
    const r = e.target.closest('input[type="radio"][name="primary_index"]');
    downOn = (r && r.checked) ? r : null;
  });
  container.addEventListener('click', (e) => {
    const r = e.target.closest('input[type="radio"][name="primary_index"]');
    if (downOn && r === downOn) {
      // décocher si on a cliqué sur un radio qui était déjà coché
      r.checked = false;
      r.dispatchEvent(new Event('change', { bubbles: true }));
    }
    downOn = null;
    updateRequired();
  });

  function renderPreviews(files){
    clearPreviews();

    if (!files || !files.length) {
      const p = document.createElement('p');
      p.className = 'note';
      p.textContent = 'Aucune image sélectionnée.';
      container.appendChild(p);
      updateRequired();
      return;
    }

    Array.from(files).forEach((file, idx) => {
      const url = URL.createObjectURL(file);

      const label = document.createElement('label');
      label.className = 'img-option';

      const radio = document.createElement('input');
      radio.type = 'radio';
      radio.name = 'primary_index';
      radio.value = String(idx);
      // ⚠️ pas de radio.checked = true ici → par défaut rien n’est coché

      // Styling de la carte sélectionnée
      radio.addEventListener('change', () => {
        container.querySelectorAll('.img-option').forEach(l => l.classList.remove('is-primary'));
        if (radio.checked) label.classList.add('is-primary');
        updateRequired();
      });

      const img = document.createElement('img');
      img.alt = 'Aperçu ' + (idx + 1);
      img.src = url;
      img.onload = () => URL.revokeObjectURL(url);

      const caption = document.createElement('span');
      caption.className = 'filename';
      caption.textContent = file.name;

      label.appendChild(radio);
      label.appendChild(img);
      label.appendChild(caption);
      container.appendChild(label);
    });

    // À l'initialisation, aucune sélection → required actif
    updateRequired();
  }

  input.addEventListener('change', (e) => renderPreviews(e.target.files));
});
