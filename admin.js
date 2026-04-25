// ════════════════════════════════════════════════════════════════
// STATE
// ════════════════════════════════════════════════════════════════
let selectedRow      = null;
let selectedItemData = null;
let canEditQty       = true;
let currentEquipmentIDs = new Set();

// Inline edit state
let editingRow      = null;
let originalRowHTML = '';
let currentEditItem = null;

// ════════════════════════════════════════════════════════════════
// PASSWORD — always required every click, no sessionStorage cache
// ════════════════════════════════════════════════════════════════
// inventoryUnlocked is intentionally NEVER set to true permanently.
// Every click on Inventory will always open the password modal.
let inventoryUnlocked    = false;
window.inventoryUnlocked = false;

function openPasswordModal() {
  const input = document.getElementById('confirmPassword');
  const err   = document.getElementById('passwordError');
  if (input) input.value = '';
  if (err)   err.style.display = 'none';
  document.getElementById('passwordModal').style.display = 'flex';
}

function closePasswordModal() {
  document.getElementById('passwordModal').style.display = 'none';
  const input = document.getElementById('confirmPassword');
  const err   = document.getElementById('passwordError');
  if (input) input.value = '';
  if (err)   err.style.display = 'none';
}

function verifyPassword() {
  const password = document.getElementById('confirmPassword').value;
  fetch('inventory_password.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ password })
  })
  .then(r => r.json())
  .then(data => {
    if (data.status === 'success') {
      closePasswordModal();
      navigateToSection('Inventory');
    } else {
      document.getElementById('passwordError').style.display = 'block';
    }
  })
  .catch(err => console.error('Error verifying password:', err));
}

// ════════════════════════════════════════════════════════════════
// SAVE EQUIPMENT LOG (PH time set server-side)
// ════════════════════════════════════════════════════════════════
function saveEquipmentLog(data, action) {
  const payload = {
    equipment_id:    data.equipment_id    || data.equipmentID    || '',
    equipment_name:  data.equipment_name  || data.equipmentName  || '',
    total_qty:       parseInt(data.total_qty       ?? data.totalQty)      || 0,
    working_qty:     parseInt(data.working_qty     ?? data.workingQty)    || 0,
    not_working_qty: parseInt(data.not_working_qty ?? data.notWorkingQty) || 0,
    account_person:  data.account_person  || data.accountablePerson || '',
    action:          action || 'Added'
  };

  if (!payload.equipment_id || !payload.equipment_name) {
    console.warn('saveEquipmentLog: missing required fields', payload);
    return;
  }

  fetch('save_equipment_log.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success) console.warn('Log save failed:', res.message);
    else refreshHistTabCount();
  })
  .catch(err => console.error('Log save error:', err));
}

// ════════════════════════════════════════════════════════════════
// INVENTORY TAB SWITCHER
// ════════════════════════════════════════════════════════════════
function switchInvTab(tab) {
  document.getElementById('invPanelList').classList.toggle('active',    tab === 'list');
  document.getElementById('invPanelHistory').classList.toggle('active', tab === 'history');
  document.getElementById('invTabListBtn').classList.toggle('active',   tab === 'list');
  document.getElementById('invTabHistBtn').classList.toggle('active',   tab === 'history');
  if (tab === 'history') loadHistoryTab();
}

// ════════════════════════════════════════════════════════════════
// HISTORY TAB — full-page table
// ════════════════════════════════════════════════════════════════
function refreshHistTabCount() {
  fetch('get_equipment_history.php?all=1')
    .then(r => r.json())
    .then(res => {
      const pill = document.getElementById('histTabCount');
      if (pill) pill.textContent = res.success ? res.data.length : '—';
    })
    .catch(() => {});
}

