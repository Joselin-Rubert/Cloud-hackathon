/* StudentFlow - Chart.js helpers with dark-mode awareness */
window.SFCharts = (function () {
  'use strict';
  const instances = {};

  function isDark() {
    return document.documentElement.classList.contains('dark');
  }

  function colors() {
    return isDark()
      ? { text: '#94a3b8', grid: 'rgba(148,163,184,0.12)', tooltipBg: '#0f172a' }
      : { text: '#64748b', grid: 'rgba(100,116,139,0.12)', tooltipBg: '#0f172a' };
  }

  function render(id, config) {
    const canvas = document.getElementById(id);
    if (!canvas || !window.Chart) return;
    if (instances[id]) {
      instances[id].destroy();
      delete instances[id];
    }
    const c = colors();
    Chart.defaults.color = c.text;
    Chart.defaults.borderColor = c.grid;
    Chart.defaults.font.family = "Inter, ui-sans-serif, system-ui, sans-serif";

    config.options = Object.assign(
      {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 500 },
        plugins: {
          legend: { labels: { boxWidth: 12, boxHeight: 12, usePointStyle: true, padding: 16, color: c.text } },
          tooltip: { backgroundColor: c.tooltipBg, padding: 10, cornerRadius: 8 },
        },
      },
      config.options || {}
    );
    instances[id] = new Chart(canvas, config);
  }

  function destroy(id) {
    if (instances[id]) {
      instances[id].destroy();
      delete instances[id];
    }
  }

  return { render: render, destroy: destroy, isDark: isDark, colors: colors };
})();
