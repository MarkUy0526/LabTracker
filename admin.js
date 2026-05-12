// ════════════════════════════════════════════════════════════════
// STATE
// ════════════════════════════════════════════════════════════════
let selectedRow      = null;
let selectedItemData = null;
let canEditQty       = true;
let currentEquipmentIDs = new Set();
let hiromiSignatureUrl = '';
let hiromiSignatureVersion = Date.now();

// Inline edit state
let editingRow      = null;
let originalRowHTML = '';
let currentEditItem = null;

// Audit state
let currentAuditID = null;
let currentAuditData = { items: [], changes: {} };
let auditSearchQuery = '';
let auditStatusFilter = 'All';

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
  document.getElementById('invPanelAudit').classList.toggle('active',   tab === 'audit');
  document.getElementById('invTabListBtn').classList.toggle('active',   tab === 'list');
  document.getElementById('invTabHistBtn').classList.toggle('active',   tab === 'history');
  document.getElementById('invTabAuditBtn').classList.toggle('active',  tab === 'audit');
  if (tab === 'history') loadHistoryTab();
  if (tab === 'audit') loadAuditSummary();
}

// ════════════════════════════════════════════════════════════════
// INVENTORY AUDIT FEATURE
// ════════════════════════════════════════════════════════════════

function loadAuditSummary() {
  fetch('get_last_audit_date.php')
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        const lastDate = document.getElementById('lastAuditDate');
        const nextDate = document.getElementById('nextScheduledDate');
        if (lastDate) lastDate.textContent = res.last_audit_date ? formatAuditDate(res.last_audit_date) : 'Never';
        if (nextDate) nextDate.textContent = res.next_scheduled_date ? formatAuditDate(res.next_scheduled_date) : '—';
      }
    })
    .catch(err => console.error('Error loading audit summary:', err));

  loadMostBorrowedEquipment();
}

function formatAuditDate(dateStr) {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function loadMostBorrowedEquipment() {
  fetch('get_most_borrowed.php')
    .then(r => r.json())
    .then(res => {
      if (res.success && res.data) {
        const tbody = document.getElementById('mostBorrowedBody');
        if (tbody) {
          tbody.innerHTML = res.data.map((item, idx) => `
            <tr>
              <td style="padding:8px 10px;text-align:center;font-weight:600;">${item.rank}</td>
              <td style="padding:8px 10px;text-align:left;">${escHtml(item.equipment_name)}</td>
              <td style="padding:8px 10px;text-align:center;">${item.borrow_frequency}</td>
              <td style="padding:8px 10px;text-align:center;">${item.total_qty_borrowed}</td>
            </tr>
          `).join('');
        }
      }
    })
    .catch(err => console.error('Error loading most borrowed equipment:', err));
}

function openStartAuditModal() {
  const today = new Date().toISOString().split('T')[0];
  const auditDateInput = document.createElement('input');
  auditDateInput.type = 'date';
  auditDateInput.value = today;
  auditDateInput.id = 'startAuditDate';
  auditDateInput.style.cssText = 'font-family:var(--font);font-size:12px;padding:8px;border:1px solid var(--border);border-radius:var(--radius);width:100%;box-sizing:border-box;margin-bottom:12px;';

  const adminInput = document.createElement('input');
  adminInput.type = 'text';
  adminInput.value = 'Admin';
  adminInput.id = 'startAuditAdmin';
  adminInput.style.cssText = 'font-family:var(--font);font-size:12px;padding:8px;border:1px solid var(--border);border-radius:var(--radius);width:100%;box-sizing:border-box;margin-bottom:16px;';

  const modal = document.createElement('div');
  modal.id = 'startAuditModal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:1100;background:rgba(26,26,24,.55);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `
    <div style="background:var(--surface);border-radius:var(--radius-lg);border:1px solid var(--border);box-shadow:var(--shadow-md);width:min(400px,100%);padding:24px;">
      <h2 style="font-size:16px;font-weight:600;color:var(--text-1);margin:0 0 16px 0;">Start New Inventory Audit</h2>
      <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:6px;">Audit Date</label>
      <div id="auditDateContainer"></div>
      <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-bottom:6px;margin-top:12px;">Admin Name</label>
      <div id="adminNameContainer"></div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button onclick="closeStartAuditModal()" style="flex:1;background:var(--surface-2);border:1px solid var(--border);padding:10px;border-radius:var(--radius);cursor:pointer;">← Back</button>
        <button onclick="beginAudit()" style="flex:1;background:var(--accent);color:#fff;border:1px solid var(--accent);padding:10px;border-radius:var(--radius);cursor:pointer;font-weight:600;">Start Checking →</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);
  document.getElementById('auditDateContainer').appendChild(auditDateInput);
  document.getElementById('adminNameContainer').appendChild(adminInput);
}

function closeStartAuditModal() {
  const modal = document.getElementById('startAuditModal');
  if (modal) modal.remove();
}

function beginAudit() {
  const auditDateInput = document.getElementById('startAuditDate');
  const adminInput = document.getElementById('startAuditAdmin');

  if (!auditDateInput || !adminInput) return;

  const auditDate = auditDateInput.value;
  const adminName = adminInput.value.trim() || 'Admin';

  const formData = new FormData();
  formData.append('audit_date', auditDate);
  formData.append('admin_name', adminName);

  fetch('create_audit.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
      if (!res.success) throw new Error(res.message);
      currentAuditID = res.audit_id;
      document.getElementById('auditDateDisplay').textContent = formatAuditDate(res.audit_date) + ' by ' + escHtml(res.admin_name);
      closeStartAuditModal();
      loadAuditChecklistItems();
    })
    .catch(err => {
      alert('Error creating audit: ' + err.message);
    });
}

function loadAuditChecklistItems() {
  fetch('get_equipment_for_audit.php')
    .then(r => r.json())
    .then(res => {
      if (!res.success || !res.data) throw new Error(res.message);
      currentAuditData.items = res.data;
      currentAuditData.previousSnapshot = res.previous_snapshot || null;
      renderAuditChecklistTable();
      document.getElementById('auditChecklistSection').style.display = 'block';
      filterAuditItems();
    })
    .catch(err => {
      alert('Error loading equipment: ' + err.message);
    });
}

function auditCount(value) {
  const n = parseInt(value, 10);
  return Number.isFinite(n) && n > 0 ? n : 0;
}

function auditConditionTotal(item) {
  return auditCount(item.actual_working_qty)
    + auditCount(item.actual_not_working_qty)
    + auditCount(item.actual_maintenance_qty);
}

function allocateAuditQuantities(total, working, notWorking, maintenance) {
  total = auditCount(total);
  working = auditCount(working);
  notWorking = auditCount(notWorking);
  maintenance = auditCount(maintenance);
  const basisTotal = working + notWorking + maintenance;

  if (total === 0) return { working: 0, notWorking: 0, maintenance: 0 };
  if (basisTotal === 0) return { working: total, notWorking: 0, maintenance: 0 };

  let nextWorking = Math.round(total * (working / basisTotal));
  let nextNotWorking = Math.round(total * (notWorking / basisTotal));
  let nextMaintenance = Math.round(total * (maintenance / basisTotal));
  let diff = total - (nextWorking + nextNotWorking + nextMaintenance);

  if (diff > 0) {
    nextWorking += diff;
  } else if (diff < 0) {
    let remaining = Math.abs(diff);
    const takeWorking = Math.min(nextWorking, remaining);
    nextWorking -= takeWorking;
    remaining -= takeWorking;
    const takeNotWorking = Math.min(nextNotWorking, remaining);
    nextNotWorking -= takeNotWorking;
    remaining -= takeNotWorking;
    nextMaintenance -= Math.min(nextMaintenance, remaining);
  }

  return { working: nextWorking, notWorking: nextNotWorking, maintenance: nextMaintenance };
}

function normalizeAuditItemQuantities(item) {
  item.previous_qty = auditCount(item.previous_qty ?? item.expected_qty);
  item.previous_working_qty = auditCount(item.previous_working_qty ?? item.expected_working_qty);
  item.previous_not_working_qty = auditCount(item.previous_not_working_qty ?? item.expected_not_working_qty);
  item.previous_maintenance_qty = auditCount(item.previous_maintenance_qty ?? item.expected_maintenance_qty);

  if (item.previous_working_qty + item.previous_not_working_qty + item.previous_maintenance_qty === 0 && item.previous_qty > 0) {
    item.previous_working_qty = item.previous_qty;
  }

  const hasActualBreakdown = ['actual_working_qty', 'actual_not_working_qty', 'actual_maintenance_qty']
    .some(key => Object.prototype.hasOwnProperty.call(item, key));

  item.actual_qty = auditCount(item.actual_qty);
  item.actual_working_qty = auditCount(item.actual_working_qty);
  item.actual_not_working_qty = auditCount(item.actual_not_working_qty);
  item.actual_maintenance_qty = auditCount(item.actual_maintenance_qty);

  if (!hasActualBreakdown && item.actual_qty > 0) {
    const allocated = allocateAuditQuantities(
      item.actual_qty,
      item.previous_working_qty,
      item.previous_not_working_qty,
      item.previous_maintenance_qty
    );
    item.actual_working_qty = allocated.working;
    item.actual_not_working_qty = allocated.notWorking;
    item.actual_maintenance_qty = allocated.maintenance;
  } else {
    item.actual_qty = auditConditionTotal(item);
  }
}

function formatAuditQtyGroup(item, prefix) {
  const total = auditCount(item[`${prefix}_qty`]);
  const working = auditCount(item[`${prefix}_working_qty`]);
  const notWorking = auditCount(item[`${prefix}_not_working_qty`]);
  const maintenance = auditCount(item[`${prefix}_maintenance_qty`]);
  return `${total} / ${working} / ${notWorking} / ${maintenance}`;
}

function auditQtyComparisonHtml(item, prefix) {
  normalizeAuditItemQuantities(item);
  const fields = [
    ['T', 'Total', `${prefix}_qty`, 'previous_qty'],
    ['W', 'Working', `${prefix}_working_qty`, 'previous_working_qty'],
    ['NW', 'Non-working', `${prefix}_not_working_qty`, 'previous_not_working_qty'],
    ['M', 'Maintenance', `${prefix}_maintenance_qty`, 'previous_maintenance_qty'],
  ];

  return `
    <div style="display:grid;grid-template-columns:repeat(4,minmax(34px,1fr));gap:4px;font-family:var(--mono);">
      ${fields.map(([label, title, valueKey, previousKey]) => {
        const value = auditCount(item[valueKey]);
        const changed = prefix === 'actual' && value !== auditCount(item[previousKey]);
        return `
          <span title="${title}" style="display:inline-flex;align-items:center;justify-content:center;gap:3px;padding:4px 5px;border-radius:6px;border:1px solid ${changed ? 'rgba(200,80,42,.45)' : 'var(--border)'};background:${changed ? 'rgba(200,80,42,.10)' : 'var(--surface)'};color:${changed ? 'var(--accent)' : 'var(--text-2)'};">
            <strong style="font-family:var(--font);font-size:10px;">${label}</strong>${value}
          </span>
        `;
      }).join('')}
    </div>
  `;
}

