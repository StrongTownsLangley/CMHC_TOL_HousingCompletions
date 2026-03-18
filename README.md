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

## Result
```
====================================================================
HOUSING COMPLETIONS - TOWNSHIP OF LANGLEY (DM)
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
                                              1445  avg all full years (16 yrs)

Units under construction as of December 2025: 2,493

Notes:
  'Completions' = move-in ready units per CMHC (all proposed
  construction work performed, or up to 10% remaining).
  '--' or missing values treated as 0.
  2010-2023: annual 'Housing Completions by Dwelling Type' tables.
  2024-2025: derived from cumulative monthly CSD data.
```

## Data source

All data comes from the [CMHC Starts and Completions Survey](https://www.cmhc-schl.gc.ca/professionals/housing-markets-data-and-research/housing-research/surveys/methods/methodologies-starts-completions-market-absorption-survey). A "completion" means all proposed construction work has been performed (up to 10% remaining is permitted). This is the closest available measure to "move-in ready" at the municipal level.

Up to 2023:
https://www.cmhc-schl.gc.ca/professionals/housing-markets-data-and-research/housing-data/data-tables/housing-market-data/housing-completions-dwelling-type

2024+:
https://www.cmhc-schl.gc.ca/professionals/housing-markets-data-and-research/housing-data/data-tables/housing-market-data/starts-completions-under-construction-census-subdivisions
