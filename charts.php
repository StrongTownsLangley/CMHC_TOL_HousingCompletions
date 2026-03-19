<?php
/**
 * charts.php
 *
 * Reads completions and population CSVs from output/, computes all
 * chart data (axis limits, ratios, metric cards), and returns JSON
 * for client-side Chart.js rendering.
 *
 * GET parameters:
 *   comp  – completions CSV filename (e.g. completions_burnaby_cy.csv)
 *   pop   – population CSV filename  (e.g. population_burnaby.csv)
 *   name  – display name for headings (e.g. City of Burnaby)
 *
 * All parameters are validated against mapping.csv before any file
 * is touched.
 */

header('Content-Type: application/json; charset=utf-8');

$BASE_DIR   = __DIR__;
$OUTPUT_DIR = $BASE_DIR . '/output';
$MAPPING    = $OUTPUT_DIR . '/mapping.csv';

// ── Read parameters ───────────────────────────────────────────────
$comp_file  = $_GET['comp'] ?? '';
$pop_file   = $_GET['pop']  ?? '';
$chart_name = $_GET['name'] ?? '';

if ($comp_file === '' || $pop_file === '' || $chart_name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters: comp, pop, name']);
    exit;
}

// ── Whitelist validation against mapping.csv ──────────────────────
if (!file_exists($MAPPING)) {
    http_response_code(500);
    echo json_encode(['error' => 'mapping.csv not found. Run generate.py first.']);
    exit;
}

$valid = false;
if (($h = fopen($MAPPING, 'r')) !== false) {
    $header = fgetcsv($h);
    $col = array_flip($header);
    while (($row = fgetcsv($h)) !== false) {
        if (($row[$col['cmhc_csv']] ?? '') === $comp_file
            && ($row[$col['pop_csv']] ?? '') === $pop_file
            && ($row[$col['chart_name']] ?? '') === $chart_name) {
            $valid = true;
            break;
        }
    }
    fclose($h);
}

if (!$valid) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters. Must match mapping.csv.']);
    exit;
}

// ── Read CSVs ─────────────────────────────────────────────────────
$comp_path = $OUTPUT_DIR . '/' . $comp_file;
$pop_path  = $OUTPUT_DIR . '/' . $pop_file;

if (!file_exists($comp_path)) {
    http_response_code(404);
    echo json_encode(['error' => "Completions file not found: $comp_file"]);
    exit;
}

$comp = read_csv($comp_path);
$pop_data = file_exists($pop_path) ? read_csv($pop_path) : [];

// ── Build year-aligned arrays ─────────────────────────────────────
$years   = array_map(fn($r) => (int)$r['year'], $comp);
$single  = array_map(fn($r) => (int)$r['single'], $comp);
$semi    = array_map(fn($r) => (int)$r['semi'], $comp);
$row_    = array_map(fn($r) => (int)$r['row'], $comp);
$apt     = array_map(fn($r) => (int)$r['apt'], $comp);
$total   = array_map(fn($r) => (int)$r['total'], $comp);

// Population lookup by year
$pop_lookup = [];
foreach ($pop_data as $r) {
    $pop_lookup[(int)$r['year']] = (int)$r['population'];
}
$population = array_map(fn($y) => $pop_lookup[$y] ?? 0, $years);
$has_pop = !empty($pop_data) && max($population) > 0;

// ── Types chart data ──────────────────────────────────────────────
$y_max_raw = max($total);
$y_max = round_up($y_max_raw, pick_step($y_max_raw));

$types = [
    'years'  => array_map('strval', $years),
    'single' => $single,
    'duplex' => $semi,
    'row'    => $row_,
    'apt'    => $apt,
    'total'  => $total,
    'yMax'   => $y_max,
    'slug'   => '',  // not needed for web
    'exportTitle' => $chart_name ? "Housing completions, $chart_name" : 'Housing completions',
];

// ── Ratio chart data (only if population available) ───────────────
$ratio_data = null;

