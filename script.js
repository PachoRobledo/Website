// Ingenio — interactividad básica
(function () {
  // Año del pie de página y fecha de hoy
  document.querySelectorAll('.year').forEach(el => { el.textContent = new Date().getFullYear(); });
  const today = document.getElementById('today');
  if (today) {
    const s = new Date().toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    today.textContent = s.charAt(0).toUpperCase() + s.slice(1);
  }

  // Menú móvil
  const toggle = document.getElementById('menu-toggle');
  const nav = document.getElementById('nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open);
    });
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => nav.classList.remove('open')));
  }

  // Curiosidades aleatorias
  const facts = [
    'Las primeras gafas de sol se usaban en China para ocultar las expresiones de los jueces.',
    'El microondas se inventó cuando una barra de chocolate se derritió cerca de un radar.',
    'Los post-it nacieron de un pegamento que «no pegaba lo suficiente».',
    'El primer ordenador electrónico de propósito general ocupaba una sala entera.',
    'El velcro se inspiró en las semillas de bardana que se pegaban al pelo de un perro.',
    'La fregona moderna fue patentada en España en los años cincuenta.',
    'Las patatas fritas de bolsa surgieron, según la leyenda, por un cliente exigente.',
    'El chupa-chups fue creado para que los niños no se mancharan las manos.'
  ];
  const factEl = document.getElementById('fact');
  const factBtn = document.getElementById('new-fact');
  if (factEl && factBtn) {
    let i = 0;
    factBtn.addEventListener('click', () => {
      i = (i + 1 + Math.floor(Math.random() * (facts.length - 1))) % facts.length;
      factEl.textContent = facts[i];
    });
  }

  // Búsqueda en las tarjetas
  const input = document.getElementById('search-input');
  const cards = document.querySelectorAll('#cards .card');
  const none = document.getElementById('no-results');
  const norm = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  if (input && cards.length) {
    input.addEventListener('input', () => {
      const q = norm(input.value.trim());
      let shown = 0;
      cards.forEach(c => {
        const match = !q || norm(c.textContent + ' ' + (c.dataset.title || '')).includes(q);
        c.style.display = match ? '' : 'none';
        if (match) shown++;
      });
      if (none) none.hidden = shown > 0;
      if (q) document.getElementById('inventos').scrollIntoView({ behavior: 'smooth' });
    });
  }

  // Boletín
  const form = document.getElementById('newsletter-form');
  const msg = document.getElementById('form-msg');
  if (form && msg) {
    form.addEventListener('submit', e => {
      e.preventDefault();
      msg.textContent = '¡Gracias! Revisa tu correo para confirmar la suscripción.';
      form.reset();
    });
  }

  // Botón volver arriba
  const top = document.getElementById('to-top');
  if (top) {
    window.addEventListener('scroll', () => top.classList.toggle('show', window.scrollY > 600), { passive: true });
    top.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }
})();