function loadHistoryTab() {
  const from  = document.getElementById('histFromDate')?.value || '';
  const to    = document.getElementById('histToDate')?.value   || '';
  let   url   = 'get_equipment_history.php?all=1';
  if (from) url += '&from=' + encodeURIComponent(from);
  if (to)   url += '&to='   + encodeURIComponent(to);

  const tbody = document.getElementById('histTableBody');
  if (!tbody) return;
  tbody.innerHTML = '<tr class="hist-loading-row"><td colspan="8">Loading history…</td></tr>';

  fetch(url)
    .then(r => r.json())
    .then(res => {
      const pill = document.getElementById('histTabCount');
      if (pill) pill.textContent = res.success ? res.data.length : '—';

      if (!res.success || !res.data.length) {
        tbody.innerHTML = '<tr class="hist-empty-row"><td colspan="8">No equipment history recorded yet.</td></tr>';
        return;
      }

      tbody.innerHTML = '';
      res.data.forEach((log, idx) => {
        const actionClass = (log.action || 'Added').toLowerCase();
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td style="color:var(--text-3);font-size:11px;">${res.data.length - idx}</td>
          <td style="font-weight:500;">${escHtml(log.equipment_name || '—')}</td>
          <td style="font-family:var(--mono);font-size:12px;color:var(--text-2);">${escHtml(log.equipment_id || '—')}</td>
          <td>
            <span class="hist-qty">
              <span class="q-total">${log.total_qty ?? '—'}</span>
              <span class="q-sep">/</span>
              <span class="q-work">${log.working_qty ?? '—'}</span>
              <span class="q-sep">/</span>
              <span class="q-nowork">${log.not_working_qty ?? '—'}</span>
            </span>
          </td>
          <td style="font-size:12px;">${escHtml(log.account_person || '—')}</td>
          <td><span class="hist-badge ${actionClass}">${escHtml(log.action || 'Added')}</span></td>
          <td style="font-size:12px;color:var(--text-2);">${escHtml(log.added_by || 'Admin')}</td>
          <td>
            <div class="hist-ts">
              <span class="hist-ts-date">${escHtml(log.date_label || '—')}</span>
              <span class="hist-ts-time">${escHtml(log.time_label || '—')}</span>
              <span class="hist-ts-tz">PST · UTC+8</span>
            </div>
          </td>`;
        tbody.appendChild(tr);
      });
    })
    .catch(() => {
      if (tbody) tbody.innerHTML = '<tr class="hist-empty-row"><td colspan="8">Error loading history.</td></tr>';
    });
}

function clearHistoryFilter() {
  const f = document.getElementById('histFromDate');
  const t = document.getElementById('histToDate');
  if (f) f.value = '';
  if (t) t.value = '';
  loadHistoryTab();
}

// ════════════════════════════════════════════════════════════════
// ADD EQUIPMENT MODAL
// ════════════════════════════════════════════════════════════════
function openAddEquipmentModal() {
  document.getElementById('addEquipmentModal').classList.add('is-open');
}
function closeAddEquipmentModal() {
  document.getElementById('addEquipmentModal').classList.remove('is-open');
}

// ════════════════════════════════════════════════════════════════
// HISTORY POPOVER (per-row clock button)
// ════════════════════════════════════════════════════════════════
let activeHistoryBtn = null;

function closeHistoryPopover() {
  const existing = document.getElementById('historyPopover');
  if (existing) existing.remove();
  if (activeHistoryBtn) {
    activeHistoryBtn.classList.remove('hist-btn-active');
    activeHistoryBtn = null;
  }
}

function openHistoryPopover(btn, equipmentId, equipmentName) {
  if (activeHistoryBtn === btn) { closeHistoryPopover(); return; }
  closeHistoryPopover();
  activeHistoryBtn = btn;
  btn.classList.add('hist-btn-active');

  const pop = document.createElement('div');
  pop.id        = 'historyPopover';
  pop.className = 'hist-popover';
  pop.innerHTML = `
    <div class="hist-pop-header">
      <div>
        <div class="hist-pop-title">History</div>
        <div class="hist-pop-sub">${escHtml(equipmentName)} · ${escHtml(equipmentId)}</div>
      </div>
      <button class="hist-pop-close" onclick="closeHistoryPopover()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
          stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="hist-pop-body" id="histPopBody">
      <div class="hist-loading">Loading…</div>
    </div>`;
  document.body.appendChild(pop);
  positionPopover(pop, btn);

  fetch(`get_equipment_history.php?equipment_id=${encodeURIComponent(equipmentId)}`)
    .then(r => r.json())
    .then(res => {
      const body = document.getElementById('histPopBody');
      if (!body) return;
      if (!res.success || !res.data.length) {
        body.innerHTML = '<div class="hist-empty">No history recorded yet.</div>';
        return;
      }
      body.innerHTML = renderHistoryEntries(res.data);
    })
    .catch(() => {
      const body = document.getElementById('histPopBody');
      if (body) body.innerHTML = '<div class="hist-empty">Failed to load history.</div>';
    });
}

function positionPopover(pop, btn) {
  const rect = btn.getBoundingClientRect();
  const popW = 340;
  let left = rect.right - popW;
  if (left < 8) left = 8;
  pop.style.left  = left + 'px';
  pop.style.top   = (rect.bottom + 8 + window.scrollY) + 'px';
  pop.style.width = popW + 'px';
}

function renderHistoryEntries(entries) {
  const groups = {};
  entries.forEach(e => {
    if (!groups[e.date_label]) groups[e.date_label] = [];
    groups[e.date_label].push(e);
  });
  let html = '';
  Object.entries(groups).forEach(([date, items]) => {
    html += `<div class="hist-date-group"><div class="hist-date-label">${escHtml(date)}</div>`;
    items.forEach(e => {
      if (e.action === 'Added') {
        html += `
        <div class="hist-entry hist-added">
          <div class="hist-entry-top">
            <span class="hist-badge added">Added</span>
            <span class="hist-time">${escHtml(e.time_label)}</span>
          </div>
          <div class="hist-entry-detail">Equipment added to inventory</div>
          ${renderSnapshot(e.snapshot)}
        </div>`;
      } else {
        html += `
        <div class="hist-entry hist-edited">
          <div class="hist-entry-top">
            <span class="hist-badge edited">Edited</span>
            <span class="hist-time">${escHtml(e.time_label)}</span>
          </div>
          <div class="hist-entry-detail">Equipment details updated</div>
          ${renderSnapshot(e.snapshot)}
        </div>`;
      }
    });
    html += '</div>';
  });
  return html;
}

function renderSnapshot(snap) {
  if (!snap || typeof snap !== 'object') return '';
  const labels = {
    equipment_name:  'Name',
    serial_number:   'SN',
    internal_sn:     'ISN',
    account_person:  'Acc. Person',
    total_qty:       'Total',
    working_qty:     'Working',
    not_working_qty: 'Not Working',
    description:     'Description',
  };
  let rows = '';
  Object.entries(labels).forEach(([key, label]) => {
    const val = snap[key];
    if (val !== undefined && val !== '') {
      rows += `<tr><td class="snap-label">${label}</td><td class="snap-val">${escHtml(String(val))}</td></tr>`;
    }
  });
  return rows ? `<table class="hist-snapshot">${rows}</table>` : '';
}

function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('click', function(e) {
  const pop = document.getElementById('historyPopover');
  if (!pop) return;
  if (!pop.contains(e.target) && !e.target.closest('.hist-btn')) closeHistoryPopover();
});

// ════════════════════════════════════════════════════════════════
// INLINE EDIT — sticky save bar
// ════════════════════════════════════════════════════════════════
function makeInlineInput(fieldName, type, value, disabled) {
  const isTextarea = type === 'textarea';
  const el = document.createElement(isTextarea ? 'textarea' : 'input');
  if (!isTextarea) el.type = type;
  el.value         = value ?? '';
  el.dataset.field = fieldName;
  el.disabled      = !!disabled;
  el.style.cssText = `
    width:100%;box-sizing:border-box;
    font-family:var(--font);font-size:12px;padding:4px 7px;
    border:1.5px solid ${disabled ? 'var(--border)' : 'var(--accent)'};
    border-radius:6px;
    background:${disabled ? 'var(--surface-2)' : 'var(--surface)'};
    color:${disabled ? 'var(--text-3)' : 'var(--text-1)'};
    outline:none;${disabled ? 'cursor:not-allowed;' : ''}
  `;
  if (isTextarea) el.rows = 2;
  if (type === 'number') el.min = 0;
  return el;
}

function createStickyActionBar(item) {
  const bar = document.createElement('div');
  bar.id = 'inlineEditBar';
  bar.style.cssText = `
    position:fixed;bottom:24px;right:32px;z-index:500;
    display:flex;align-items:center;gap:10px;
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius-lg);padding:10px 16px;
    box-shadow:0 4px 24px rgba(0,0,0,.13);
    animation:slideUpBar .2s cubic-bezier(.4,0,.2,1) both;
  `;

  const label = document.createElement('span');
  label.style.cssText = 'font-size:12px;color:var(--text-3);font-weight:500;margin-right:4px;';
  label.textContent   = `Editing: ${item.equipment_id}`;
  bar.appendChild(label);

  const borrowed = parseInt(item.available) !== parseInt(item.working_qty);
  if (borrowed) {
    const warn = document.createElement('span');
    warn.style.cssText = 'font-size:11px;color:var(--warn);margin-right:6px;';
    warn.textContent   = '⚠ Qty locked (borrowed)';
    bar.appendChild(warn);
  }

  const saveBtn = document.createElement('button');
  saveBtn.id = 'inlineEditSaveBtn';
  saveBtn.textContent = 'Save changes';
  saveBtn.style.cssText = `
    background:var(--accent);color:#fff;border:none;
    padding:7px 18px;border-radius:var(--radius);
    font-family:var(--font);font-size:13px;font-weight:600;
    cursor:pointer;white-space:nowrap;
  `;
  saveBtn.onmouseenter = () => { saveBtn.style.background = '#245A40'; };
  saveBtn.onmouseleave = () => { saveBtn.style.background = 'var(--accent)'; };

  const cancelBtn = document.createElement('button');
  cancelBtn.textContent = 'Cancel';
  cancelBtn.style.cssText = `
    background:var(--surface-2);color:var(--text-2);
    border:1px solid var(--border);padding:7px 14px;
    border-radius:var(--radius);font-family:var(--font);
    font-size:13px;cursor:pointer;white-space:nowrap;
  `;
  cancelBtn.onmouseenter = () => { cancelBtn.style.background = 'var(--border)'; };
  cancelBtn.onmouseleave = () => { cancelBtn.style.background = 'var(--surface-2)'; };

  bar.appendChild(saveBtn);
  bar.appendChild(cancelBtn);
  saveBtn.addEventListener('click',   saveInlineEdit);
  cancelBtn.addEventListener('click', cancelInlineEdit);
  return bar;
}

function removeStickyBar() {
  const b = document.getElementById('inlineEditBar');
  if (b) b.remove();
}

function enterInlineEdit(row, item) {
  if (editingRow && editingRow !== row) cancelInlineEdit();
  editingRow      = row;
  originalRowHTML = row.innerHTML;
  currentEditItem = item;

  const borrowed = parseInt(item.available) !== parseInt(item.working_qty);

  // Handle image column (cell 1) separately
  const imageCell = row.cells[1];
  if (imageCell) {
    imageCell.innerHTML = '';
    imageCell.style.verticalAlign = 'top';
    imageCell.style.padding = '6px 8px';

    const container = document.createElement('div');
    container.style.display = 'flex';
    container.style.flexDirection = 'column';
    container.style.gap = '6px';
    container.style.alignItems = 'center';

    // Show current image preview
    const preview = document.createElement('div');
    preview.id = 'imagePreview';
    if (item.photo_url) {
      preview.innerHTML = `<img src="${item.photo_url}" alt="Equipment" style="width:50px;height:50px;object-fit:cover;border-radius:4px;border:1px solid var(--border);">`;
    } else {
      preview.innerHTML = `<div style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;background:var(--surface-2);border-radius:4px;border:1px solid var(--border);color:var(--text-3);font-size:20px;">📷</div>`;
    }

    // File input
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.id = 'equipmentImageInput';
    fileInput.accept = 'image/*';
    fileInput.style.display = 'none';
    fileInput.dataset.field = 'equipmentImage';

    // Upload button
    const uploadBtn = document.createElement('button');
    uploadBtn.type = 'button';
    uploadBtn.textContent = 'Upload';
    uploadBtn.style.cssText = `
      padding:4px 10px;font-size:11px;
      background:var(--accent);color:#fff;border:1px solid var(--accent);
      border-radius:4px;cursor:pointer;font-weight:500;
    `;
    uploadBtn.onclick = (e) => {
      e.preventDefault();
      fileInput.click();
    };

    // Handle file selection
    fileInput.onchange = function() {
      if (this.files && this.files[0]) {
        const file = this.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
          preview.innerHTML = `<img src="${e.target.result}" alt="Equipment" style="width:50px;height:50px;object-fit:cover;border-radius:4px;border:1px solid var(--border);">`;
        };
        reader.readAsDataURL(file);
      }
    };

    container.appendChild(preview);
    container.appendChild(uploadBtn);
    container.appendChild(fileInput);
    imageCell.appendChild(container);
  }

  // All other fields (shifted by 1 due to new image column)
  const fields = [
    [0, 'equipmentID',       'text',     item.equipment_id,    true],
    [2, 'equipmentName',     'text',     item.equipment_name,  false],
    [3, 'serialNumber',      'text',     item.serial_number,   false],
    [4, 'internalSN',        'text',     item.internal_sn,     false],
    [5, 'accountablePerson', 'text',     item.account_person,  false],
    [6, 'totalQty',          'number',   item.total_qty,       borrowed],
    [7, 'workingQty',        'number',   item.working_qty,     borrowed],
    [8, 'notWorkingQty',     'number',   item.not_working_qty, borrowed],
    [9, 'description',       'textarea', item.description,     false],
    // cell 10 is the history button — leave intact
  ];

  fields.forEach(([cellIdx, fieldName, type, value, dis]) => {
    const cell = row.cells[cellIdx];
    if (!cell) return;
    cell.innerHTML = '';
    cell.style.verticalAlign = 'top';
    cell.style.padding       = '6px 8px';
    cell.appendChild(makeInlineInput(fieldName, type, value, dis));
  });

  row.style.background = 'var(--accent-soft)';
  removeStickyBar();
  document.body.appendChild(createStickyActionBar(item));
}

