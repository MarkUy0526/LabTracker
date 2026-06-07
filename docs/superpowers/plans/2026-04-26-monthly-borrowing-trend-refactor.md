# Monthly Borrowing Trend Chart Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the per-equipment monthly line chart with a single line chart showing total daily borrow requests for a user-selected date range and status filter.

**Architecture:** A new dedicated PHP endpoint (`fetch_daily_borrow_trend.php`) handles the query and zero-fills missing days. The chart card in `admin.php` gets inline filter controls (From / To date inputs + Status dropdown + Filter button). `admin.js` wires everything together — setting defaults on init and re-rendering the chart on each filter action.

**Tech Stack:** PHP (MySQLi), Chart.js (already loaded), vanilla JS, CSS variables already defined in `admin.php`

---

## File Map

| Action | File | Responsibility |
|---|---|---|
| Create | `fetch_daily_borrow_trend.php` | Accepts `from`, `to`, `status`; returns daily borrow request counts |
| Modify | `admin.php` lines 632–635 | Replace static chart card with filterable card (title + filter row + canvas) |
| Modify | `admin.js` lines 1174, 1248–1265 | Add `trendChart` var, replace old trend block, add `loadTrendChart()` |

---

## Task 1: Create `fetch_daily_borrow_trend.php`

**Files:**
- Create: `fetch_daily_borrow_trend.php`

- [ ] **Step 1: Create the file with input validation**

Create `fetch_daily_borrow_trend.php` at the project root (same level as `fetch_stats.php`):

```php
<?php
require 'db.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$from   = $_GET['from']   ?? '';
$to     = $_GET['to']     ?? '';
$status = $_GET['status'] ?? 'All';

if (!$from || !$to) {
    echo json_encode(['success' => false, 'message' => 'from and to are required']);
    exit;
}

$allowed = ['All', 'Accepted', 'Pending', 'Rejected'];
if (!in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}
```

- [ ] **Step 2: Add the SQL query**

Append after the validation block:

```php
if ($status === 'All') {
    $sql = "SELECT DATE(date) AS day, COUNT(*) AS count
            FROM borrow_requests
            WHERE DATE(date) BETWEEN ? AND ?
            GROUP BY day
            ORDER BY day";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $from, $to);
} else {
    $sql = "SELECT DATE(date) AS day, COUNT(*) AS count
            FROM borrow_requests
            WHERE DATE(date) BETWEEN ? AND ?
              AND status = ?
            GROUP BY day
            ORDER BY day";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $from, $to, $status);
}

$stmt->execute();
$result = $stmt->get_result();

$dbData = [];
while ($row = $result->fetch_assoc()) {
    $dbData[$row['day']] = (int) $row['count'];
}
```

- [ ] **Step 3: Zero-fill every day in the range and build labels**

Append after the query block:

```php
$labels = [];
$counts = [];

$current = new DateTime($from);
$end     = new DateTime($to);

while ($current <= $end) {
    $dateKey  = $current->format('Y-m-d');
    $labels[] = $current->format('M j');   // e.g. "Apr 1"
    $counts[] = $dbData[$dateKey] ?? 0;
    $current->modify('+1 day');
}

echo json_encode([
    'success' => true,
    'labels'  => $labels,
    'counts'  => $counts,
]);
?>
```

- [ ] **Step 4: Verify the endpoint manually in the browser**

Visit (adjust month to current month):
```
http://localhost/LabTracker/fetch_daily_borrow_trend.php?from=2025-04-01&to=2025-04-30&status=All
```

Expected response shape:
```json
{
  "success": true,
  "labels": ["Apr 1", "Apr 2", ..., "Apr 30"],
  "counts": [0, 3, 1, 0, ...]
}
```
- `labels` must have exactly 30 entries for April
- `counts` must have the same length as `labels`
- Days with no data must appear as `0`, not missing

Also test with status filter:
```
http://localhost/LabTracker/fetch_daily_borrow_trend.php?from=2025-04-01&to=2025-04-30&status=Accepted
```

- [ ] **Step 5: Commit**

```bash
git add fetch_daily_borrow_trend.php
git commit -m "feat: add fetch_daily_borrow_trend endpoint"
```

---

## Task 2: Update the chart card in `admin.php`

**Files:**
- Modify: `admin.php` lines 632–635

- [ ] **Step 1: Replace the static card with the filterable card**

In `admin.php`, find and replace lines 632–635 (the chart card block):

**Find (exact):**
```html
        <div class="card">
          <p style="font-size:13px;font-weight:600;margin-bottom:14px;">Monthly Borrowing Trend</p>
          <div style="height:280px;"><canvas id="equipmentTrendChart" height="280"></canvas></div>
        </div>
```

**Replace with:**
```html
        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
            <p style="font-size:13px;font-weight:600;margin:0;">Monthly Borrowing Trend</p>
            <div style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;">
              <div>
                <label style="display:block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:3px;">From</label>
                <input type="date" id="trendFrom" style="font-family:var(--font);font-size:12px;padding:6px 10px;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text-1);outline:none;">
              </div>
              <div style="padding-bottom:7px;color:var(--text-3);">→</div>
              <div>
                <label style="display:block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:3px;">To</label>
                <input type="date" id="trendTo" style="font-family:var(--font);font-size:12px;padding:6px 10px;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text-1);outline:none;">
              </div>
              <div>
                <label style="display:block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:3px;">Status</label>
                <select id="trendStatus" style="font-family:var(--font);font-size:12px;padding:6px 10px;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text-1);outline:none;">
                  <option value="All">All</option>
                  <option value="Accepted">Accepted</option>
                  <option value="Pending">Pending</option>
                  <option value="Rejected">Rejected</option>
                </select>
              </div>
              <button id="trendFilterBtn" style="font-family:var(--font);font-size:12px;padding:6px 14px;border:1px solid var(--accent);border-radius:4px;background:var(--accent);color:#fff;cursor:pointer;">Filter</button>
            </div>
          </div>
          <div style="height:280px;"><canvas id="equipmentTrendChart" height="280"></canvas></div>
        </div>
```

