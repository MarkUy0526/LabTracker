<?php
session_start();
require 'db.php';
require 'return_photo_helpers.php';

ensureReturnPhotoColumns($conn);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $guestNumber = trim($_POST['guest_number'] ?? '');

    if (!$guestNumber) {
        echo json_encode(['success' => false, 'message' => 'Login ID is required.']);
        exit;
    }

    // Fetch all requests for this guest
    $sql = "SELECT * FROM borrow_requests WHERE guest_number = ? ORDER BY date DESC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }

    $stmt->bind_param("s", $guestNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No requests found for this Login ID.']);
        exit;
    }

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requestID = $row['id'];

        // Get equipment for this request
        $eqSql = "SELECT * FROM borrowed_equipment WHERE borrow_request_id = ?";
        $eqStmt = $conn->prepare($eqSql);
        $eqStmt->bind_param("i", $requestID);
        $eqStmt->execute();
        $eqResult = $eqStmt->get_result();

        $equipment = [];
        while ($eqRow = $eqResult->fetch_assoc()) {
            $equipment[] = $eqRow;
        }

        $requests[] = [
            'request' => $row,
            'equipment' => $equipment
        ];
    }

    echo json_encode([
        'success' => true,
        'guest_number' => $guestNumber,
        'requests' => $requests
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EQUILAB — Check Request Status</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --ink:       #0f0f0f;
      --paper:     #f5f2ec;
      --cream:     #ede9e0;
      --accent:    #c8502a;
      --accent2:   #e8a87c;
      --muted:     #8a8478;
      --border:    #d4cfc5;
      --white:     #ffffff;
      --radius:    14px;
      --shadow:    0 4px 32px rgba(15,15,15,0.10);
      --green:     #4CAF50;
      --orange:    #FF9800;
      --red:       #F44336;
      --blue:      #2196F3;
    }

    html, body {
      height: 100%;
      font-family: 'DM Sans', sans-serif;
      background-color: var(--paper);
      color: var(--ink);
    }

    .container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 40px 20px;
    }

    .header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 32px;
    }

    .logo {
      width: 36px;
      height: 36px;
      background: var(--ink);
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .logo svg { width: 20px; height: 20px; stroke: white; }

    .header-title {
      display: flex;
      flex-direction: column;
    }

    .header-label {
      font-size: 0.72rem;
      font-weight: 500;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 3px;
    }

    .header-name {
      font-family: 'Syne', sans-serif;
      font-weight: 800;
      font-size: 1.4rem;
      letter-spacing: -0.01em;
      color: var(--ink);
    }

    .search-section {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 24px;
      margin-bottom: 32px;
      box-shadow: 0 1px 6px rgba(15,15,15,0.05);
    }

    .search-title {
      font-size: 0.92rem;
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 16px;
    }

    .search-form {
      display: flex;
      gap: 12px;
      align-items: flex-end;
      flex-wrap: wrap;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
      min-width: 200px;
    }

    .form-label {
      font-size: 0.76rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: var(--muted);
    }

    .form-input {
      padding: 12px 16px;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.93rem;
      color: var(--ink);
      background: var(--paper);
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-input::placeholder { color: #bbb7ae; }
    .form-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(200,80,42,0.10);
      background: var(--white);
    }

    .btn-primary {
      padding: 12px 24px;
      background: var(--accent);
      color: var(--white);
      border: none;
      border-radius: 8px;
      font-family: 'Syne', sans-serif;
      font-weight: 600;
      font-size: 0.9rem;
      letter-spacing: 0.04em;
      cursor: pointer;
      transition: all 0.22s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-primary:hover {
      background: #a83d1f;
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(200,80,42,0.28);
    }

    .btn-primary:active { transform: translateY(0); }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

    .spinner {
      width: 14px;
      height: 14px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      display: inline-block;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    .error-message {
      color: var(--red);
      font-size: 0.85rem;
      margin-top: 12px;
      min-height: 20px;
      text-align: center;
    }

    .results-section {
      display: none;
    }

    .results-section.show { display: block; }

    .request-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 24px;
      margin-bottom: 20px;
      box-shadow: 0 1px 6px rgba(15,15,15,0.05);
    }

    .request-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--border);
    }

    .request-info h3 {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 8px;
    }

    .request-info p {
      font-size: 0.84rem;
      color: var(--muted);
      margin-bottom: 4px;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 20px;
      font-size: 0.82rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .status-pending {
      background: #fff3cd;
      color: #b45309;
    }

    .status-approved {
      background: #d4edda;
      color: #2e7d32;
    }

    .status-denied {
      background: #fde8e8;
      color: #c62828;
    }

    .status-released {
      background: #d1ecf1;
      color: #0c5460;
    }

    .status-returned {
      background: #e2e3e5;
      color: #383d41;
    }

    .status-not-returned {
      background: #f8d7da;
      color: #721c24;
    }

    .request-details {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }

    .detail-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .detail-label {
      font-size: 0.76rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: var(--muted);
    }

    .detail-value {
      font-size: 0.92rem;
      color: var(--ink);
      font-weight: 500;
    }

    .equipment-section {
      margin-top: 20px;
    }

    .equipment-title {
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .equipment-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .equipment-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px;
      background: var(--cream);
      border-radius: 8px;
      border: 1px solid var(--border);
    }

    .equipment-name {
      font-size: 0.9rem;
      font-weight: 500;
      color: var(--ink);
    }

    .equipment-qty {
      font-size: 0.84rem;
      color: var(--muted);
      font-weight: 600;
    }

    .no-results {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 40px;
      text-align: center;
      color: var(--muted);
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--accent);
      text-decoration: none;
      font-size: 0.88rem;
      font-weight: 600;
      margin-top: 24px;
      transition: color 0.2s;
    }

    .back-link:hover { color: #a83d1f; }

    @media (max-width: 640px) {
      .container { padding: 20px 16px; }
      .search-form { flex-direction: column; }
      .form-group { min-width: auto; }
      .request-header { flex-direction: column; gap: 12px; }
      .request-details { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="container">

  <!-- Header -->
  <div class="header">
    <div class="logo">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4"/>
        <path d="M15 3h6v6"/><path d="M10 14 21 3"/>
      </svg>
    </div>
    <div class="header-title">
      <span class="header-label">Lab Equipment Tracker</span>
      <span class="header-name">Request Status</span>
    </div>
  </div>

  <!-- Search Section -->
  <div class="search-section">
    <div class="search-title">Check Your Request Status</div>
    <p style="font-size: 0.84rem; color: var(--muted); margin-bottom: 16px;">
      Enter your Login ID to view all your borrowing requests and their current status.
    </p>

    <form id="checkStatusForm" class="search-form">
      <div class="form-group" style="min-width: 300px;">
        <label class="form-label" for="guestNumber">Login ID</label>
        <input
          class="form-input"
          type="text"
          id="guestNumber"
          placeholder="e.g., 04132601"
          autocomplete="off"
          required
        />
      </div>
      <button class="btn-primary" type="submit" id="checkBtn">
        <span id="checkBtnText">Check Status</span>
      </button>
    </form>
    <div class="error-message" id="errorMessage"></div>
  </div>

  <!-- Results Section -->
  <div class="results-section" id="resultsSection">
    <div id="resultsContainer"></div>
    <a href="#" onclick="resetForm(); return false;" class="back-link">
      ← Check another Login ID
    </a>
  </div>

</div>

<script>
  document.getElementById('checkStatusForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const guestNumber = document.getElementById('guestNumber').value.trim();
    const checkBtn = document.getElementById('checkBtn');
    const checkBtnText = document.getElementById('checkBtnText');
    const errorMessage = document.getElementById('errorMessage');
    const resultsSection = document.getElementById('resultsSection');
    const resultsContainer = document.getElementById('resultsContainer');

    if (!guestNumber) {
      errorMessage.textContent = 'Please enter your Login ID.';
      return;
    }

    checkBtn.disabled = true;
    checkBtnText.innerHTML = '<span class="spinner"></span>';
    errorMessage.textContent = '';

    const formData = new FormData();
    formData.append('guest_number', guestNumber);

    fetch('check_status.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      checkBtn.disabled = false;
      checkBtnText.textContent = 'Check Status';

      if (!data.success) {
        errorMessage.textContent = data.message || 'An error occurred.';
        resultsSection.classList.remove('show');
        return;
      }

      // Display results
      resultsContainer.innerHTML = '';

      const header = document.createElement('div');
      header.style.marginBottom = '24px';
      header.innerHTML = `
        <h2 style="font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--ink); margin-bottom: 4px;">
          Login ID: ${escapeHtml(data.guest_number)}
        </h2>
        <p style="color: var(--muted); font-size: 0.88rem;">
          ${data.requests.length} request${data.requests.length !== 1 ? 's' : ''} found
        </p>
      `;
      resultsContainer.appendChild(header);

      if (data.requests.length === 0) {
        resultsContainer.innerHTML = '<div class="no-results">No requests found.</div>';
        resultsSection.classList.add('show');
        return;
      }

      data.requests.forEach((req, idx) => {
        const r = req.request;
        const card = document.createElement('div');
        card.className = 'request-card';

        const statusClass = 'status-' + (r.status || 'pending').toLowerCase();
        const statusDisplay = (r.status || 'Pending').charAt(0).toUpperCase() + (r.status || 'Pending').slice(1).toLowerCase();
        const verificationStatus = r.return_verification_status || 'Pending Verification';
        const hasReturnPhoto = !!r.return_photo_path;
        const canSubmitReturnPhoto = r.status === 'Approved' && verificationStatus !== 'Verified';

        let equipmentHtml = '<div class="equipment-section"><div class="equipment-title">Equipment Borrowed</div><div class="equipment-list">';

        if (req.equipment && req.equipment.length > 0) {
          req.equipment.forEach(eq => {
            equipmentHtml += `
              <div class="equipment-item">
                <span class="equipment-name">${escapeHtml(eq.equipment_name)}</span>
                <span class="equipment-qty">Qty: ${eq.quantity}</span>
              </div>
            `;
          });
        } else {
          equipmentHtml += '<div style="padding: 12px; text-align: center; color: var(--muted);">No equipment listed</div>';
        }

        equipmentHtml += '</div></div>';

        const returnPhotoHtml = r.status === 'Approved' ? `
          <div class="equipment-section">
            <div class="equipment-title">Return Photo Submission</div>
            <div class="equipment-list" style="padding: 14px;">
              <p style="font-size: 0.9rem; color: var(--muted); margin-bottom: 10px;">
                Verification Status: <strong>${escapeHtml(verificationStatus)}</strong>
              </p>
              ${hasReturnPhoto ? `
                <p style="font-size: 0.88rem; color: var(--muted); margin-bottom: 10px;">
                  Submitted: ${r.return_submitted_at ? new Date(r.return_submitted_at).toLocaleString() : 'Recorded'}
                </p>
                <a href="${escapeHtml(r.return_photo_path)}" target="_blank" rel="noopener" style="display:inline-block;margin-bottom:12px;color:var(--accent);font-weight:700;">View uploaded photo</a>
                <img src="${escapeHtml(r.return_photo_path)}" alt="Uploaded return photo" style="display:block;width:100%;max-width:360px;max-height:240px;object-fit:cover;border:1px solid var(--border);border-radius:12px;">
              ` : `
                <p style="font-size: 0.88rem; color: var(--muted); margin-bottom: 10px;">
                  Upload a clear photo showing all equipment being returned when staff cannot inspect it immediately.
                </p>
              `}
              ${canSubmitReturnPhoto ? `
                <form class="return-photo-form" data-request-id="${r.id}" style="margin-top:12px;display:grid;gap:10px;max-width:420px;">
                  <input type="file" name="return_photo" accept="image/*" capture="environment" required style="font:inherit;">
                  <button type="submit" class="submit-return-photo-btn" style="border:none;border-radius:10px;background:var(--ink);color:var(--white);padding:10px 14px;font-weight:800;cursor:pointer;">
                    ${hasReturnPhoto ? 'Replace Return Photo' : 'Submit Return Photo'}
                  </button>
                  <div class="return-photo-message" style="font-size:0.86rem;color:var(--muted);"></div>
                </form>
              ` : ''}
            </div>
          </div>
        ` : '';

        card.innerHTML = `
          <div class="request-header">
            <div class="request-info">
              <h3>Request #${r.id}</h3>
              <p><strong>Borrower:</strong> ${escapeHtml(r.borrower_name || 'N/A')}</p>
              <p><strong>Instructor:</strong> ${escapeHtml(r.instructor_name || 'N/A')}</p>
            </div>
            <span class="status-badge ${statusClass}">${statusDisplay}</span>
          </div>

          <div class="request-details">
            <div class="detail-item">
              <span class="detail-label">Date Requested</span>
              <span class="detail-value">${new Date(r.date).toLocaleDateString()}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Usage Date</span>
              <span class="detail-value">${new Date(r.usage_date).toLocaleDateString()}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Department</span>
              <span class="detail-value">${escapeHtml(r.department || 'N/A')}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Room</span>
              <span class="detail-value">${escapeHtml(r.room || 'N/A')}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Student ID</span>
              <span class="detail-value">${escapeHtml(r.student_id || 'N/A')}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Subject Code</span>
              <span class="detail-value">${escapeHtml(r.subject_code || 'N/A')}</span>
            </div>
          </div>

          ${equipmentHtml}
          ${returnPhotoHtml}
        `;

        resultsContainer.appendChild(card);
      });

      resultsSection.classList.add('show');
      attachReturnPhotoForms(data.guest_number);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    })
    .catch(err => {
      checkBtn.disabled = false;
      checkBtnText.textContent = 'Check Status';
      errorMessage.textContent = 'Network error. Please try again.';
      console.error(err);
    });
  });

  function resetForm() {
    document.getElementById('checkStatusForm').reset();
    document.getElementById('guestNumber').focus();
    document.getElementById('resultsSection').classList.remove('show');
    document.getElementById('errorMessage').textContent = '';
  }

  function attachReturnPhotoForms(guestNumber) {
    document.querySelectorAll('.return-photo-form').forEach(form => {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        const fileInput = form.querySelector('input[type="file"]');
        const message = form.querySelector('.return-photo-message');
        const btn = form.querySelector('.submit-return-photo-btn');

        if (!fileInput.files || !fileInput.files[0]) {
          message.textContent = 'Please select a return photo.';
          return;
        }

        const formData = new FormData();
        formData.append('borrow_request_id', form.dataset.requestId);
        formData.append('guest_number', guestNumber);
        formData.append('return_photo', fileInput.files[0]);

        btn.disabled = true;
        btn.textContent = 'Submitting...';
        message.textContent = '';

        fetch('submit_return_photo.php', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(res => {
          if (!res.success) {
            message.textContent = res.message || 'Upload failed.';
            btn.disabled = false;
            btn.textContent = 'Submit Return Photo';
            return;
          }

          message.textContent = res.message || 'Return photo submitted.';
          document.getElementById('checkStatusForm').dispatchEvent(new Event('submit'));
        })
        .catch(() => {
          message.textContent = 'Network error uploading return photo.';
          btn.disabled = false;
          btn.textContent = 'Submit Return Photo';
        });
      });
    });
  }

  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
  }

  // Focus on input on load
  document.getElementById('guestNumber').focus();
</script>

</body>
</html>