if ($has_pop) {
    $n_yr       = count($years);
    $first_year = $years[0];
    $last_year  = $years[$n_yr - 1];
    $last_comp  = $total[$n_yr - 1];
    $last_pop   = $population[$n_yr - 1];
    $sum_comp   = array_sum($total);

    $ratios = [];
    for ($i = 0; $i < $n_yr; $i++) {
        $ratios[] = $population[$i] > 0
            ? round($total[$i] / $population[$i] * 100, 2)
            : 0;
    }
    $last_ratio = $ratios[$n_yr - 1];
    $avg_ratio  = round(array_sum($ratios) / $n_yr, 2);
    $pop_pct    = $population[0] > 0
        ? round(($last_pop - $population[0]) / $population[0] * 100)
        : 0;
    $pop_change = ($pop_pct >= 0 ? '+' : '') . $pop_pct . '%';

    $comp_max = round_up(max($total), pick_step(max($total)));
    [$p_min, $p_max, $p_step] = pop_axis($population);

    $ratio_max_raw = max($ratios);
    $ratio_max = $ratio_max_raw <= 4
        ? round_up($ratio_max_raw, 0.5)
        : round_up($ratio_max_raw, 1);

    $year_range = "$first_year – $last_year";

    $ratio_data = [
        'years'       => array_map('strval', $years),
        'completions' => $total,
        'population'  => $population,
        'compMax'     => $comp_max,
        'popMin'      => $p_min,
        'popMax'      => $p_max,
        'popStep'     => $p_step,
        'ratioMax'    => $ratio_max,
        'slug'        => '',
        'exportSubtitle' => $chart_name ? "$chart_name · $year_range" : $year_range,
        'metricCards' => [
            [
                'label' => "TOTAL COMPLETIONS ($last_year)",
                'value' => number_format($last_comp),
                'sub'   => "$n_yr-yr total: " . number_format($sum_comp),
            ],
            [
                'label' => "POPULATION ($last_year)",
                'value' => number_format($last_pop),
                'sub'   => "$pop_change since $first_year",
            ],
            [
                'label' => 'COMPLETIONS / POPULATION',
                'value' => number_format($last_ratio, 2) . '%',
                'sub'   => "$n_yr-yr avg: " . number_format($avg_ratio, 2) . '%',
            ],
        ],
    ];
}

// ── Output ────────────────────────────────────────────────────────
echo json_encode([
    'ok'         => true,
    'chart_name' => $chart_name,
    'year_range' => $years[0] . ' – ' . $years[count($years) - 1],
    'types'      => $types,
    'ratio'      => $ratio_data,
], JSON_UNESCAPED_UNICODE);


// ── Helper functions (mirror chart_annual.py) ─────────────────────

function read_csv(string $path): array {
    $rows = [];
    if (($h = fopen($path, 'r')) === false) return $rows;
    $header = fgetcsv($h);
    $header = array_map(fn($s) => strtolower(trim($s)), $header);
    while (($row = fgetcsv($h)) !== false) {
        $assoc = [];
        foreach ($header as $i => $key) {
            $assoc[$key] = trim($row[$i] ?? '');
        }
        $rows[] = $assoc;
    }
    fclose($h);
    return $rows;
}

function pick_step(int $max_val): int {
    if ($max_val <= 500)     return 50;
    if ($max_val <= 1500)    return 250;
    if ($max_val <= 5000)    return 500;
    if ($max_val <= 20000)   return 2000;
    if ($max_val <= 100000)  return 10000;
    if ($max_val <= 500000)  return 50000;
    return 100000;
}

function round_up(float $value, float $step): float {
    return ceil($value / $step) * $step;
}

function pop_axis(array $pop): array {
    $lo = min($pop);
    $hi = max($pop);
    $step = pick_step($hi - $lo);
    $axis_min = max(0, floor($lo / $step) * $step - $step);
    $axis_max = ceil($hi / $step) * $step + $step;
    return [$axis_min, $axis_max, $step];
}