- [ ] **Step 2: Verify the HTML renders correctly**

Open `http://localhost/LabTracker/admin.php`, navigate to the Schedule section. Confirm:
- The card shows "Monthly Borrowing Trend" on the left
- From / To date inputs + Status dropdown + Filter button appear on the right
- The controls wrap correctly at narrow window widths
- No JS errors in browser console yet (chart won't work until Task 3)

- [ ] **Step 3: Commit**

```bash
git add admin.php
git commit -m "feat: add date range and status filter controls to trend chart card"
```

---

## Task 3: Update `admin.js` — wire up `loadTrendChart()`

**Files:**
- Modify: `admin.js` line 1174 (add `trendChart` variable)
- Modify: `admin.js` lines 1248–1263 (replace old trend block)
- Modify: `admin.js` line 1265 (insert new `loadTrendChart` function after `initScheduleCharts`)

- [ ] **Step 1: Add the `trendChart` module-level variable**

In `admin.js`, find line 1174:
```js
let chartsInitialized = false;
```

Replace with:
```js
let chartsInitialized = false;
let trendChart = null;
```

- [ ] **Step 2: Replace the old equipment trend block with defaults + filter wiring**

Find lines 1248–1263 (the entire `if (data.equipmentTrend ...)` block):

```js
    if (data.equipmentTrend && Object.keys(data.equipmentTrend).length) {
      const ctx = document.getElementById('equipmentTrendChart')?.getContext('2d');
      if (!ctx) return;
      const months   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const datasets = Object.entries(data.equipmentTrend).map(([item, vals], i) => ({
        label: item, data: months.map(mo => vals[mo] || 0),
        borderColor: `hsl(${i*45},70%,50%)`, backgroundColor:'transparent', tension:0.3
      }));
      new Chart(ctx, {
        type: 'line', data: { labels:months, datasets },
        options: { responsive:true, maintainAspectRatio:false,
          plugins: { title:{ display:true, text:'Monthly Borrowing Frequency' }, legend:{ position:'top' } },
          scales:  { y:{ beginAtZero:true, ticks:{ stepSize:1, precision:0 }, title:{ display:true, text:'Times Borrowed' } } }
        }
      });
    }
```

Replace with:

```js
    const now       = new Date();
    const firstDay  = new Date(now.getFullYear(), now.getMonth(), 1);
    const pad       = n => String(n).padStart(2, '0');
    const fmtDate   = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

    const fromEl    = document.getElementById('trendFrom');
    const toEl      = document.getElementById('trendTo');
    const filterBtn = document.getElementById('trendFilterBtn');

    if (fromEl) fromEl.value = fmtDate(firstDay);
    if (toEl)   toEl.value   = fmtDate(now);

    if (filterBtn) filterBtn.addEventListener('click', loadTrendChart);
    loadTrendChart();
```

- [ ] **Step 3: Add the `loadTrendChart` function**

Immediately after the closing `}` of `initScheduleCharts()` (after line 1265), insert:

```js
function loadTrendChart() {
  const from   = document.getElementById('trendFrom')?.value;
  const to     = document.getElementById('trendTo')?.value;
  const status = document.getElementById('trendStatus')?.value ?? 'All';

  if (!from || !to) return;

  fetch(`fetch_daily_borrow_trend.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&status=${encodeURIComponent(status)}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;

      if (trendChart) { trendChart.destroy(); trendChart = null; }

      const ctx = document.getElementById('equipmentTrendChart')?.getContext('2d');
      if (!ctx) return;

      if (!data.labels.length) {
        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
        ctx.save();
        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-3') || '#64748b';
        ctx.font = '13px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('No data for selected range', ctx.canvas.width / 2, ctx.canvas.height / 2);
        ctx.restore();
        return;
      }

      trendChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [{
            label: 'Borrow Requests',
            data: data.counts,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.08)',
            tension: 0.3,
            pointRadius: 3,
            fill: true,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
          },
          scales: {
            x: { ticks: { maxTicksLimit: 15, maxRotation: 45, font: { size: 11 } } },
            y: {
              beginAtZero: true,
              ticks: { stepSize: 1, precision: 0 },
              title: { display: true, text: 'No. of Requests' }
            }
          }
        }
      });
    })
    .catch(err => console.error('loadTrendChart error:', err));
}
```

- [ ] **Step 4: Verify the chart works end-to-end**

Open `http://localhost/LabTracker/admin.php`, go to the Schedule section:

1. Chart loads automatically showing the current month with Status = All
2. Verify line chart appears with "No. of Requests" on Y-axis and date labels on X-axis
3. Change the From date to 3 days ago and click Filter — chart re-renders for the narrower range
4. Set Status to "Accepted" and click Filter — chart updates
5. Set a date range with no data (e.g. far future) and click Filter — canvas shows "No data for selected range" text
6. Check browser console — no JS errors

- [ ] **Step 5: Commit**

```bash
git add admin.js
git commit -m "feat: wire up daily borrow trend chart with date range and status filter"
```
