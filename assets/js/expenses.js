/* StudentFlow - Expenses page */
(function () {
  'use strict';
  const SF = window.SF;
  const CH = window.SFCharts;
  const data = window.SF_DATA || {};
  const colors = data.colors || {};
  const PALETTE = ['#6366f1', '#f97316', '#10b981', '#f43f5e', '#0ea5e9', '#a855f7', '#eab308', '#64748b'];
  let chartCache = null;

  function txt(sel, val) {
    const el = document.querySelector(sel);
    if (el) el.textContent = val;
  }

  function renderSummary(s) {
    txt('[data-exp-stat="today"]', SF.fmtMoney(s.today));
    txt('[data-exp-stat="week"]', SF.fmtMoney(s.week));
    txt('[data-exp-stat="month"]', SF.fmtMoney(s.month));
    txt('[data-exp-stat="budget"]', s.budget > 0 ? SF.fmtMoney(s.remaining) : 'Not set');
    txt('[data-exp-label="budget"]', s.budget > 0 ? 'Budget Left' : 'Monthly Budget');
    txt('[data-exp-foot="budget"]', s.budget > 0 ? 'of ' + SF.fmtMoney(s.budget) + ' budget' : 'set it in settings');
  }

  function renderInsights(list) {
    const box = document.getElementById('insightList');
    if (!box) return;
    box.innerHTML = (list || [])
      .map(function (line) {
        return '<li class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-300">' +
          '<span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span><span>' + SF.esc(line) + '</span></li>';
      })
      .join('');
  }

  function renderCharts(c) {
    chartCache = c;
    const cats = Object.keys(c.categories || {}).filter(function (k) { return c.categories[k] > 0; });

    CH.render('catChart', {
      type: 'doughnut',
      data: {
        labels: cats.length ? cats : ['No spending'],
        datasets: [{
          data: cats.length ? cats.map(function (k) { return c.categories[k]; }) : [1],
          backgroundColor: cats.length ? cats.map(function (k) { return colors[k] || PALETTE[0]; }) : ['#e2e8f0'],
          borderWidth: 0,
        }],
      },
      options: { cutout: '68%', plugins: { legend: { position: 'right' } } },
    });

    CH.render('weekChart', {
      type: 'line',
      data: {
        labels: (c.weeks || []).map(function (x) { return x.label; }),
        datasets: [{ label: 'Spent', data: (c.weeks || []).map(function (x) { return x.value; }), borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.12)', fill: true, tension: 0.35, pointRadius: 3 }],
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: CH.colors().grid } }, x: { grid: { display: false } } } },
    });

    CH.render('monthChart', {
      type: 'bar',
      data: {
        labels: (c.months || []).map(function (x) { return x.label; }),
        datasets: [{ label: 'Spent', data: (c.months || []).map(function (x) { return x.value; }), backgroundColor: '#8b5cf6', borderRadius: 6 }],
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: CH.colors().grid } }, x: { grid: { display: false } } } },
    });
  }

  function rowHtml(e) {
    const catColor = colors[e.category] || '#64748b';
    return (
      '<li class="grid grid-cols-2 sm:grid-cols-12 gap-2 sm:gap-4 px-5 py-3.5 items-center hover:bg-slate-50 dark:hover:bg-slate-800/40 transition" ' +
      'data-id="' + e.id + '" data-amount="' + SF.esc(String(e.amount)) + '" data-category="' + SF.esc(e.category) + '" ' +
      'data-description="' + SF.esc(e.description || '') + '" data-expense-date="' + e.expense_date + '">' +
      '<span class="sm:col-span-3 text-xs sm:text-sm text-slate-500 dark:text-slate-400 order-1">' +
      new Date(e.expense_date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + '</span>' +
      '<span class="sm:col-span-4 text-sm font-medium text-slate-800 dark:text-slate-200 order-3 sm:order-2 col-span-2 truncate">' + SF.esc(e.description || '—') + '</span>' +
      '<span class="sm:col-span-3 order-2 sm:order-3"><span class="inline-block rounded-full text-white px-2.5 py-0.5 text-[11px] font-medium" style="background:' + catColor + '">' +
      SF.esc(e.category) + '</span></span>' +
      '<span class="sm:col-span-2 text-right flex sm:block items-center justify-end gap-2 order-4">' +
      '<span class="text-sm font-semibold text-slate-900 dark:text-white">' + SF.fmtMoney(e.amount) + '</span>' +
      '<span class="flex gap-1">' +
      '<button type="button" data-edit-expense class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15" title="Edit"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>' +
      '<button type="button" data-delete-expense class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15" title="Delete"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>' +
      '</span></span></li>'
    );
  }

  function renderList(list) {
    const ul = document.getElementById('expenseList');
    const empty = document.getElementById('expenseEmpty');
    const count = document.getElementById('expenseCount');
    if (!ul) return;
    if (count) count.textContent = (list || []).length + ' entries';
    if (!list || !list.length) {
      ul.innerHTML = '';
      if (empty) empty.classList.remove('hidden');
      return;
    }
    if (empty) empty.classList.add('hidden');
    ul.innerHTML = list.map(rowHtml).join('');
    if (window.lucide) lucide.createIcons({ nodes: [ul] });
  }

  function applyPayload(d) {
    renderSummary(d.summary);
    renderInsights(d.insights);
    renderCharts(d.charts);
    renderList(d.expenses);
  }

  /* ---------------- modal form ---------------- */
  const form = document.getElementById('expenseForm');
  const modalError = document.getElementById('expenseModalError');
  const openBtn = document.getElementById('addExpenseBtn');

  function showError(msg) {
    if (!modalError) return;
    modalError.textContent = msg;
    modalError.classList.toggle('hidden', !msg);
  }

  function todayStr() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  if (openBtn) {
    openBtn.addEventListener('click', function () {
      form.reset();
      document.getElementById('exf_id').value = '';
      document.getElementById('exf_date').value = todayStr();
      document.getElementById('expenseModalTitle').textContent = 'Add Expense';
      showError('');
      SF.openModal('expenseModal');
      setTimeout(function () { document.getElementById('exf_amount').focus(); }, 50);
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const id = document.getElementById('exf_id').value;
      const fd = new FormData(form);
      const payload = {};
      fd.forEach(function (v, k) { payload[k] = v; });
      payload.action = id ? 'update' : 'create';
      if (!id) delete payload.id;

      SF.post('actions/expense_actions.php', payload).then(function (json) {
        if (!json.ok) {
          showError(json.message || 'Please check the form.');
          return;
        }
        SF.closeModal('expenseModal');
        applyPayload(json.data);
        SF.toast(json.message, 'success');
      });
    });
  }

  const list = document.getElementById('expenseList');
  if (list) {
    list.addEventListener('click', function (e) {
      const row = e.target.closest('li[data-id]');
      if (!row) return;
      const id = row.getAttribute('data-id');

      if (e.target.closest('[data-delete-expense]')) {
        SF.confirm('This expense will be permanently removed.', 'Delete expense').then(function (ok) {
          if (!ok) return;
          SF.post('actions/expense_actions.php', { action: 'delete', id: id }).then(function (json) {
            if (!json.ok) return;
            applyPayload(json.data);
            SF.toast(json.message, 'success');
          });
        });
        return;
      }
      if (e.target.closest('[data-edit-expense]')) {
        document.getElementById('exf_id').value = id;
        document.getElementById('exf_amount').value = row.getAttribute('data-amount');
        document.getElementById('exf_category').value = row.getAttribute('data-category');
        document.getElementById('exf_date').value = row.getAttribute('data-expense-date');
        document.getElementById('exf_description').value = row.getAttribute('data-description') || '';
        document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
        showError('');
        SF.openModal('expenseModal');
      }
    });
  }

  window.addEventListener('sf:theme', function () { if (chartCache) renderCharts(chartCache); });

  renderCharts((data.charts || {}));
})();