function cancelInlineEdit() {
  removeStickyBar();
  if (!editingRow) return;
  editingRow.innerHTML        = originalRowHTML;
  editingRow.style.background = '';
  const histBtn = editingRow.querySelector('.hist-btn');
  if (histBtn && currentEditItem) {
    histBtn.onclick = () => openHistoryPopover(histBtn, currentEditItem.equipment_id, currentEditItem.equipment_name);
  }
  editingRow = null; originalRowHTML = ''; currentEditItem = null;
  loadInventory();
}

// FIX 1: saveInlineEdit — removed dataType:'json' so AJAX error handler
// won't fire on non-JSON responses; parse manually + call saveEquipmentLog
function saveInlineEdit() {
  if (!editingRow || !currentEditItem) return;

  function val(fieldName) {
    const el = editingRow.querySelector(`[data-field="${fieldName}"]`);
    return el ? el.value.trim() : '';
  }

  const equipmentID       = currentEditItem.equipment_id;
  const equipmentName     = val('equipmentName');
  const serialNumber      = val('serialNumber');
  const internalSN        = val('internalSN');
  const accountablePerson = val('accountablePerson');
  const totalQty          = val('totalQty')      || String(currentEditItem.total_qty);
  const workingQty        = val('workingQty')    || String(currentEditItem.working_qty);
  const notWorkingQty     = val('notWorkingQty') || String(currentEditItem.not_working_qty);
  const description       = val('description');

  if (!equipmentName || !accountablePerson) {
    alert('Equipment Name and Accountable Person are required.');
    return;
  }

  const saveBtn = document.getElementById('inlineEditSaveBtn');
  if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }

  // Create FormData to handle file uploads
  const formData = new FormData();
  formData.append('equipmentID', equipmentID);
  formData.append('equipmentName', equipmentName);
  formData.append('serialNumber', serialNumber);
  formData.append('internalSN', internalSN);
  formData.append('totalQty', totalQty);
  formData.append('workingQty', workingQty);
  formData.append('notWorkingQty', notWorkingQty);
  formData.append('description', description);
  formData.append('accountablePerson', accountablePerson);

  // Add image file if selected
  const imageInput = document.getElementById('equipmentImageInput');
  if (imageInput && imageInput.files && imageInput.files[0]) {
    formData.append('equipment_image', imageInput.files[0]);
  }

  $.ajax({
    url: 'edit_equipment.php', method: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    success: function(rawRes) {
      let res;
      try { res = typeof rawRes === 'string' ? JSON.parse(rawRes) : rawRes; }
      catch(e) { res = { success: false, message: 'Invalid server response' }; }

      if (res.success) {
        // ── Record edit to history log ──
        saveEquipmentLog({
          equipment_id:    equipmentID,
          equipment_name:  equipmentName,
          total_qty:       totalQty,
          working_qty:     workingQty,
          not_working_qty: notWorkingQty,
          account_person:  accountablePerson
        }, 'Edited');

        removeStickyBar();
        editingRow = null; originalRowHTML = ''; currentEditItem = null;
        selectedRow = null; selectedItemData = null;
        loadInventory();
        loadInventoryPreview();
      } else {
        alert('Error saving: ' + (res.message || 'Unknown error'));
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save changes'; }
      }
    },
    error: function(xhr) {
      console.error('edit_equipment.php error:', xhr.status, xhr.responseText);
      alert('Save failed. Check console for details.');
      if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save changes'; }
    }
  });
}

