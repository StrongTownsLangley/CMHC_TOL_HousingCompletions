#!/usr/bin/env python3
"""
Extract annual population estimates for BC municipalities from BC Stats data.

Downloads two Excel files from BC Stats and caches them locally in a pop_cache/
folder. By default extracts Township of Langley data. Use -m to match other
municipalities with wildcard patterns (e.g. -m "Bur*" for Burnaby).

Usage:
    python pop_annual.py                  # Township of Langley
    python pop_annual.py -m "Bur*"        # Burnaby
    python pop_annual.py -m "Victoria"    # Victoria
    python pop_annual.py -m "*"           # all municipalities
    python pop_annual.py --refresh        # re-download cached files
"""

import argparse
import fnmatch
import os
import sys
import urllib.request

try:
    import openpyxl
except ImportError:
    sys.exit("openpyxl is required. Install it with: pip install openpyxl")

CACHE_DIR = "pop_cache"

SOURCES = [
    {
        "url": "https://www2.gov.bc.ca/assets/gov/data/statistics/people-population-community/population/pop_municipal_subprov_areas_2001_2011.xlsx",
        "filename": "pop_municipal_subprov_areas_2001_2011.xlsx",
    },
    {
        "url": "https://www2.gov.bc.ca/assets/gov/data/statistics/people-population-community/population/pop_municipal_subprov_areas.xlsx",
        "filename": "pop_municipal_subprov_areas.xlsx",
    },
]


def download_file(url, dest):
    """Download a file if it doesn't already exist."""
    if os.path.exists(dest):
        return False
    print(f"Downloading {os.path.basename(dest)}...")
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req) as resp, open(dest, "wb") as f:
        f.write(resp.read())
    print(f"  Saved to {dest}")
    return True


def ensure_cache(refresh=False):
    """Download source files into the cache directory."""
    os.makedirs(CACHE_DIR, exist_ok=True)
    paths = []
    for src in SOURCES:
        dest = os.path.join(CACHE_DIR, src["filename"])
        if refresh and os.path.exists(dest):
            os.remove(dest)
        download_file(src["url"], dest)
        paths.append(dest)
    return paths


def read_municipalities(path):
    """
    Read the 'Mun Name Sort' sheet and return a dict of
    {municipality_name: {"area_type": str, "years": {year: population, ...}}}.
    """
    wb = openpyxl.load_workbook(path, data_only=True, read_only=True)
    ws = wb["Mun Name Sort"]

    rows = list(ws.iter_rows(values_only=True))
    wb.close()

    # Find the header row containing year columns
    header = None
    header_idx = None
    for i, row in enumerate(rows):
        if row and row[0] == "Name":
            header = row
            header_idx = i
            break

    if header is None:
        return {}

    # Identify which columns are year integers
    year_cols = []
    for col_idx, val in enumerate(header):
        if isinstance(val, (int, str)):
            try:
                year = int(val)
                if 1900 < year < 2100:
                    year_cols.append((col_idx, year))
            except (ValueError, TypeError):
                pass

    # Find the Area Type column
    area_type_col = None
    for col_idx, val in enumerate(header):
        if val == "Area Type":
            area_type_col = col_idx
            break

    municipalities = {}
    for row in rows[header_idx + 1 :]:
        name = row[0]
        if not name or not isinstance(name, str):
            continue
        name = name.strip()
        area_type = row[area_type_col].strip() if area_type_col and row[area_type_col] else ""
        pop = {}
        for col_idx, year in year_cols:
            val = row[col_idx] if col_idx < len(row) else None
            if isinstance(val, (int, float)) and val > 0:
                pop[year] = int(val)
        if pop:
            municipalities[name] = {"area_type": area_type, "years": pop}

    return municipalities


