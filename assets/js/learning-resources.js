/* StudentFlow - Learning Resource Finder page */
(function () {
  'use strict';
  const SF = window.SF;

  const form    = document.getElementById('resourceForm');
  const input   = document.getElementById('resourceQuery');
  const status  = document.getElementById('resourceStatus');
  const grid    = document.getElementById('resourceGrid');
  const loading = document.getElementById('resourceLoading');
  const empty   = document.getElementById('resourceEmpty');
  const btn     = document.getElementById('resourceSearchBtn');

  let inFlight = false;

  function setLoading(show) {
    inFlight = show;
    if (loading) loading.classList.toggle('hidden', !show);
    if (grid) grid.classList.add('hidden');
    if (empty) empty.classList.add('hidden');
    if (btn) btn.disabled = show;
    if (status) status.innerHTML = show
      ? '<p class="flex items-center gap-2 text-sm text-sky-600 dark:text-sky-400"><i data-lucide="loader-circle" class="w-4 h-4 animate-spin"></i> Searching books…</p>'
      : '';
    if (show && window.lucide) lucide.createIcons({ nodes: [status] });
  }

  function showError(message) {
    if (status) {
      status.innerHTML =
        '<div class="flex items-start gap-3 rounded-2xl border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 px-4 py-3 text-sm text-rose-600 dark:text-rose-400">' +
        '<i data-lucide="circle-alert" class="w-4.5 h-4.5 mt-0.5 shrink-0"></i><span>' + SF.esc(message || 'Something went wrong. Please try again.') + '</span></div>';
      if (window.lucide) lucide.createIcons({ nodes: [status] });
    }
    if (grid) grid.classList.add('hidden');
    if (empty) empty.classList.add('hidden');
  }

  function coverHtml(r) {
    if (r.cover) {
      return '<img src="' + SF.esc(r.cover) + '" alt="" loading="lazy" data-cover class="w-full h-40 object-cover rounded-t-2xl bg-slate-100 dark:bg-slate-800">';
    }
    return '<div class="w-full h-40 flex flex-col items-center justify-center bg-gradient-to-br from-sky-50 to-indigo-100 dark:from-slate-800 dark:to-slate-800 rounded-t-2xl border-b border-slate-200 dark:border-slate-700">' +
      '<i data-lucide="book-open" class="w-8 h-8 text-slate-300 dark:text-slate-600"></i></div>';
  }

  function cardHtml(r) {
    const meta = [];
    if (r.year) meta.push(r.year);
    if (r.publisher) meta.push(r.publisher);
    if (r.pages) meta.push(r.pages + ' pages');
    if (r.provider) meta.push('via ' + r.provider);

    return (
      '<article class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-soft transition hover:-translate-y-0.5 hover:shadow-lg">' +
      coverHtml(r) +
      '<div class="flex flex-col flex-1 p-4">' +
      '<div class="flex items-center gap-2">' +
      '<span class="rounded-full bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 px-2 py-0.5 text-[10px] font-bold tracking-wide">' + SF.esc(r.type || 'Book') + '</span>' +
      '</div>' +
      '<h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-white line-clamp-2 leading-snug">' + SF.esc(r.title) + '</h3>' +
      '<p class="mt-1 text-xs text-slate-500 dark:text-slate-400 truncate">' + SF.esc(r.author) + '</p>' +
      (meta.length ? '<p class="mt-0.5 text-[11px] text-slate-400 truncate">' + SF.esc(meta.join(' · ')) + '</p>' : '') +
      (r.description ? '<p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400 line-clamp-3">' + SF.esc(r.description) + '</p>' : '') +
      '<div class="mt-auto pt-4 flex items-center gap-2">' +
      (r.preview
        ? '<a href="' + SF.esc(r.preview) + '" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold px-3 py-2"><i data-lucide="book-open" class="w-3.5 h-3.5"></i> Preview</a>'
        : '') +
      (r.link
        ? '<a href="' + SF.esc(r.link) + '" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-semibold px-3 py-2"><i data-lucide="external-link" class="w-3.5 h-3.5"></i> Open</a>'
        : '') +
      '</div></div></article>'
    );
  }

  function render(resources) {
    if (grid) grid.classList.remove('hidden');
    if (resources.length === 0) {
      grid.innerHTML = '';
      if (empty) empty.classList.remove('hidden');
      if (grid) grid.classList.add('hidden');
      return;
    }
    if (empty) empty.classList.add('hidden');
    grid.innerHTML = resources.map(cardHtml).join('');
    if (window.lucide) lucide.createIcons({ nodes: [grid] });

    // Graceful fallback when a cover image fails to load.
    grid.querySelectorAll('[data-cover]').forEach(function (img) {
      img.addEventListener('error', function () {
        const holder = document.createElement('div');
        holder.className = 'w-full h-40 flex items-center justify-center bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-800 rounded-t-2xl border-b border-slate-200 dark:border-slate-700';
        holder.innerHTML = '<i data-lucide="book-open" class="w-8 h-8 text-slate-300 dark:text-slate-600"></i>';
        img.replaceWith(holder);
        if (window.lucide) lucide.createIcons({ nodes: [holder] });
      });
    });
  }

  function search(query) {
    query = (query || '').trim();
    if (!query || inFlight) return;
    if (input) input.value = query;
    setLoading(true);
    return SF.get('api/learning-resources.php?action=search&q=' + encodeURIComponent(query))
      .then(function (json) {
        setLoading(false);
        if (!json.ok) { showError(json.message); return; }
        const items = (json.data && json.data.resources) || [];
        render(items);
      });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      search(input ? input.value : '');
    });
  }

  if (input) {
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); search(input.value); }
    });
  }

  document.querySelectorAll('[data-resource-topic]').forEach(function (chip) {
    chip.addEventListener('click', function () { search(chip.getAttribute('data-resource-topic')); });
  });

  if (empty) empty.classList.remove('hidden');
})();