// ════════════════════════════════════════════════════════════════
// DOCUMENT READY
// ════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {

  // ── ADD button ──
  document.getElementById('addEquipmentBtn').onclick = () => {
    selectedItemData = null; canEditQty = true;
    ['equipmentID','equipmentName','serialNumber','internalSN',
     'accountablePerson','totalQty','workingQty','notWorkingQty','description']
      .forEach(id => { document.getElementById(id).value = ''; });
    ['equipmentID','totalQty','workingQty','notWorkingQty']
      .forEach(id => { document.getElementById(id).disabled = false; });
    openAddEquipmentModal();
  };

  window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('addEquipmentModal')) closeAddEquipmentModal();
  });

  // ── EDIT button (null-guard so missing button won't crash DOMContentLoaded) ──
  const editBtn = document.getElementById('editEquipmentBtn');
  if (editBtn) {
    editBtn.onclick = () => {
      if (!selectedItemData || !selectedRow) {
        alert('Please click on an equipment row first to select it.');
        return;
      }
      if (editingRow === selectedRow) return;
      enterInlineEdit(selectedRow, selectedItemData);
    };
  }

  // FIX 2: SUBMIT ADD MODAL — after success, call saveEquipmentLog + loadInventory
  document.getElementById('submitEquipmentBtn').onclick = function(e) {
    e.preventDefault();
    const equipmentID       = document.getElementById('equipmentID').value.trim();
    const equipmentName     = document.getElementById('equipmentName').value.trim();
    const serialNumber      = document.getElementById('serialNumber').value.trim();
    const internalSN        = document.getElementById('internalSN').value.trim();
    const totalQty          = document.getElementById('totalQty').value.trim();
    const workingQty        = document.getElementById('workingQty').value.trim();
    const notWorkingQty     = document.getElementById('notWorkingQty').value.trim();
    const description       = document.getElementById('description').value.trim();
    const accountablePerson = document.getElementById('accountablePerson').value.trim();

    if (!equipmentID || !equipmentName || !totalQty || !workingQty || !notWorkingQty || !accountablePerson) {
      alert('Please fill in all required fields.'); return;
    }
    if (currentEquipmentIDs.has(equipmentID)) {
      alert('Equipment ID already exists. Please use a unique ID.'); return;
    }

    $.ajax({
      url: 'add_equipment.php', method: 'POST',
      data: { equipmentID, equipmentName, serialNumber, internalSN,
              totalQty, workingQty, notWorkingQty, description, accountablePerson },
      // FIX: removed dataType:'json' to avoid false error triggers
      success: function(rawData) {
        let data;
        try { data = typeof rawData === 'string' ? JSON.parse(rawData) : rawData; }
        catch(e) { data = { success: false, message: 'Invalid response' }; }

        if (data.success) {
          alert('Equipment added successfully!');
          closeAddEquipmentModal();

          // ── Record to history log with PH timestamp ──
          saveEquipmentLog({
            equipment_id:    equipmentID,
            equipment_name:  equipmentName,
            total_qty:       totalQty,
            working_qty:     workingQty,
            not_working_qty: notWorkingQty,
            account_person:  accountablePerson
          }, 'Added');

          selectedRow = null; selectedItemData = null;
          ['equipmentID','equipmentName','serialNumber','internalSN','totalQty',
           'workingQty','notWorkingQty','description','accountablePerson']
            .forEach(id => { document.getElementById(id).value = ''; });

          // FIX 3: reload inventory immediately so new equipment shows without page refresh
          loadInventory();
          loadInventoryPreview();
        } else {
          alert('Error: ' + (data.message || 'Unknown error'));
        }
      },
      error: function(xhr) {
        console.error('add_equipment.php error:', xhr.status, xhr.responseText);
        alert('Add failed. Check console for details.');
      }
    });
  };

  // ── DELETE ──
  document.getElementById('deleteEquipmentBtn').onclick = function() {
    if (!selectedItemData) { alert('Please select an equipment item to delete.'); return; }
    fetch('fetch_equipment.php')
      .then(r => r.json())
      .then(data => {
        const eq = data.find(e => e.equipment_id === selectedItemData.equipment_id);
        if (!eq) { alert('Equipment not found.'); return; }
        if (parseInt(eq.available) !== parseInt(eq.working_qty)) {
          alert('This equipment is currently borrowed. It cannot be deleted.'); return;
        }
        if (confirm('Are you sure you want to delete this equipment?')) {
          $.ajax({
            url: 'delete_equipment.php', method: 'POST',
            data: { equipmentID: selectedItemData.equipment_id },
            success: function(res) {
              if (res.success) {
                alert('Equipment deleted successfully!');
                loadInventory(); loadInventoryPreview();
                selectedRow = null; selectedItemData = null;
              } else { alert('Error: ' + res.message); }
            }
          });
        }
      })
      .catch(() => alert('Failed to fetch equipment data.'));
  };

  // ── EXPORT / IMPORT ──
  document.getElementById('downloadExcelBtn').onclick = () => { window.location.href = 'export_equipment_excel.php'; };
  document.getElementById('downloadCsvBtn').onclick   = () => { window.location.href = 'export_equipment_csv.php'; };
  $('#uploadExcelBtn').on('click', function() { $('#uploadExcelInput').click(); });

  // ── IMPORT: step 1 — preview ──
  let _pendingImportFile = null;

  document.getElementById('uploadExcelInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;

    // Store reference BEFORE clearing the input
    _pendingImportFile = file;
    this.value = '';

    const formData = new FormData();
    formData.append('excelFile', file);
    formData.append('preview', '1');

    const importBtn = document.getElementById('uploadExcelBtn');
    importBtn.disabled    = true;
    importBtn.textContent = 'Reading…';

    fetch('import_equipment_excel.php', { method: 'POST', body: formData })
      .then(r => {
        // Always get text first so we can inspect the raw response if JSON parse fails
        return r.text().then(text => {
          try {
            return JSON.parse(text);
          } catch (e) {
            // PHP returned non-JSON — surface the raw output for debugging
            throw new Error('Server returned non-JSON:\n\n' + text.substring(0, 500));
          }
        });
      })
      .then(res => {
        importBtn.disabled    = false;
        importBtn.textContent = '↑ Import';
        if (!res.success) { alert('Preview failed: ' + res.message); return; }
        openImportPreview(res, file);
      })
      .catch(err => {
        importBtn.disabled    = false;
        importBtn.textContent = '↑ Import';
        console.error('Import preview error:', err);
        alert('Failed to read file:\n' + (err.message || err));
      });
  });

  function openImportPreview(res, file) {
    const modal = document.getElementById('importPreviewModal');
    const tbody = document.getElementById('importPreviewBody');
    const meta  = document.getElementById('importPreviewMeta');

    meta.textContent = `File: ${file.name} · ${res.rows.length} row(s) · ${res.new_count} new · ${res.dup_count} duplicate(s)`;

    tbody.innerHTML = '';
    res.rows.forEach(row => {
      const isDup  = row.status === 'duplicate';
      const badge  = isDup
        ? `<span style="font-size:10px;font-weight:600;color:var(--warn);background:var(--warn-soft);border:1px solid #f5c98a;padding:2px 7px;border-radius:10px;text-transform:uppercase;">Duplicate</span>`
        : `<span style="font-size:10px;font-weight:600;color:var(--accent);background:var(--accent-soft);border:1px solid #a8d5b5;padding:2px 7px;border-radius:10px;text-transform:uppercase;">New</span>`;
      const tr = document.createElement('tr');
      tr.style.background = isDup ? 'rgba(230,126,34,.06)' : '';
      tr.innerHTML = `
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);">${badge}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);font-family:var(--mono);font-size:11.5px;">${escHtml(row.equipment_id)}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);font-weight:500;">${escHtml(row.equipment_name)}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);color:var(--text-3);">${escHtml(row.serial_number || '—')}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);color:var(--text-3);">${escHtml(row.internal_sn || '—')}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);">${escHtml(row.account_person || '—')}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);text-align:center;">${row.total_qty}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);text-align:center;color:var(--accent);">${row.working_qty}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);text-align:center;color:var(--danger);">${row.not_working_qty}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);color:var(--text-3);font-size:12px;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
            title="${escHtml(row.description || '')}">${escHtml(row.description || '—')}</td>`;
      tbody.appendChild(tr);
    });

    const confirmBtn = document.getElementById('confirmImportBtn');
    confirmBtn.disabled = false;  // Always enable — we now update duplicates
    confirmBtn.title    = '';

    modal.style.display = 'flex';
  }

  window.closeImportPreview = function() {
    document.getElementById('importPreviewModal').style.display = 'none';
    _pendingImportFile = null;
  };

  // ── IMPORT: step 2 — confirm ──
  document.getElementById('confirmImportBtn').addEventListener('click', function() {
    if (!_pendingImportFile) return;
    const btn  = this;
    const file = _pendingImportFile;   // capture before closeImportPreview nulls it
    btn.disabled    = true;
    btn.textContent = 'Importing…';

    const formData = new FormData();
    formData.append('excelFile', file);
    // No 'preview' flag → triggers insert mode

    fetch('import_equipment_excel.php', { method: 'POST', body: formData })
      .then(r => r.text().then(text => {
        try { return JSON.parse(text); }
        catch (e) { throw new Error('Server returned non-JSON:\n\n' + text.substring(0, 500)); }
      }))
      .then(res => {
        btn.disabled    = false;
        btn.textContent = 'Confirm Import';
        closeImportPreview();
        if (res.success) {
          loadInventory();
          loadInventoryPreview();
          const banner = document.createElement('div');
          banner.textContent = res.message;
          banner.style.cssText = `
            position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--accent);color:#fff;font-family:var(--font);font-size:13px;
            font-weight:500;padding:10px 20px;border-radius:var(--radius);
            box-shadow:var(--shadow-md);z-index:9999;white-space:nowrap;
            animation:fadeUp .2s ease both;`;
          document.body.appendChild(banner);
          setTimeout(() => banner.remove(), 3500);
        } else {
          alert('Import failed: ' + res.message);
        }
      })
      .catch(err => {
        btn.disabled    = false;
        btn.textContent = 'Confirm Import';
        console.error('Import error:', err);
        alert('Import error:\n' + (err.message || err));
      });
  });

  // ── FILTER / SEARCH ──
  const categorySelect = document.getElementById('categorySelect');
  const searchInput    = document.getElementById('searchInput');

  function filterInventory() {
    const fv  = categorySelect.value.toLowerCase();
    const kw  = searchInput.value.toLowerCase();
    const map = { equipment:'e', measuring:'m', chemicals:'c', books:'b' };
    document.getElementById('equipmentList').querySelectorAll('tr').forEach(row => {
      if (!row.cells || row.cells.length < 8) return;
      const id   = row.cells[0].textContent.trim();
      const w    = parseInt(row.cells[6]?.textContent.trim(), 10);
      const nw   = parseInt(row.cells[7]?.textContent.trim(), 10);
      const text = row.textContent.toLowerCase();
      let show   = true;
      if (fv === 'working')         show = w  > 0;
      else if (fv === 'notworking') show = nw > 0;
      else if (fv !== 'all')        show = id.charAt(0).toLowerCase() === map[fv];
      row.style.display = (show && text.includes(kw)) ? '' : 'none';
    });
  }

  categorySelect.addEventListener('change', filterInventory);
  searchInput.addEventListener('input', filterInventory);

  loadInventory();
  refreshHistTabCount();
});

