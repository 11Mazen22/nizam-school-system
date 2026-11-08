/*
 * Nizam -- dashboard religion-breakdown chart. Reads its data from the
 * canvas element's data-chart attribute (server-rendered JSON) rather than
 * an inline <script> block, because CSP's script-src is 'self' only (no
 * 'unsafe-inline') -- unlike style-src, this was deliberately NOT relaxed;
 * see Response::securityHeaders()'s docblock. External-file-plus-data-
 * attribute is the standard CSP-safe way to pass server data to a script.
 */
document.addEventListener('DOMContentLoaded', function () {
  var canvas = document.getElementById('religion-chart');
  if (!canvas || !window.Chart) {
    return;
  }

  var raw = canvas.getAttribute('data-chart');
  var data;
  try {
    data = JSON.parse(raw);
  } catch (e) {
    return;
  }

  if (!data.labels.length) {
    return; // empty state is handled server-side; nothing to draw
  }

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: data.labels,
      datasets: [{
        data: data.counts,
        backgroundColor: ['#315efb', '#0f9f91', '#94a3b8'],
        borderRadius: 7,
        maxBarThickness: 54,
      }],
    },
    options: {
      animation: window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ? false
        : { duration: 420, easing: 'easeOutQuart' },
      plugins: { legend: { display: false } },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { precision: 0, color: '#64748b' },
          grid: { color: '#e4eaf2' },
        },
        x: {
          ticks: { color: '#334155' },
          grid: { display: false },
        },
      },
    },
  });
});