function auditChangeSummary(item) {
  normalizeAuditItemQuantities(item);
  const changes = [];
  const previousStatus = item.previous_status || 'Complete';
  const previousNotes = (item.previous_notes || '').trim();
  const currentNotes = (item.damage_notes || '').trim();

  [
    ['T', item.previous_qty, item.actual_qty],
    ['W', item.previous_working_qty, item.actual_working_qty],
    ['NW', item.previous_not_working_qty, item.actual_not_working_qty],
    ['M', item.previous_maintenance_qty, item.actual_maintenance_qty],
  ].forEach(([label, previousValue, currentValue]) => {
    if (auditCount(previousValue) !== auditCount(currentValue)) {
      changes.push(`${label}: ${auditCount(previousValue)} -> ${auditCount(currentValue)}`);
    }
  });

  if (previousStatus !== (item.status || 'Complete')) {
    changes.push(`Status: ${previousStatus} -> ${item.status || 'Complete'}`);
  }
  if (previousNotes !== currentNotes) {
    changes.push('Notes changed');
  }
  if (!item.previous_found) {
    changes.push('No previous snapshot record');
  }

  return changes.length ? changes.join('; ') : 'No change from previous report';
}

function auditItemHasChanges(item) {
  normalizeAuditItemQuantities(item);
  const previousStatus = item.previous_status || 'Complete';
  const previousNotes = (item.previous_notes || '').trim();
  const currentNotes = (item.damage_notes || '').trim();

  const qtyChanges = [
    item.previous_qty !== item.actual_qty,
    item.previous_working_qty !== item.actual_working_qty,
    item.previous_not_working_qty !== item.actual_not_working_qty,
    item.previous_maintenance_qty !== item.actual_maintenance_qty,
  ];

  return qtyChanges.some(c => c) || previousStatus !== (item.status || 'Complete') || previousNotes !== currentNotes || !item.previous_found;
}

function refreshAuditChangeSummary(row, item) {
  const summary = row?.querySelector('.audit-change-summary');
  if (summary) summary.textContent = auditChangeSummary(item);
}

function updateAuditRowStatus(item, row) {
  const statusSelect = row.querySelector('.audit-status-select');
  if (statusSelect && statusSelect.value !== 'Damaged') {
    if (item.actual_qty === item.previous_qty) {
      item.status = 'Complete';
      statusSelect.value = 'Complete';
    } else if (item.actual_qty < item.previous_qty) {
      item.status = 'Missing';
      statusSelect.value = 'Missing';
    } else {
      item.status = 'Complete';
      statusSelect.value = 'Complete';
    }
  }
}

function renderAuditChecklistTable() {
  const tbody = document.getElementById('auditItemsBody');
  if (!tbody) return;

  tbody.innerHTML = currentAuditData.items.map((item, idx) => {
    normalizeAuditItemQuantities(item);
    const status = determineAuditStatus(item);
    item.status = status;
    return `
      <tr data-equipment-id="${escHtml(item.equipment_id)}" data-index="${idx}" style="border-bottom:1px solid var(--border);">
        <td style="padding:10px 12px;text-align:left;">${escHtml(item.equipment_name)}</td>
        <td style="padding:10px 12px;text-align:center;color:var(--text-2);font-family:var(--mono);font-size:12px;">${formatAuditQtyGroup(item, 'previous')}</td>
        <td style="padding:10px 12px;text-align:center;">
          <div style="display:grid;grid-template-columns:repeat(4,54px);gap:5px;justify-content:center;">
            <input type="number" min="0" value="${item.actual_qty}" class="audit-total-input"
                   title="Actual Total" data-index="${idx}"
                   style="width:54px;text-align:center;padding:5px 4px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--text-1);font-family:var(--font);font-size:12px;outline:none;" />
            <input type="number" min="0" value="${item.actual_working_qty}" class="audit-condition-input"
                   title="Actual Working" data-index="${idx}" data-field="actual_working_qty"
                   style="width:54px;text-align:center;padding:5px 4px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--accent);font-family:var(--font);font-size:12px;outline:none;" />
            <input type="number" min="0" value="${item.actual_not_working_qty}" class="audit-condition-input"
                   title="Actual Not Working" data-index="${idx}" data-field="actual_not_working_qty"
                   style="width:54px;text-align:center;padding:5px 4px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--danger);font-family:var(--font);font-size:12px;outline:none;" />
            <input type="number" min="0" value="${item.actual_maintenance_qty}" class="audit-condition-input"
                   title="Actual Maintenance" data-index="${idx}" data-field="actual_maintenance_qty"
                   style="width:54px;text-align:center;padding:5px 4px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--warn);font-family:var(--font);font-size:12px;outline:none;" />
          </div>
        </td>
        <td style="padding:10px 12px;text-align:left;">
          <select class="audit-status-select" data-index="${idx}"
                  style="font-family:var(--font);font-size:12px;padding:5px 6px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--text-1);outline:none;">
            <option value="Complete" ${status === 'Complete' ? 'selected' : ''}>Complete</option>
            <option value="Missing" ${status === 'Missing' ? 'selected' : ''}>Missing</option>
            <option value="Damaged" ${status === 'Damaged' ? 'selected' : ''}>Damaged</option>
          </select>
        </td>
        <td style="padding:10px 12px;text-align:left;">
          <textarea placeholder="Notes..." class="audit-damage-notes" data-index="${idx}" style="font-family:var(--font);font-size:11px;padding:4px 6px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--text-1);width:100%;min-height:28px;resize:vertical;outline:none;">${escHtml(item.damage_notes || '')}</textarea>
          <div class="audit-change-summary" style="margin-top:4px;font-size:10px;color:var(--text-3);line-height:1.35;">${escHtml(auditChangeSummary(item))}</div>
        </td>
      </tr>
    `;
  }).join('');

  // Add event listeners after rendering
  tbody.querySelectorAll('.audit-total-input').forEach(input => {
    input.addEventListener('change', function() {
      const idx = parseInt(this.getAttribute('data-index'));
      const row = this.closest('tr');
      if (idx >= 0 && row) {
        const item = currentAuditData.items[idx];
        if (item) {
          const nextTotal = auditCount(this.value);
          const allocated = allocateAuditQuantities(
            nextTotal,
            item.actual_working_qty || item.previous_working_qty,
            item.actual_not_working_qty || item.previous_not_working_qty,
            item.actual_maintenance_qty || item.previous_maintenance_qty
          );
          item.actual_working_qty = allocated.working;
          item.actual_not_working_qty = allocated.notWorking;
          item.actual_maintenance_qty = allocated.maintenance;
          item.actual_qty = nextTotal;
          row.querySelector('[data-field="actual_working_qty"]').value = allocated.working;
          row.querySelector('[data-field="actual_not_working_qty"]').value = allocated.notWorking;
          row.querySelector('[data-field="actual_maintenance_qty"]').value = allocated.maintenance;
          updateAuditRowStatus(item, row);
          refreshAuditChangeSummary(row, item);
          filterAuditItems();
        }
      }
    });
  });

  tbody.querySelectorAll('.audit-condition-input').forEach(input => {
    input.addEventListener('change', function() {
      const idx = parseInt(this.getAttribute('data-index'));
      const field = this.getAttribute('data-field');
      const row = this.closest('tr');
      if (idx >= 0 && field && row) {
        const item = currentAuditData.items[idx];
        if (item) {
          item[field] = auditCount(this.value);
          item.actual_qty = auditConditionTotal(item);
          row.querySelector('.audit-total-input').value = item.actual_qty;
          updateAuditRowStatus(item, row);
          refreshAuditChangeSummary(row, item);
          filterAuditItems();
        }
      }
    });
  });

  tbody.querySelectorAll('.audit-status-select').forEach(select => {
    select.addEventListener('change', function() {
      const idx = parseInt(this.getAttribute('data-index'));
      if (idx >= 0) {
        const item = currentAuditData.items[idx];
        if (item) {
          item.status = this.value;
          refreshAuditChangeSummary(this.closest('tr'), item);
          filterAuditItems();
        }
      }
    });
  });

  tbody.querySelectorAll('.audit-damage-notes').forEach(textarea => {
    textarea.addEventListener('change', function() {
      const idx = parseInt(this.getAttribute('data-index'));
      if (idx >= 0) {
        const item = currentAuditData.items[idx];
        if (item) {
          item.damage_notes = this.value;
          refreshAuditChangeSummary(this.closest('tr'), item);
        }
      }
    });
  });
}

function determineAuditStatus(item) {
  if (item.status === 'Damaged') return 'Damaged';
  normalizeAuditItemQuantities(item);
  if (item.actual_qty === item.previous_qty) return 'Complete';
  if (item.actual_qty < item.previous_qty) return 'Missing';
  return 'Complete';
}

function filterAuditItems() {
  const searchQuery = (document.getElementById('auditSearchInput')?.value || '').toLowerCase();
  const statusFilter = document.getElementById('auditStatusFilter')?.value || 'All';

  const tbody = document.getElementById('auditItemsBody');
  if (!tbody) return;

  let allCount = 0, completeCount = 0, missingCount = 0, damagedCount = 0;

  Array.from(tbody.querySelectorAll('tr')).forEach(row => {
    const equipmentName = row.textContent.toLowerCase();
    const statusSelect = row.querySelector('.audit-status-select');
    const status = statusSelect?.value || 'Complete';

    const matchesSearch = equipmentName.includes(searchQuery);
    const matchesStatus = statusFilter === 'All' || status === statusFilter;
    const isVisible = matchesSearch && matchesStatus;

    row.style.display = isVisible ? '' : 'none';

    if (isVisible) {
      allCount++;
      if (status === 'Complete') completeCount++;
      else if (status === 'Missing') missingCount++;
      else if (status === 'Damaged') damagedCount++;
    }
  });

  const countAll = document.getElementById('auditCountAll');
  const countComplete = document.getElementById('auditCountComplete');
  const countMissing = document.getElementById('auditCountMissing');
  const countDamaged = document.getElementById('auditCountDamaged');

  if (countAll) countAll.textContent = allCount;
  if (countComplete) countComplete.textContent = completeCount;
  if (countMissing) countMissing.textContent = missingCount;
  if (countDamaged) countDamaged.textContent = damagedCount;
}

function saveDraftAudit() {
  if (!currentAuditID) {
    alert('No active audit');
    return;
  }

  const items = currentAuditData.items.map(item => ({
    equipment_id: item.equipment_id,
    equipment_name: item.equipment_name,
    previous_qty: item.previous_qty,
    previous_working_qty: item.previous_working_qty || 0,
    previous_not_working_qty: item.previous_not_working_qty || 0,
    previous_maintenance_qty: item.previous_maintenance_qty || 0,
    actual_qty: item.actual_qty || 0,
    actual_working_qty: item.actual_working_qty || 0,
    actual_not_working_qty: item.actual_not_working_qty || 0,
    actual_maintenance_qty: item.actual_maintenance_qty || 0,
    status: item.status || 'Complete',
    damage_notes: item.damage_notes || ''
  }));

  if (items.length === 0) {
    alert('No items to save');
    return;
  }

  const payload = { audit_id: currentAuditID, items: items };

  fetch('save_audit_items.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
    .then(r => r.json())
    .then(res => {
      if (!res.success) throw new Error(res.message);
      console.log('Saved ' + items.length + ' items');
      showAuditFeedback('Draft audit saved (' + items.length + ' items)');
    })
    .catch(err => {
      console.error('Save error:', err);
      alert('Error saving draft: ' + err.message);
    });
}

function submitAudit() {
  if (!currentAuditID) {
    alert('No active audit');
    return;
  }

  // First save all items
  const items = currentAuditData.items.map(item => ({
    equipment_id: item.equipment_id,
    equipment_name: item.equipment_name,
    previous_qty: item.previous_qty,
    previous_working_qty: item.previous_working_qty || 0,
    previous_not_working_qty: item.previous_not_working_qty || 0,
    previous_maintenance_qty: item.previous_maintenance_qty || 0,
    actual_qty: item.actual_qty || 0,
    actual_working_qty: item.actual_working_qty || 0,
    actual_not_working_qty: item.actual_not_working_qty || 0,
    actual_maintenance_qty: item.actual_maintenance_qty || 0,
    status: item.status || 'Complete',
    damage_notes: item.damage_notes || ''
  }));

  if (items.length === 0) {
    alert('No items to submit');
    return;
  }

  // Save items first
  fetch('save_audit_items.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ audit_id: currentAuditID, items: items })
  })
    .then(r => r.json())
    .then(saveRes => {
      if (!saveRes.success) throw new Error(saveRes.message);

      // Then submit the audit
      return fetch('submit_audit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ audit_id: currentAuditID })
      }).then(r => r.json());
    })
    .then(res => {
      if (!res.success) throw new Error(res.message);

      document.getElementById('auditChecklistSection').style.display = 'none';
      document.getElementById('auditSummarySection').style.display = 'block';

      // Store audit ID in hidden field
      document.getElementById('currentAuditIdStorage').value = currentAuditID;

      document.getElementById('summaryTotal').textContent = res.summary.total_items;
      document.getElementById('summaryComplete').textContent = res.summary.complete_count;
      document.getElementById('summaryMissing').textContent = res.summary.missing_count;
      document.getElementById('summaryDamaged').textContent = res.summary.damaged_count;

      showAuditFeedback('Audit submitted successfully!');
    })
    .catch(err => {
      console.error('Submit error:', err);
      alert('Error submitting audit: ' + err.message);
    });
}

