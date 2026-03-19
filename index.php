<?php
$MAPPING = __DIR__ . '/output/mapping.csv';
$municipalities = [];
if (file_exists($MAPPING) && ($h = fopen($MAPPING, 'r')) !== false) {
    $header = fgetcsv($h);
    $col = array_flip($header);
    while (($row = fgetcsv($h)) !== false) {
        $municipalities[] = [
            'display_name' => $row[$col['display_name']] ?? '',
            'cmhc_csv'     => $row[$col['cmhc_csv']]     ?? '',
            'pop_csv'      => $row[$col['pop_csv']]      ?? '',
            'chart_name'   => $row[$col['chart_name']]   ?? '',
        ];
    }
    fclose($h);
}
$mapping_json = json_encode($municipalities, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BC Housing Completions</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f6f5f1;--card:#fff;--text:#2c2c2a;--text-sec:#5f5e5a;
  --muted:#b4b2a9;--border:#e8e7e3;--border-lt:#f1efe8;
  --accent:#3266ad;--surface:#faf9f7;
  --blue:#3266ad;--coral:#D85A30;--purple:#534AB7;--green:#1D9E75;--grey:#73726c;
  --grid:#f1efe8;--tick:#b4b2a9;
  --shadow:0 1px 3px rgba(0,0,0,.04),0 8px 24px rgba(0,0,0,.06);
  --radius:12px;--radius-sm:8px;
}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* ── Top bar ─────────────────────────────────── */
.topbar{background:var(--card);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.topbar-inner{max-width:960px;margin:0 auto;padding:1.25rem 2.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem}
.topbar h1{font-size:1.1rem;font-weight:600;letter-spacing:-.02em;white-space:nowrap}
.topbar h1 span{color:var(--muted);font-weight:400;margin-left:.3em}
.sel-wrap{position:relative;min-width:280px}
.sel-wrap select{font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:500;color:var(--text);background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:10px 40px 10px 14px;width:100%;appearance:none;cursor:pointer;outline:none;transition:border-color .15s,box-shadow .15s}
.sel-wrap select:hover{border-color:var(--muted)}
.sel-wrap select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(50,102,173,.12)}
.sel-wrap::after{content:'';position:absolute;right:14px;top:50%;transform:translateY(-50%);border:5px solid transparent;border-top:6px solid var(--muted);pointer-events:none}

/* ── Content ─────────────────────────────────── */
.main{max-width:960px;margin:0 auto;padding:2rem 2.5rem}

/* ── Cards ───────────────────────────────────── */
.card{background:var(--card);border-radius:16px;padding:2.5rem 2.5rem 2rem;box-shadow:var(--shadow);margin-bottom:2rem;overflow:visible}
.card-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem}
.card-header h2{font-size:1.35rem;font-weight:600;letter-spacing:-.02em;line-height:1.3}
.card-header p{font-size:.82rem;color:var(--muted);margin-top:4px;font-family:'DM Mono',monospace}

