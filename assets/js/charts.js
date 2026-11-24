/* Draws every <canvas class="js-chart" data-chart='{"type","labels","values","unit"}'> with Chart.js.
   The numbers are also in a table next to each chart, so nothing depends on this script. */
(function () {
  'use strict';
  if (typeof Chart === 'undefined') { return; }

  var ink = '#1d2b3a', brand = '#0b5f66', line = '#d5dde4';
  // Colour-blind-safe set, used in this order. Charts also carry labels and numbers, so colour is never the only cue.
  var palette = ['#0b5f66', '#d98e04', '#6a4c93', '#2a9d8f', '#c1440e', '#3d5a80', '#8a8f98', '#b5838d'];

  Chart.defaults.font.family = '"Atkinson Hyperlegible", system-ui, sans-serif';
  Chart.defaults.color = ink;

  function colors(n) { var out = []; for (var i = 0; i < n; i++) { out.push(palette[i % palette.length]); } return out; }

  document.querySelectorAll('canvas.js-chart').forEach(function (el) {
    var cfg;
    try { cfg = JSON.parse(el.getAttribute('data-chart')); } catch (e) { return; }
    var type = cfg.type, horizontal = type === 'hbar', isRound = type === 'doughnut';
    var ds = { label: cfg.unit, data: cfg.values };
    if (isRound) { ds.backgroundColor = colors(cfg.values.length); ds.borderColor = '#fff'; ds.borderWidth = 2; }
    else if (type === 'line') { ds.borderColor = brand; ds.backgroundColor = 'rgba(11,95,102,.15)'; ds.fill = true; ds.tension = .25; ds.pointRadius = 3; }
    else { ds.backgroundColor = brand; ds.borderRadius = 3; }

    var options = { responsive: true, maintainAspectRatio: false, animation: { duration: 400 }, plugins: { legend: { display: isRound, position: 'bottom' } } };
    if (!isRound) {
      options.indexAxis = horizontal ? 'y' : 'x';
      options.scales = {
        x: { grid: { display: horizontal, color: line }, ticks: { precision: 0, autoSkip: false, maxRotation: horizontal ? 0 : 55 } },
        y: { grid: { color: line }, beginAtZero: true, ticks: { precision: 0 } }
      };
      if (horizontal) { options.scales.y = { grid: { display: false }, ticks: { autoSkip: false } }; options.scales.x.beginAtZero = true; }
    }
    new Chart(el, { type: isRound ? 'doughnut' : (type === 'line' ? 'line' : 'bar'), data: { labels: cfg.labels, datasets: [ds] }, options: options });
  });
})();
