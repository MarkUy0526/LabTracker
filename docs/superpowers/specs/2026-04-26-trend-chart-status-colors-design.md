# Trend Chart Status Colors Design

**Date:** 2026-04-26
**Status:** Approved

## Goal

Change the Monthly Borrowing Trend chart line color to reflect the currently selected status filter, so the chart is visually consistent with the status color system already used throughout the admin panel.

## Current Behavior

The chart always uses a hardcoded indigo (`#6366f1`) for both `borderColor` and `backgroundColor` regardless of which status is selected in the dropdown.

## New Behavior

The line and fill color update to match the selected status:

| Status dropdown | Line color | Fill color |
|---|---|---|
| All | `#6366f1` (indigo — unchanged) | `rgba(99,102,241,0.08)` |
| Accepted | `var(--accent)` `#2D6A4F` (green) | `var(--accent-soft)` |
| Pending | `var(--warn)` `#E67E22` (orange) | `var(--warn-soft)` |
| Rejected | `var(--danger)` `#C0392B` (red) | `var(--danger-soft)` |

Colors are read from live CSS variables using `getComputedStyle` so they stay in sync with the theme automatically.

## Architecture

### Modified: `admin.js` — `loadTrendChart()` only

Inside `loadTrendChart()`, immediately before the `new Chart(...)` call, add:

```js
const cssVar = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const statusColors = {
  All:      { border: '#6366f1',            bg: 'rgba(99,102,241,0.08)' },
  Accepted: { border: cssVar('--accent'),   bg: cssVar('--accent-soft') },
  Pending:  { border: cssVar('--warn'),     bg: cssVar('--warn-soft')   },
  Rejected: { border: cssVar('--danger'),   bg: cssVar('--danger-soft') },
};
const color = statusColors[status] ?? statusColors.All;
```

Then replace the two hardcoded color strings in the Chart.js dataset:

```js
// Before
borderColor:     '#6366f1',
backgroundColor: 'rgba(99,102,241,0.08)',

// After
borderColor:     color.border,
backgroundColor: color.bg,
```

## What Does Not Change

- No PHP files touched
- No HTML changes in `admin.php`
- Chart type, axes, legend, tooltip, filter behavior — all unchanged
- The `showTrendMessage` helper and `AbortController` logic — unchanged