function viewCurrentAuditDetail() {
  if (!currentAuditID) {
    alert('Invalid audit ID');
    return;
  }

  fetch('get_audit_details.php?audit_id=' + encodeURIComponent(currentAuditID))
    .then(r => r.json())
    .then(res => {
      if (!res.success) throw new Error(res.message);

      const modal = document.createElement('div');
      modal.id = 'auditDetailModal';
      modal.style.cssText = 'position:fixed;inset:0;z-index:1200;background:rgba(26,26,24,.55);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:20px;';

      const itemsHtml = res.items.map(item => `
        <tr>
          <td style="padding:8px;border-bottom:1px solid var(--border);">${escHtml(item.equipment_name)}</td>
          <td style="padding:8px;border-bottom:1px solid var(--border);text-align:center;">${auditQtyComparisonHtml(item, 'previous')}</td>
          <td style="padding:8px;border-bottom:1px solid var(--border);text-align:center;">${auditQtyComparisonHtml(item, 'actual')}</td>
          <td style="padding:8px;border-bottom:1px solid var(--border);">${escHtml(item.status)}</td>
        </tr>
      `).join('');

      modal.innerHTML = `
        <div style="background:var(--surface);border-radius:var(--radius-lg);border:1px solid var(--border);width:min(900px,100%);max-height:80vh;overflow-y:auto;padding:24px;box-shadow:var(--shadow-md);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 style="font-size:16px;font-weight:600;color:var(--text-1);margin:0;">Audit Details</h2>
            <button class="close-detail-modal" style="background:none;border:none;cursor:pointer;color:var(--text-3);font-size:20px;padding:0;width:30px;height:30px;">×</button>
          </div>

          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px;">
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Audit ID</span><div style="font-size:14px;font-weight:600;color:var(--text-1);">${res.audit.id}</div></div>
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Snapshot</span><div style="font-size:14px;font-weight:600;color:var(--text-1);">${res.snapshot ? `#${res.snapshot.id} · ${escHtml(res.snapshot.snapshot_at)}` : 'Legacy'}</div></div>
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Date</span><div style="font-size:14px;font-weight:600;color:var(--text-1);">${formatAuditDate(res.audit.audit_date)}</div></div>
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Admin</span><div style="font-size:14px;font-weight:600;color:var(--text-1);">${escHtml(res.audit.admin_name)}</div></div>
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Complete</span><div style="font-size:14px;font-weight:600;color:var(--accent);">${res.audit.complete_count}</div></div>
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Missing</span><div style="font-size:14px;font-weight:600;color:var(--danger);">${res.audit.missing_count}</div></div>
          </div>

          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:var(--surface-2);">
                <th style="padding:10px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-3);">Equipment</th>
                <th style="padding:10px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-3);">Previous Report <span title="Total">T</span>/<span title="Working">W</span>/<span title="Not Working">NW</span>/<span title="Maintenance">M</span></th>
                <th style="padding:10px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-3);">New Report <span title="Total">T</span>/<span title="Working">W</span>/<span title="Not Working">NW</span>/<span title="Maintenance">M</span></th>
                <th style="padding:10px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-3);">Status</th>
              </tr>
            </thead>
            <tbody>${itemsHtml}</tbody>
          </table>

          <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
            <button class="export-current-audit-xlsx-btn" data-audit-id="${currentAuditID}" style="background:var(--surface-2);border:1px solid var(--border);padding:10px 18px;border-radius:var(--radius);cursor:pointer;font-weight:600;">↓ Export XLSX</button>
            <button class="close-detail-modal" style="background:var(--surface-2);border:1px solid var(--border);padding:10px 18px;border-radius:var(--radius);cursor:pointer;">Close</button>
          </div>
        </div>
      `;

      document.body.appendChild(modal);

      // Add event listeners
      modal.querySelectorAll('.close-detail-modal').forEach(btn => {
        btn.addEventListener('click', function() {
          modal.remove();
        });
      });

      modal.querySelectorAll('.export-current-audit-xlsx-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const xlsxAuditId = this.getAttribute('data-audit-id');
          exportAuditExcel(xlsxAuditId);
        });
      });
    })
    .catch(err => {
      alert('Error loading audit details: ' + err.message);
    });
}

function showAuditFeedback(message) {
  const feedback = document.createElement('div');
  feedback.style.cssText = `
    position:fixed;bottom:20px;right:20px;z-index:2000;
    background:var(--accent);color:#fff;
    padding:12px 20px;border-radius:var(--radius);
    font-size:12px;font-weight:600;
    box-shadow:0 4px 12px rgba(0,0,0,.15);
    animation:slideUpBar .3s ease-out;
  `;
  feedback.textContent = message;
  document.body.appendChild(feedback);

  setTimeout(() => feedback.remove(), 3000);
}

function showAuditPastAudits() {
  document.getElementById('auditChecklistSection').style.display = 'none';
  document.getElementById('auditSummarySection').style.display = 'none';
  document.getElementById('pastAuditsSection').style.display = 'block';

  fetch('get_audits.php')
    .then(r => r.json())
    .then(res => {
      if (!res.success || !res.data) throw new Error(res.message);

      const tbody = document.getElementById('pastAuditsBody');
      if (tbody) {
        tbody.innerHTML = res.data.map(audit => `
          <tr style="border-bottom:1px solid var(--border);" data-audit-id="${audit.id}">
            <td style="padding:10px 12px;text-align:left;">${formatAuditDate(audit.audit_date)}</td>
            <td style="padding:10px 12px;text-align:left;">${escHtml(audit.admin_name)}</td>
            <td style="padding:10px 12px;text-align:center;color:var(--accent);font-weight:600;">${audit.complete_count}</td>
            <td style="padding:10px 12px;text-align:center;color:var(--danger);font-weight:600;">${audit.missing_count}</td>
            <td style="padding:10px 12px;text-align:center;color:var(--warn);font-weight:600;">${audit.damaged_count}</td>
            <td style="padding:10px 12px;text-align:center;">
              <button class="view-audit-details-btn" data-audit-id="${audit.id}" style="background:var(--surface-2);border:1px solid var(--border);padding:5px 10px;border-radius:var(--radius);cursor:pointer;font-size:11px;">View Details</button>
            </td>
          </tr>
        `).join('');

        // Add event listeners to buttons
        document.querySelectorAll('.view-audit-details-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            const auditId = this.getAttribute('data-audit-id');
            viewAuditDetail(auditId);
          });
        });
      }
    })
    .catch(err => {
      alert('Error loading past audits: ' + err.message);
    });
}

function viewAuditDetail(auditId) {
  if (!auditId) {
    alert('Invalid audit ID');
    return;
  }

  fetch('get_audit_details.php?audit_id=' + encodeURIComponent(auditId))
    .then(r => r.json())
    .then(res => {
      if (!res.success) throw new Error(res.message);

      const modal = document.createElement('div');
      modal.id = 'auditDetailModal';
      modal.style.cssText = 'position:fixed;inset:0;z-index:1200;background:rgba(26,26,24,.55);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:20px;';
      modal.setAttribute('data-audit-id', auditId);

      const itemsHtml = res.items.map(item => {
        const hasChanges = auditItemHasChanges(item);
        const rowBg = hasChanges ? 'background:rgba(255, 193, 7, .08);' : '';
        return `
        <tr style="${rowBg}">
          <td style="padding:8px;border-bottom:1px solid var(--border);">${escHtml(item.equipment_name)}</td>
          <td style="padding:8px;border-bottom:1px solid var(--border);text-align:center;">${auditQtyComparisonHtml(item, 'previous')}</td>
          <td style="padding:8px;border-bottom:1px solid var(--border);text-align:center;">${auditQtyComparisonHtml(item, 'actual')}</td>
          <td style="padding:8px;border-bottom:1px solid var(--border);">${escHtml(item.status)}</td>
        </tr>
      `;
      }).join('');

      modal.innerHTML = `
        <div style="background:var(--surface);border-radius:var(--radius-lg);border:1px solid var(--border);width:min(900px,100%);max-height:80vh;overflow-y:auto;padding:24px;box-shadow:var(--shadow-md);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 style="font-size:16px;font-weight:600;color:var(--text-1);margin:0;">Audit Details</h2>
            <button class="close-audit-modal" style="background:none;border:none;cursor:pointer;color:var(--text-3);font-size:20px;padding:0;width:30px;height:30px;">×</button>
          </div>

          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px;">
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Audit ID</span><div style="font-size:14px;font-weight:600;color:var(--text-1);">${res.audit.id}</div></div>
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Snapshot</span><div style="font-size:14px;font-weight:600;color:var(--text-1);">${res.snapshot ? `#${res.snapshot.id} · ${escHtml(res.snapshot.snapshot_at)}` : 'Legacy'}</div></div>
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Date</span><div style="font-size:14px;font-weight:600;color:var(--text-1);">${formatAuditDate(res.audit.audit_date)}</div></div>
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Admin</span><div style="font-size:14px;font-weight:600;color:var(--text-1);">${escHtml(res.audit.admin_name)}</div></div>
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Complete</span><div style="font-size:14px;font-weight:600;color:var(--accent);">${res.audit.complete_count}</div></div>
            <div><span style="color:var(--text-3);font-size:11px;font-weight:600;text-transform:uppercase;">Missing</span><div style="font-size:14px;font-weight:600;color:var(--danger);">${res.audit.missing_count}</div></div>
          </div>

          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:var(--surface-2);">
                <th style="padding:10px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-3);">Equipment</th>
                <th style="padding:10px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-3);">Previous Report <span title="Total">T</span>/<span title="Working">W</span>/<span title="Not Working">NW</span>/<span title="Maintenance">M</span></th>
                <th style="padding:10px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-3);">New Report <span title="Total">T</span>/<span title="Working">W</span>/<span title="Not Working">NW</span>/<span title="Maintenance">M</span></th>
                <th style="padding:10px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-3);">Status</th>
              </tr>
            </thead>
            <tbody>${itemsHtml}</tbody>
          </table>

          <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
            <button class="export-audit-xlsx-btn" data-audit-id="${auditId}" style="background:var(--surface-2);border:1px solid var(--border);padding:10px 18px;border-radius:var(--radius);cursor:pointer;font-weight:600;">↓ Export XLSX</button>
            <button class="close-audit-modal" style="background:var(--surface-2);border:1px solid var(--border);padding:10px 18px;border-radius:var(--radius);cursor:pointer;">Close</button>
          </div>
        </div>
      `;

      document.body.appendChild(modal);

      // Add event listeners
      modal.querySelectorAll('.close-audit-modal').forEach(btn => {
        btn.addEventListener('click', function() {
          modal.remove();
        });
      });

      modal.querySelectorAll('.export-audit-xlsx-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const xlsxAuditId = this.getAttribute('data-audit-id');
          exportAuditExcel(xlsxAuditId);
        });
      });

    })
    .catch(err => {
      alert('Error loading audit details: ' + err.message);
    });
}

function exportAuditExcel(auditId) {
  if (!auditId) {
    alert('Invalid audit ID');
    return;
  }
  const url = 'export_audit_excel.php?audit_id=' + encodeURIComponent(auditId);
  window.open(url, '_blank');
}

function closeAuditInterface() {
  document.getElementById('auditChecklistSection').style.display = 'none';
  document.getElementById('auditSummarySection').style.display = 'block';
  document.getElementById('pastAuditsSection').style.display = 'none';

  currentAuditID = null;
  currentAuditData = { items: [], changes: {} };
  auditSearchQuery = '';
  auditStatusFilter = 'All';

  const searchInput = document.getElementById('auditSearchInput');
  const filterSelect = document.getElementById('auditStatusFilter');
  if (searchInput) searchInput.value = '';
  if (filterSelect) filterSelect.value = 'All';
}

function resetToAuditSummary() {
  closeAuditInterface();
  loadAuditSummary();
}

function importAuditToInventory(auditId) {
  const audit_id = auditId || currentAuditID;

  console.log('importAuditToInventory called:', { auditId, currentAuditID, audit_id });

  if (!audit_id) {
    alert('No active audit');
    return;
  }

  const itemCount = currentAuditData.items?.length || 'all';
  const confirmed = confirm('This will update ' + itemCount + ' equipment quantities in the inventory based on audit results.\n\nContinue?');
  if (!confirmed) return;

  fetch('import_audit_to_inventory.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ audit_id: audit_id })
  })
    .then(r => r.json())
    .then(res => {
      if (!res.success) throw new Error(res.message);
      loadInventory();
      loadInventoryPreview();
      showAuditFeedback('✅ Imported! Updated ' + res.updated_count + ' equipment items');
      setTimeout(() => {
        alert('Inventory updated successfully!\n\n' + res.updated_count + ' equipment items were updated with audit data.');
      }, 500);
    })
    .catch(err => {
      alert('Error importing to inventory: ' + err.message);
    });
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
    not_working_qty: 'Not-working',
    maintenance_qty: 'Maintenance',
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

function isEquipmentBorrowable(item) {
  return parseInt(item?.is_borrowable ?? 1, 10) === 1;
}

function renderBorrowingStatusBadge(item) {
  const borrowable = isEquipmentBorrowable(item);
  const label = borrowable ? 'Available for Borrowing' : 'Restricted / Hidden';
  const color = borrowable ? 'var(--accent)' : 'var(--warn)';
  const bg = borrowable ? 'var(--accent-soft)' : 'var(--warn-soft)';
  const border = borrowable ? '#a8d5b5' : '#f5c98a';
  return `<span class="borrow-status-badge" style="display:inline-flex;align-items:center;justify-content:center;padding:3px 8px;border-radius:999px;border:1px solid ${border};background:${bg};color:${color};font-size:10.5px;font-weight:700;line-height:1.2;white-space:nowrap;">${label}</span>`;
}

function formatInventoryTimestamp(value) {
  if (!value) return 'Never';
  const normalized = String(value).replace(' ', 'T');
  const date = new Date(normalized);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  });
}

function updateInventoryMetadataDisplay(metadata = {}) {
  const importedEl = document.getElementById('inventoryLastImported');
  const editedEl = document.getElementById('inventoryLastEdited');
  if (importedEl) importedEl.textContent = formatInventoryTimestamp(metadata.last_imported_at);
  if (editedEl) editedEl.textContent = formatInventoryTimestamp(metadata.last_edited_at);
}

function loadInventoryMetadata(items = null) {
  fetch('get_inventory_metadata.php')
    .then(r => r.json())
    .then(res => {
      if (res.success && res.metadata) {
        updateInventoryMetadataDisplay(res.metadata);
        return;
      }
      throw new Error(res.message || 'Unable to load inventory metadata.');
    })
    .catch(() => {
      const sourceItems = Array.isArray(items) ? items : [];
      const latest = (field) => sourceItems
        .map(item => item[field])
        .filter(Boolean)
        .sort()
        .pop() || null;

      updateInventoryMetadataDisplay({
        last_imported_at: latest('last_imported_at'),
        last_edited_at: latest('last_edited_at')
      });
    });
}

const INVENTORY_CONDITION_FIELDS = {
  total:       { itemKey: 'total_qty',       label: 'Total',       className: 'condition-t' },
  working:     { itemKey: 'working_qty',     label: 'Working',     className: 'condition-w' },
  notWorking:  { itemKey: 'not_working_qty', label: 'Not-working', className: 'condition-nw' },
  maintenance: { itemKey: 'maintenance_qty', label: 'Maintenance', className: 'condition-m' }
};

function getInventoryRowSearchText(row) {
  if (!row?.cells) return '';
  return Array.from(row.cells).map(cell => cell.textContent || '').join(' ').toLowerCase();
}

function renderConditionQtyInput(item, fieldKey) {
  const field = INVENTORY_CONDITION_FIELDS[fieldKey];
  const value = toNonNegativeInt(item[field.itemKey]) ?? 0;
  return `<input type="number"
    class="condition-qty-input ${field.className}"
    min="0"
    value="${value}"
    data-equipment-id="${escHtml(item.equipment_id || '')}"
    data-condition-field="${fieldKey}"
    data-previous-value="${value}"
    title="Update ${escHtml(field.label)} quantity"
    onclick="event.stopPropagation()"
    ondblclick="event.stopPropagation()"
    onfocus="this.dataset.previousValue=this.value"
    onchange="updateEquipmentCondition(this)">`;
}

function updateEquipmentCondition(input) {
  if (!input) return;
  const equipmentID = input.dataset.equipmentId || '';
  const fieldKey = input.dataset.conditionField || '';
  const field = INVENTORY_CONDITION_FIELDS[fieldKey];
  const previousValue = toNonNegativeInt(input.dataset.previousValue) ?? 0;
  const nextValue = toNonNegativeInt(input.value);

  if (!equipmentID || !field) return;
  if (nextValue === null) {
    input.value = previousValue;
    alert('Condition quantity must be a whole number.');
    return;
  }
  if (previousValue === nextValue) return;

  input.disabled = true;
  const formData = new FormData();
  formData.append('equipmentID', equipmentID);
  formData.append('field', fieldKey);
  formData.append('quantity', String(nextValue));

  fetch('update_equipment_condition.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
      if (!res.success) throw new Error(res.message || 'Unable to update condition quantity.');
      input.dataset.previousValue = String(nextValue);
      loadInventoryMetadata();

      if (selectedItemData && selectedItemData.equipment_id === equipmentID) {
        selectedItemData[field.itemKey] = nextValue;
        if (fieldKey === 'working' && res.available != null) selectedItemData.available = res.available;
      }
      if (currentEditItem && currentEditItem.equipment_id === equipmentID) {
        currentEditItem[field.itemKey] = nextValue;
        if (fieldKey === 'working' && res.available != null) currentEditItem.available = res.available;
      }

      showInventoryFeedback(`${field.label} quantity updated to ${nextValue}`);
    })
    .catch(err => {
      input.value = previousValue;
      alert(err.message || 'Condition quantity update failed.');
    })
    .finally(() => {
      input.disabled = false;
    });
}

function getHiromiSignatureSrc() {
  if (!hiromiSignatureUrl) return '';
  const joiner = hiromiSignatureUrl.includes('?') ? '&' : '?';
  return `${hiromiSignatureUrl}${joiner}v=${hiromiSignatureVersion}`;
}

function buildHiromiSignatureImage() {
  const src = getHiromiSignatureSrc();
  if (src) {
    return `<img src="${escHtml(src)}" alt="Mr. Hiromi Rivas e-signature" style="display:block;width:180px;height:60px;object-fit:contain;margin:8px 0;">`;
  }
  return `<div style="width:180px;height:60px;border:1.5px dashed #bbb;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#777;font-size:12px;margin:8px 0;background:#fafafa;">e-signature here</div>`;
}

function buildHiromiApprovalBlock(editable = false) {
  return `
    <strong>Approved by:</strong><br/>
    <div class="hiromi-esig-slot">${buildHiromiSignatureImage()}</div>
    ${editable ? `
      <label style="display:inline-block;margin:2px 0 8px 0;padding:5px 10px;border:1px solid #bbb;border-radius:4px;background:#fff;cursor:pointer;font-size:12px;">
        Upload e-signature
        <input type="file" class="hiromi-esig-input" accept="image/png,image/jpeg,image/webp" style="display:none;">
      </label>
      <span class="hiromi-esig-status" style="display:block;color:#777;font-size:12px;min-height:16px;"></span>
    ` : ''}
    <em style="display:block;margin-top:4px;">Mr. Hiromi Rivas</em>
    <em style="display:block;">Applied Physics Professor</em>`;
}

function refreshHiromiSignatureSlots() {
  document.querySelectorAll('.hiromi-esig-slot').forEach(slot => {
    slot.innerHTML = buildHiromiSignatureImage();
  });
}

function loadHiromiSignature() {
  return fetch('get_esignature.php')
    .then(r => r.json())
    .then(res => {
      if (res.success && res.url) {
        hiromiSignatureUrl = res.url;
        hiromiSignatureVersion = Date.now();
        refreshHiromiSignatureSlots();
      }
      return res;
    })
    .catch(err => console.warn('Unable to load e-signature:', err));
}

document.addEventListener('change', function(e) {
  const input = e.target.closest('.hiromi-esig-input');
  if (!input || !input.files || !input.files[0]) return;

  const status = input.closest('td')?.querySelector('.hiromi-esig-status');
  const formData = new FormData();
  formData.append('signature', input.files[0]);
  if (status) status.textContent = 'Uploading...';

  fetch('upload_esignature.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
      if (!res.success) throw new Error(res.message || 'Upload failed');
      hiromiSignatureUrl = res.url;
      hiromiSignatureVersion = Date.now();
      refreshHiromiSignatureSlots();
      if (status) status.textContent = 'E-signature updated.';
      input.value = '';
    })
    .catch(err => {
      if (status) status.textContent = err.message || 'Upload failed.';
      input.value = '';
    });
});

document.addEventListener('DOMContentLoaded', loadHiromiSignature);

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

function makeInlineSelect(fieldName, value) {
  const el = document.createElement('select');
  el.dataset.field = fieldName;
  el.innerHTML = `
    <option value="1">Available for Borrowing</option>
    <option value="0">Restricted / Hidden from Guest Side</option>`;
  el.value = String(parseInt(value ?? 1, 10) === 1 ? 1 : 0);
  el.style.cssText = `
    width:100%;box-sizing:border-box;
    font-family:var(--font);font-size:12px;padding:4px 7px;
    border:1.5px solid var(--accent);
    border-radius:6px;background:var(--surface);color:var(--text-1);
    outline:none;
  `;
  return el;
}

function toNonNegativeInt(value) {
  if (value === '' || value === null || value === undefined) return null;
  const num = Number(value);
  if (!Number.isInteger(num) || num < 0) return null;
  return num;
}

function validateInventoryValues(values, requireId = false) {
  const equipmentID = (values.equipmentID || '').trim();
  const equipmentName = (values.equipmentName || '').trim();
  const accountablePerson = (values.accountablePerson || '').trim();
  const totalQty = toNonNegativeInt(values.totalQty);
  const workingQty = toNonNegativeInt(values.workingQty);
  const notWorkingQty = toNonNegativeInt(values.notWorkingQty);
  const maintenanceQty = toNonNegativeInt(values.maintenanceQty);

  if (requireId && !equipmentID) return { valid: false, message: 'Equipment ID is required.' };
  if (!equipmentName) return { valid: false, message: 'Equipment Name is required.' };
  if (!accountablePerson) return { valid: false, message: 'Accountable Person is required.' };
  if (totalQty === null || workingQty === null || notWorkingQty === null || maintenanceQty === null) {
    return { valid: false, message: 'Total, Working, Non-working, and Maintenance must be whole numbers.' };
  }
  if ((workingQty + notWorkingQty + maintenanceQty) !== totalQty) {
    return { valid: false, message: 'Working + Non-working + Maintenance must equal Total Qty.' };
  }
  if (workingQty === 0 && notWorkingQty === 0 && maintenanceQty === 0) {
    return { valid: false, message: 'Select at least one condition count.' };
  }
  return { valid: true, totalQty, workingQty, notWorkingQty, maintenanceQty };
}

function validateEquipmentDetails(values, requireId = false) {
  const equipmentID = (values.equipmentID || '').trim();
  const equipmentName = (values.equipmentName || '').trim();
  const accountablePerson = (values.accountablePerson || '').trim();

  if (requireId && !equipmentID) return { valid: false, message: 'Equipment ID is required.' };
  if (!equipmentName) return { valid: false, message: 'Equipment Name is required.' };
  if (!accountablePerson) return { valid: false, message: 'Accountable Person is required.' };
  return { valid: true };
}

function setButtonDisabledState(btn, disabled) {
  if (!btn) return;
  btn.disabled = disabled;
  btn.style.opacity = disabled ? '.55' : '1';
  btn.style.cursor = disabled ? 'not-allowed' : 'pointer';
}

function showInventoryFeedback(message) {
  let toast = document.getElementById('inventoryFeedbackToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'inventoryFeedbackToast';
    toast.style.cssText = `
      position:fixed;right:28px;bottom:88px;z-index:1400;
      background:var(--accent);color:#fff;border-radius:var(--radius);
      padding:10px 14px;box-shadow:0 8px 28px rgba(0,0,0,.18);
      font-family:var(--font);font-size:13px;font-weight:600;
      max-width:340px;opacity:0;transform:translateY(8px);
      transition:opacity .18s ease, transform .18s ease;
    `;
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
  });
  clearTimeout(toast._hideTimer);
  toast._hideTimer = setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(8px)';
  }, 3200);
}

function buildInventoryUpdateMessage(equipmentName, previousNotWorking, nextNotWorking) {
  const deltaNotWorking = Math.max(0, nextNotWorking - previousNotWorking);
  if (deltaNotWorking > 0) {
    return `Inventory updated: ${deltaNotWorking} item${deltaNotWorking === 1 ? '' : 's'} marked as Not Working`;
  }
  return `Inventory updated: ${equipmentName} saved`;
}

function confirmRiskyInventoryUpdate(previousNotWorking, nextNotWorking) {
  if (nextNotWorking > previousNotWorking) {
    return confirm('This will update inventory counts and mark items as Not Working. Continue?');
  }
  return true;
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

function getInlineInventoryValues() {
  function val(fieldName) {
    const el = editingRow?.querySelector(`[data-field="${fieldName}"]`);
    return el ? el.value.trim() : '';
  }

  return {
    equipmentID: currentEditItem?.equipment_id || '',
    equipmentName: val('equipmentName'),
    accountablePerson: val('accountablePerson'),
    isBorrowable: val('isBorrowable') || '1'
  };
}

function updateInlineEditSaveState() {
  if (!editingRow) return;
  const saveBtn = document.getElementById('inlineEditSaveBtn');
  const validation = validateEquipmentDetails(getInlineInventoryValues());
  setButtonDisabledState(saveBtn, !validation.valid);
  if (saveBtn) saveBtn.title = validation.valid ? '' : validation.message;
}

function getAddInventoryValues() {
  const get = id => document.getElementById(id)?.value.trim() || '';
  return {
    equipmentID: get('equipmentID'),
    equipmentName: get('equipmentName'),
    accountablePerson: get('accountablePerson'),
    isBorrowable: document.getElementById('borrowingStatus')?.value || '1',
    totalQty: get('totalQty'),
    workingQty: get('workingQty'),
    notWorkingQty: get('notWorkingQty'),
    maintenanceQty: get('maintenanceQty')
  };
}

function updateAddEquipmentSaveState() {
  const submitBtn = document.getElementById('submitEquipmentBtn');
  const validation = validateInventoryValues(getAddInventoryValues(), true);
  const duplicate = currentEquipmentIDs.has(getAddInventoryValues().equipmentID);
  setButtonDisabledState(submitBtn, !validation.valid || duplicate);
  if (submitBtn) {
    submitBtn.title = duplicate ? 'Equipment ID already exists.' : (validation.valid ? '' : validation.message);
  }
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

  // Equipment detail editing intentionally skips the Condition cell.
  const fields = [
    [0, 'equipmentID',       'text',     item.equipment_id,    true],
    [2, 'equipmentName',     'text',     item.equipment_name,  false],
    [3, 'serialNumber',      'text',     item.serial_number,   false],
    [4, 'internalSN',        'text',     item.internal_sn,     false],
    [5, 'accountablePerson', 'text',     item.account_person,  false],
    [11, 'description',      'textarea', item.description,     false],
    // cell 12 is the history button.
  ];

  fields.forEach(([cellIdx, fieldName, type, value, dis]) => {
    const cell = row.cells[cellIdx];
    if (!cell) return;
    cell.innerHTML = '';
    cell.style.verticalAlign = 'top';
    cell.style.padding       = '6px 8px';
    cell.appendChild(makeInlineInput(fieldName, type, value, dis));
  });

  const visibilityCell = row.cells[6];
  if (visibilityCell) {
    visibilityCell.innerHTML = '';
    visibilityCell.style.verticalAlign = 'top';
    visibilityCell.style.padding = '6px 8px';
    visibilityCell.appendChild(makeInlineSelect('isBorrowable', item.is_borrowable));
  }

  row.style.background = 'var(--accent-soft)';
  removeStickyBar();
  document.body.appendChild(createStickyActionBar(item));
  row.querySelectorAll('input[data-field], textarea[data-field], select[data-field]').forEach(el => {
    el.addEventListener('input', updateInlineEditSaveState);
    el.addEventListener('change', updateInlineEditSaveState);
  });
  updateInlineEditSaveState();
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
  const totalQty          = String(currentEditItem.total_qty ?? 0);
  const workingQty        = String(currentEditItem.working_qty ?? 0);
  const notWorkingQty     = String(currentEditItem.not_working_qty ?? 0);
  const maintenanceQty    = String(currentEditItem.maintenance_qty ?? 0);
  const description       = val('description');
  const isBorrowable      = val('isBorrowable') || '1';

  const validation = validateEquipmentDetails({
    equipmentName,
    accountablePerson
  });
  if (!validation.valid) {
    alert(validation.message);
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
  formData.append('maintenanceQty', maintenanceQty);
  formData.append('description', description);
  formData.append('accountablePerson', accountablePerson);
  formData.append('isBorrowable', isBorrowable);

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
        showInventoryFeedback(`Inventory updated: ${equipmentName} saved`);
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
const reportsState = {
  data: [],
  filteredData: [],
  currentPage: 1,
  rowsPerPage: 10,
  search: '',
  status: 'All',
  from: '',
  to: ''
};

function moveScheduleSummaryToReports() {
  const summary = document.getElementById('statsSummary');
  const mount = document.getElementById('reportsSummaryMount');
  if (!summary || !mount) return;
  if (summary.parentElement !== mount) mount.appendChild(summary);
  summary.style.marginTop = '0';
}

function switchReportsTab(tab) {
  moveScheduleSummaryToReports();
  document.getElementById('reportsPanelList')?.classList.toggle('active', tab === 'list');
  document.getElementById('reportsPanelSummary')?.classList.toggle('active', tab === 'summary');
  document.getElementById('reportsTabListBtn')?.classList.toggle('active', tab === 'list');
  document.getElementById('reportsTabSummaryBtn')?.classList.toggle('active', tab === 'summary');

  if (tab === 'summary') {
    initScheduleCharts();
    setTimeout(() => {
      if (trendChart && typeof trendChart.resize === 'function') trendChart.resize();
    }, 80);
  }
}

function initReportsControls() {
  moveScheduleSummaryToReports();

  const rowsSelect = document.getElementById('reportsRowsPerPage');
  if (rowsSelect) reportsState.rowsPerPage = parseInt(rowsSelect.value, 10) || reportsState.rowsPerPage;

  document.getElementById('reportsSearch')?.addEventListener('input', e => {
    reportsState.search = e.target.value.trim().toLowerCase();
    applyReportsFilters(true);
    renderReportsPage();
  });

  document.getElementById('reportsStatusFilter')?.addEventListener('change', e => {
    reportsState.status = e.target.value || 'All';
    applyReportsFilters(true);
    renderReportsPage();
  });

  document.getElementById('reportsFrom')?.addEventListener('change', e => {
    reportsState.from = e.target.value || '';
    applyReportsFilters(true);
    renderReportsPage();
  });

  document.getElementById('reportsTo')?.addEventListener('change', e => {
    reportsState.to = e.target.value || '';
    applyReportsFilters(true);
    renderReportsPage();
  });

  document.getElementById('reportsFilterBtn')?.addEventListener('click', () => {
    reportsState.from = document.getElementById('reportsFrom')?.value || '';
    reportsState.to = document.getElementById('reportsTo')?.value || '';
    applyReportsFilters(true);
    renderReportsPage();
  });

  document.getElementById('reportsClearBtn')?.addEventListener('click', () => {
    const search = document.getElementById('reportsSearch');
    const status = document.getElementById('reportsStatusFilter');
    const from = document.getElementById('reportsFrom');
    const to = document.getElementById('reportsTo');
    if (search) search.value = '';
    if (status) status.value = 'All';
    if (from) from.value = '';
    if (to) to.value = '';
    reportsState.search = '';
    reportsState.status = 'All';
    reportsState.from = '';
    reportsState.to = '';
    applyReportsFilters(true);
    renderReportsPage();
  });

  rowsSelect?.addEventListener('change', e => {
    reportsState.rowsPerPage = parseInt(e.target.value, 10) || 10;
    reportsState.currentPage = 1;
    renderReportsPage();
  });

  document.getElementById('reportsGoToPage')?.addEventListener('change', e => {
    setReportsPage(parseInt(e.target.value, 10) || 1);
  });

  document.getElementById('reportsGoToPage')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') setReportsPage(parseInt(e.target.value, 10) || 1);
  });

  document.getElementById('reportsPagination')?.addEventListener('click', e => {
    const btn = e.target.closest('button');
    if (!btn || btn.disabled) return;
    const action = btn.dataset.pageAction;
    const totalPages = getReportsTotalPages();
    if (btn.dataset.page) {
      setReportsPage(parseInt(btn.dataset.page, 10));
    } else if (action === 'first') {
      setReportsPage(1);
    } else if (action === 'prev') {
      setReportsPage(reportsState.currentPage - 1);
    } else if (action === 'next') {
      setReportsPage(reportsState.currentPage + 1);
    } else if (action === 'last') {
      setReportsPage(totalPages);
    }
  });
}

document.addEventListener('DOMContentLoaded', initReportsControls);

function getReportDate(entry) {
  return String(entry.borrowRequest?.date || '').split(/[T\s]/)[0];
}

function getReportSearchText(entry) {
  const req = entry.borrowRequest || {};
  const equipment = (entry.equipmentList || []).map(eq => eq.equipment_name).join(' ');
  return [
    req.borrower_name,
    req.guest_number,
    req.student_id,
    req.instructor_name,
    req.subject_code,
    req.department,
    req.room,
    req.status,
    req.usage_date,
    req.date,
    equipment
  ].filter(Boolean).join(' ').toLowerCase();
}

function applyReportsFilters(resetPage = false) {
  reportsState.filteredData = reportsState.data.filter(entry => {
    const req = entry.borrowRequest || {};
    const date = getReportDate(entry);
    const matchesSearch = !reportsState.search || getReportSearchText(entry).includes(reportsState.search);
    const matchesStatus = reportsState.status === 'All' || req.status === reportsState.status;
    const matchesFrom = !reportsState.from || (date && date >= reportsState.from);
    const matchesTo = !reportsState.to || (date && date <= reportsState.to);
    return matchesSearch && matchesStatus && matchesFrom && matchesTo;
  });

  if (resetPage) reportsState.currentPage = 1;
  reportsState.currentPage = Math.min(Math.max(reportsState.currentPage, 1), getReportsTotalPages());
}

function getReportsTotalPages() {
  return Math.max(1, Math.ceil(reportsState.filteredData.length / reportsState.rowsPerPage));
}

function setReportsPage(page) {
  reportsState.currentPage = Math.min(Math.max(page, 1), getReportsTotalPages());
  renderReportsPage();
}

function getReportsVisibleEntries() {
  const start = (reportsState.currentPage - 1) * reportsState.rowsPerPage;
  return reportsState.filteredData.slice(start, start + reportsState.rowsPerPage);
}

function getReportPageNumbers() {
  const totalPages = getReportsTotalPages();
  const maxButtons = 5;
  let start = Math.max(1, reportsState.currentPage - Math.floor(maxButtons / 2));
  let end = Math.min(totalPages, start + maxButtons - 1);
  start = Math.max(1, end - maxButtons + 1);
  const pages = [];
  for (let page = start; page <= end; page++) pages.push(page);
  return pages;
}

function updateReportsPagination() {
  const total = reportsState.filteredData.length;
  const totalPages = getReportsTotalPages();
  const start = total ? ((reportsState.currentPage - 1) * reportsState.rowsPerPage) + 1 : 0;
  const end = total ? Math.min(start + reportsState.rowsPerPage - 1, total) : 0;
  const summary = document.getElementById('reportsPageSummary');
  const pageNumbers = document.getElementById('reportsPageNumbers');
  const goTo = document.getElementById('reportsGoToPage');

  if (summary) summary.textContent = `Showing ${start}-${end} of ${total} reports`;
  if (goTo) {
    goTo.value = reportsState.currentPage;
    goTo.max = totalPages;
  }

  if (pageNumbers) {
    pageNumbers.innerHTML = '';
    if (total > 0) {
      getReportPageNumbers().forEach(page => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'reports-page-btn' + (page === reportsState.currentPage ? ' active' : '');
        btn.dataset.page = String(page);
        btn.textContent = String(page);
        pageNumbers.appendChild(btn);
      });
    }
  }

  document.querySelectorAll('[data-page-action="first"], [data-page-action="prev"]').forEach(btn => {
    btn.disabled = reportsState.currentPage <= 1 || total === 0;
  });
  document.querySelectorAll('[data-page-action="next"], [data-page-action="last"]').forEach(btn => {
    btn.disabled = reportsState.currentPage >= totalPages || total === 0;
  });
}

function renderReportCard(entry) {
  const req = entry.borrowRequest;
  const eqList = entry.equipmentList || [];
  const reqId = req.id;
  const isAccepted = req.status === 'Accepted';
  const statusColor = isAccepted ? 'var(--accent)' : 'var(--danger)';
  const statusBg = isAccepted ? 'var(--accent-soft)' : 'var(--danger-soft)';

  const eqRows = eqList.map(eq => {
    const returnedVal = eq.returned_on || '';
    const remarksVal = eq.remarks || '';
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
            ${buildReturnRemarksControl(remarksVal, eq.quantity)}
          </td>
        </tr>`;
    }
    return `
      <tr>
        <td style="padding:6px 10px;border-bottom:1px solid var(--border);">${escHtml(eq.equipment_name)}</td>
        <td style="padding:6px 10px;border-bottom:1px solid var(--border);text-align:center;">${eq.quantity}</td>
        <td style="padding:6px 10px;border-bottom:1px solid var(--border);text-align:center;">${returnedVal ? formatDateToDDMMYYYY(returnedVal) : '&mdash;'}</td>
        <td style="padding:6px 10px;border-bottom:1px solid var(--border);">${escHtml(remarksVal || '-')}</td>
      </tr>`;
  }).join('');

  const card = document.createElement('div');
  card.className = 'report-entry';
  card.dataset.reqId = reqId;
  card.innerHTML = `
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
      <div>
        <span style="font-weight:600;font-size:14px;">${escHtml(req.borrower_name)}</span>
        <span style="color:var(--text-3);font-size:12px;margin-left:8px;">Borrower #${escHtml(req.guest_number)}</span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span style="background:${statusBg};color:${statusColor};font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.05em;">${escHtml(req.status)}</span>
        <button class="downloadPdfBtn" data-req-id="${reqId}"
          style="font-family:var(--font);font-size:12px;padding:5px 12px;border-radius:var(--radius);cursor:pointer;">PDF</button>
        ${isAccepted ? `<button class="saveReturnInfoBtn" data-req-id="${reqId}"
          style="font-family:var(--font);font-size:12px;padding:5px 12px;border-radius:var(--radius);cursor:pointer;">Save Return Info</button>` : ''}
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;font-size:13px;color:var(--text-2);margin-bottom:12px;">
      <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Student ID</span><br>${escHtml(req.student_id || '-')}</div>
      <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Instructor</span><br>${escHtml(req.instructor_name || '-')}</div>
      <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Subject Code</span><br>${escHtml(req.subject_code || '-')}</div>
      <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Department</span><br>${escHtml(req.department || '-')}</div>
      <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Room</span><br>${escHtml(req.room || '-')}</div>
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

  return card;
}

function updateReportsReturnInfo(reqId, returnedItems) {
  [reportsState.data, reportsState.filteredData].forEach(list => {
    const entry = list.find(item => item.borrowRequest?.id == reqId);
    if (!entry) return;
    returnedItems.forEach(item => {
      const eq = (entry.equipmentList || []).find(e => e.equipment_name === item.equipment_name);
      if (eq) {
        eq.returned_on = item.returned_on;
        eq.remarks = item.remarks;
      }
    });
  });
}

function wireReportCardControls(container) {
  container.querySelectorAll('.return-remarks-select').forEach(select => {
    select.addEventListener('change', () => {
      const customInput = select.closest('td')?.querySelector('.return-remarks-other-input');
      if (!customInput) return;
      customInput.style.display = select.value === '__other__' ? 'block' : 'none';
      if (select.value === '__other__') customInput.focus();
    });
  });

  container.querySelectorAll('.saveReturnInfoBtn').forEach(btn => {
    btn.addEventListener('click', () => {
      const reqId = btn.dataset.reqId;
      const card = container.querySelector(`.report-entry[data-req-id="${reqId}"]`);
      if (!card) return;

      const returnedItems = [];
      card.querySelectorAll('tr[data-eq-name]').forEach(row => {
        returnedItems.push({
          equipment_name: row.dataset.eqName,
          returned_on: row.querySelector('.return-date-input')?.value || '',
          remarks: getReturnRemarksValue(row)
        });
      });

      btn.disabled = true;
      btn.textContent = 'Saving...';

      fetch('update_return_info.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ borrow_request_id: reqId, returned_items: returnedItems })
      })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          updateReportsReturnInfo(reqId, returnedItems);
          btn.textContent = 'Saved';
          btn.style.background = 'var(--accent-soft)';
          btn.style.color = 'var(--accent)';
          btn.style.borderColor = 'var(--accent)';
          setTimeout(() => {
            btn.textContent = 'Save Return Info';
            btn.style.cssText = '';
            btn.disabled = false;
          }, 2000);
        } else {
          alert('Save failed: ' + (res.message || 'Unknown error'));
          btn.disabled = false;
          btn.textContent = 'Save Return Info';
        }
      })
      .catch(err => {
        console.error('Save return info error:', err);
        alert('Network error saving return info.');
        btn.disabled = false;
        btn.textContent = 'Save Return Info';
      });
    });
  });

  container.querySelectorAll('.downloadPdfBtn').forEach(btn => {
    btn.addEventListener('click', () => {
      const reqId = btn.dataset.reqId;
      const entry = reportsState.data.find(item => item.borrowRequest?.id == reqId);
      const req = entry?.borrowRequest;
      const eqList = entry?.equipmentList || [];
      const card = container.querySelector(`.report-entry[data-req-id="${reqId}"]`);
      if (!req) return;

      const currentReturnInfo = new Map();
      card?.querySelectorAll('tr[data-eq-name]').forEach(row => {
        currentReturnInfo.set(row.dataset.eqName, {
          returned_on: row.querySelector('.return-date-input')?.value || '',
          remarks: getReturnRemarksValue(row)
        });
      });

      const eqRows = eqList.map(eq => {
        const liveInfo = currentReturnInfo.get(eq.equipment_name) || {};
        const returnedOn = liveInfo.returned_on ?? eq.returned_on;
        const remarks = liveInfo.remarks ?? eq.remarks;
        return `
          <tr>
            <td style="border: 1px solid #000; padding: 8px;">${escHtml(eq.equipment_name)}</td>
            <td style="border: 1px solid #000; padding: 8px; text-align: center;">${eq.quantity}</td>
            <td style="border: 1px solid #000; padding: 8px; text-align: center;">YES</td>
            <td style="border: 1px solid #000; padding: 8px;">${returnedOn ? formatDateToDDMMYYYY(returnedOn) : '-'}</td>
            <td style="border: 1px solid #000; padding: 8px;">${escHtml(remarks || '-')}</td>
          </tr>
        `;
      }).join('');

      const formHtml = `
        <div style="font-family: Arial, sans-serif; font-size: 12px; padding: 20px; max-width: 800px; margin: 0 auto;">
          <div style="text-align: center; margin-bottom: 20px;">
            <h3 style="margin: 4px 0;">EULOGIO "AMANG" RODRIGUEZ INSTITUTE OF SCIENCE AND TECHNOLOGY</h3>
            <h3 style="margin: 4px 0;">COLLEGE OF ARTS AND SCIENCES</h3>
            <h3 style="margin: 4px 0;">APPLIED PHYSICS DEPARTMENT</h3>
            <h2 style="margin: 10px 0;">Equipment-borrowing Form</h2>
          </div>
          <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <tr><td style="padding: 5px;"><strong>Borrower Login Number:</strong> ${escHtml(req.guest_number)}</td><td style="padding: 5px;"><strong>Date:</strong> ${formatDateToDDMMYYYY(req.date)}</td></tr>
            <tr><td style="padding: 5px;"><strong>Borrower's Name:</strong> ${escHtml(req.borrower_name)}</td><td style="padding: 5px;"><strong>Instructor's Name:</strong> ${escHtml(req.instructor_name || '-')}</td></tr>
            <tr><td style="padding: 5px;"><strong>Student ID:</strong> ${escHtml(req.student_id || '-')}</td><td style="padding: 5px;"><strong>Subject Code:</strong> ${escHtml(req.subject_code || '-')}</td></tr>
            <tr><td style="padding: 5px;"><strong>Department:</strong> ${escHtml(req.department || '-')}</td><td style="padding: 5px;"><strong>Date(s) of Usage:</strong> ${formatDateToDDMMYYYY(req.usage_date)}</td></tr>
            <tr><td colspan="2" style="padding: 5px;"><strong>Room:</strong> ${escHtml(req.room || '-')}</td></tr>
          </table>
          <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #000;">
            <thead>
              <tr style="background: #f0f0f0;">
                <th style="border: 1px solid #000; padding: 8px; text-align: left;">Equipment / Material</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: center;">Quantity</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: center;">Available in the lab?</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: left;">Returned on</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: left;">Remarks</th>
              </tr>
            </thead>
            <tbody>${eqRows}</tbody>
          </table>
          <div style="margin-bottom: 20px;">
            <strong>Borrower's Declaration of Commitment:</strong><br/>
            <em>"I will be accountable to any damage incurred in the equipment and will return the equipment promptly and in the same working condition it was borrowed."</em>
          </div>
          <table style="width: 100%; margin-top: 40px;">
            <tr><td colspan="2" style="padding: 20px; text-align: left; vertical-align: top;">${buildHiromiApprovalBlock(false)}</td></tr>
          </table>
        </div>
      `;

      const wrapper = document.createElement('div');
      wrapper.innerHTML = formHtml;
      html2pdf().set({
        margin: [10, 10, 10, 10],
        filename: `borrow-report-${reqId}.pdf`,
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
      }).from(wrapper).save();
    });
  });
}

function renderReportsPage() {
  const container = document.getElementById('reportsList');
  if (!container) return;
  container.innerHTML = '';

  if (!reportsState.data.length) {
    container.innerHTML = '<div style="color:var(--text-3);font-style:italic;padding:16px 0;">No accepted or rejected requests yet.</div>';
    updateReportsPagination();
    return;
  }

  if (!reportsState.filteredData.length) {
    container.innerHTML = '<div style="color:var(--text-3);font-style:italic;padding:16px 0;">No reports match the current filters.</div>';
    updateReportsPagination();
    return;
  }

  getReportsVisibleEntries().forEach(entry => {
    container.appendChild(renderReportCard(entry));
  });

  wireReportCardControls(container);
  updateReportsPagination();
}

function loadReports(options = {}) {
  const container = document.getElementById('reportsList');
  if (!container) return;
  const keepPage = options.keepPage !== false;
  container.innerHTML = '<div style="color:var(--text-3);font-style:italic;padding:16px 0;">Loading reports...</div>';

  fetch('fetch_borrow_reports.php')
    .then(r => r.json())
    .then(json => {
      if (!json.success) {
        reportsState.data = [];
        reportsState.filteredData = [];
        renderReportsPage();
        return;
      }

      reportsState.data = Array.isArray(json.data) ? json.data : [];
      applyReportsFilters(!keepPage);
      renderReportsPage();
    })
    .catch(err => {
      console.error('loadReports error:', err);
      container.innerHTML = '<div style="color:var(--danger);padding:16px 0;">Failed to load reports.</div>';
    });
}

document.addEventListener('DOMContentLoaded', () => {

  // ── ADD button ──
  document.getElementById('addEquipmentBtn').onclick = () => {
    selectedItemData = null; canEditQty = true;
    ['equipmentID','equipmentName','serialNumber','internalSN',
     'accountablePerson','totalQty','workingQty','notWorkingQty','maintenanceQty','description']
      .forEach(id => { document.getElementById(id).value = ''; });
    const maintenanceInput = document.getElementById('maintenanceQty');
    if (maintenanceInput) maintenanceInput.value = '0';
    const borrowingStatus = document.getElementById('borrowingStatus');
    if (borrowingStatus) borrowingStatus.value = '1';
    ['equipmentID','totalQty','workingQty','notWorkingQty','maintenanceQty']
      .forEach(id => { document.getElementById(id).disabled = false; });
    openAddEquipmentModal();
    updateAddEquipmentSaveState();
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
  ['equipmentID','equipmentName','accountablePerson','borrowingStatus','totalQty','workingQty','notWorkingQty','maintenanceQty']
    .forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('input', updateAddEquipmentSaveState);
      el.addEventListener('change', updateAddEquipmentSaveState);
    });
  updateAddEquipmentSaveState();

  document.getElementById('submitEquipmentBtn').onclick = function(e) {
    e.preventDefault();
    const equipmentID       = document.getElementById('equipmentID').value.trim();
    const equipmentName     = document.getElementById('equipmentName').value.trim();
    const serialNumber      = document.getElementById('serialNumber').value.trim();
    const internalSN        = document.getElementById('internalSN').value.trim();
    const totalQty          = document.getElementById('totalQty').value.trim();
    const workingQty        = document.getElementById('workingQty').value.trim();
    const notWorkingQty     = document.getElementById('notWorkingQty').value.trim();
    const maintenanceQty    = document.getElementById('maintenanceQty').value.trim();
    const description       = document.getElementById('description').value.trim();
    const accountablePerson = document.getElementById('accountablePerson').value.trim();
    const isBorrowable      = document.getElementById('borrowingStatus')?.value || '1';

    const validation = validateInventoryValues({
      equipmentID,
      equipmentName,
      accountablePerson,
      totalQty,
      workingQty,
      notWorkingQty,
      maintenanceQty
    }, true);
    if (!validation.valid) { alert(validation.message); return; }
    if (currentEquipmentIDs.has(equipmentID)) {
      alert('Equipment ID already exists. Please use a unique ID.'); return;
    }
    if (!confirmRiskyInventoryUpdate(0, validation.notWorkingQty)) return;

    $.ajax({
      url: 'add_equipment.php', method: 'POST',
      data: { equipmentID, equipmentName, serialNumber, internalSN,
              totalQty, workingQty, notWorkingQty, maintenanceQty, description, accountablePerson, isBorrowable },
      // FIX: removed dataType:'json' to avoid false error triggers
      success: function(rawData) {
        let data;
        try { data = typeof rawData === 'string' ? JSON.parse(rawData) : rawData; }
        catch(e) { data = { success: false, message: 'Invalid response' }; }

        if (data.success) {
          showInventoryFeedback(buildInventoryUpdateMessage(equipmentName, 0, validation.notWorkingQty));
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
           'workingQty','notWorkingQty','maintenanceQty','description','accountablePerson']
            .forEach(id => { document.getElementById(id).value = ''; });
          const borrowingStatus = document.getElementById('borrowingStatus');
          if (borrowingStatus) borrowingStatus.value = '1';

          // FIX 3: reload inventory immediately so new equipment shows without page refresh
          loadInventory();
          loadInventoryPreview();
          updateAddEquipmentSaveState();
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

    openImportWarning(file);
  });

  function openImportWarning(file) {
    let modal = document.getElementById('importWarningModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'importWarningModal';
      modal.style.cssText = 'position:fixed;inset:0;z-index:1250;background:rgba(26,26,24,.55);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box;';
      modal.innerHTML = `
        <div style="background:var(--surface);border-radius:var(--radius-lg);border:1px solid var(--border);box-shadow:var(--shadow-md);width:min(460px,100%);padding:22px 24px;">
          <div style="font-size:16px;font-weight:700;color:var(--text-1);margin-bottom:8px;">Confirm Inventory Import</div>
          <div id="importWarningFile" style="font-size:12px;color:var(--text-3);margin-bottom:14px;"></div>
          <div style="border:1px solid #f5c98a;background:var(--warn-soft);color:var(--text-1);border-radius:var(--radius);padding:12px 14px;font-size:13px;line-height:1.45;margin-bottom:18px;">
            Importing this file will overwrite matching equipment records in the current Inventory List and add any new records from the file. Review the preview carefully before confirming the final import.
          </div>
          <div style="display:flex;justify-content:flex-end;gap:10px;">
            <button id="cancelImportWarningBtn" style="background:var(--surface-2);color:var(--text-2);border:1px solid var(--border);padding:9px 18px;border-radius:var(--radius);font-family:var(--font);font-size:13px;cursor:pointer;">Cancel</button>
            <button id="continueImportWarningBtn" style="background:var(--accent);color:#fff;border:1px solid var(--accent);padding:9px 18px;border-radius:var(--radius);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer;">Continue Import</button>
          </div>
        </div>`;
      document.body.appendChild(modal);
      document.getElementById('cancelImportWarningBtn').addEventListener('click', () => {
        modal.style.display = 'none';
        _pendingImportFile = null;
      });
      document.getElementById('continueImportWarningBtn').addEventListener('click', () => {
        modal.style.display = 'none';
        startImportPreview(_pendingImportFile);
      });
    }

    document.getElementById('importWarningFile').textContent = `Selected file: ${file.name}`;
    modal.style.display = 'flex';
  }

  function startImportPreview(file) {
    if (!file) return;

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
  }

  function openImportPreview(res, file) {
    const modal = document.getElementById('importPreviewModal');
    const tbody = document.getElementById('importPreviewBody');
    const meta  = document.getElementById('importPreviewMeta');

    meta.textContent = `File: ${file.name} · ${res.rows.length} row(s) · ${res.new_count} new · ${res.dup_count} duplicate(s)`;

    const restrictedCount = res.rows.filter(row => parseInt(row.is_borrowable ?? 1, 10) !== 1).length;
    meta.textContent = `File: ${file.name} · ${res.rows.length} row(s) · ${res.new_count} new · ${res.dup_count} duplicate(s) · ${restrictedCount} restricted`;

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
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);">${renderBorrowingStatusBadge(row)}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);text-align:center;">${row.total_qty}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);text-align:center;color:var(--accent);">${row.working_qty}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);text-align:center;color:var(--danger);">${row.not_working_qty}</td>
        <td style="padding:7px 12px;border-bottom:1px solid var(--border);text-align:center;color:var(--warn);">${row.maintenance_qty || 0}</td>
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
      if (!row.cells || row.cells.length < 13) return;
      const id   = row.cells[0].textContent.trim();
      const total = parseInt(row.cells[7]?.querySelector('input')?.value ?? row.cells[7]?.textContent, 10) || 0;
      const w = parseInt(row.cells[8]?.querySelector('input')?.value ?? row.cells[8]?.textContent, 10) || 0;
      const nw = parseInt(row.cells[9]?.querySelector('input')?.value ?? row.cells[9]?.textContent, 10) || 0;
      const m = parseInt(row.cells[10]?.querySelector('input')?.value ?? row.cells[10]?.textContent, 10) || 0;
      const borrowable = row.dataset.borrowable === '1';
      const text = getInventoryRowSearchText(row);
      let show   = true;
      if (fv === 'totalqty')        show = total > 0;
      else if (fv === 'working')    show = w > 0;
      else if (fv === 'notworking') show = nw > 0;
      else if (fv === 'maintenance') show = m > 0;
      else if (fv === 'borrowable') show = borrowable;
      else if (fv === 'restricted') show = !borrowable;
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
      loadInventoryMetadata(items);

      items.forEach(item => {
        currentEquipmentIDs.add(item.equipment_id);

        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.dataset.borrowable = isEquipmentBorrowable(item) ? '1' : '0';
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
          <td>${renderBorrowingStatusBadge(item)}</td>
          <td>${renderConditionQtyInput(item, 'total')}</td>
          <td>${renderConditionQtyInput(item, 'working')}</td>
          <td>${renderConditionQtyInput(item, 'notWorking')}</td>
          <td>${renderConditionQtyInput(item, 'maintenance')}</td>
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
          if (e.target.closest('.hist-btn') || e.target.closest('.condition-qty-input')) return;
          if (editingRow === row) return;
          if (editingRow && editingRow !== row) { cancelInlineEdit(); return; }

          if (selectedRow && selectedRow !== row) selectedRow.classList.remove('selected');
          row.classList.add('selected');
          selectedRow = row;
          selectedItemData = item;
        });

        // Double click → enter inline edit immediately
        row.addEventListener('dblclick', function(e) {
          if (e.target.closest('.hist-btn') || e.target.closest('.condition-qty-input')) return;
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
        if (!r.cells || r.cells.length < 13) return;
        const id   = r.cells[0].textContent.trim();
        const total = parseInt(r.cells[7]?.querySelector('input')?.value ?? r.cells[7]?.textContent, 10) || 0;
        const w = parseInt(r.cells[8]?.querySelector('input')?.value ?? r.cells[8]?.textContent, 10) || 0;
        const nw = parseInt(r.cells[9]?.querySelector('input')?.value ?? r.cells[9]?.textContent, 10) || 0;
        const m = parseInt(r.cells[10]?.querySelector('input')?.value ?? r.cells[10]?.textContent, 10) || 0;
        const borrowable = r.dataset.borrowable === '1';
        const text = getInventoryRowSearchText(r);
        let show   = true;
        if (fv === 'totalqty')        show = total > 0;
        else if (fv === 'working')    show = w > 0;
        else if (fv === 'notworking') show = nw > 0;
        else if (fv === 'maintenance') show = m > 0;
        else if (fv === 'borrowable') show = borrowable;
        else if (fv === 'restricted') show = !borrowable;
        else if (fv !== 'all')        show = id.charAt(0).toLowerCase() === map[fv];
        r.style.display = (show && text.includes(kw)) ? '' : 'none';
      });
    },
    error: function() {
      container.innerHTML = '<tr><td colspan="13">Error loading inventory</td></tr>';
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
  } else if (sectionName === 'Borrow Requests') {
    loadBorrowRequestsAndUpdateCount();
  } else if (sectionName === 'Inventory') {
    loadInventory();
    refreshHistTabCount();
  } else if (sectionName === 'Reports') {
    loadReports();
    moveScheduleSummaryToReports();
    if (document.getElementById('reportsPanelSummary')?.classList.contains('active')) {
      initScheduleCharts();
      setTimeout(() => {
        if (trendChart && typeof trendChart.resize === 'function') trendChart.resize();
      }, 80);
    }
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

      const cssVar = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
      const statusColors = {
        All:      { border: cssVar('--chart-default'), bg: cssVar('--chart-default-soft') },
        Accepted: { border: cssVar('--accent'),       bg: cssVar('--accent-soft')        },
        Pending:  { border: cssVar('--warn'),         bg: cssVar('--warn-soft')          },
        Rejected: { border: cssVar('--danger'),       bg: cssVar('--danger-soft')        },
      };
      const color = statusColors[status] ?? statusColors.All;

      trendChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [{
            label: 'Borrow Requests',
            data: data.counts,
            borderColor: color.border,
            backgroundColor: color.bg,
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
        <td class="centered-cell">${item.maintenance_qty ?? 0}</td>
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
          <div style="font-size:11px;color:var(--text-3);">Borrower #${escHtml(req.guest_number)} · ${escHtml(req.student_id || '—')}</div>`;
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
        <strong>Borrower Number:</strong> ${request.guest_number}<br/>
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
              <tr><td><strong>Borrower Login Number:</strong> ${request.guest_number}</td><td><strong>Date:</strong> ${formatDateToDDMMYYYY(request.date)}</td></tr>
              <tr><td><strong>Borrower's Name:</strong> ${request.borrower_name}</td><td><strong>Instructor's Name:</strong> ${request.instructor_name}</td></tr>
              <tr><td><strong>Student ID:</strong> ${request.student_id}</td><td><strong>Subject Code:</strong> ${request.subject_code}</td></tr>
              <tr><td><strong>Department:</strong> ${request.department || '—'}</td><td><strong>Date(s) of Usage:</strong> ${formatDateToDDMMYYYY(request.usage_date)}</td></tr>
              <tr><td colspan="2"><strong>Room:</strong> ${request.room}</td></tr>
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
                <td colspan="2" style="padding-top:30px;">${buildHiromiApprovalBlock(true)}</td>
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
  const parts = String(dateStr).split(/[T\s]/)[0].split('-');
  if (parts.length === 3) return `${parts[1]}/${parts[2]}/${parts[0]}`;
  const d = new Date(dateStr); if (isNaN(d)) return dateStr;
  return `${String(d.getMonth()+1).padStart(2,'0')}/${String(d.getDate()).padStart(2,'0')}/${d.getFullYear()}`;
}