def merge_data(all_data):
    """
    Merge municipality dicts from multiple files. Later files take
    precedence for overlapping years.
    """
    merged = {}
    for data in all_data:
        for name, info in data.items():
            if name not in merged:
                merged[name] = {"area_type": info["area_type"], "years": {}}
            if info["area_type"]:
                merged[name]["area_type"] = info["area_type"]
            merged[name]["years"].update(info["years"])
    return merged


def match_municipalities(merged, pattern):
    """Return sorted list of municipality names matching a fnmatch pattern."""
    matched = [
        name for name in merged if fnmatch.fnmatch(name, pattern)
    ]
    return sorted(matched)


def slugify(name):
    """Convert a municipality name to a lowercase filename-safe slug."""
    out = []
    for ch in name.lower():
        if ch.isalnum():
            out.append(ch)
        elif out and out[-1] != "_":
            out.append("_")
    return "".join(out).strip("_")


def filter_years(years_data, start=None, end=None):
    """Return sorted (year, pop) pairs filtered by start/end."""
    years = sorted(years_data.keys())
    if start:
        years = [y for y in years if y >= start]
    if end:
        years = [y for y in years if y <= end]
    return [(y, years_data[y]) for y in years]


def write_outputs(name, area_type, rows, output_dir):
    """Write .txt and .csv files for one municipality. Returns the file stem."""
    stem = f"population_{slugify(name)}"
    os.makedirs(output_dir, exist_ok=True)

    txt_path = os.path.join(output_dir, f"{stem}.txt")
    csv_path = os.path.join(output_dir, f"{stem}.csv")

    full_name = f"{name} ({area_type})" if area_type else name

    with open(txt_path, "w") as f:
        f.write(f"{full_name} Population Estimates\n")
        f.write("Source: BC Stats, July 1 estimates\n")
        f.write("https://www2.gov.bc.ca/gov/content/data/statistics/people-population-community/population/population-estimates\n\n")
        f.write(f"{'Year':<8}{'Population':>12}\n")
        f.write("-" * 20 + "\n")
        for year, pop in rows:
            f.write(f"{year:<8}{pop:>12,}\n")

    with open(csv_path, "w") as f:
        f.write("Year,Population\n")
        for year, pop in rows:
            f.write(f"{year},{pop}\n")

    return stem


def main():
    parser = argparse.ArgumentParser(
        description="Extract BC municipal population estimates from BC Stats data."
    )
    parser.add_argument(
        "-m", "--match",
        default="Langley, District Municipality",
        help='Municipality name or wildcard pattern (default: "Langley, District Municipality"). '
             'Examples: "Bur*", "Victoria", "*Surrey*", "*"',
    )
    parser.add_argument(
        "--start", type=int, default=None,
        help="Start year (default: earliest available)",
    )
    parser.add_argument(
        "--end", type=int, default=None,
        help="End year (default: latest available)",
    )
    parser.add_argument(
        "-o", "--output-dir", default="output",
        help="Directory for output files (default: output/)",
    )
    parser.add_argument(
        "--refresh", action="store_true",
        help="Re-download cached files",
    )
    parser.add_argument(
        "--list", action="store_true", dest="list_all",
        help="List all available municipality names and exit",
    )
    args = parser.parse_args()

    paths = ensure_cache(refresh=args.refresh)

    all_data = []
    for path in paths:
        all_data.append(read_municipalities(path))

    merged = merge_data(all_data)

    if args.list_all:
        for name in sorted(merged.keys()):
            area_type = merged[name]["area_type"]
            print(f"{name} ({area_type})" if area_type else name)
        return

    matched = match_municipalities(merged, args.match)

    if not matched:
        print(f"No municipalities matching '{args.match}'.")
        print("Use --list to see all available names.")
        sys.exit(1)

    for name in matched:
        info = merged[name]
        rows = filter_years(info["years"], start=args.start, end=args.end)
        if not rows:
            print(f"{name}: no data in the requested year range, skipping.")
            continue
        stem = write_outputs(name, info["area_type"], rows, args.output_dir)
        print(f"  {stem}.txt  {stem}.csv")


if __name__ == "__main__":
    main()