// ════════════════════════════════════════════════════════════════
// LOAD INVENTORY — click row = enter inline edit directly
// ════════════════════════════════════════════════════════════════
function loadInventory() {
  if (editingRow) {
    editingRow.innerHTML    = originalRowHTML;
    editingRow.style.background = '';
    editingRow = null; originalRowHTML = ''; currentEditItem = null;
  }
  closeHistoryPopover();

  const container = document.getElementById('equipmentList');
  if (!container) return;
  container.innerHTML = '';

  $.ajax({
    url: 'get_equipment.php', method: 'GET',
    success: function(data) {
      const items = typeof data === 'string' ? JSON.parse(data) : data;
      currentEquipmentIDs.clear();

      items.forEach(item => {
        currentEquipmentIDs.add(item.equipment_id);

        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.title = 'Click to select, then click Edit — or double-click to edit directly';
        row.innerHTML = `
          <td>${item.equipment_id}</td>
          <td style="text-align:center;">
            ${item.photo_url
              ? `<img src="${item.photo_url}" alt="Equipment" style="width:60px;height:60px;object-fit:cover;border-radius:4px;border:1px solid var(--border);">`
              : `<div style="width:60px;height:60px;display:flex;align-items:center;justify-content:center;background:var(--surface-2);border-radius:4px;border:1px solid var(--border);color:var(--text-3);font-size:24px;">📷</div>`
            }
          </td>
          <td>${item.equipment_name}</td>
          <td>${item.serial_number  ?? ''}</td>
          <td>${item.internal_sn   ?? ''}</td>
          <td>${item.account_person ?? ''}</td>
          <td>${item.total_qty     ?? 0}</td>
          <td>${item.working_qty   ?? 0}</td>
          <td>${item.not_working_qty ?? 0}</td>
          <td>${item.description   ?? ''}</td>
          <td class="hist-cell">
            <button class="hist-btn" title="View history"
              onclick="event.stopPropagation(); openHistoryPopover(this,
                '${item.equipment_id.replace(/'/g,"\\'")}',
                '${(item.equipment_name ?? '').replace(/'/g,"\\'")}')">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
              </svg>
            </button>
          </td>`;

        // Single click → select row (highlight)
        row.addEventListener('click', function(e) {
          if (e.target.closest('.hist-btn')) return;
          if (editingRow === row) return;
          if (editingRow && editingRow !== row) { cancelInlineEdit(); return; }

          if (selectedRow && selectedRow !== row) selectedRow.classList.remove('selected');
          row.classList.add('selected');
          selectedRow = row;
          selectedItemData = item;
        });

        // Double click → enter inline edit immediately
        row.addEventListener('dblclick', function(e) {
          if (e.target.closest('.hist-btn')) return;
          if (editingRow && editingRow !== row) cancelInlineEdit();
          enterInlineEdit(row, item);
        });

        container.appendChild(row);
      });

      // Re-apply current filter
      const fv  = document.getElementById('categorySelect')?.value.toLowerCase() || 'all';
      const kw  = document.getElementById('searchInput')?.value.toLowerCase() || '';
      const map = { equipment:'e', measuring:'m', chemicals:'c', books:'b' };
      container.querySelectorAll('tr').forEach(r => {
        if (!r.cells || r.cells.length < 9) return;
        const id   = r.cells[0].textContent.trim();
        const w    = parseInt(r.cells[7]?.textContent.trim(), 10);
        const nw   = parseInt(r.cells[8]?.textContent.trim(), 10);
        const text = r.textContent.toLowerCase();
        let show   = true;
        if (fv === 'working')         show = w  > 0;
        else if (fv === 'notworking') show = nw > 0;
        else if (fv !== 'all')        show = id.charAt(0).toLowerCase() === map[fv];
        r.style.display = (show && text.includes(kw)) ? '' : 'none';
      });
    },
    error: function() {
      container.innerHTML = '<tr><td colspan="11">Error loading inventory</td></tr>';
    }
  });
}

// ════════════════════════════════════════════════════════════════
// INVENTORY COUNT
// ════════════════════════════════════════════════════════════════
function updateInventoryCount() {
  $.ajax({
    url: 'get_equipment.php', method: 'GET',
    success: function(data) {
      const items = typeof data === 'string' ? JSON.parse(data) : data;
      const el = document.getElementById('inventoryCount');
      if (el) el.textContent = items.length;
    },
    error: function() {
      const el = document.getElementById('inventoryCount');
      if (el) el.textContent = 0;
    }
  });
}
document.addEventListener('DOMContentLoaded', () => { updateInventoryCount(); });
document.addEventListener('DOMContentLoaded', () => { initCalendar(); });

// ════════════════════════════════════════════════════════════════
// LOGOUT / PAGE SHOW
// ════════════════════════════════════════════════════════════════
function logout() {
  window.location.href = 'logout.php';
}
window.addEventListener('pageshow', function(e) { if (e.persisted) window.location.reload(); });

// ════════════════════════════════════════════════════════════════
// NAVIGATION
// FIX 4: Inventory ALWAYS asks for password — no sessionStorage caching
// ════════════════════════════════════════════════════════════════
const sectionMap = {
  Dashboard:         'manageSection',
  Schedule:          'scheduleSection',
  'Borrow Requests': 'queueSection',
  Inventory:         'inventorySection',
  Reports:           'reportsSection'
};

function navigateToSection(sectionName) {
  Object.values(sectionMap).forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
  if (!sectionMap[sectionName]) sectionName = 'Dashboard';
  const el = document.getElementById(sectionMap[sectionName]);
  if (el) el.style.display = 'block';

  if (sectionName === 'Schedule') {
    if (typeof calendar !== 'undefined' && calendar) calendar.render();
    else if (typeof initCalendar === 'function') initCalendar();
    initScheduleCharts();
  } else if (sectionName === 'Borrow Requests') {
    loadBorrowRequestsAndUpdateCount();
  } else if (sectionName === 'Inventory') {
    loadInventory();
    refreshHistTabCount();
  } else if (sectionName === 'Reports') {
    loadReports();
  }
  setActiveSidebarItem(sectionName);
}

function setActiveSidebarItem(sectionName) {
  const target = sectionName.trim().toLowerCase();
  document.querySelectorAll('.sidebar .nav-item[data-section]').forEach(item => {
    item.classList.toggle('active', item.getAttribute('data-section')?.trim().toLowerCase() === target);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  navigateToSection('Dashboard');
  // Initialize sidebar borrow queue badge
  fetch('fetch_borrow_requests.php')
    .then(r => r.json())
    .then(json => {
      const badge = document.getElementById('borrowQueueCount');
      if (badge) badge.textContent = (json.success && json.data.length) ? json.data.length : '0';
    })
    .catch(() => {});
});

// FIX 4: ALWAYS open password modal for Inventory — no cache check
$('.nav-item').on('click', function() {
  const section = $(this).data('section');
  if (section === 'Inventory') {
    openPasswordModal(); // always ask, every single time
  } else if (section) {
    navigateToSection(section);
  }
});

document.getElementById('inventoryNav').addEventListener('click', e => {
  e.preventDefault();
  openPasswordModal(); // always ask, every single time
});

// ════════════════════════════════════════════════════════════════
// DASHBOARD SLIDER
// ════════════════════════════════════════════════════════════════
$(document).ready(function() {
  const $wrapper  = $('.slider-wrapper');
  const $slides   = $('.slide');
  const $dotsWrap = $('.dots-container');
  const count     = $slides.length;
  let   current   = 0, interval;
  const scheduleIndex = $slides.index($slides.filter('[data-target="Schedule"]'));

  for (let i = 0; i < count; i++)
    $dotsWrap.append($('<span>').addClass('dot' + (i === 0 ? ' active' : '')).attr('data-index', i));
  const $dots = $('.dot');

  function goTo(i) {
    if (i < 0) i = count - 1; if (i >= count) i = 0; current = i;
    $wrapper.css('transform', `translateX(-${i * 100}%)`);
    $dots.removeClass('active').eq(i).addClass('active');
    if (i === scheduleIndex && typeof miniCalendar !== 'undefined')
      setTimeout(() => { miniCalendar.render(); miniCalendar.updateSize(); }, 50);
  }
  function start() { interval = setInterval(() => goTo(current + 1), 5000); }
  function stop()  { clearInterval(interval); }
  $dots.on('click', function() { stop(); goTo($(this).data('index')); start(); });
  $slides.on('click', function() {
    const target = $(this).data('target'); if (!target) return; stop();
    if (target === 'Inventory') {
      openPasswordModal(); // always ask from dashboard slide too
    } else {
      navigateToSection(target); setActiveSidebarItem(target);
    }
  });
  goTo(0); start();
});

// ════════════════════════════════════════════════════════════════
// DATE / TODAY STATS
// ════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
  const today = new Date();
  // Use local date (not UTC) — toISOString() returns UTC which can be wrong date in PH (UTC+8)
  const pad   = n => String(n).padStart(2, '0');
  const localDate = `${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;

  const dateEl = document.getElementById('currentDate');
  if (dateEl) dateEl.textContent = today.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });

  // One fetch — populates BOTH the stat cards AND the Schedule slider stats
  fetch(`fetch_borrow_stats.php?date=${localDate}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      const s   = data.stats;
      const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val ?? 0; };

      // Stat cards (top of dashboard)
      set('totalRequests',    s.total    || 0);
      set('acceptedRequests', s.accepted || 0);
      set('rejectedRequests', s.rejected || 0);
      set('pendingRequests',  s.pending  || 0);

      // Schedule slider panel
      set('totalReq2', s.total    || 0);
      set('accReq2',   s.accepted || 0);
      set('rejReq2',   s.rejected || 0);
      set('penReq2',   s.pending  || 0);
    })
    .catch(err => console.error('fetch_borrow_stats error:', err));
});

// ════════════════════════════════════════════════════════════════
// ════════════════════════════════════════════════════════════════
// FULL CALENDAR (Schedule section)
// ════════════════════════════════════════════════════════════════
let calendar;
let chartsInitialized = false;
let trendChart = null;
let trendFetchController = null;

function initCalendar() {
  const calendarEl = document.getElementById('calendar');
  if (!calendarEl) return;
  if (calendar) { calendar.render(); refreshCalendarStats(); return; }
  calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,dayGridYear' },
    datesSet: function() { refreshCalendarStats('#calendar'); }
  });
  calendar.render();
  refreshCalendarStats();
}

