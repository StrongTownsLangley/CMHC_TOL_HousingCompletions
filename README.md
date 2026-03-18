# CMHC Housing Completions — Township of Langley

A Python script that downloads and compiles CMHC housing completions data for the Township of Langley (Langley DM (District Municipality)), producing a single time series of move-in ready units from 2010 to 2025.

## Why this exists

Getting annual housing completions at the municipal level for Langley turned out to be surprisingly difficult:

- **CMHC's interactive portal** has a data gap for Langley (DM) from January 2023 through June 2025, likely due to an archived data series during their census geography transition.
- **The Township of Langley** publishes monthly building statistics, but these track permits issued, not completions or occupancy.
- **CMHC's downloadable Excel files** do contain the data, but are spread across six different URL patterns and two different file formats spanning 2010–2023, with no single download covering all years.
- **2024–2025** aren't in the annual files yet. The data has to be assembled from monthly CSD-level publications and cumulated manually.

This script handles all of that.

## Usage

```
pip install openpyxl requests
python cmhc_langley_completions.py
```

Downloads are cached in `cmhc_cache/` and that folder is also included in this github in case the URLs are changed in future.

## Output

| File | Contents |
|---|---|
| `langley_dm_completions.txt` | Formatted summary table |
| `langley_dm_completions.csv` | One row per year: single, semi, row, apartment, total |
| `langley_dm_monthly_detail_2024_2025.csv` | Month-by-month detail for 2024–2025 |

## Data source

All data comes from the [CMHC Starts and Completions Survey](https://www.cmhc-schl.gc.ca/professionals/housing-markets-data-and-research/housing-research/surveys/methods/methodologies-starts-completions-market-absorption-survey). A "completion" means all proposed construction work has been performed (up to 10% remaining is permitted). This is the closest available measure to "move-in ready" at the municipal level.

Up to 2023:
https://www.cmhc-schl.gc.ca/professionals/housing-markets-data-and-research/housing-data/data-tables/housing-market-data/housing-completions-dwelling-type

2024+:
https://www.cmhc-schl.gc.ca/professionals/housing-markets-data-and-research/housing-data/data-tables/housing-market-data/starts-completions-under-construction-census-subdivisions
