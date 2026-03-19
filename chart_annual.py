#!/usr/bin/env python3
"""
Generate housing-chart HTML files from CSV data.

Templates (chart_annual_types_template.html and
chart_annual_ratio_to_pop_template.html) must sit in the same
directory as this script.

Usage:
  python chart_annual.py completions.csv population.csv -n "City of Burnaby"
  python chart_annual.py completions.csv population.csv -n "City of Burnaby" -o charts
  python chart_annual.py completions.csv                                      # types only
  python chart_annual.py completions.csv population.csv                       # no name in headings

Files can be given in any order; the script detects which is which
from the CSV headers.

  completions CSV:  year,single,semi,row,apt,total
  population  CSV:  year,population

Options:
  -n NAME   Pretty name shown in chart headings (e.g. "City of Burnaby").
            Also used to derive the output slug. If omitted, the slug is
            taken from the completions filename and headings are generic.
  -o DIR    Output directory (default: output).
"""

import argparse
import csv
import json
import math
import os
import re
import sys
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def read_csv(path):
    rows = []
    with open(path, newline="", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        for row in reader:
            rows.append({k.strip().lower(): v.strip() for k, v in row.items()})
    return rows


def sniff_csv(path):
    """Return 'completions' or 'population' based on the header row."""
    with open(path, newline="", encoding="utf-8-sig") as f:
        header = {h.strip().lower() for h in f.readline().split(",")}
    if {"single", "semi", "row", "apt", "total"} & header:
        return "completions"
    if "population" in header:
        return "population"
    return None


def nice_num(n):
    return f"{n:,}"


def round_up(value, step):
    return math.ceil(value / step) * step


def pick_step(max_val):
    if max_val <= 500:    return 50
    if max_val <= 1_500:  return 250
    if max_val <= 5_000:  return 500
    if max_val <= 20_000: return 2_000
    if max_val <= 100_000: return 10_000
    if max_val <= 500_000: return 50_000
    return 100_000


def pop_axis(pop_list):
    lo, hi = min(pop_list), max(pop_list)
    step = pick_step(hi - lo)
    axis_min = max(0, math.floor(lo / step) * step - step)
    axis_max = math.ceil(hi / step) * step + step
    return axis_min, axis_max, step


def slug_from_name(name):
    s = name.lower()
    s = re.sub(
        r"^(city|town|township|village|district|municipality|regional district)\s+of\s+",
        "", s,
    )
    return re.sub(r"[^a-z0-9]+", "_", s).strip("_")


def slug_from_filename(path):
    stem = Path(path).stem.lower()
    stem = re.sub(r"^completions_", "", stem)
    stem = re.sub(r"_(cy|dm|ca|csd|cd|cma|rd|vl)$", "", stem)
    cleaned = re.sub(r"[^a-z0-9]+", "_", stem).strip("_")
    return cleaned or ""


def resolve_slug(name, completions_path):
    if name:
        return slug_from_name(name)
    s = slug_from_filename(completions_path)
    if s:
        return s
    print(
        "ERROR: Could not determine a file slug from the completions filename.\n"
        "       Please supply -n \"Municipality Name\".",
        file=sys.stderr,
    )
    sys.exit(1)


# ---------------------------------------------------------------------------
# Data loading
# ---------------------------------------------------------------------------

def load_completions(path):
    rows = read_csv(path)
    years, single, semi, row_, apt, total = [], [], [], [], [], []
    for r in rows:
        years.append(int(r["year"]))
        single.append(int(r["single"]))
        semi.append(int(r["semi"]))
        row_.append(int(r["row"]))
        apt.append(int(r["apt"]))
        total.append(int(r["total"]))
    return years, single, semi, row_, apt, total


def load_population(path, year_range):
    rows = read_csv(path)
    lookup = {int(r["year"]): int(r["population"]) for r in rows}
    missing = [y for y in year_range if y not in lookup]
    if missing:
        print(f"WARNING: population file missing years: {missing}", file=sys.stderr)
    return [lookup.get(y, 0) for y in year_range]


# ---------------------------------------------------------------------------
# Template rendering
# ---------------------------------------------------------------------------

def render_template(template_name, replacements):
    tpl = SCRIPT_DIR / template_name
    if not tpl.exists():
        print(f"ERROR: template not found: {tpl}", file=sys.stderr)
        sys.exit(1)
    html = tpl.read_text(encoding="utf-8")
    for key, val in replacements.items():
        html = html.replace("{{" + key + "}}", str(val))
    return html


# ---------------------------------------------------------------------------
# Chart 1: completions by dwelling type
# ---------------------------------------------------------------------------

def build_types_chart(name, slug, years, single, semi, row_, apt, total, output_dir=None):
    title_suffix = f" \u2014 {name}" if name else ""
    heading_suffix = f", {name}" if name else ""
    year_range = f"{years[0]} \u2013 {years[-1]}"
    export_title = f"Housing completions, {name}" if name else "Housing completions"

    y_max_raw = max(total)
    y_max = round_up(y_max_raw, pick_step(y_max_raw))

    data_obj = {
        "years": [str(y) for y in years],
        "single": single, "duplex": semi, "row": row_,
        "apt": apt, "total": total,
        "yMax": y_max, "slug": slug, "exportTitle": export_title,
    }

    html = render_template("chart_annual_types_template.html", {
        "TITLE_SUFFIX": title_suffix,
        "HEADING_SUFFIX": heading_suffix,
        "YEAR_RANGE": year_range,
        "DATA_JSON": json.dumps(data_obj),
    })

    if output_dir is not None:
        out = os.path.join(output_dir, f"chart_annual_types_{slug}.html")
        Path(out).write_text(html, encoding="utf-8")
        print(f"  -> {out}", file=sys.stderr)

    return html


# ---------------------------------------------------------------------------
# Chart 2: completions + population + ratio
# ---------------------------------------------------------------------------

def build_pop_chart(name, slug, years, total, pop, output_dir=None):
    n_yr = len(years)
    first_year, last_year = years[0], years[-1]
    year_range = f"{first_year} \u2013 {last_year}"
    heading_place = f"{name} \u00b7 " if name else ""

    last_comp, last_pop, sum_comp = total[-1], pop[-1], sum(total)
    ratios = [round(c / p * 100, 2) for c, p in zip(total, pop)]
    last_ratio = ratios[-1]
    avg_ratio = round(sum(ratios) / len(ratios), 2)
    pop_pct = round((last_pop - pop[0]) / pop[0] * 100)
    pop_change = f"+{pop_pct}%" if pop_pct >= 0 else f"{pop_pct}%"

    comp_max = round_up(max(total), pick_step(max(total)))
    p_min, p_max, p_step = pop_axis(pop)
    ratio_max_raw = max(ratios)
    ratio_max = round_up(ratio_max_raw, 0.5) if ratio_max_raw <= 4 else round_up(ratio_max_raw, 1)

    export_subtitle = f"{name} \u00b7 {year_range}" if name else year_range

    data_obj = {
        "years": [str(y) for y in years],
        "completions": total, "population": pop,
        "compMax": comp_max, "popMin": p_min, "popMax": p_max, "popStep": p_step,
        "ratioMax": ratio_max, "slug": slug, "exportSubtitle": export_subtitle,
        "metricCards": [
            {"label": f"TOTAL COMPLETIONS ({last_year})",
             "value": nice_num(last_comp),
             "sub": f"{n_yr}-yr total: {nice_num(sum_comp)}"},
            {"label": f"POPULATION ({last_year})",
             "value": nice_num(last_pop),
             "sub": f"{pop_change} since {first_year}"},
            {"label": "COMPLETIONS / POPULATION",
             "value": f"{last_ratio:.2f}%",
             "sub": f"{n_yr}-yr avg: {avg_ratio:.2f}%"},
        ],
    }

    html = render_template("chart_annual_ratio_to_pop_template.html", {
        "TITLE_SUFFIX": f" \u2014 {name}" if name else "",
        "HEADING_PLACE": heading_place,
        "YEAR_RANGE": year_range,
        "LAST_YEAR": str(last_year), "FIRST_YEAR": str(first_year),
        "N_YR": str(n_yr),
        "LAST_COMPLETIONS": nice_num(last_comp),
        "SUM_COMPLETIONS": nice_num(sum_comp),
        "LAST_POP": nice_num(last_pop),
        "POP_CHANGE": pop_change,
        "LAST_RATIO": f"{last_ratio:.2f}",
        "AVG_RATIO": f"{avg_ratio:.2f}",
        "DATA_JSON": json.dumps(data_obj),
    })

    if output_dir is not None:
        out = os.path.join(output_dir, f"chart_annual_ratio_to_pop_{slug}.html")
        Path(out).write_text(html, encoding="utf-8")
        print(f"  -> {out}", file=sys.stderr)

    return html


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="Generate housing-chart HTML files from CSV data.",
        epilog="Templates must be in the same directory as this script.",
    )
    parser.add_argument(
        "files", nargs="+", metavar="FILE",
        help="One or two CSV files (completions and/or population). "
             "Order does not matter; detected automatically from headers.",
    )
    parser.add_argument(
        "-n", metavar="NAME",
        help="Pretty name for headings (e.g. \"City of Burnaby\").",
    )
    parser.add_argument(
        "-o", metavar="DIR", default="output",
        help="Output directory (default: output). Ignored with --stdout.",
    )
    parser.add_argument(
        "--stdout", action="store_true",
        help="Print chart HTML as JSON to stdout instead of writing files. "
             "JSON keys: types_html (always), ratio_html (if population given).",
    )
    args = parser.parse_args()

    if len(args.files) > 2:
        parser.error("At most two CSV files (completions + population).")

    # --- classify files ---
    comp_path = None
    pop_path = None
    for f in args.files:
        if not os.path.isfile(f):
            print(f"ERROR: file not found: {f}", file=sys.stderr)
            sys.exit(1)
        kind = sniff_csv(f)
        if kind == "completions":
            if comp_path:
                parser.error(f"Two completions files provided: {comp_path} and {f}")
            comp_path = f
        elif kind == "population":
            if pop_path:
                parser.error(f"Two population files provided: {pop_path} and {f}")
            pop_path = f
        else:
            print(f"ERROR: cannot identify CSV type for: {f}\n"
                  f"       Expected headers: year,single,semi,row,apt,total\n"
                  f"       or:               year,population",
                  file=sys.stderr)
            sys.exit(1)

    if not comp_path:
        parser.error("No completions CSV detected. Need at least a completions file.")

    name = args.n or ""
    slug = resolve_slug(name, comp_path)

    # Where to write files (None = don't write, just return HTML)
    out_dir = None if args.stdout else args.o
    if out_dir:
        os.makedirs(out_dir, exist_ok=True)

    print(f"Slug: {slug}", file=sys.stderr)
    if name:
        print(f"Name: {name}", file=sys.stderr)

    years, single, semi, row_, apt, total = load_completions(comp_path)
    print(f"Completions: {len(years)} years ({years[0]}-{years[-1]})", file=sys.stderr)

    types_html = build_types_chart(name, slug, years, single, semi, row_, apt, total, out_dir)

    ratio_html = None
    if pop_path:
        pop = load_population(pop_path, years)
        print(f"Population:  {len(pop)} years matched", file=sys.stderr)
        ratio_html = build_pop_chart(name, slug, years, total, pop, out_dir)
    else:
        print("No population file; skipping ratio chart.", file=sys.stderr)

    if args.stdout:
        result = {"types_html": types_html}
        if ratio_html is not None:
            result["ratio_html"] = ratio_html
        sys.stdout.write(json.dumps(result))
    else:
        print("Done.", file=sys.stderr)


if __name__ == "__main__":
    main()