function refreshCalendarStats(containerSelector = '#calendar') {
  const container = document.querySelector(containerSelector);
  if (!container) return;
  container.querySelectorAll('.custom-stats').forEach(el => el.remove());
  container.querySelectorAll('.fc-daygrid-day').forEach(dayCell => {
    const dateStr = dayCell.getAttribute('data-date');
    if (!dateStr) return;
    fetch(`fetch_borrow_stats.php?date=${dateStr}`).then(r => r.json()).then(data => {
      if (!data.success) return;
      const s = data.stats;
      if (!s.total && !s.accepted && !s.rejected && !s.pending) return;
      const content = `<div class="custom-stats" style="font-size:.75em;margin-top:5px;line-height:1.2;">
        <div style="font-weight:bold;margin-bottom:4px;">Total: ${s.total}</div>
        <div style="color:green;">Accepted: ${s.accepted}</div>
        <div style="color:red;">Rejected: ${s.rejected}</div>
        <div style="color:orange;">Pending: ${s.pending}</div></div>`;
      const frame = dayCell.querySelector('.fc-daygrid-day-frame');
      if (frame && !frame.querySelector('.custom-stats')) frame.insertAdjacentHTML('beforeend', content);
    });
  });
}

// Initialize charts when Schedule section is opened (canvas must be visible)
function initScheduleCharts() {
  if (chartsInitialized) return;
  fetch('fetch_stats.php').then(r => r.json()).then(data => {
    if (!data.success) return;
    chartsInitialized = true;
    const w = data.weekly, m = data.monthly;
    const curr  = new Date();
    const first = curr.getDate() - curr.getDay();
    const sun   = new Date(new Date(curr).setDate(first));
    const sat   = new Date(new Date(curr).setDate(first + 6));
    const fmt   = d => `${d.getMonth()+1}/${d.getDate()}`;

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val ?? 'N/A'; };
    set('weekLabel',        `${fmt(sun)} - ${fmt(sat)}`);
    set('monthLabel',       new Date().toLocaleString('default', { month:'long', year:'numeric' }));
    set('weeklyTotal',      w.total);
    set('weeklyAccepted',   w.accepted);
    set('weeklyRejected',   w.rejected);
    set('weeklyTopItem',    w.topItem);
    set('monthlyTotal',     m.total);
    set('monthlyAccepted',  m.accepted);
    set('monthlyRejected',  m.rejected);
    set('monthlyTopItem',   m.topItem);

    function pie(id, title, acc, rej) {
      const ctx = document.getElementById(id)?.getContext('2d');
      if (!ctx) return;
      new Chart(ctx, {
        type: 'doughnut',
        data: { labels:['Accepted','Rejected'], datasets:[{ data:[acc, rej], backgroundColor:['#4CAF50','#F44336'] }] },
        options: { responsive:true, plugins:{ title:{ display:true, text:title }, legend:{ position:'bottom' } } }
      });
    }
    pie('weeklyChart',  'Weekly Requests',  w.accepted, w.rejected);
    pie('monthlyChart', 'Monthly Requests', m.accepted, m.rejected);

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
  }).catch(err => console.error('fetch_stats error:', err));
}

function showTrendMessage(msg) {
  if (trendChart) { trendChart.destroy(); trendChart = null; }
  const canvas = document.getElementById('equipmentTrendChart');
  if (!canvas) return;
  const wrapper = canvas.parentElement;
  wrapper.style.position = 'relative';
  wrapper.querySelectorAll('.trend-msg').forEach(el => el.remove());
  const p = document.createElement('p');
  p.className = 'trend-msg';
  p.textContent = msg;
  p.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);margin:0;font-size:13px;color:var(--text-3);pointer-events:none;';
  wrapper.appendChild(p);
}

function loadTrendChart() {
  if (trendFetchController) trendFetchController.abort();
  trendFetchController = new AbortController();

  const from   = document.getElementById('trendFrom')?.value;
  const to     = document.getElementById('trendTo')?.value;
  const status = document.getElementById('trendStatus')?.value ?? 'All';

  if (!from || !to) return;

  if (from > to) {
    showTrendMessage('"From" date must not be after "To" date');
    return;
  }

  fetch(`fetch_daily_borrow_trend.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&status=${encodeURIComponent(status)}`, { signal: trendFetchController.signal })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        showTrendMessage(data.message || 'Failed to load chart data');
        return;
      }

      if (!data.labels.length) {
        showTrendMessage('No data for selected range');
        return;
      }

      const canvas = document.getElementById('equipmentTrendChart');
      if (canvas) canvas.parentElement.querySelectorAll('.trend-msg').forEach(el => el.remove());

      if (trendChart) { trendChart.destroy(); trendChart = null; }

      const ctx = document.getElementById('equipmentTrendChart')?.getContext('2d');
      if (!ctx) return;

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
            fill: true
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
    .catch(err => {
      if (err.name === 'AbortError') return;
      console.error('loadTrendChart error:', err);
    });
}


// ════════════════════════════════════════════════════════════════
// INVENTORY PREVIEW (dashboard)
// ════════════════════════════════════════════════════════════════
function loadInventoryPreview() {
  $.ajax({ url: 'get_equipment.php', method: 'GET', success: function(data) {
    const items = typeof data === 'string' ? JSON.parse(data) : data;
    const body  = document.getElementById('inventoryPreviewBody');
    if (!body) return;
    body.innerHTML = '';
    items.slice(0, 10).forEach(item => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="centered-cell">${item.equipment_id}</td>
        <td class="centered-cell">${item.equipment_name}</td>
        <td class="centered-cell">${item.serial_number   ?? ''}</td>
        <td class="centered-cell">${item.internal_sn     ?? ''}</td>
        <td class="centered-cell">${item.account_person  ?? ''}</td>
        <td class="centered-cell">${item.total_qty       ?? 0}</td>
        <td class="centered-cell">${item.working_qty     ?? 0}</td>
        <td class="centered-cell">${item.not_working_qty ?? 0}</td>
        <td class="centered-cell">${item.description     ?? ''}</td>`;
      body.appendChild(row);
    });
    const countEl = document.getElementById('inventoryCount');
    if (countEl) countEl.textContent = items.length;
  }});
}
document.addEventListener('DOMContentLoaded', () => { loadInventoryPreview(); });

// ════════════════════════════════════════════════════════════════
// BORROW REQUESTS
// ════════════════════════════════════════════════════════════════
// DASHBOARD — populate slider panels on load
// ════════════════════════════════════════════════════════════════
function updateBorrowRequestsOverview() {
  fetch('fetch_borrow_requests.php').then(r => r.json()).then(json => {
    if (!json.success) return;

    // Count badge in the Borrow Requests slide
    const countEl = document.getElementById('totalRequestCount');
    if (countEl) countEl.textContent = json.data.length;

    // Borrow queue list inside the dashboard slide (id="borrowQueueDashboard")
    const container = document.getElementById('borrowQueueDashboard');
    if (container) {
      container.innerHTML = '';
      if (!json.data.length) {
        container.innerHTML = '<p class="click-message">No pending requests at the moment.</p>';
        return;
      }
      json.data.slice(0, 5).forEach(entry => {
        const req = entry.borrowRequest;
        const div = document.createElement('div');
        div.className = 'borrow-request';
        div.style.cssText = 'padding:10px 14px;margin-bottom:6px;cursor:default;';
        div.innerHTML = `
          <div style="font-weight:500;font-size:13px;">${escHtml(req.borrower_name)}</div>
          <div style="font-size:11px;color:var(--text-3);">Guest #${escHtml(req.guest_number)} · ${escHtml(req.student_id || '—')}</div>`;
        container.appendChild(div);
      });
      if (json.data.length > 5) {
        const more = document.createElement('p');
        more.className = 'click-message';
        more.textContent = `+${json.data.length - 5} more — click to view all`;
        container.appendChild(more);
      }
    }
  }).catch(err => console.error('Failed to load borrow requests overview:', err));
}
document.addEventListener('DOMContentLoaded', () => {
  updateBorrowRequestsOverview();
});

document.addEventListener('DOMContentLoaded', () => {
  // Populate Reports slide (reportCount + recentReportsList)
  fetch('fetch_borrow_reports.php').then(r => r.json()).then(json => {
    const countEl = document.getElementById('reportCount');
    const listEl  = document.getElementById('recentReportsList');
    if (!json.success) {
      if (listEl) listEl.innerHTML = '<li style="color:var(--text-3);">Failed to load.</li>';
      return;
    }
    if (countEl) countEl.textContent = json.data.length;
    if (listEl) {
      listEl.innerHTML = '';
      if (!json.data.length) {
        listEl.innerHTML = '<li style="color:var(--text-3);">No reports yet.</li>';
        return;
      }
      json.data.slice(0, 5).forEach(entry => {
        const req        = entry.borrowRequest;
        const isAccepted = req.status === 'Accepted';
        const li         = document.createElement('li');
        li.innerHTML = `
          <span style="font-weight:500;">${escHtml(req.borrower_name)}</span>
          <span style="float:right;font-size:11px;font-weight:600;
            color:${isAccepted ? 'var(--accent)' : 'var(--danger)'};">
            ${escHtml(req.status)}
          </span>`;
        listEl.appendChild(li);
      });
    }
  }).catch(() => {
    const listEl = document.getElementById('recentReportsList');
    if (listEl) listEl.innerHTML = '<li style="color:var(--text-3);">Error loading reports.</li>';
  });
});



