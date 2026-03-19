# BC Housing Completions by Municipality

Python scripts that download CMHC housing completions and BC Stats population data for any BC municipality, producing time series from 2010 to 2025. Includes a web interface for browsing all 77 municipalities with population data.

**Web version: [strongtownslangley.org/library/completions](https://strongtownslangley.org/library/completions/)**

## Why this exists

Getting annual housing completions at the municipal level turned out to be surprisingly difficult:

- **CMHC's interactive portal** has data gaps for some municipalities (notably Langley DM from January 2023 through June 2025), likely due to an archived data series during their census geography transition.
- **Municipal building statistics** track permits issued, not completions or occupancy.
- **CMHC's downloadable Excel files** contain the data, but are spread across six different URL patterns and two different file formats spanning 2010 to 2023, with no single download covering all years.
- **2024 and 2025** are not in the annual files yet. The data has to be assembled from monthly CSD-level publications and cumulated manually.

These scripts handle all of that for any BC municipality.

## Quick start

```bash
pip install openpyxl requests
```

For a single municipality (defaults to Township of Langley):

```bash
python cmhc_annual.py
python pop_annual.py
python chart_annual.py output/completions_langley_dm.csv output/population_langley_district_municipality.csv -n "District of Langley"
```

For all 77 municipalities with both completions and population data:

```bash
python cmhc_annual.py -m "*"     # populate cmhc_cache/
python pop_annual.py -m "*"      # populate pop_cache/
python generate.py               # build all CSVs + mapping.csv
```

Downloads are cached in `cmhc_cache/` and `pop_cache/`. Both folders are included in this repository in case the source URLs change.

## Scripts

### cmhc_annual.py

Downloads CMHC completions data and extracts results for any municipality. Handles the annual files (2010 to 2023) and monthly files (2024 to 2025) automatically, merging cumulative monthly data into annual totals.

```
python cmhc_annual.py                            # Langley Township, all years
python cmhc_annual.py -m "Burnaby (CY)"          # exact match
python cmhc_annual.py -m "Bur*"                  # Burnaby, Burlington, Burns Lake
python cmhc_annual.py -m "Sur*" --start 2020     # Surrey from 2020
python cmhc_annual.py -m "Lang*"                 # Langley DM + Langley CY + Langford
python cmhc_annual.py --list                     # print all 542 municipality names
python cmhc_annual.py --refresh                  # re-download all cached files
```

| Option | Description |
|---|---|
| `-m PATTERN` | Municipality name or wildcard pattern. Without wildcards, matches as substring. Default: `Langley (DM)` |
| `-o DIR` | Output directory. Default: `output` |
| `--start YEAR` | Start year. Default: 2010 |
| `--end YEAR` | End year. Default: 2025 |
| `--list` | Print all available municipality names and exit |
| `--refresh` | Re-download files even if cached |

Output per municipality: `completions_{slug}.csv` and `completions_{slug}.txt`.

CSV columns: `year, single, semi, row, apt, total`.

### pop_annual.py

Extracts annual population estimates for BC municipalities from BC Stats data (two xlsx files covering 2001 to present).

```
python pop_annual.py                             # Township of Langley
python pop_annual.py -m "Bur*"                   # Burnaby, Burns Lake
python pop_annual.py -m "Victoria"               # Victoria
python pop_annual.py -m "*"                       # all municipalities
python pop_annual.py -m "*" --start 2010          # all, from 2010
python pop_annual.py --refresh                    # re-download cached files
python pop_annual.py --list                       # print all municipality names
```

| Option | Description |
|---|---|
| `-m PATTERN` | Municipality name or wildcard pattern. Default: `Langley, District Municipality` |
| `-o DIR` | Output directory. Default: `output` |
| `--start YEAR` | Start year. Default: earliest available |
| `--end YEAR` | End year. Default: latest available |
| `--list` | Print all available municipality names and exit |
| `--refresh` | Re-download cached files |

Output per municipality: `population_{slug}.csv` and `population_{slug}.txt`.

CSV columns: `Year, Population`.

### chart_annual.py

Generates standalone HTML chart files from the CSVs, using Chart.js. Produces up to two charts: a dwelling-type breakdown (always) and a completions-vs-population ratio chart (when a population CSV is provided). Auto-detects which CSV is which from the headers, so file order does not matter.

```
python chart_annual.py output/completions_burnaby_cy.csv output/population_burnaby.csv -n "City of Burnaby"
python chart_annual.py output/completions_langley_dm.csv    # types chart only, no population
python chart_annual.py comp.csv pop.csv -n "City of Burnaby" -o charts
python chart_annual.py comp.csv pop.csv -n "City of Burnaby" --stdout   # JSON to stdout
```

| Option | Description |
|---|---|
| `-n NAME` | Display name for chart headings (e.g. "City of Burnaby"). Also used to derive the output filename slug. If omitted, the slug comes from the completions filename. |
| `-o DIR` | Output directory. Default: `output`. Ignored with `--stdout`. |
| `--stdout` | Print chart HTML as a JSON object to stdout instead of writing files. Keys: `types_html` (always), `ratio_html` (if population given). |

Output: `chart_annual_types_{slug}.html` and `chart_annual_ratio_to_pop_{slug}.html`. Both include an Export PNG button.

Templates (`chart_annual_types_template.html` and `chart_annual_ratio_to_pop_template.html`) must be in the same directory as the script.

### generate.py

One-time batch script for the web interface. Reads the xlsx files in `cmhc_cache/` and `pop_cache/` to find every BC municipality that appears in both datasets (77 matches), then calls `cmhc_annual.py` and `pop_annual.py` once per municipality to produce all CSVs. Writes `output/mapping.csv` which powers the web dropdown.

```
python generate.py                                           # default cache dirs
python generate.py --pop-cache pop_cache --cmhc-cache cmhc_cache
python generate.py -o output                                 # custom output dir
```

| Option | Description |
|---|---|
| `--pop-cache DIR` | BC Stats cache directory. Default: `pop_cache` |
| `--cmhc-cache DIR` | CMHC cache directory. Default: `cmhc_cache` |
| `-o DIR` | Output directory. Default: `output` |

Requires both cache directories to exist and contain data from prior runs. Produces 77 completions CSVs, 77 population CSVs, and `mapping.csv`.

## Web interface

The web interface at [strongtownslangley.org/library/completions](https://strongtownslangley.org/library/completions/) provides a dropdown of all 77 BC municipalities and renders the charts interactively using Chart.js. It consists of two PHP files and the pre-generated `output/` directory:

- **`index.php`** reads `output/mapping.csv` to populate the municipality dropdown. On selection, it fetches chart data from `charts.php` and renders three Chart.js charts directly in the page: dwelling-type breakdown, dual-axis completions vs population, and completions as a percentage of population.
- **`charts.php`** reads the completions and population CSVs, computes axis limits, ratios, and metric card values, and returns JSON. All parameters are validated against `mapping.csv` before any file is read.

No Python runs at request time. All data is pre-generated by `generate.py`.

To deploy, copy `index.php`, `charts.php`, and the `output/` folder to any PHP-capable web server.

## Example output

```
====================================================================
HOUSING COMPLETIONS - LANGLEY (DM)
Source: CMHC Starts and Completions Survey
====================================================================

  Year    Single    Semi     Row      Apt    Total  Note
--------------------------------------------------------------------
  2010       218      12     244      308      782
  2011       199       2     391      396      988
  2012       219       2     348      614     1183
  2013       310       4     201      443      958
  2014       262      18     384      515     1179
  2015       275      14     459      271     1019
  2016       243      20     500      438     1201
  2017       420      34     713      435     1602
  2018       364       4     323      670     1361
  2019       273      28     207      583     1091
  2020       275      50     404      458     1187
  2021       253      46     563      977     1839
  2022       336      42     559     1581     2518
  2023       363     120     512     1103     2098
  2024       403      76     359     1242     2080
  2025       248      84     477     1232     2041
--------------------------------------------------------------------
                                              2115  5-yr avg (2021-2025)
                                              1445  avg (16 full yrs)
Under construction (December 2025): 2,493
```

## Charts

Standalone HTML charts (via `chart_annual.py`):

<img width="1200" height="800" alt="langley_housing_completions" src="https://github.com/user-attachments/assets/6c288120-d05b-4bdf-9424-46b1a946ff0c" />

<img width="1200" height="1300" alt="langley_completions_population" src="https://github.com/user-attachments/assets/fb12a0c6-e767-43d4-b010-147d808a5783" />

## Data sources

**Housing completions** come from the [CMHC Starts and Completions Survey](https://www.cmhc-schl.gc.ca/professionals/housing-markets-data-and-research/housing-research/surveys/methods/methodologies-starts-completions-market-absorption-survey). A "completion" means all proposed construction work has been performed (up to 10% remaining is permitted). This is the closest available measure to "move-in ready" at the municipal level.

- 2010 to 2023: [Housing Completions by Dwelling Type](https://www.cmhc-schl.gc.ca/professionals/housing-markets-data-and-research/housing-data/data-tables/housing-market-data/housing-completions-dwelling-type) (annual)
- 2024 onward: [Starts, Completions, and Under Construction by CSD](https://www.cmhc-schl.gc.ca/professionals/housing-markets-data-and-research/housing-data/data-tables/housing-market-data/starts-completions-under-construction-census-subdivisions) (monthly, cumulated to annual)

**Population estimates** come from [BC Stats](https://www2.gov.bc.ca/gov/content/data/statistics/people-population-community/population/population-estimates), July 1 estimates.
