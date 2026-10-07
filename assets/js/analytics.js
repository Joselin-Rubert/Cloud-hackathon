/* StudentFlow - Analytics page */
(function () {
  'use strict';
  const SF = window.SF;
  const CH = window.SFCharts;
  let cache = null;

  function showLoading(show) {
    document.querySelectorAll('.chart-loading').forEach(function (el) {
      el.classList.toggle('hidden', !show);
    });
  }

  function render(d) {
    cache = d;

    // metric cards
    const map = {
      'm-complete': d.tasks.total_completed + ' / ' + d.tasks.total_open,
      'm-study': SF.fmtMinutes(d.study.week),
      'm-focus': SF.fmtMinutes(d.focus.week),
      'm-expense': SF.fmtMoney(d.expenses.month),
      'm-habits': d.habits.rate + '%',
      'm-goals': d.goals.average + '%',
    };
    Object.keys(map).forEach(function (key) {
      const el = document.getElementById(key);
      if (el) el.textContent = map[key];
    });

    // 1. tasks per day
    CH.render('taskChart', {
      type: 'bar',
      data: {
        labels: d.tasks.days.map(function (x) { return x.label; }),
        datasets: [
          { label: 'Completed', data: d.tasks.days.map(function (x) { return x.completed; }), backgroundColor: '#6366f1', borderRadius: 6 },
          { label: 'Created', data: d.tasks.days.map(function (x) { return x.due; }), backgroundColor: '#e2e8f0', borderRadius: 6 },
        ],
      },
      options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: CH.colors().grid } }, x: { grid: { display: false } } } },
    });

    // 2. study minutes
    CH.render('studyChart', {
      type: 'bar',
      data: {
        labels: d.study.days.map(function (x) { return x.label; }),
        datasets: [{ label: 'Minutes', data: d.study.days.map(function (x) { return x.value; }), backgroundColor: '#0ea5e9', borderRadius: 6 }],
      },
      options: { scales: { y: { beginAtZero: true, grid: { color: CH.colors().grid } }, x: { grid: { display: false } } } },
    });

    // 3. focus minutes
    CH.render('focusChart', {
      type: 'line',
      data: {
        labels: d.focus.days.map(function (x) { return x.label; }),
        datasets: [{ label: 'Minutes', data: d.focus.days.map(function (x) { return x.value; }), borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.12)', fill: true, tension: 0.35 }],
      },
      options: { scales: { y: { beginAtZero: true, grid: { color: CH.colors().grid } }, x: { grid: { display: false } } } },
    });

    // 4. expense doughnut
    const expLabels = Object.keys(d.expenses.categories);
    CH.render('expenseChart', {
      type: 'doughnut',
      data: {
        labels: expLabels.length ? expLabels : ['No spending'],
        datasets: [{
          data: expLabels.length ? expLabels.map(function (k) { return d.expenses.categories[k]; }) : [1],
          backgroundColor: expLabels.length
            ? ['#6366f1', '#f97316', '#10b981', '#f43f5e', '#0ea5e9', '#a855f7', '#eab308', '#64748b']
            : ['#e2e8f0'],
        }],
      },
      options: { cutout: '68%', plugins: { legend: { position: 'right' } } },
    });

    // 5. habit completion
    CH.render('habitChart', {
      type: 'line',
      data: {
        labels: d.habits.days.map(function (x) { return x.label; }),
        datasets: [{ label: 'Completion %', data: d.habits.days.map(function (x) { return x.value; }), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.12)', fill: true, tension: 0.35, yAxisID: 'y' }],
      },
      options: { scales: { y: { beginAtZero: true, max: 100, grid: { color: CH.colors().grid } }, x: { grid: { display: false } } } },
    });

    // 6. goals
    CH.render('goalChart', {
      type: 'bar',
      data: {
        labels: d.goals.items.map(function (x) { return x.label; }),
        datasets: [{ label: 'Progress %', data: d.goals.items.map(function (x) { return x.value; }), backgroundColor: d.goals.items.map(function (x) { return x.status === 'completed' ? '#10b981' : x.status === 'overdue' ? '#f43f5e' : '#8b5cf6'; }), borderRadius: 6 }],
      },
      options: {
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, max: 100, grid: { color: CH.colors().grid } }, y: { grid: { display: false } } },
      },
    });

    // 7. deadline risk distribution
    const dc = (d.deadline_risk && d.deadline_risk.counts) || {};
    CH.render('riskChart', {
      type: 'doughnut',
      data: {
        labels: ['Critical', 'Approaching', 'On track'],
        datasets: [{
          data: [dc.critical || 0, dc.approaching || 0, dc.safe || 0],
          backgroundColor: ['#f43f5e', '#f59e0b', '#10b981'],
        }],
      },
      options: { cutout: '68%', plugins: { legend: { position: 'right' } } },
    });
  }

  function load() {
    showLoading(true);
    return SF.get('api/analytics.php').then(function (json) {
      showLoading(false);
      if (!json.ok) { SF.toast(json.message || 'Failed to load analytics.', 'error'); return; }
      render(json.data);
      SF.toast('Analytics refreshed.', 'success');
    });
  }

  const btn = document.getElementById('btnRefreshAnalytics');
  if (btn) btn.addEventListener('click', function () { btn.disabled = true; load().then(function () { btn.disabled = false; }); });

  window.addEventListener('sf:theme', function () { if (cache) render(cache); });

  load();
})();