// ════════════════════════════════════════════════════════════════
// BORROW QUEUE
// ════════════════════════════════════════════════════════════════
function loadBorrowRequests() {
  const startDate = document.getElementById('startDate')?.value;
  const endDate   = document.getElementById('endDate')?.value;
  let url = 'fetch_borrow_requests.php';
  const params = new URLSearchParams();
  if (startDate) params.append('startDate', startDate);
  if (endDate)   params.append('endDate',   endDate);
  if (params.toString()) url += '?' + params;

  fetch(url).then(r=>r.json()).then(json => {
    if (!json.success) return;
    const container = document.getElementById('borrowQueue');
    container.innerHTML = '';
    if (!json.data.length) {
      container.innerHTML = `<div style="text-align:left;color:#555;font-style:italic;margin-top:20px;font-size:1.1em;">No borrow requests are available at this time.</div>`;
      return;
    }
    json.data.forEach(entry => {
      const request = entry.borrowRequest, equipmentList = entry.equipmentList;
      borrowRequestMap[request.id] = { ...request, equipment: equipmentList };
      const div = document.createElement('div');
      div.className = 'borrow-request';
      div.innerHTML = `
        <strong>Guest Number:</strong> ${request.guest_number}<br/>
        <strong>Borrower's Name:</strong> ${request.borrower_name}<br/>
        <strong>Student ID:</strong> ${request.student_id}<br/>
        <button class="view-request-btn" data-id="${request.id}">View Request</button>
        <div class="action-buttons" style="margin-top:10px;">
          <button class="accept-btn" data-id="${request.id}">Accept</button>
          <button class="reject-btn" data-id="${request.id}">Reject</button>
        </div>
        <div id="borrowerFormSection-${request.id}" class="borrower-form-section" style="display:none;margin-top:20px;">
          <button onclick="closeBorrowerForm(${request.id})" style="float:right;margin-bottom:10px;background-color:#c62828;color:white;border:none;padding:5px 10px;border-radius:5px;cursor:pointer;">Close</button>
          <div class="form-container">
            <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;">
              <tr><td colspan="2" style="text-align:center;">
                <h4>EULOGIO "AMANG" RODRIGUEZ INSTITUTE OF SCIENCE AND TECHNOLOGY</h4>
                <h4>COLLEGE OF ARTS AND SCIENCES</h4><h4>APPLIED PHYSICS DEPARTMENT</h4>
                <h3>Equipment-borrowing Form</h3>
              </td></tr>
              <tr><td><strong>Guest Login Number:</strong> ${request.guest_number}</td><td><strong>Date:</strong> ${formatDateToDDMMYYYY(request.date)}</td></tr>
              <tr><td><strong>Borrower's Name:</strong> ${request.borrower_name}</td><td><strong>Instructor's Name:</strong> ${request.instructor_name}</td></tr>
              <tr><td><strong>Student ID:</strong> ${request.student_id}</td><td><strong>Subject Code:</strong> ${request.subject_code}</td></tr>
              <tr><td><strong>Date(s) of Usage:</strong> ${formatDateToDDMMYYYY(request.usage_date)}</td><td><strong>Room:</strong> ${request.room}</td></tr>
              <tr><td colspan="2" style="padding-top:15px;">
                <table style="width:100%;border-collapse:collapse;" border="1">
                  <thead><tr style="text-align:center;"><th>Equipment / Material</th><th>Quantity</th><th>Available in the lab?</th><th>Returned on</th><th>Remarks</th></tr></thead>
                  <tbody id="equipmentListInForm-${request.id}"></tbody>
                </table>
              </td></tr>
              <tr><td colspan="2" style="padding-top:20px;">
                <strong>Borrower's Declaration of Commitment:</strong><br/>
                <em>"I will be accountable to any damage incurred in the equipment and will return the equipment promptly and in the same working condition it was borrowed."</em>
              </td></tr>
              <tr>
                <td style="padding-top:30px;"><p>Approved by:<br><br><div style="width:180px;height:60px;border:1.5px dashed #ccc;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;font-size:13px;margin-bottom:8px;background:#fafafa;">e-signature here</div>__________________________<br><em style="display:block;margin-top:4px;">Mr. Hiromi Rivas</em><em style="display:block;">Applied Physics Professor</em></p></td>
                <td style="text-align:right;padding-top:30px;"><p>_________________________________<br><em>Signature over Printed Name of Borrower</em></p></td>
              </tr>
            </table>
          </div>
        </div>`;
      container.appendChild(div);
    });
    attachActionHandlers();
  });
}

function clearFilters() {
  document.getElementById('startDate').value = '';
  document.getElementById('endDate').value   = '';
  loadBorrowRequests();
}

document.addEventListener('DOMContentLoaded', () => {
  fetch('process_rejected_requests.php').then(r=>r.json()).then(data => {
    if (data.success) { refreshCalendarStats(); loadBorrowRequestsAndUpdateCount(); }
  });
});

function attachActionHandlers() {
  document.querySelectorAll('.accept-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (confirm('Are you sure you want to ACCEPT this borrow request?'))
        addToReports(btn.dataset.id, 'Accepted').then(() => { refreshCalendarStats(); loadBorrowRequestsAndUpdateCount(); });
    });
  });
  document.querySelectorAll('.reject-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (confirm('Are you sure you want to REJECT this borrow request?'))
        addToReports(btn.dataset.id, 'Rejected').then(() => { refreshCalendarStats(); loadBorrowRequestsAndUpdateCount(); });
    });
  });
  document.querySelectorAll('.view-request-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id, data = borrowRequestMap[id]; if (!data) return;
      const formSection = document.getElementById(`borrowerFormSection-${id}`); if (!formSection) return;
      if (formSection.style.display === 'none' || !formSection.style.display) {
        formSection.style.display = 'block';
        const tbody = document.getElementById(`equipmentListInForm-${id}`);
        tbody.innerHTML = '';
        data.equipment.forEach(eq => {
          const row = document.createElement('tr');
          row.innerHTML = `<td>${eq.equipment_name}</td><td>${eq.quantity}</td><td>${eq.available}</td><td></td><td></td>`;
          tbody.appendChild(row);
        });
      } else { formSection.style.display = 'none'; }
    });
  });
}

function formatDateToDDMMYYYY(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr); if (isNaN(d)) return dateStr;
  return `${String(d.getDate()).padStart(2,'0')}-${String(d.getMonth()+1).padStart(2,'0')}-${d.getFullYear()}`;
}

const borrowRequestMap = {};


// ════════════════════════════════════════════════════════════════
// ACCEPT / REJECT — update status then reload
// ════════════════════════════════════════════════════════════════
function addToReports(id, status) {
  return fetch('update_borrow_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `id=${encodeURIComponent(id)}&status=${encodeURIComponent(status)}`
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success) console.warn('Status update failed:', res.message);
    return res;
  })
  .catch(err => console.error('addToReports error:', err));
}

// ════════════════════════════════════════════════════════════════
// RELOAD BORROW REQUESTS + UPDATE SIDEBAR BADGE COUNT
// ════════════════════════════════════════════════════════════════
function loadBorrowRequestsAndUpdateCount() {
  loadBorrowRequests();
  fetch('fetch_borrow_requests.php')
    .then(r => r.json())
    .then(json => {
      const badge = document.getElementById('borrowQueueCount');
      if (badge) badge.textContent = (json.success && json.data.length) ? json.data.length : '0';
    })
    .catch(() => {});
}

