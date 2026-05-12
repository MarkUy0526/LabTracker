<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EQUILAB — Admin</title>
  <link rel="stylesheet" href="dashboard.css" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* ═══ INVENTORY TAB SWITCHER ═══ */
    .inv-tab-row {
      display: flex;
      gap: 0;
      margin-bottom: 20px;
      border-bottom: 2px solid var(--border);
    }
    .inv-tab-btn {
      padding: 9px 22px;
      background: transparent;
      border: none;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      font-family: var(--font);
      font-size: 13px;
      font-weight: 500;
      color: var(--text-3);
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 7px;
      transition: color .18s, border-color .18s;
    }
    .inv-tab-btn:hover { color: var(--text-1); }
    .inv-tab-btn.active {
      color: var(--accent);
      border-bottom-color: var(--accent);
      font-weight: 600;
    }
    .inv-tab-btn .tab-count {
      background: var(--accent-soft);
      color: var(--accent);
      font-size: 10px;
      font-weight: 700;
      padding: 1px 7px;
      border-radius: 20px;
    }
    .inv-tab-panel { display: none; }
    .inv-tab-panel.active { display: block; }

    /* ═══ HISTORY TABLE (full-page, inside the History tab) ═══ */
    .hist-page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 16px;
    }
    .hist-page-title {
      font-size: 15px;
      font-weight: 600;
      color: var(--text-1);
    }
    .hist-page-sub {
      font-size: 12px;
      color: var(--text-3);
      margin-top: 2px;
    }
    .hist-filter-row {
      display: flex;
      align-items: flex-end;
      gap: 10px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }
    .hist-filter-row label {
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--text-3);
      display: block;
      margin-bottom: 4px;
    }
    .hist-filter-row input[type="date"] {
      font-family: var(--font);
      font-size: 12px;
      padding: 7px 10px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: var(--bg);
      color: var(--text-1);
      outline: none;
    }
    .hist-filter-row input[type="date"]:focus { border-color: var(--accent); }

    .hist-table-wrap {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow);
    }
    .hist-full-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    .hist-full-table thead th {
      background: var(--surface-2);
      padding: 10px 14px;
      text-align: left;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--text-3);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    .hist-full-table tbody td {
      padding: 11px 14px;
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
      color: var(--text-1);
    }
    .hist-full-table tbody tr:last-child td { border-bottom: none; }
    .hist-full-table tbody tr:hover { background: var(--bg); }

    /* Date-time cell */
    .hist-ts { display: flex; flex-direction: column; gap: 1px; }
    .hist-ts-date {
      font-weight: 600;
      font-size: 12.5px;
      color: var(--text-1);
    }
    .hist-ts-time {
      font-size: 11.5px;
      color: var(--accent);
      font-family: var(--mono);
      font-weight: 600;
    }
    .hist-ts-tz {
      font-size: 10px;
      color: var(--text-3);
      letter-spacing: .04em;
    }

    /* Action badge */
    .hist-badge {
      display: inline-block;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: .07em;
      text-transform: uppercase;
      padding: 3px 9px;
      border-radius: 20px;
    }
    .hist-badge.added  { background: var(--accent-soft); color: var(--accent); }
    .hist-badge.edited { background: var(--warn-soft);   color: var(--warn);   }

    /* Qty display */
    .hist-qty { font-family: var(--mono); font-size: 12px; }
    .hist-qty .q-total  { color: var(--text-1); font-weight: 600; }
    .hist-qty .q-sep    { color: var(--text-3); margin: 0 3px; }
    .hist-qty .q-work   { color: var(--accent); }
    .hist-qty .q-nowork { color: var(--danger); }

    .hist-empty-row td {
      text-align: center;
      padding: 36px;
      color: var(--text-3);
      font-style: italic;
      font-size: 13px;
    }
    .hist-loading-row td {
      text-align: center;
      padding: 24px;
      color: var(--text-3);
      font-size: 13px;
    }

    /* ═══ PER-ROW HISTORY POPOVER (existing) ═══ */
    .hist-cell { width: 40px; text-align: center; }
    .hist-btn {
      background: transparent;
      border: 1px solid var(--border);
      border-radius: 7px;
      padding: 4px 7px;
      cursor: pointer;
      color: var(--text-3);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: background .15s, color .15s, border-color .15s;
    }
    .hist-btn:hover, .hist-btn.hist-btn-active {
      background: var(--accent-soft);
      color: var(--accent);
      border-color: #a8d5b5;
    }
    .hist-popover {
      position: absolute;
      z-index: 600;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: 0 8px 32px rgba(0,0,0,.14);
      min-width: 300px;
      max-width: 360px;
      animation: popIn .15s cubic-bezier(.4,0,.2,1) both;
    }
    @keyframes popIn {
      from { opacity:0; transform:translateY(-6px) scale(.97); }
      to   { opacity:1; transform:none; }
    }
    .hist-pop-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      padding: 12px 14px 10px;
      border-bottom: 1px solid var(--border);
    }
    .hist-pop-title { font-size: 13px; font-weight: 600; color: var(--text-1); }
    .hist-pop-sub   { font-size: 11px; color: var(--text-3); margin-top: 2px; }
    .hist-pop-close {
      background: none; border: none; cursor: pointer;
      color: var(--text-3); padding: 2px;
      display: flex; align-items: center;
    }
    .hist-pop-close:hover { color: var(--text-1); }
    .hist-pop-body {
      padding: 10px 14px 14px;
      max-height: 320px;
      overflow-y: auto;
    }
    .hist-loading, .hist-empty {
      text-align: center;
      padding: 20px 0;
      color: var(--text-3);
      font-size: 12px;
      font-style: italic;
    }
    .hist-date-group { margin-bottom: 14px; }
    .hist-date-label {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--text-3);
      margin-bottom: 6px;
    }
    .hist-entry {
      border-radius: 8px;
      padding: 8px 10px;
      margin-bottom: 6px;
      border-left: 3px solid transparent;
    }
    .hist-added  { background: var(--accent-soft); border-left-color: var(--accent); }
    .hist-edited { background: var(--warn-soft);   border-left-color: var(--warn); }
    .hist-entry-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 5px;
    }
    .hist-time { font-size: 11px; color: var(--text-3); font-family: var(--mono); }
    .hist-entry-detail { font-size: 11.5px; color: var(--text-2); }
    .hist-field  { font-weight: 600; color: var(--text-1); }
    .hist-arrow  { margin: 0 4px; color: var(--text-3); }
    .hist-old    { text-decoration: line-through; color: var(--danger); }
    .hist-new    { font-weight: 600; color: var(--accent); }
    .hist-snapshot {
      width: 100%;
      border-collapse: collapse;
      margin-top: 6px;
      font-size: 11px;
    }
    .snap-label {
      color: var(--text-3);
      padding: 2px 6px 2px 0;
      font-weight: 500;
      white-space: nowrap;
    }
    .snap-val { color: var(--text-1); padding: 2px 0; }

    .condition-qty-input {
      width: 64px;
      text-align: center;
      padding: 5px 6px;
      border: 1px solid var(--border);
      border-radius: 7px;
      background: var(--surface);
      color: var(--text-1);
      font-family: var(--font);
      font-size: 12px;
      outline: none;
      transition: border-color .15s, background .15s, box-shadow .15s;
    }
    .condition-qty-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 2px var(--accent-soft);
    }
    .condition-qty-input.condition-w { color: var(--accent); }
    .condition-qty-input.condition-nw { color: var(--danger); }
    .condition-qty-input.condition-m { color: var(--warn); }
    .condition-qty-input:disabled {
      background: var(--surface-2);
      color: var(--text-3);
      cursor: not-allowed;
    }

    .inventory-meta-panel {
      display: flex;
      align-items: stretch;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }
    .inventory-meta-group,
    .equipment-id-legend {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 9px 12px;
    }
    .inventory-meta-item {
      display: flex;
      align-items: baseline;
      gap: 7px;
      padding-right: 10px;
      border-right: 1px solid var(--border);
    }
    .inventory-meta-item:last-child {
      border-right: none;
      padding-right: 0;
    }
    .inventory-meta-label,
    .equipment-id-legend .legend-label {
      color: var(--text-3);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .05em;
      text-transform: uppercase;
    }
    .inventory-meta-item strong,
    .equipment-id-legend span:not(.legend-label) {
      color: var(--text-1);
      font-size: 12px;
    }
    .equipment-id-legend strong {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 20px;
      height: 20px;
      margin-right: 4px;
      border-radius: 6px;
      background: var(--surface-2);
      color: var(--accent);
      font-family: var(--mono);
      font-size: 11px;
    }

    @keyframes slideUpBar {
      from { opacity:0; transform:translateY(12px); }
      to   { opacity:1; transform:none; }
    }
  </style>
