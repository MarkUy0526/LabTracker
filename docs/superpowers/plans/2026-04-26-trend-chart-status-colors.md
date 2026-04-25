# Trend Chart Status Colors Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Monthly Borrowing Trend chart line and fill colors reflect the currently selected status filter.

**Architecture:** Single change inside `loadTrendChart()` in `admin.js` — add a status-to-color lookup map that reads live CSS variable values, then use the resolved color pair in the Chart.js dataset instead of the current hardcoded indigo.

**Tech Stack:** Vanilla JS, Chart.js (already loaded), CSS custom properties defined in `dashboard.css`

---

## File Map

| Action | File | What changes |
|---|---|---|
| Modify | `admin.js` lines 1315–1325 | Add color map after `if (!ctx) return;`, replace 2 hardcoded color strings |

---

## Task 1: Apply status-based colors to the trend chart

**Files:**
- Modify: `admin.js` lines 1315–1325

- [ ] **Step 1: Add the color map immediately after line 1315 (`if (!ctx) return;`)**

Find this exact block in `admin.js` (lines 1314–1317):

```js
      const ctx = document.getElementById('equipmentTrendChart')?.getContext('2d');
      if (!ctx) return;

      trendChart = new Chart(ctx, {
```

Replace with:

```js
      const ctx = document.getElementById('equipmentTrendChart')?.getContext('2d');
      if (!ctx) return;

      const cssVar = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
      const statusColors = {
        All:      { border: '#6366f1',            bg: 'rgba(99,102,241,0.08)' },
        Accepted: { border: cssVar('--accent'),   bg: cssVar('--accent-soft') },
        Pending:  { border: cssVar('--warn'),     bg: cssVar('--warn-soft')   },
        Rejected: { border: cssVar('--danger'),   bg: cssVar('--danger-soft') },
      };
      const color = statusColors[status] ?? statusColors.All;

      trendChart = new Chart(ctx, {
```

- [ ] **Step 2: Replace the two hardcoded color strings in the dataset**

Find these two lines inside the dataset (lines 1324–1325):

```js
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.08)',
```

Replace with:

```js
            borderColor: color.border,
            backgroundColor: color.bg,
```

- [ ] **Step 3: Verify the change looks correct**

Read the modified `loadTrendChart()` from `admin.js` (lines 1314–1330) and confirm:
- `cssVar` helper and `statusColors` map are present between `if (!ctx) return;` and `trendChart = new Chart(...)`
- `color` is assigned via `statusColors[status] ?? statusColors.All`
- `borderColor: color.border` and `backgroundColor: color.bg` are in the dataset
- No other lines were changed

- [ ] **Step 4: Copy the updated file to XAMPP and test in browser**

```bash
cp "C:/Users/User/Desktop/dev/LabTracker/admin.js" "C:/xampp/htdocs/LabTracker/admin.js"
```

Open `http://localhost/LabTracker/login.php`, log in, go to the Schedule section and verify:

| Action | Expected |
|---|---|
| Load with Status = All | Indigo (`#6366f1`) line |
| Change Status to Accepted + Filter | Green line (`#2D6A4F`) |
| Change Status to Pending + Filter | Orange line (`#E67E22`) |
| Change Status to Rejected + Filter | Red line (`#C0392B`) |
| Switch back to All + Filter | Indigo line returns |

- [ ] **Step 5: Commit**

```bash
git add admin.js
git commit -m "feat: apply status-based colors to monthly borrowing trend chart"
```