// ════════════════════════════════════════════════════════════════
// REPORTS — load accepted / rejected requests with return info editing
// ════════════════════════════════════════════════════════════════
function loadReports() {
  const container = document.getElementById('reportsList');
  if (!container) return;
  container.innerHTML = '<div style="color:var(--text-3);font-style:italic;padding:16px 0;">Loading reports…</div>';

  fetch('fetch_borrow_reports.php')
    .then(r => r.json())
    .then(json => {
      container.innerHTML = '';
      if (!json.success || !json.data.length) {
        container.innerHTML = '<div style="color:var(--text-3);font-style:italic;padding:16px 0;">No accepted or rejected requests yet.</div>';
        return;
      }
      json.data.forEach(entry => {
        const req    = entry.borrowRequest;
        const eqList = entry.equipmentList || [];
        const reqId  = req.id;

        const isAccepted  = req.status === 'Accepted';
        const statusColor = isAccepted ? 'var(--accent)'      : 'var(--danger)';
        const statusBg    = isAccepted ? 'var(--accent-soft)' : 'var(--danger-soft)';

        // Build editable equipment rows (only for Accepted)
        const eqRows = eqList.map((eq, idx) => {
          const returnedVal = eq.returned_on || '';
          const remarksVal  = eq.remarks     || '';
          if (isAccepted) {
            return `
            <tr data-eq-name="${escHtml(eq.equipment_name)}">
              <td style="padding:6px 10px;border-bottom:1px solid var(--border);">${escHtml(eq.equipment_name)}</td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border);text-align:center;">${eq.quantity}</td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border);text-align:center;">
                <input type="date" class="return-date-input" value="${escHtml(returnedVal)}"
                  style="font-family:var(--font);font-size:12px;padding:3px 6px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text-1);width:130px;">
              </td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border);">
                <input type="text" class="return-remarks-input" value="${escHtml(remarksVal)}" placeholder="e.g. Good condition"
                  style="font-family:var(--font);font-size:12px;padding:3px 6px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text-1);width:100%;box-sizing:border-box;">
              </td>
            </tr>`;
          } else {
            return `
            <tr>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border);">${escHtml(eq.equipment_name)}</td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border);text-align:center;">${eq.quantity}</td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border);text-align:center;">${returnedVal ? formatDateToDDMMYYYY(returnedVal) : '—'}</td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border);">${escHtml(remarksVal || '—')}</td>
            </tr>`;
          }
        }).join('');

        const card = document.createElement('div');
        card.className = 'report-entry';
        card.dataset.reqId = reqId;
        card.innerHTML = `
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
            <div>
              <span style="font-weight:600;font-size:14px;">${escHtml(req.borrower_name)}</span>
              <span style="color:var(--text-3);font-size:12px;margin-left:8px;">Guest #${escHtml(req.guest_number)}</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <span style="background:${statusBg};color:${statusColor};font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.05em;">${escHtml(req.status)}</span>
              <button class="downloadPdfBtn" data-req-id="${reqId}"
                style="font-family:var(--font);font-size:12px;padding:5px 12px;border-radius:var(--radius);cursor:pointer;">
                ⬇ PDF
              </button>
              ${isAccepted ? `<button class="saveReturnInfoBtn" data-req-id="${reqId}"
                style="font-family:var(--font);font-size:12px;padding:5px 12px;border-radius:var(--radius);cursor:pointer;">
                Save Return Info
              </button>` : ''}
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;font-size:13px;color:var(--text-2);margin-bottom:12px;">
            <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Student ID</span><br>${escHtml(req.student_id || '—')}</div>
            <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Instructor</span><br>${escHtml(req.instructor_name || '—')}</div>
            <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Subject Code</span><br>${escHtml(req.subject_code || '—')}</div>
            <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Room</span><br>${escHtml(req.room || '—')}</div>
            <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Usage Date</span><br>${formatDateToDDMMYYYY(req.usage_date)}</div>
            <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Request Date</span><br>${formatDateToDDMMYYYY(req.date)}</div>
          </div>
          ${eqRows.length ? `
          <table style="width:100%;border-collapse:collapse;font-size:12px;" class="report-eq-table">
            <thead>
              <tr style="background:var(--surface-2);">
                <th style="padding:6px 10px;text-align:left;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">Equipment</th>
                <th style="padding:6px 10px;text-align:center;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">Qty</th>
                <th style="padding:6px 10px;text-align:center;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">Returned On</th>
                <th style="padding:6px 10px;text-align:left;font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">Remarks</th>
              </tr>
            </thead>
            <tbody>${eqRows}</tbody>
          </table>` : '<div style="font-size:12px;color:var(--text-3);">No equipment listed.</div>'}`;

        container.appendChild(card);
      });

      // ── Wire Save Return Info buttons ──
      container.querySelectorAll('.saveReturnInfoBtn').forEach(btn => {
        btn.addEventListener('click', () => {
          const reqId = btn.dataset.reqId;
          const card  = container.querySelector(`.report-entry[data-req-id="${reqId}"]`);
          if (!card) return;

          const returnedItems = [];
          card.querySelectorAll('tr[data-eq-name]').forEach(row => {
            returnedItems.push({
              equipment_name: row.dataset.eqName,
              returned_on:    row.querySelector('.return-date-input')?.value    || '',
              remarks:        row.querySelector('.return-remarks-input')?.value || ''
            });
          });

          btn.disabled    = true;
          btn.textContent = 'Saving…';

          fetch('update_return_info.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ borrow_request_id: reqId, returned_items: returnedItems })
          })
          .then(r => r.json())
          .then(res => {
            if (res.success) {
              btn.textContent = 'Saved ✓';
              btn.style.background    = 'var(--accent-soft)';
              btn.style.color         = 'var(--accent)';
              btn.style.borderColor   = 'var(--accent)';
              setTimeout(() => {
                btn.textContent = 'Save Return Info';
                btn.style.cssText = '';
                btn.disabled = false;
              }, 2000);
            } else {
              alert('Save failed: ' + (res.message || 'Unknown error'));
              btn.disabled    = false;
              btn.textContent = 'Save Return Info';
            }
          })
          .catch(err => {
            console.error('Save return info error:', err);
            alert('Network error saving return info.');
            btn.disabled    = false;
            btn.textContent = 'Save Return Info';
          });
        });
      });

      // ── Wire PDF Download buttons ──
      container.querySelectorAll('.downloadPdfBtn').forEach(btn => {
        btn.addEventListener('click', () => {
          const reqId = btn.dataset.reqId;
          const card  = container.querySelector(`.report-entry[data-req-id="${reqId}"]`);
          if (!card) return;

          // Clone card, strip inputs → show values as plain text for PDF
          const clone = card.cloneNode(true);
          clone.querySelectorAll('.saveReturnInfoBtn, .downloadPdfBtn').forEach(b => b.remove());
          clone.querySelectorAll('input.return-date-input').forEach(input => {
            const span = document.createElement('span');
            span.textContent = input.value ? formatDateToDDMMYYYY(input.value) : '—';
            input.replaceWith(span);
          });
          clone.querySelectorAll('input.return-remarks-input').forEach(input => {
            const span = document.createElement('span');
            span.textContent = input.value || '—';
            input.replaceWith(span);
          });

          const wrapper = document.createElement('div');
          wrapper.style.cssText = 'padding:24px;font-family:sans-serif;font-size:13px;color:#111;';
          wrapper.appendChild(clone);

          html2pdf().set({
            margin:      [10, 10, 10, 10],
            filename:    `borrow-report-${reqId}.pdf`,
            html2canvas: { scale: 2, useCORS: true },
            jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' }
          }).from(wrapper).save();
        });
      });
    })
    .catch(err => {
      console.error('loadReports error:', err);
      container.innerHTML = '<div style="color:var(--danger);padding:16px 0;">Failed to load reports.</div>';
    });
}


// ════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  const showBtn    = document.getElementById('showChangeCredBtn');
  const currSec    = document.getElementById('currentPassSection');
  const changeSec  = document.getElementById('change-credentials');
  const verifyBtn  = document.getElementById('verifyCurrentPassBtn');
  const changeForm = document.getElementById('change-form');
  const currInput  = document.getElementById('currentPassword');
  const newUser    = document.getElementById('newUsername');
  const newPass    = document.getElementById('newPassword');

  if (!showBtn) return;

  showBtn.addEventListener('click', () => {
    showBtn.style.display = 'none';
    currSec.classList.remove('hidden');
    currInput.value = '';
  });
  document.getElementById('backFromVerifyBtn').addEventListener('click', () => {
    currSec.classList.add('hidden');
    showBtn.style.display = 'inline-block';
    currInput.value = '';
  });
  document.getElementById('backFromChangeBtn').addEventListener('click', () => {
    changeSec.classList.add('hidden');
    showBtn.style.display = 'inline-block';
    newUser.value = ''; newPass.value = '';
  });
  verifyBtn.addEventListener('click', () => {
    const pwd = currInput.value.trim();
    if (!pwd) { alert('Please enter your current password.'); return; }
    fetch('verify_password.php', {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ password: pwd })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        currSec.classList.add('hidden');
        changeSec.classList.remove('hidden');
        newUser.value = ''; newPass.value = '';
      } else { alert(data.message || 'Incorrect password.'); }
    })
    .catch(() => alert('Error verifying password.'));
  });
  changeForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const username = newUser.value.trim();
    const password = newPass.value.trim();
    if (!username || !password) { alert('Fields cannot be empty.'); return; }
    fetch('update_credentials.php', {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ username, password })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert('Credentials updated!');
        changeSec.classList.add('hidden');
        showBtn.style.display = 'inline-block';
      } else { alert(data.message || 'Failed to update.'); }
    })
    .catch(() => alert('An error occurred.'));
  });
});