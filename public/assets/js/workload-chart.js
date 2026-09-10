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
        backgroundColor: '#2f4b7c',
      }],
    },
    options: {
      animation: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });
});
