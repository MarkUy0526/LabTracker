# Monthly Borrowing Trend Chart Refactor

**Date:** 2026-04-26
**Status:** Approved

## Goal

Refactor the existing "Monthly Borrowing Trend" chart so it shows how many borrow requests were submitted each day within a user-selected date range, replacing the current per-equipment monthly view.

## What Changes

### Current behavior
- X-axis: Jan–Dec (months of the year)
- Lines: one per equipment name
- Data: how many times each equipment was borrowed per month
- No user controls — always shows the full current year

### New behavior
- X-axis: each day in the selected date range (e.g. "Apr 1", "Apr 2" … "Apr 26")
- Single line: total number of borrow requests on each day
- Filters: From date / To date / Status dropdown
- Default on load: From = 1st of current month, To = today, Status = All

## Decisions

| Question | Decision |
|---|---|
| What does one data point represent? | One borrow request = one person |
| One line or one per equipment? | Single line (total requests per day) |
| Date control | From / To date inputs (native browser date picker) |
| Status filter | Single dropdown: All / Accepted / Pending / Rejected |
| Chart type | Line chart |
| UI placement | Filters inline with card title (title left, controls right) |
| Input styling | Match existing system style — `var(--border)`, `var(--bg)`, `var(--text-1)` |

## Architecture

### New file: `fetch_daily_borrow_trend.php`

Accepts query params:
- `from` — start date `YYYY-MM-DD`
- `to` — end date `YYYY-MM-DD`
- `status` — one of `All`, `Accepted`, `Pending`, `Rejected`

Returns JSON:
```json
{
  "success": true,
  "labels": ["Apr 1", "Apr 2", "Apr 3"],
  "counts": [3, 0, 5]
}
```

Zero-fills days that have no requests so the line is continuous across the range.

SQL query:
```sql
SELECT DATE(date) AS day, COUNT(*) AS count
FROM borrow_requests
WHERE DATE(date) BETWEEN ? AND ?
  AND (? = 'All' OR status = ?)
GROUP BY day
ORDER BY day
```

### Modified: `admin.php`

Chart card (`#equipmentTrendChart` section) updated:

- Card header becomes a flex row:
  - Left: `<p>Monthly Borrowing Trend</p>`
  - Right: From input + arrow + To input + Status `<select>` + Filter `<button>`
- Canvas element stays, `id="equipmentTrendChart"` unchanged (avoids touching JS references)
- Input/select/label styling matches `.hist-filter-row` pattern already in the file
- New element IDs: `#trendFrom`, `#trendTo`, `#trendStatus`, `#trendFilterBtn`

### Modified: `admin.js`

In `initScheduleCharts()`:

1. Remove the old `data.equipmentTrend` block entirely
2. Add a `let trendChart = null` variable at the top of the function scope
3. On init: set `#trendFrom` to first day of current month, `#trendTo` to today
4. Call `loadTrendChart()` once on init
5. Filter button click calls `loadTrendChart()`

New `loadTrendChart()` function:
```
function loadTrendChart() {
  - Read from/to/status from the three inputs
  - fetch(`fetch_daily_borrow_trend.php?from=…&to=…&status=…`)
  - If trendChart exists, call trendChart.destroy()
  - If no data points: show "No data for selected range" in the canvas area
  - Otherwise: create new Chart.js line chart
      type: 'line'
      labels: response.labels
      datasets: [{ data: response.counts, borderColor: accent color, tension: 0.3 }]
      scales.y: beginAtZero, stepSize 1, title "No. of Requests"
}
```

## What Is Not Changing

- `fetch_stats.php` — untouched; still serves weekly/monthly doughnut charts
- `weeklyChart` and `monthlyChart` canvases — untouched
- All other sections of `admin.php` and `admin.js`

## Empty State

When the fetch returns zero data points for the range, render a centered text message inside the chart canvas area: `"No data for selected range"` instead of an empty chart.