</head>

<body>

  <!-- ── SIDEBAR ── -->
  <div class="sidebar">
    <div class="logo">
      <span class="logo-icon">⬡</span>
      <h2>EQUILAB</h2>
    </div>
    <nav>
      <a href="#" class="nav-item active" data-section="Dashboard" data-tooltip="Dashboard">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span class="nav-label">Dashboard</span>
      </a>
      <a href="#" class="nav-item" data-section="Schedule" data-tooltip="Schedule">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        <span class="nav-label">Schedule</span>
      </a>
      <a href="#" class="nav-item" data-section="Borrow Requests" data-tooltip="Borrow Requests">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
        <span class="nav-label">Borrow Requests</span>
        <span id="borrowQueueCount" class="queue-count">0</span>
      </a>
      <a href="#" class="nav-item" data-section="Inventory" id="inventoryNav" data-tooltip="Inventory">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        <span class="nav-label">Inventory</span>
      </a>
      <a href="#" class="nav-item" data-section="Reports" data-tooltip="Reports">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        <span class="nav-label">Reports</span>
      </a>
    </nav>
    <div class="logout">
      <a href="#" onclick="logout()" class="nav-item" data-tooltip="Log Out">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/></svg>
        <span class="nav-label">Log Out</span>
      </a>
    </div>
  </div>

  <!-- ── MAIN ── -->
  <div class="main-content">

    <!-- ═══════════════════ DASHBOARD ═══════════════════ -->
    <div id="manageSection">
      <div class="page-header">
        <h1>Dashboard</h1>
        <p id="currentDate" style="color:var(--text-3);font-size:13px;"></p>
      </div>

      <div class="stat-grid">
        <div class="stat-card"><div class="label">Total Today</div><div class="value" id="totalRequests">0</div></div>
        <div class="stat-card green"><div class="label">Accepted</div><div class="value" id="acceptedRequests">0</div></div>
        <div class="stat-card red"><div class="label">Rejected</div><div class="value" id="rejectedRequests">0</div></div>
        <div class="stat-card orange"><div class="label">Pending</div><div class="value" id="pendingRequests">0</div></div>
      </div>

      <div class="card" style="margin-bottom:24px;max-width:440px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
          <span style="font-weight:600;font-size:14px;">Admin Credentials</span>
          <button id="showChangeCredBtn" class="primary-btn">Change</button>
        </div>
        <p style="font-size:12px;color:var(--text-3);">Update your username and password.</p>
      </div>

      <div id="currentPassSection" class="form-section hidden" style="margin-bottom:16px;">
        <label style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-2);display:block;margin-bottom:6px;">Current Password</label>
        <input type="password" id="currentPassword" placeholder="Enter current password" />
        <div style="display:flex;gap:8px;margin-top:8px;">
          <button id="verifyCurrentPassBtn" class="secondary-btn">Verify</button>
          <button type="button" id="backFromVerifyBtn" class="back-btn">Back</button>
        </div>
        <p id="verifyMessage" class="message-text"></p>
      </div>

      <div id="change-credentials" class="form-section hidden" style="margin-bottom:24px;">
        <h3 style="font-size:14px;font-weight:600;margin-bottom:12px;">New Credentials</h3>
        <form id="change-form">
          <label style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-2);display:block;margin-bottom:4px;">New Username</label>
          <input type="text" id="newUsername" placeholder="Username" required style="margin-bottom:10px;"/>
          <label style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-2);display:block;margin-bottom:4px;">New Password</label>
          <input type="password" id="newPassword" placeholder="Password" required style="margin-bottom:10px;"/>
          <div style="display:flex;gap:8px;">
            <button type="submit" id="submitChangeCredentialsBtn" class="primary-btn">Update</button>
            <button type="button" id="backFromChangeBtn" class="back-btn">Back</button>
          </div>
          <p id="change-message" class="message-text"></p>
        </form>
      </div>

      <!-- Slider -->
      <div class="slider-container">
        <div class="slider-wrapper">
          <div class="slide" data-target="Schedule">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
              <span style="width:8px;height:8px;border-radius:50%;background:var(--accent);display:inline-block;"></span>
              <h3 style="margin:0;">Schedule</h3>
            </div>
            <p>Today's borrow request statistics at a glance.</p>
            <div id="dailyStats" style="margin-top:14px;">
              <p>Total: <strong id="totalReq2">0</strong> &nbsp;·&nbsp; Accepted: <strong style="color:var(--accent)" id="accReq2">0</strong> &nbsp;·&nbsp; Rejected: <strong style="color:var(--danger)" id="rejReq2">0</strong> &nbsp;·&nbsp; Pending: <strong style="color:var(--warn)" id="penReq2">0</strong></p>
              <p class="click-instruction">Click to open the Schedule section →</p>
            </div>
          </div>
          <div class="slide" data-target="Borrow Requests">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
              <span style="width:8px;height:8px;border-radius:50%;background:#E67E22;display:inline-block;"></span>
              <h3 style="margin:0;">Borrow Requests</h3>
            </div>
            <p>Total: <strong><span id="totalRequestCount">0</span></strong></p>
            <div id="borrowQueueDashboard" style="margin-top:12px;"></div>
          </div>
          <div class="slide" data-target="Inventory">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
              <span style="width:8px;height:8px;border-radius:50%;background:#1A6FB5;display:inline-block;"></span>
              <h3 style="margin:0;">Inventory</h3>
            </div>
            <p>Total items: <strong><span id="inventoryCount">0</span></strong></p>
            <div class="inventory-preview-container" style="margin-top:12px;">
              <table class="preview-table">
                <thead>
                  <tr><th>ID</th><th>Equipment</th><th>SN</th><th>ISN</th><th>Acc Person</th><th title="Total">T</th><th title="Working">W</th><th title="Not Working">NW</th><th title="Maintenance">M</th><th>Desc</th></tr>
                </thead>
                <tbody id="inventoryPreviewBody"></tbody>
              </table>
            </div>
            <p class="preview-note">Preview only — click to view full inventory.</p>
          </div>
          <div class="slide" data-target="Reports">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
              <span style="width:8px;height:8px;border-radius:50%;background:var(--danger);display:inline-block;"></span>
              <h3 style="margin:0;">Reports</h3>
            </div>
            <p>Total entries: <strong><span id="reportCount">0</span></strong></p>
            <ul id="recentReportsList" class="recent-reports"><li style="color:var(--text-3);">Loading…</li></ul>
          </div>
        </div>
        <div class="dots-container"></div>
      </div>
    </div><!-- /manageSection -->

    <!-- ═══════════════════ INVENTORY ═══════════════════ -->
    <div id="inventorySection" style="display:none;">
      <div class="page-header"><h1>Inventory</h1></div>

      <!-- ── TAB SWITCHER ── -->
      <div class="inv-tab-row">
        <button class="inv-tab-btn active" id="invTabListBtn" onclick="switchInvTab('list')">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <path d="M9 9h6M9 12h6M9 15h4"/>
          </svg>
          Inventory List
        </button>
        <button class="inv-tab-btn" id="invTabHistBtn" onclick="switchInvTab('history')">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
          </svg>
          History
          <span class="tab-count" id="histTabCount">—</span>
        </button>
        <button class="inv-tab-btn" id="invTabAuditBtn" onclick="switchInvTab('audit')">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M9 12l2 2 4-4M7 20h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v13a2 2 0 002 2z"/>
          </svg>
          Inventory Audit
        </button>
      </div>

      <!-- ── TAB: INVENTORY LIST ── -->
      <div class="inv-tab-panel active" id="invPanelList">

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
          <select id="categorySelect">
            <optgroup label="Category">
              <option value="all">All Categories</option>
              <option value="equipment">Equipment</option>
              <option value="measuring">Measuring Tools</option>
              <option value="chemicals">Chemicals</option>
              <option value="books">Books</option>
            </optgroup>
            <optgroup label="Condition">
              <option value="totalQty">Total</option>
              <option value="working">Working</option>
              <option value="notWorking">Non-working</option>
              <option value="maintenance">Maintenance</option>
            </optgroup>
            <optgroup label="Borrowing Visibility">
              <option value="borrowable">Available for Borrowing</option>
              <option value="restricted">Restricted / Hidden</option>
            </optgroup>
          </select>
          <div class="search-bar">
            <input type="text" placeholder="Search equipment…" id="searchInput">
          </div>
        </div>

        <div class="btn-group">
          <div class="left-buttons">
            <button id="addEquipmentBtn">+ Add</button>
            <button id="editEquipmentBtn">Edit</button>
            <button id="deleteEquipmentBtn" class="danger">Delete</button>
          </div>
          <div class="right-buttons" style="margin-left:auto;display:flex;gap:6px;align-items:center;">
            <!-- Export split button -->
            <div style="position:relative;display:inline-flex;">
              <button id="downloadExcelBtn" style="border-radius:var(--radius) 0 0 var(--radius);border-right:none;">↓ Export XLSX</button>
              <button id="downloadCsvBtn"   style="border-radius:0 var(--radius) var(--radius) 0;font-size:12px;padding:6px 10px;background:var(--surface-2);color:var(--text-2);border:1px solid var(--border);">CSV</button>
            </div>
            <!-- Import — accepts csv and xlsx -->
            <input type="file" id="uploadExcelInput" accept=".xlsx,.xls,.csv" style="display:none;" />
            <button id="uploadExcelBtn">↑ Import</button>
          </div>
        </div>

        <div id="editHintBanner" style="
          display:flex;align-items:center;gap:8px;
          background:var(--accent-soft);border:1px solid #a8d5b5;
          border-radius:var(--radius);padding:10px 16px;
          margin-bottom:14px;font-size:13px;color:var(--accent);">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
            <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
          </svg>
          <span>Click any row to select it, then click <strong>Edit</strong> to edit equipment details. Admins can update <strong>T / W / NW / M</strong> quantities directly in the Condition columns. The <strong>🕐</strong> button on each row shows that equipment's change history.</span>
        </div>

        <div class="inventory-meta-panel">
          <div class="inventory-meta-group">
            <div class="inventory-meta-item">
              <span class="inventory-meta-label">Last Imported</span>
              <strong id="inventoryLastImported">Never</strong>
            </div>
            <div class="inventory-meta-item">
              <span class="inventory-meta-label">Last Edited</span>
              <strong id="inventoryLastEdited">Never</strong>
            </div>
          </div>
          <div class="equipment-id-legend" aria-label="Equipment ID prefix legend">
            <span class="legend-label">Equipment ID Prefix</span>
            <span><strong>E</strong> Equipment</span>
            <span><strong>M</strong> Measuring Tools</span>
            <span><strong>C</strong> Chemicals</span>
            <span><strong>B</strong> Books</span>
          </div>
        </div>

        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow);">
          <table id="equipmentTable">
            <thead>
              <tr>
                <th rowspan="2" class="small-column">ID</th>
                <th rowspan="2" style="width:80px;text-align:center;">Image</th>
                <th rowspan="2">Equipment</th>
                <th rowspan="2">SN</th>
                <th rowspan="2">ISN</th>
                <th rowspan="2">Acc Person</th>
                <th rowspan="2">Visibility</th>
                <th colspan="4">Condition</th>
                <th rowspan="2">Description</th>
                <th rowspan="2" style="width:44px;text-align:center;">Log</th>
              </tr>
              <tr>
                <th title="Total">T</th>
                <th title="Working">W</th>
                <th title="Not Working">NW</th>
                <th title="Maintenance">M</th>
              </tr>
            </thead>
            <tbody id="equipmentList"></tbody>
          </table>
        </div>

      </div><!-- /invPanelList -->

      <!-- ── TAB: HISTORY ── -->
      <div class="inv-tab-panel" id="invPanelHistory">

        <div class="hist-page-header">
          <div>
            <div class="hist-page-title">Equipment Add History</div>
            <div class="hist-page-sub">All timestamps are in <strong>Philippine Standard Time (PST · UTC+8)</strong>.</div>
          </div>
        </div>

        <!-- Date filter -->
        <div class="hist-filter-row">
          <div>
            <label>From</label>
            <input type="date" id="histFromDate">
          </div>
          <div>
            <label>To</label>
            <input type="date" id="histToDate">
          </div>
          <button onclick="loadHistoryTab()" style="background:var(--accent);color:#fff;border-color:var(--accent);font-size:12px;padding:7px 16px;">
            Filter
          </button>
          <button onclick="clearHistoryFilter()" style="background:var(--surface-2);font-size:12px;padding:7px 14px;">
            Clear
          </button>
        </div>

        <!-- History table -->
        <div class="hist-table-wrap">
          <table class="hist-full-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Equipment Name</th>
                <th>Equipment ID</th>
                <th>Qty&nbsp;<span style="font-weight:400;color:var(--text-3);">(T / W / NW / M)</span></th>
                <th>Accountable Person</th>
                <th>Action</th>
                <th>Added By</th>
                <th>Date &amp; Time <span style="font-weight:400;">(PST)</span></th>
              </tr>
            </thead>
            <tbody id="histTableBody">
              <tr class="hist-loading-row"><td colspan="8">Loading history…</td></tr>
            </tbody>
          </table>
        </div>

      </div><!-- /invPanelHistory -->

      <!-- ── TAB: INVENTORY AUDIT ── -->
      <div class="inv-tab-panel" id="invPanelAudit">

        <!-- Summary Section -->
        <div class="card" style="margin-bottom:24px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:16px;">
            <div>
              <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:6px;">Last Audit Date</div>
              <div style="font-size:18px;font-weight:600;color:var(--text-1);" id="lastAuditDate">Never</div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:6px;">Next Scheduled (6 months)</div>
              <div style="font-size:18px;font-weight:600;color:var(--accent);" id="nextScheduledDate">—</div>
            </div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button onclick="openStartAuditModal()" style="background:var(--accent);color:#fff;border-color:var(--accent);padding:10px 18px;font-weight:600;">+ Start New Audit</button>
            <button onclick="showAuditPastAudits()" style="background:var(--surface-2);padding:10px 18px;">View Past Audits</button>
          </div>
        </div>

        <!-- Condition Legend -->
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px;flex-wrap:wrap;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);padding:12px 16px;font-size:11px;color:var(--text-2);">
          <strong style="color:var(--text-1);">Condition Legend:</strong>
          <span title="Total equipment count" style="display:inline-flex;align-items:center;gap:4px;"><strong>T</strong> = Total</span>
          <span title="Operational equipment" style="display:inline-flex;align-items:center;gap:4px;"><strong>W</strong> = Working</span>
          <span title="Broken or defective equipment" style="display:inline-flex;align-items:center;gap:4px;"><strong>NW</strong> = Not-working</span>
          <span title="Equipment being serviced" style="display:inline-flex;align-items:center;gap:4px;"><strong>M</strong> = Maintenance</span>
        </div>

        <!-- Audit Interface (initially hidden) -->
        <div id="auditChecklistSection" style="display:none;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
            <div style="font-size:14px;font-weight:600;color:var(--text-1);">
              Current Audit: <span id="auditDateDisplay">—</span>
            </div>
            <button onclick="closeAuditInterface()" style="background:var(--surface-2);padding:6px 12px;font-size:12px;">✕ Close</button>
          </div>

          <div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);padding:12px 16px;font-size:11px;color:var(--text-2);">
            <strong style="color:var(--text-1);">Condition Legend:</strong>
            <span title="Total" style="display:inline-flex;align-items:center;gap:4px;"><strong>T</strong> = Total</span>
            <span title="Working" style="display:inline-flex;align-items:center;gap:4px;"><strong>W</strong> = Working</span>
            <span title="Not-working" style="display:inline-flex;align-items:center;gap:4px;"><strong>NW</strong> = Not-working</span>
            <span title="Maintenance" style="display:inline-flex;align-items:center;gap:4px;"><strong>M</strong> = Maintenance</span>
          </div>

          <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
            <div class="search-bar" style="flex:1;min-width:200px;">
              <input type="text" placeholder="Search equipment…" id="auditSearchInput" onkeyup="filterAuditItems()">
            </div>
            <select id="auditStatusFilter" onchange="filterAuditItems()" style="font-family:var(--font);font-size:12px;padding:6px 10px;border:1px solid var(--border);border-radius:var(--radius);background:var(--bg);color:var(--text-1);outline:none;">
              <option value="All">All Items</option>
              <option value="Complete">Complete</option>
              <option value="Missing">Missing</option>
              <option value="Damaged">Damaged</option>
            </select>
          </div>

          <div style="display:flex;gap:8px;margin-bottom:12px;font-size:12px;flex-wrap:wrap;">
            <span style="padding:6px 12px;background:var(--bg);border-radius:var(--radius);color:var(--text-2);">
              <strong id="auditCountAll">0</strong> All
            </span>
            <span style="padding:6px 12px;background:var(--bg);border-radius:var(--radius);color:var(--accent);">
              <strong id="auditCountComplete">0</strong> Complete
            </span>
            <span style="padding:6px 12px;background:var(--bg);border-radius:var(--radius);color:var(--danger);">
              <strong id="auditCountMissing">0</strong> Missing
            </span>
            <span style="padding:6px 12px;background:var(--bg);border-radius:var(--radius);color:var(--warn);">
              <strong id="auditCountDamaged">0</strong> Damaged
            </span>
          </div>

          <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow);margin-bottom:16px;">
            <div style="max-height:600px;overflow-y:auto;">
              <table style="width:100%;border-collapse:collapse;font-size:13px;" id="auditChecklistTable">
                <thead style="position:sticky;top:0;background:var(--surface-2);z-index:1;">
                  <tr>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);">Equipment Name</th>
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);width:150px;">Previous<br><span style="font-weight:400;"><span title="Total">T</span> / <span title="Working">W</span> / <span title="Not Working">NW</span> / <span title="Maintenance">M</span></span></th>
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);width:250px;">New<br><span style="font-weight:400;"><span title="Total">T</span> / <span title="Working">W</span> / <span title="Not Working">NW</span> / <span title="Maintenance">M</span></span></th>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);width:140px;">Status</th>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);">Damage Notes</th>
                  </tr>
                </thead>
                <tbody id="auditItemsBody"></tbody>
              </table>
            </div>
          </div>

          <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
            <button onclick="saveDraftAudit()" style="background:var(--surface-2);padding:10px 18px;font-weight:600;">↙ Save Draft</button>
            <button onclick="submitAudit()" style="background:var(--accent);color:#fff;border-color:var(--accent);padding:10px 18px;font-weight:600;">Submit Audit →</button>
          </div>
        </div>

        <!-- Audit Summary (shown after submit) -->
        <div id="auditSummarySection" style="display:none;">
          <div class="card" style="text-align:center;margin-bottom:24px;">
            <h2 style="font-size:18px;font-weight:600;margin-bottom:16px;color:var(--text-1);">Audit Results</h2>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
              <div style="background:var(--bg);padding:16px;border-radius:var(--radius);">
                <div style="font-size:24px;font-weight:600;color:var(--text-1);" id="summaryTotal">0</div>
                <div style="font-size:11px;color:var(--text-3);margin-top:4px;">Total Items</div>
              </div>
              <div style="background:var(--accent-soft);padding:16px;border-radius:var(--radius);">
                <div style="font-size:24px;font-weight:600;color:var(--accent);" id="summaryComplete">0</div>
                <div style="font-size:11px;color:var(--text-3);margin-top:4px;">Complete</div>
              </div>
              <div style="background:#FDECEA;padding:16px;border-radius:var(--radius);">
                <div style="font-size:24px;font-weight:600;color:var(--danger);" id="summaryMissing">0</div>
                <div style="font-size:11px;color:var(--text-3);margin-top:4px;">Missing</div>
              </div>
              <div style="background:var(--warn-soft);padding:16px;border-radius:var(--radius);">
                <div style="font-size:24px;font-weight:600;color:var(--warn);" id="summaryDamaged">0</div>
                <div style="font-size:11px;color:var(--text-3);margin-top:4px;">Damaged</div>
              </div>
            </div>
            <input type="hidden" id="currentAuditIdStorage" value="">
            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:16px;">
              <button onclick="importAuditToInventory(document.getElementById('currentAuditIdStorage').value)" style="background:var(--accent);color:#fff;border:1px solid var(--accent);padding:10px 18px;font-weight:600;border-radius:var(--radius);cursor:pointer;">📥 Import Results to Inventory</button>
            </div>
          </div>
        </div>

        <!-- Past Audits Table -->
        <div id="pastAuditsSection" style="display:none;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
            <h2 style="font-size:15px;font-weight:600;margin:0;color:var(--text-1);">Audit History</h2>
            <button onclick="resetToAuditSummary()" style="background:var(--surface-2);padding:6px 12px;font-size:12px;">← Back</button>
          </div>
          <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow);">
            <div style="max-height:600px;overflow-y:auto;">
              <table style="width:100%;border-collapse:collapse;font-size:12px;" id="pastAuditsTable">
                <thead style="position:sticky;top:0;background:var(--surface-2);z-index:1;">
                  <tr>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);">Date</th>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);">Admin</th>
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);width:80px;">Complete</th>
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);width:80px;">Missing</th>
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);width:80px;">Damaged</th>
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);width:60px;">Action</th>
                  </tr>
                </thead>
                <tbody id="pastAuditsBody"></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Most Borrowed Equipment Panel -->
        <div id="mostBorrowedSection" style="margin-top:24px;">
          <div class="card">
            <h2 style="font-size:15px;font-weight:600;margin-bottom:12px;color:var(--text-1);">Most Borrowed Equipment</h2>
            <p style="font-size:12px;color:var(--text-3);margin-bottom:12px;">Based on last 6 months of borrowing requests</p>
            <div style="max-height:320px;overflow-y:auto;">
              <table style="width:100%;font-size:12px;" id="mostBorrowedTable">
                <thead>
                  <tr style="background:var(--bg);">
                    <th style="padding:8px 10px;text-align:center;font-size:10px;font-weight:600;color:var(--text-3);width:40px;">Rank</th>
                    <th style="padding:8px 10px;text-align:left;font-size:10px;font-weight:600;color:var(--text-3);">Equipment</th>
                    <th style="padding:8px 10px;text-align:center;font-size:10px;font-weight:600;color:var(--text-3);width:120px;">Times Borrowed</th>
                    <th style="padding:8px 10px;text-align:center;font-size:10px;font-weight:600;color:var(--text-3);width:100px;">Total Qty</th>
                  </tr>
                </thead>
                <tbody id="mostBorrowedBody">
                  <tr><td colspan="4" style="padding:16px;text-align:center;color:var(--text-3);">Loading…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div><!-- /invPanelAudit -->

    </div><!-- /inventorySection -->

    <!-- ═══════════════════ SCHEDULE ═══════════════════ -->
    <div id="scheduleSection" style="display:none;">
      <div class="page-header"><h1>Schedule</h1></div>
      <div id="calendar"></div>
      <div id="statsSummary" style="margin-top:28px;">
        <h2 style="font-size:16px;font-weight:600;margin-bottom:16px;">Summary</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
          <div class="card" style="text-align:center;">
            <p style="font-size:12px;color:var(--text-3);font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">Month — <span id="monthLabel">—</span></p>
            <div style="display:flex;justify-content:space-around;margin-bottom:12px;">
              <div><div style="font-size:22px;font-weight:600;font-family:var(--mono);" id="monthlyTotal">0</div><div style="font-size:11px;color:var(--text-3);">Total</div></div>
              <div><div style="font-size:22px;font-weight:600;font-family:var(--mono);color:var(--accent);" id="monthlyAccepted">0</div><div style="font-size:11px;color:var(--text-3);">Accepted</div></div>
              <div><div style="font-size:22px;font-weight:600;font-family:var(--mono);color:var(--danger);" id="monthlyRejected">0</div><div style="font-size:11px;color:var(--text-3);">Rejected</div></div>
            </div>
            <p style="font-size:12px;color:var(--text-3);">Top: <strong id="monthlyTopItem">N/A</strong></p>
            <div style="width:180px;height:180px;margin:12px auto 0;"><canvas id="monthlyChart"></canvas></div>
          </div>
          <div class="card" style="text-align:center;">
            <p style="font-size:12px;color:var(--text-3);font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">Week — <span id="weekLabel">—</span></p>
            <div style="display:flex;justify-content:space-around;margin-bottom:12px;">
              <div><div style="font-size:22px;font-weight:600;font-family:var(--mono);" id="weeklyTotal">0</div><div style="font-size:11px;color:var(--text-3);">Total</div></div>
              <div><div style="font-size:22px;font-weight:600;font-family:var(--mono);color:var(--accent);" id="weeklyAccepted">0</div><div style="font-size:11px;color:var(--text-3);">Accepted</div></div>
              <div><div style="font-size:22px;font-weight:600;font-family:var(--mono);color:var(--danger);" id="weeklyRejected">0</div><div style="font-size:11px;color:var(--text-3);">Rejected</div></div>
            </div>
            <p style="font-size:12px;color:var(--text-3);">Top: <strong id="weeklyTopItem">N/A</strong></p>
            <div style="width:180px;height:180px;margin:12px auto 0;"><canvas id="weeklyChart"></canvas></div>
          </div>
        </div>
        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
            <p style="font-size:13px;font-weight:600;margin:0;">Monthly Borrowing Trend</p>
            <div style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;">
              <div>
                <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:4px;">From</label>
                <input type="date" id="trendFrom" style="font-family:var(--font);font-size:12px;padding:6px 10px;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text-1);outline:none;">
              </div>
              <div style="padding-bottom:7px;color:var(--text-3);">&rarr;</div>
              <div>
                <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:4px;">To</label>
                <input type="date" id="trendTo" style="font-family:var(--font);font-size:12px;padding:6px 10px;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text-1);outline:none;">
              </div>
              <div>
                <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:4px;">Status</label>
                <select id="trendStatus" style="font-family:var(--font);font-size:12px;padding:6px 10px;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text-1);outline:none;">
                  <option value="All">All</option>
                  <option value="Accepted">Accepted</option>
                  <option value="Pending">Pending</option>
                  <option value="Rejected">Rejected</option>
                </select>
              </div>
              <button id="trendFilterBtn" class="primary" style="background:var(--accent);color:#fff;border-color:var(--accent);font-size:12px;">Filter</button>
            </div>
          </div>
          <div style="height:280px;"><canvas id="equipmentTrendChart" height="280"></canvas></div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════ BORROW REQUESTS ═══════════════════ -->
    <div id="queueSection" style="display:none;">
      <div class="page-header"><h1>Borrow Requests</h1></div>
      <div style="display:flex;align-items:flex-end;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <div>
          <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:4px;">Start Date</label>
          <input type="date" id="startDate">
        </div>
        <div>
          <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:4px;">End Date</label>
          <input type="date" id="endDate">
        </div>
        <button onclick="loadBorrowRequests()" class="primary" style="background:var(--accent);color:#fff;border-color:var(--accent);">Filter</button>
        <button onclick="clearFilters()" style="background:var(--surface-2);">Clear</button>
      </div>
      <div id="borrowQueue" style="display:flex;flex-direction:column;gap:8px;"></div>
    </div>

    <!-- ═══════════════════ REPORTS ═══════════════════ -->
    <div id="reportsSection" style="display:none;">
      <div class="page-header"><h1>Reports</h1></div>
      <div class="inv-tab-row">
        <button class="inv-tab-btn active" id="reportsTabListBtn" onclick="switchReportsTab('list')">Report Entries</button>
        <button class="inv-tab-btn" id="reportsTabSummaryBtn" onclick="switchReportsTab('summary')">Summary</button>
      </div>

      <div class="inv-tab-panel active" id="reportsPanelList">
        <div class="reports-filter-row">
          <div class="reports-search-field">
            <label for="reportsSearch">Search</label>
            <input type="search" id="reportsSearch" placeholder="Borrower, borrower, instructor, room">
          </div>
          <div>
            <label for="reportsStatusFilter">Status</label>
            <select id="reportsStatusFilter">
              <option value="All">All</option>
              <option value="Accepted">Accepted</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>
          <div>
            <label for="reportsFrom">From</label>
            <input type="date" id="reportsFrom">
          </div>
          <div>
            <label for="reportsTo">To</label>
            <input type="date" id="reportsTo">
          </div>
          <button id="reportsFilterBtn" class="primary">Filter</button>
          <button id="reportsClearBtn">Clear</button>
        </div>

        <div id="reportsList" style="display:flex;flex-direction:column;gap:10px;"></div>

        <div class="reports-pagination" id="reportsPagination">
          <div class="reports-page-summary" id="reportsPageSummary">Showing 0-0 of 0 reports</div>
          <div class="reports-page-controls">
            <button type="button" class="reports-page-btn" data-page-action="first">&lt;&lt;</button>
            <button type="button" class="reports-page-btn" data-page-action="prev">&lt;</button>
            <div class="reports-page-numbers" id="reportsPageNumbers"></div>
            <button type="button" class="reports-page-btn" data-page-action="next">&gt;</button>
            <button type="button" class="reports-page-btn" data-page-action="last">&gt;&gt;</button>
          </div>
          <div class="reports-page-options">
            <label for="reportsGoToPage">Go to</label>
            <input type="number" id="reportsGoToPage" min="1" value="1">
            <label for="reportsRowsPerPage">Rows</label>
            <select id="reportsRowsPerPage">
              <option value="10">10</option>
              <option value="15">15</option>
              <option value="25">25</option>
            </select>
          </div>
        </div>
      </div>

      <div class="inv-tab-panel" id="reportsPanelSummary">
        <div id="reportsSummaryMount"></div>
      </div>
    </div>

  </div><!-- /main-content -->

  <!-- ── ADD EQUIPMENT MODAL ── -->
  <div id="addEquipmentModal" class="modal">
    <div class="modal-content" style="position:relative;max-width:500px;">
      <span class="close" onclick="closeAddEquipmentModal()">&times;</span>
      <h2 id="equipmentModalTitle">Add Equipment</h2>
      <label>Equipment ID</label>
      <input type="text" id="equipmentID" required />
      <label>Equipment Name</label>
      <input type="text" id="equipmentName" required />
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div><label>Serial Number</label><input type="text" id="serialNumber" /></div>
        <div><label>Internal SN</label><input type="text" id="internalSN" /></div>
      </div>
      <label>Accountable Person</label>
      <input type="text" id="accountablePerson" />
      <label>Borrowing Visibility</label>
      <select id="borrowingStatus">
        <option value="1">Available for Borrowing</option>
        <option value="0">Restricted / Hidden from Borrower Side</option>
      </select>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
        <div><label>Total Qty</label><input type="number" id="totalQty" /></div>
        <div><label>Working</label><input type="number" id="workingQty" /></div>
        <div><label>Not Working</label><input type="number" id="notWorkingQty" /></div>
        <div><label>Maintenance</label><input type="number" id="maintenanceQty" /></div>
      </div>
      <label>Description</label>
      <textarea id="description"></textarea>
      <button id="submitEquipmentBtn" style="width:100%;margin-top:16px;padding:10px;justify-content:center;">Submit</button>
    </div>
  </div>

  <!-- ── PASSWORD MODAL ── -->
  <div id="passwordModal" style="display:none;">
    <div class="modal-content" style="position:relative;text-align:center;">
      <span class="close" onclick="closePasswordModal()">&times;</span>
      <h3>Confirm Password</h3>
      <p style="font-size:13px;color:var(--text-3);margin-bottom:16px;">Enter your password to access Inventory</p>
      <input type="password" id="confirmPassword" placeholder="Password" style="width:100%;font-family:var(--font);font-size:13px;padding:9px 12px;border:1px solid var(--border);border-radius:var(--radius);background:var(--bg);outline:none;margin-bottom:12px;box-sizing:border-box;" />
      <button onclick="verifyPassword()" style="width:100%;background:var(--accent);color:#fff;border-color:var(--accent);justify-content:center;padding:10px;">Confirm</button>
      <p id="passwordError" style="color:var(--danger);display:none;font-size:12px;margin-top:8px;">Incorrect password. Try again.</p>
    </div>
  </div>

  <!-- ── IMPORT PREVIEW MODAL ── -->
  <div id="importPreviewModal" style="display:none;position:fixed;inset:0;z-index:1200;background:rgba(26,26,24,.55);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:24px;box-sizing:border-box;">
    <div style="background:var(--surface);border-radius:var(--radius-lg);border:1px solid var(--border);box-shadow:var(--shadow-md);width:min(880px,100%);max-height:min(calc(100vh - 48px),720px);display:flex;flex-direction:column;overflow:hidden;">

      <!-- Header -->
      <div style="padding:20px 24px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <div>
          <div style="font-size:15px;font-weight:600;color:var(--text-1);">Import Preview</div>
          <div id="importPreviewMeta" style="font-size:12px;color:var(--text-3);margin-top:2px;"></div>
        </div>
        <button onclick="closeImportPreview()" style="background:none;border:none;cursor:pointer;color:var(--text-3);padding:4px;line-height:1;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Legend -->
      <div style="padding:10px 24px;background:var(--bg);border-bottom:1px solid var(--border);display:flex;gap:16px;flex-shrink:0;">
        <span style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-2);">
          <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--accent-soft);border:1px solid #a8d5b5;"></span> New — will be imported
        </span>
        <span style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-2);">
          <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--warn-soft);border:1px solid #f5c98a;"></span> Duplicate — matching inventory row will be overwritten
        </span>
      </div>

      <!-- Table -->
      <div style="overflow:auto;flex:1;min-height:0;">
        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
          <thead style="position:sticky;top:0;z-index:1;">
            <tr style="background:var(--surface-2);">
              <th style="padding:9px 12px;text-align:left;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap;">Status</th>
              <th style="padding:9px 12px;text-align:left;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">ID</th>
              <th style="padding:9px 12px;text-align:left;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">Equipment Name</th>
              <th style="padding:9px 12px;text-align:left;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">SN</th>
              <th style="padding:9px 12px;text-align:left;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">ISN</th>
              <th style="padding:9px 12px;text-align:left;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">Acc. Person</th>
              <th style="padding:9px 12px;text-align:left;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">Visibility</th>
              <th style="padding:9px 12px;text-align:center;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">Total</th>
              <th style="padding:9px 12px;text-align:center;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">W</th>
              <th style="padding:9px 12px;text-align:center;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">NW</th>
              <th style="padding:9px 12px;text-align:center;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">M</th>
              <th style="padding:9px 12px;text-align:left;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">Description</th>
            </tr>
          </thead>
          <tbody id="importPreviewBody"></tbody>
        </table>
      </div>

      <!-- Footer -->
      <div style="padding:14px 24px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-shrink:0;">
        <button onclick="closeImportPreview()" style="background:var(--surface-2);color:var(--text-2);border:1px solid var(--border);padding:9px 20px;border-radius:var(--radius);font-family:var(--font);font-size:13px;cursor:pointer;">Cancel</button>
        <button id="confirmImportBtn" style="background:var(--accent);color:#fff;border:1px solid var(--accent);padding:9px 20px;border-radius:var(--radius);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer;">Confirm Import</button>
      </div>
    </div>
  </div>

  <script src="admin.js?v=20260507a"></script>
</body>
</html>