function buildReturnRemarksControl(remarksValue, quantity) {
  const qty = Number(quantity) || 0;
  const options = ['Good Condition', 'Not Working','Lost','Disposed'];
  if (qty > 1) options.push('Complete', 'Incomplete');

  const saved = String(remarksValue || '').trim();
  const selectedStandard = options.find(option => option.toLowerCase() === saved.toLowerCase()) || '';
  const useOther = saved && !selectedStandard;
  const optionHtml = [
    '<option value="">Select remarks</option>',
    ...options.map(option => `<option value="${escHtml(option)}"${selectedStandard === option ? ' selected' : ''}>${escHtml(option)}</option>`),
    `<option value="__other__"${useOther ? ' selected' : ''}>Others</option>`
  ].join('');

  return `
    <select class="return-remarks-select"
      style="font-family:var(--font);font-size:12px;padding:4px 7px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text-1);width:100%;box-sizing:border-box;">
      ${optionHtml}
    </select>
    <input type="text" class="return-remarks-other-input" value="${useOther ? escHtml(saved) : ''}" placeholder="Enter remarks"
      style="display:${useOther ? 'block' : 'none'};margin-top:6px;font-family:var(--font);font-size:12px;padding:4px 7px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text-1);width:100%;box-sizing:border-box;">
  `;
}

