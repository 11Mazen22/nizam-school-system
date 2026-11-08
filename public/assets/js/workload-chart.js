/*
 * Nizam -- Assignments workload-summary chart. Same CSP-safe pattern as
 * dashboard.js: reads server-rendered JSON from the canvas's data-chart
 * attribute rather than an inline <script> block.
 */
document.addEventListener('DOMContentLoaded', function () {
  var canvas = document.getElementById('workload-chart');
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
    return;
  }

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: data.labels,
      datasets: [{
        data: data.totals,
        backgroundColor: '#315efb',
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
