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
        backgroundColor: ['#2f4b7c', '#b4842a', '#5b6b7a'],
      }],
    },
    options: {
      animation: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });
});