/* ── Metrics row ─────────────────────────────── */
.metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:2rem}
.metric{background:var(--surface);border-radius:10px;padding:1rem 1.15rem}
.metric-label{font-size:.72rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
.metric-value{font-size:1.4rem;font-weight:600;color:#1a1a1a;letter-spacing:-.02em}
.metric-sub{font-size:.72rem;color:var(--muted);margin-top:2px;font-family:'DM Mono',monospace}

/* ── Legend ───────────────────────────────────── */
.legend{display:flex;flex-wrap:wrap;gap:18px;margin-bottom:1.25rem;font-size:.78rem;color:var(--muted)}
.legend-item{display:flex;align-items:center;gap:5px}
.legend-swatch{width:10px;height:10px;border-radius:2px;flex-shrink:0}
.legend-swatch.dashed{background:transparent !important;border:1.5px dashed var(--grey)}

/* ── Chart containers ────────────────────────── */
.chart-wrap{position:relative;width:100%;overflow:visible}
.chart-wrap.tall{height:380px}
.chart-wrap.short{height:200px}
canvas{display:block}

/* ── Panel divider / title ───────────────────── */
.panel{margin-bottom:2rem}
.panel:last-of-type{margin-bottom:0}
.panel-title{font-size:.82rem;font-weight:500;color:var(--text-sec);margin-bottom:.75rem;display:flex;align-items:center;gap:8px}
.panel-title .dot{width:8px;height:8px;border-radius:2px;flex-shrink:0}
.divider{height:1px;background:var(--border-lt);margin:1.5rem 0}

/* ── Footer ──────────────────────────────────── */
.card-footer{margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border-lt);font-size:.72rem;color:var(--muted);font-family:'DM Mono',monospace;display:flex;flex-direction:column;gap:2px}

/* ── States ──────────────────────────────────── */
.status{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:4rem 2rem;text-align:center}
.status-icon{width:56px;height:56px;margin:0 auto 1.25rem;background:var(--surface);border-radius:50%;display:flex;align-items:center;justify-content:center}
.status-icon svg{width:26px;height:26px;stroke:var(--muted);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.status h2{font-size:1.05rem;font-weight:600;margin-bottom:.4rem}
.status p{font-size:.85rem;color:var(--muted);max-width:360px;margin:0 auto;line-height:1.5}
.spinner{width:32px;height:32px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 1rem}
@keyframes spin{to{transform:rotate(360deg)}}
.error-banner{background:#fef2f0;border:1px solid #f0c6be;border-radius:var(--radius-sm);padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.85rem;color:#9e3620;display:none}
.no-mapping{background:#fef8ee;border:1px solid #eedcb0;border-radius:var(--radius-sm);padding:1rem 1.25rem;font-size:.85rem;color:#8a6914;line-height:1.5}
.no-mapping code{font-family:'DM Mono',monospace;font-size:.82rem;background:rgba(0,0,0,.06);padding:2px 5px;border-radius:3px}

@media(max-width:640px){
  .topbar-inner{flex-direction:column;align-items:stretch}
  .sel-wrap{min-width:auto}
  .main{padding:1.25rem}
  .card{padding:1.5rem}
  .metrics{grid-template-columns:1fr}
  .chart-wrap.tall{height:280px}
  .chart-wrap.short{height:160px}
}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <h1>BC Housing Completions <span>by municipality</span></h1>
<?php if ($municipalities): ?>
    <div class="sel-wrap">
      <select id="sel">
        <option value="" selected disabled>Select a municipality&hellip;</option>
<?php foreach ($municipalities as $i => $m): ?>
        <option value="<?= $i ?>"><?= htmlspecialchars($m['display_name'], ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
      </select>
    </div>
<?php endif; ?>
  </div>
</div>

<div class="main">
<?php if (!$municipalities): ?>
  <div class="no-mapping">
    <strong>mapping.csv not found.</strong> Run the generator first:<br>
    <code>python generate.py</code>
  </div>
<?php else: ?>

  <div id="errorBanner" class="error-banner"></div>

  <div id="emptyState" class="status">
    <div class="status-icon">
      <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 16l4-6 4 4 5-7"/></svg>
    </div>
    <h2>Choose a municipality</h2>
    <p>Select a BC municipality from the dropdown to view housing completions and population data.</p>
  </div>

  <div id="loadingState" class="status" style="display:none">
    <div class="spinner"></div>
    <h2>Loading data&hellip;</h2>
    <p>Fetching and computing chart data.</p>
  </div>

  <!-- Types chart card -->
  <div id="typesCard" class="card" style="display:none">
    <div class="card-header">
      <div>
        <h2 id="typesTitle">Housing completions</h2>
        <p id="typesSub"></p>
      </div>
    </div>
    <div class="legend">
      <span class="legend-item"><span class="legend-swatch" style="background:var(--blue)"></span>Single</span>
      <span class="legend-item"><span class="legend-swatch" style="background:var(--green)"></span>Duplex</span>
      <span class="legend-item"><span class="legend-swatch" style="background:var(--coral)"></span>Row</span>
      <span class="legend-item"><span class="legend-swatch" style="background:var(--purple)"></span>Apartment</span>
      <span class="legend-item"><span class="legend-swatch dashed"></span>Total</span>
    </div>
    <div class="chart-wrap tall"><canvas id="typesCanvas"></canvas></div>
    <div class="card-footer"><span>Source: CMHC Starts and Completions Survey</span></div>
  </div>

  <!-- Ratio chart card -->
  <div id="ratioCard" class="card" style="display:none">
    <div class="card-header">
      <div>
        <h2>Housing completions &amp; population growth</h2>
        <p id="ratioSub"></p>
      </div>
    </div>
    <div id="metricsRow" class="metrics"></div>
    <div class="panel">
      <div class="panel-title">
        <span class="dot" style="background:var(--blue)"></span> Total completions
        <span style="color:var(--muted);font-weight:400">&amp;</span>
        <span class="dot" style="background:var(--coral)"></span> Population
      </div>
      <div class="chart-wrap tall"><canvas id="dualCanvas"></canvas></div>
    </div>
    <div class="divider"></div>
    <div class="panel">
      <div class="panel-title">
        <span class="dot" style="background:var(--purple)"></span> Completions as % of population
      </div>
      <div class="chart-wrap short"><canvas id="ratioCanvas"></canvas></div>
    </div>
    <div class="card-footer">
      <span>Housing: CMHC Starts and Completions Survey</span>
      <span>Population: BC Stats, July 1 estimates</span>
    </div>
  </div>

<?php endif; ?>
</div>

<?php if ($municipalities): ?>
<script>
(function() {
  var MAPPING = <?= $mapping_json ?>;
  var BLUE = '#3266ad', CORAL = '#D85A30', PURPLE = '#534AB7',
      GREEN = '#1D9E75', GREY = '#73726c', GRID = '#f1efe8', TICK = '#b4b2a9';

  var sel       = document.getElementById('sel');
  var empty     = document.getElementById('emptyState');
  var loading   = document.getElementById('loadingState');
  var errorEl   = document.getElementById('errorBanner');
  var typesCard = document.getElementById('typesCard');
  var ratioCard = document.getElementById('ratioCard');

  var live = { types: null, dual: null, ratio: null };

  sel.addEventListener('change', function() {
    var m = MAPPING[parseInt(sel.value, 10)];
    if (!m) return;

    showError('');
    empty.style.display = 'none';
    typesCard.style.display = 'none';
    ratioCard.style.display = 'none';
    loading.style.display = '';

    var url = 'charts.php?comp=' + encodeURIComponent(m.cmhc_csv)
            + '&pop=' + encodeURIComponent(m.pop_csv)
            + '&name=' + encodeURIComponent(m.chart_name);

    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        loading.style.display = 'none';
        if (!d.ok) {
          showError(d.error || 'Error loading data.');
          empty.style.display = '';
          return;
        }
        renderTypes(d);
        if (d.ratio) renderRatio(d);
      })
      .catch(function(e) {
        loading.style.display = 'none';
        empty.style.display = '';
        showError('Network error: ' + e.message);
      });
  });

  /* ── Types chart ─────────────────────────────────────── */

  function renderTypes(d) {
    var T = d.types;
    document.getElementById('typesTitle').textContent =
      d.chart_name ? 'Housing completions, ' + d.chart_name : 'Housing completions';
    document.getElementById('typesSub').textContent =
      d.year_range + ' \u00b7 by dwelling type';

    kill('types');
    live.types = new Chart(document.getElementById('typesCanvas'), {
      type: 'line',
      data: {
        labels: T.years,
        datasets: [
          ds('Total',     T.total,  GREY,   { w: 2.5, d: true }),
          ds('Apartment', T.apt,    PURPLE),
          ds('Row',       T.row,    CORAL),
          ds('Single',    T.single, BLUE),
          ds('Duplex',    T.duplex, GREEN)
        ]
      },
      options: opts({
        y: {
          beginAtZero: true, max: T.yMax,
          ticks: { color: TICK, font: mono(), callback: fmtN,
                   stepSize: T.yMax <= 2000 ? 250 : T.yMax <= 5000 ? 500 : 1000 },
          afterFit: pad, grid: { color: GRID }, border: { display: false }
        }
      })
    });
    typesCard.style.display = '';
  }

  /* ── Ratio charts ────────────────────────────────────── */

  function renderRatio(d) {
    var R = d.ratio;

    document.getElementById('ratioSub').textContent =
      (d.chart_name ? d.chart_name + ' \u00b7 ' : '') + d.year_range;

    // Metric cards
    var html = '';
    R.metricCards.forEach(function(c) {
      html += '<div class="metric">'
            + '<div class="metric-label">' + esc(c.label) + '</div>'
            + '<div class="metric-value">' + esc(c.value) + '</div>'
            + '<div class="metric-sub">' + esc(c.sub) + '</div></div>';
    });
    document.getElementById('metricsRow').innerHTML = html;

    var ratio = R.completions.map(function(c, i) {
      return Math.round(c / R.population[i] * 10000) / 100;
    });

    // Dual axis
    kill('dual');
    live.dual = new Chart(document.getElementById('dualCanvas'), {
      type: 'line',
      data: {
        labels: R.years,
        datasets: [
          { label: 'Total completions', data: R.completions,
            borderColor: BLUE, backgroundColor: BLUE + '18',
            borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#fff',
            pointBorderColor: BLUE, pointBorderWidth: 1.5, pointHoverRadius: 5,
            tension: 0.3, fill: true, yAxisID: 'y' },
          { label: 'Population', data: R.population,
            borderColor: CORAL, backgroundColor: 'transparent',
            borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#fff',
            pointBorderColor: CORAL, pointBorderWidth: 1.5, pointHoverRadius: 5,
            tension: 0.3, fill: false, borderDash: [6, 4], yAxisID: 'y1' }
        ]
      },
      options: opts({
        y: {
          type: 'linear', position: 'left', beginAtZero: true, max: R.compMax,
          title: { display: true, text: 'Completions', color: BLUE,
                   font: { family: "'DM Sans'", size: 11, weight: '500' } },
          ticks: { color: BLUE, font: mono(), callback: fmtN,
                   stepSize: R.compMax <= 2000 ? 250 : R.compMax <= 5000 ? 500 : 1000 },
          afterFit: pad, grid: { color: GRID }, border: { display: false }
        },
        y1: {
          type: 'linear', position: 'right', min: R.popMin, max: R.popMax,
          title: { display: true, text: 'Population', color: CORAL,
                   font: { family: "'DM Sans'", size: 11, weight: '500' } },
          ticks: { color: CORAL, font: mono(), stepSize: R.popStep,
                   callback: function(v) { return v >= 10000 ? (v/1000).toFixed(0)+'k' : v.toLocaleString(); } },
          grid: { display: false }, border: { display: false }
        }
      })
    });

    // Percentage line
    kill('ratio');
    live.ratio = new Chart(document.getElementById('ratioCanvas'), {
      type: 'line',
      data: {
        labels: R.years,
        datasets: [{
          label: 'Completions / population', data: ratio,
          borderColor: PURPLE, backgroundColor: PURPLE + '18',
          borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#fff',
          pointBorderColor: PURPLE, pointBorderWidth: 1.5, pointHoverRadius: 5,
          tension: 0.3, fill: true
        }]
      },
      options: opts({
        y: {
          min: 0, max: R.ratioMax,
          ticks: { color: TICK, font: mono(),
                   stepSize: R.ratioMax <= 2 ? 0.5 : 1,
                   callback: function(v) { return v.toFixed(1) + '%'; } },
          afterFit: pad, grid: { color: GRID }, border: { display: false }
        }
      }, function(c) { return ' ' + c.parsed.y.toFixed(2) + '%'; })
    });

    ratioCard.style.display = '';
  }

  /* ── Shared helpers ──────────────────────────────────── */

  function ds(label, data, color, o) {
    o = o || {};
    return {
      label: label, data: data, borderColor: color, backgroundColor: color + '12',
      borderWidth: o.w || 2, pointRadius: 3, pointBackgroundColor: '#fff',
      pointBorderColor: color, pointBorderWidth: 1.5, pointHoverRadius: 5,
      pointHoverBackgroundColor: color, tension: 0.3, fill: false,
      borderDash: o.d ? [6, 4] : undefined
    };
  }

  function opts(scales, labelCb) {
    return {
      responsive: true, maintainAspectRatio: false,
      layout: { padding: { left: 4, right: 4, top: 8 } },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#fff', titleColor: '#1a1a1a', bodyColor: '#5f5e5a',
          borderColor: '#e8e7e3', borderWidth: 1, padding: 12, bodySpacing: 5,
          cornerRadius: 8, displayColors: true, boxWidth: 8, boxHeight: 8, boxPadding: 4,
          titleFont: { family: "'DM Sans'", weight: '600', size: 13 },
          bodyFont: { family: "'DM Sans'", size: 12 },
          callbacks: {
            label: labelCb || function(c) {
              return ' ' + c.dataset.label + ':  ' + c.parsed.y.toLocaleString();
            }
          }
        }
      },
      scales: Object.assign({
        x: { ticks: { color: TICK, font: mono(), autoSkip: false, maxRotation: 0 },
             grid: { display: false }, border: { color: '#e8e7e3' } }
      }, scales)
    };
  }

  function mono() { return { family: "'DM Mono'", size: 11 }; }
  function fmtN(v) { return v.toLocaleString(); }
  function pad(a) { a.width = a.width + 8; }
  function kill(k) { if (live[k]) { live[k].destroy(); live[k] = null; } }

  function esc(s) {
    var el = document.createElement('span');
    el.textContent = s;
    return el.innerHTML;
  }

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.style.display = msg ? '' : 'none';
  }
})();
</script>
<?php endif; ?>
</body>
</html>