function getReturnRemarksValue(row) {
  const select = row.querySelector('.return-remarks-select');
  if (!select) return row.querySelector('.return-remarks-input')?.value || '';
  if (select.value === '__other__') {
    return row.querySelector('.return-remarks-other-input')?.value.trim() || '';
  }
  return select.value || '';
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
    if (res.success && document.getElementById('reportsSection')?.style.display !== 'none') {
      loadReports({ keepPage: true });
    }
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
function loadReportsLegacy() {
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
                ${buildReturnRemarksControl(remarksVal, eq.quantity)}
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
              <span style="color:var(--text-3);font-size:12px;margin-left:8px;">Borrower #${escHtml(req.guest_number)}</span>
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
            <div><span style="color:var(--text-3);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Department</span><br>${escHtml(req.department || '—')}</div>
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
      container.querySelectorAll('.return-remarks-select').forEach(select => {
        select.addEventListener('change', () => {
          const customInput = select.closest('td')?.querySelector('.return-remarks-other-input');
          if (!customInput) return;
          customInput.style.display = select.value === '__other__' ? 'block' : 'none';
          if (select.value === '__other__') customInput.focus();
        });
      });

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
              remarks:        getReturnRemarksValue(row)
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
          const req = json.data.find(entry => entry.borrowRequest.id == reqId)?.borrowRequest;
          const eqList = json.data.find(entry => entry.borrowRequest.id == reqId)?.equipmentList || [];
          const card = container.querySelector(`.report-entry[data-req-id="${reqId}"]`);
          if (!req) return;

          const currentReturnInfo = new Map();
          card?.querySelectorAll('tr[data-eq-name]').forEach(row => {
            currentReturnInfo.set(row.dataset.eqName, {
              returned_on: row.querySelector('.return-date-input')?.value || '',
              remarks: getReturnRemarksValue(row)
            });
          });

          // Build full equipment-borrowing form for PDF
          const eqRows = eqList.map(eq => {
            const liveInfo = currentReturnInfo.get(eq.equipment_name) || {};
            const returnedOn = liveInfo.returned_on ?? eq.returned_on;
            const remarks = liveInfo.remarks ?? eq.remarks;
            return `
              <tr>
                <td style="border: 1px solid #000; padding: 8px;">${escHtml(eq.equipment_name)}</td>
                <td style="border: 1px solid #000; padding: 8px; text-align: center;">${eq.quantity}</td>
                <td style="border: 1px solid #000; padding: 8px; text-align: center;">YES</td>
                <td style="border: 1px solid #000; padding: 8px;">${returnedOn ? formatDateToDDMMYYYY(returnedOn) : '—'}</td>
                <td style="border: 1px solid #000; padding: 8px;">${escHtml(remarks || '—')}</td>
              </tr>
            `;
          }).join('');

          const formHtml = `
            <div style="font-family: Arial, sans-serif; font-size: 12px; padding: 20px; max-width: 800px; margin: 0 auto;">
              <div style="text-align: center; margin-bottom: 20px;">
                <h3 style="margin: 4px 0;">EULOGIO "AMANG" RODRIGUEZ INSTITUTE OF SCIENCE AND TECHNOLOGY</h3>
                <h3 style="margin: 4px 0;">COLLEGE OF ARTS AND SCIENCES</h3>
                <h3 style="margin: 4px 0;">APPLIED PHYSICS DEPARTMENT</h3>
                <h2 style="margin: 10px 0;">Equipment-borrowing Form</h2>
              </div>

              <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                  <td style="padding: 5px;"><strong>Borrower Login Number:</strong> ${escHtml(req.guest_number)}</td>
                  <td style="padding: 5px;"><strong>Date:</strong> ${formatDateToDDMMYYYY(req.date)}</td>
                </tr>
                <tr>
                  <td style="padding: 5px;"><strong>Borrower's Name:</strong> ${escHtml(req.borrower_name)}</td>
                  <td style="padding: 5px;"><strong>Instructor's Name:</strong> ${escHtml(req.instructor_name || '—')}</td>
                </tr>
                <tr>
                  <td style="padding: 5px;"><strong>Student ID:</strong> ${escHtml(req.student_id || '—')}</td>
                  <td style="padding: 5px;"><strong>Subject Code:</strong> ${escHtml(req.subject_code || '—')}</td>
                </tr>
                <tr>
                  <td style="padding: 5px;"><strong>Department:</strong> ${escHtml(req.department || '—')}</td>
                  <td style="padding: 5px;"><strong>Date(s) of Usage:</strong> ${formatDateToDDMMYYYY(req.usage_date)}</td>
                </tr>
                <tr>
                  <td colspan="2" style="padding: 5px;"><strong>Room:</strong> ${escHtml(req.room || '—')}</td>
                </tr>
              </table>

              <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #000;">
                <thead>
                  <tr style="background: #f0f0f0;">
                    <th style="border: 1px solid #000; padding: 8px; text-align: left;">Equipment / Material</th>
                    <th style="border: 1px solid #000; padding: 8px; text-align: center;">Quantity</th>
                    <th style="border: 1px solid #000; padding: 8px; text-align: center;">Available in the lab?</th>
                    <th style="border: 1px solid #000; padding: 8px; text-align: left;">Returned on</th>
                    <th style="border: 1px solid #000; padding: 8px; text-align: left;">Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  ${eqRows}
                </tbody>
              </table>

              <div style="margin-bottom: 20px;">
                <strong>Borrower's Declaration of Commitment:</strong><br/>
                <em>"I will be accountable to any damage incurred in the equipment and will return the equipment promptly and in the same working condition it was borrowed."</em>
              </div>

              <table style="width: 100%; margin-top: 40px;">
                <tr>
                  <td colspan="2" style="padding: 20px; text-align: left; vertical-align: top;">
                    ${buildHiromiApprovalBlock(false)}
                  </td>
                </tr>
              </table>
            </div>
          `;

          const wrapper = document.createElement('div');
          wrapper.innerHTML = formHtml;

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
