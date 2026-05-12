<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrower Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* ─── RULES MODAL (2-slide scrollable) ─── */
        #rulesModal {
            display: flex;
            position: fixed;
            z-index: 1400;
            inset: 0;
            background: rgba(26,26,24,.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 24px 16px;
            box-sizing: border-box;
            flex-direction: column;
            align-items: stretch;
            min-height: 100vh;
            min-height: 100dvh;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .3s ease, visibility .3s ease;
        }
        #rulesModal.is-open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .rules-modal-panel {
            align-self: center;
            margin-block: auto;
            margin-inline: auto;
            width: min(520px, 100%);
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: 0 8px 40px rgba(0,0,0,.18);
            display: flex;
            flex-direction: column;
            max-height: min(calc(100dvh - 48px), 480px);
            overflow: hidden;
            transform: translateY(24px) scale(.97);
            opacity: 0;
            transition: transform .35s cubic-bezier(.34,1.2,.64,1), opacity .3s ease;
        }
        #rulesModal.is-open .rules-modal-panel {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        /* Header strip */
        .rules-header {
            padding: 14px 20px 10px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .rules-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .rules-header h2 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-1);
            margin: 0;
        }
        .rules-step-badge {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            background: var(--surface-2);
            color: var(--text-3);
            padding: 3px 10px;
            border-radius: 20px;
        }

        /* Slide dots */
        .rules-dots {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .rules-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--border);
            transition: background .2s, width .2s;
            cursor: default;
        }
        .rules-dot.active {
            background: var(--accent);
            width: 18px;
            border-radius: 3px;
        }

        /* Slides viewport */
        .rules-slides-viewport {
            overflow: hidden;
            flex: 1;
            min-height: 0;
        }
        .rules-slides-track {
            display: flex;
            width: 200%;
            height: 100%;
            transition: transform .38s cubic-bezier(.4,0,.2,1);
        }
        .rules-slide {
            width: 50%;
            height: 100%;
            overflow-y: auto;
            padding: 14px 20px;
            box-sizing: border-box;
            -webkit-overflow-scrolling: touch;
        }
        .rules-slide::-webkit-scrollbar { width: 4px; }
        .rules-slide::-webkit-scrollbar-track { background: transparent; }
        .rules-slide::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        .rules-slide-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-2);
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 8px;
        }
        .rules-slide ol {
            padding-left: 18px;
            margin: 0;
        }
        .rules-slide ol li {
            font-size: 13.5px;
            color: var(--text-2);
            line-height: 1.65;
            margin-bottom: 8px;
            padding-left: 4px;
        }
        .rules-slide ol li strong {
            color: var(--text-1);
        }

        /* Scroll hint */
        .scroll-hint {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-3);
            margin-top: 10px;
            padding: 8px 12px;
            background: var(--surface-2);
            border-radius: var(--radius);
            transition: opacity .3s ease;
        }
        .scroll-hint svg { flex-shrink: 0; }

        /* Agree section — hidden until scrolled to bottom of slide 2 */
        .rules-agree-section {
            padding: 12px 20px 14px;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
            overflow: hidden;
            max-height: 0;
            opacity: 0;
            transition: max-height .4s cubic-bezier(.4,0,.2,1), opacity .3s ease, padding .3s ease;
            padding-top: 0;
            padding-bottom: 0;
        }
        .rules-agree-section.visible {
            max-height: 140px;
            opacity: 1;
            padding-top: 12px;
            padding-bottom: 14px;
        }
        .rules-agree-label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: var(--text-2);
            margin-bottom: 10px;
            cursor: pointer;
            line-height: 1.5;
        }
        .rules-agree-label input[type="checkbox"] {
            margin-top: 2px;
            flex-shrink: 0;
            width: 15px; height: 15px;
            accent-color: var(--accent);
        }
        .rules-agree-btn {
            width: 100%;
            padding: 11px;
            background: var(--accent);
            color: #fff;
            border: 1px solid var(--accent);
            border-radius: var(--radius);
            font-family: var(--font);
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background var(--transition), opacity var(--transition);
        }
        .rules-agree-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }
        .rules-agree-btn:not(:disabled):hover {
            background: #245A40;
        }

        /* Footer nav (Next button) */
        .rules-footer {
            padding: 10px 20px 12px;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .rules-nav-btn {
            font-family: var(--font);
            font-size: 13px;
            font-weight: 500;
            padding: 8px 18px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            background: var(--surface-2);
            color: var(--text-2);
            cursor: pointer;
            transition: background var(--transition);
        }
        .rules-nav-btn:hover { background: var(--border); }
        .rules-nav-btn:disabled { opacity: .35; cursor: not-allowed; }
        .rules-nav-next {
            background: var(--text-1);
            color: #fff;
            border-color: var(--text-1);
        }
        .rules-nav-next:hover { background: var(--accent); border-color: var(--accent); }

        /* ─── BORROWER FORM OVERLAY ─── */
        .guest-borrower-overlay {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .25s ease, visibility .25s ease;
        }
        .guest-borrower-overlay.is-open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            display: flex !important;
            flex-direction: column;
            align-items: stretch;
            padding: 24px 16px;
            box-sizing: border-box;
            width: 100%;
            max-width: 100vw;
            min-height: 100vh;
            min-height: 100dvh;
            isolation: isolate;
        }
        .guest-modal-panel {
            transition: transform .32s cubic-bezier(.4,0,.2,1), opacity .28s ease;
            transform: translateY(20px);
            opacity: 0;
        }
        .guest-borrower-overlay.is-open .guest-modal-panel {
            transform: translateY(0);
            opacity: 1;
        }

        /* Step badge inside form header */
        .form-step-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            background: var(--surface-2);
            color: var(--text-3);
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 14px;
        }
        .form-step-badge .step-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--accent);
        }

        /* ─── REVIEW MODAL ─── */
        .review-overlay {
            display: flex;
            position: fixed;
            z-index: 1300;
            inset: 0;
            background: rgba(26,26,24,.55);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            padding: 24px 16px;
            box-sizing: border-box;
            flex-direction: column;
            align-items: stretch;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-y: auto;
            isolation: isolate;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .28s ease, visibility .28s ease;
        }
        .review-overlay.is-open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .review-panel {
            align-self: center;
            margin-block: auto;
            margin-inline: auto;
            width: min(760px, 100%);
            max-height: min(calc(100dvh - 48px), calc(100vh - 48px));
            overflow-y: auto;
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: 0 8px 40px rgba(0,0,0,.18);
            padding: 32px 36px 28px;
            position: relative;
            transform: translateY(32px) scale(.97);
            opacity: 0;
            transition: transform .32s cubic-bezier(.34,1.3,.64,1), opacity .28s ease;
        }
        .review-overlay.is-open .review-panel {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        .review-panel h2 {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-1);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .review-badge {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            background: var(--accent-soft);
            color: var(--accent);
            padding: 3px 8px;
            border-radius: 20px;
        }
        .review-section-title {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--text-3);
            margin: 18px 0 10px;
        }
        .review-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 24px;
        }
        .review-field { display: flex; flex-direction: column; gap: 3px; }
        .review-field .r-label { font-size: 11px; color: var(--text-3); font-weight: 500; text-transform: uppercase; letter-spacing: .05em; }
        .review-field .r-value { font-size: 13.5px; color: var(--text-1); }
        .review-eq-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 4px; }
        .review-eq-table thead th {
            background: var(--surface-2); color: var(--text-2); font-weight: 600;
            font-size: 11px; letter-spacing: .06em; text-transform: uppercase;
            padding: 8px 12px; border-bottom: 1px solid var(--border); text-align: left;
        }
        .review-eq-table tbody td { padding: 9px 12px; border-bottom: 1px solid var(--border); color: var(--text-1); }
        .review-eq-table tbody tr:last-child td { border-bottom: none; }
        .review-actions {
            display: flex; gap: 10px; justify-content: flex-end;
            margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border);
        }
        .review-edit-btn {
            background: var(--surface-2) !important; color: var(--text-1) !important;
            border: 1px solid var(--border) !important; padding: 10px 22px !important;
            font-size: 13px !important; border-radius: var(--radius) !important;
            cursor: pointer !important; font-family: var(--font) !important;
            transition: background var(--transition) !important;
        }
        .review-edit-btn:hover { background: var(--border) !important; }
        .review-confirm-btn {
            background: var(--accent) !important; color: #fff !important;
            border: 1px solid var(--accent) !important; padding: 10px 22px !important;
            font-size: 13px !important; font-weight: 600 !important;
            border-radius: var(--radius) !important; cursor: pointer !important;
            font-family: var(--font) !important; display: flex !important;
            align-items: center !important; gap: 6px !important;
            transition: background var(--transition), transform var(--transition) !important;
        }
        .review-confirm-btn:hover { background: #245A40 !important; transform: translateY(-1px) !important; }
        .review-confirm-btn:active { transform: translateY(0) !important; }

        /* ── EQUIPMENT CARD GRID ── */
        .eq-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 14px;
            padding: 4px 0 16px;
        }
        .eq-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            cursor: pointer;
            transition: border-color var(--transition), box-shadow var(--transition), transform var(--transition);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .eq-card:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
        }
        .eq-card.selected {
            border-color: var(--accent);
            box-shadow: 0 0 0 2px var(--accent-soft), 0 4px 16px rgba(0,0,0,.08);
        }
        .eq-card.disabled {
            opacity: .5;
            cursor: not-allowed;
            pointer-events: none;
        }
        .eq-card-img {
            width: 100%;
            aspect-ratio: 4/3;
            object-fit: cover;
            display: block;
            background: var(--surface-2);
        }
        .eq-card-img-placeholder {
            width: 100%;
            aspect-ratio: 4/3;
            background: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-3);
        }
        .eq-card-body {
            padding: 10px 11px 11px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1;
        }
        .eq-card-name {
            font-size: 12.5px;
            font-weight: 500;
            color: var(--text-1);
            line-height: 1.35;
        }
        .eq-card-id {
            font-size: 11px;
            color: var(--text-3);
            font-family: var(--mono, monospace);
        }
        .eq-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 6px;
        }
        .eq-card-avail {
            font-size: 11px;
            font-weight: 500;
        }
        .eq-card-avail.ok   { color: var(--accent); }
        .eq-card-avail.none { color: var(--danger, #e24b4a); }
        .eq-card-check {
            width: 18px; height: 18px;
            accent-color: var(--accent);
            cursor: pointer;
            flex-shrink: 0;
        }
        .eq-card-selected-badge {
            position: absolute;
            top: 7px; right: 7px;
            width: 22px; height: 22px;
            border-radius: 50%;
            background: var(--accent);
            display: none;
            align-items: center;
            justify-content: center;
        }
        .eq-card.selected .eq-card-selected-badge { display: flex; }
        .eq-card-selected-badge svg { width: 12px; height: 12px; }

        /* ═══ DEPARTMENT-ROOM CONDITIONAL SELECT ═══ */
        #departmentSelect {
            transition: border-color 0.28s ease, opacity 0.28s ease;
        }
        #roomSelect {
            transition: border-color 0.28s ease, opacity 0.28s ease, background-color 0.28s ease, color 0.28s ease;
            cursor: pointer;
        }
        #roomSelect:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: var(--surface-2);
            color: var(--text-3);
            border-color: var(--border);
        }
        #roomSelect:not(:disabled) {
            opacity: 1;
            border-color: var(--accent);
        }
        #roomSelect:not(:disabled):hover {
            background-color: var(--surface-2);
        }
        #roomSelect:not(:disabled):focus {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }
        /* Custom text input for "Others" department */
        #roomCustomInput {
            transition: border-color 0.28s ease, opacity 0.28s ease;
            display: none;
            width: 200px;
        }
        #roomCustomInput.show {
            display: inline-block;
        }
        #roomCustomInput:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ═══ DEPARTMENT-INSTRUCTOR CONDITIONAL SELECT ═══ */
        #instructorName {
            transition: border-color 0.28s ease, opacity 0.28s ease, background-color 0.28s ease, color 0.28s ease;
            cursor: pointer;
        }
        #instructorName:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: var(--surface-2);
            color: var(--text-3);
            border-color: var(--border);
        }
        #instructorName:not(:disabled) {
            opacity: 1;
            border-color: var(--accent);
        }
        #instructorName:not(:disabled):hover {
            background-color: var(--surface-2);
        }
        #instructorName:not(:disabled):focus {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }
        /* Custom text input for "Others" instructor */
        #instructorCustomInput {
            transition: border-color 0.28s ease, opacity 0.28s ease;
            display: none;
            width: 200px;
        }
        #instructorCustomInput.show {
            display: inline-block;
        }
        #instructorCustomInput:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Custom text input for "Other" instructor */
        #instructorCustomInput {
            transition: border-color 0.28s ease, opacity 0.28s ease;
            display: none !important;
            width: 200px;
        }
        #instructorCustomInput.show {
            display: inline-block !important;
        }
        #instructorCustomInput:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Hide select using class */
        select.hidden-input {
            display: none !important;
        }

        /* Align instructor inputs container */
        #instructorInputsContainer {
            display: inline-flex;
            gap: 0;
            align-items: center;
        }
        #instructorInputsContainer select,
        #instructorInputsContainer input[type="text"] {
            margin: 0;
        }

        /* Align room inputs */
        #roomInputsContainer {
            display: inline-flex;
            gap: 0;
            align-items: center;
        }
        #roomInputsContainer select,
        #roomInputsContainer input[type="text"] {
            margin: 0;
        }

        /* Dynamic asterisk */
        .required-indicator {
            color: var(--accent);
            font-weight: 700;
            display: inline;
            transition: opacity 0.2s ease;
        }
        .required-indicator.hidden {
            display: none;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <span class="logo-icon">⬡</span>
            <h2>EQUILAB</h2>
        </div>
        <nav>
            <a href="#" class="nav-item active" onclick="showEquipment()">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <span class="nav-label">Equipment</span>
            </a>
        </nav>
        <div class="logout">
            <a href="logout.php" class="nav-item">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/></svg>
                <span class="nav-label">Log Out</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div id="topBar" class="top-bar">
            <header>
                <h1>Borrower</h1>
                <p id="borrowerLoginNumber" style="font-size:1rem;color:#555;"></p>
            </header>
        </div>

        <div class="dashboard-container">
            <div id="equipmentSection">
                <div class="category-dropdown">
                    <select id="categorySelect">
                        <option value="all">All</option>
                        <option value="equipment">Equipment</option>
                        <option value="measuring">Measuring Tools</option>
                        <option value="chemicals">Chemicals</option>
                        <option value="books">Books</option>
                    </select>
                </div>
                <div class="search-bar">
                    <input type="text" placeholder="Search" id="searchInput">
                </div>
                <h1>Equipment</h1>
                <div id="equipmentList" class="eq-card-grid"></div>
                <div id="borrowerFormActions" class="borrower-actions" aria-live="polite">
                    <button type="button" id="openBorrowerFormBtn" disabled aria-disabled="true"
                        title="Select at least one item from the table">Open Borrower Form</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ RULES MODAL (2 slides, scroll-to-reveal) ══════════ -->
    <div id="rulesModal">
        <div class="rules-modal-panel">

            <div class="rules-header">
                <div class="rules-header-top">
                    <h2>Rules &amp; Regulations</h2>
                    <span class="rules-step-badge" id="rulesStepLabel">Slide 1 of 2</span>
                </div>
                <div class="rules-dots">
                    <div class="rules-dot active" id="rulesDot0"></div>
                    <div class="rules-dot"        id="rulesDot1"></div>
                </div>
            </div>

            <div class="rules-slides-viewport">
                <div class="rules-slides-track" id="rulesTrack">

                    <!-- Slide 1: Rules 1–3 -->
                    <div class="rules-slide" id="rulesSlide0">
                        <p class="rules-slide-title">General borrowing rules</p>
                        <ol>
                            <li><strong>Condition on return.</strong> Equipment must be returned in the exact same working condition it was borrowed. Any damage incurred is the responsibility of the borrower.</li>
                            <li><strong>Operating hours only.</strong> Borrowing and returning of equipment is only allowed during official laboratory operating hours. Requests outside these hours will not be entertained.</li>
                            <li><strong>Immediate damage reporting.</strong> Any damage, malfunction, or loss discovered during use must be reported immediately to the laboratory technician. Do not attempt to repair equipment on your own.</li>
                        </ol>
                        <div class="scroll-hint" id="scrollHint0">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                            Scroll down to continue
                        </div>
                    </div>

                    <!-- Slide 2: Rules 4–5 -->
                    <div class="rules-slide" id="rulesSlide1">
                        <p class="rules-slide-title">Responsibility &amp; access</p>
                        <ol start="4">
                            <li><strong>Timely returns.</strong> Equipment must be returned promptly on the agreed return date. Late returns may result in a temporary or permanent suspension of borrowing privileges.</li>
                            <li><strong>Registered borrowers only.</strong> Only borrowers with a valid Borrower Login Number issued by the system are permitted to borrow laboratory equipment. Lending your borrower number to another person is strictly prohibited.</li>
                        </ol>
                        <div class="scroll-hint" id="scrollHint1">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                            Scroll down to accept
                        </div>
                    </div>

                </div>
            </div>

            <!-- Agree section: only appears after scrolling to bottom of slide 2 -->
            <div class="rules-agree-section" id="rulesAgreeSection">
                <label class="rules-agree-label">
                    <input type="checkbox" id="rulesCheckbox">
                    I have read and agree to follow all the rules and regulations listed above.
                </label>
                <button class="rules-agree-btn" id="rulesAgreeBtn" disabled>Agree &amp; Continue</button>
            </div>

            <div class="rules-footer">
                <button class="rules-nav-btn" id="rulesPrevBtn" disabled>← Back</button>
                <button class="rules-nav-btn rules-nav-next" id="rulesNextBtn">Next →</button>
            </div>

        </div>
    </div>

    <!-- ══════════ BORROWER FORM OVERLAY ══════════ -->
    <div id="borrowerFormModal" class="guest-borrower-overlay">
        <div class="guest-modal-panel">
            <button type="button" id="closeBorrowerFormBtn" class="guest-modal-close" aria-label="Close">&times;</button>

            <!-- Step 1 of 2 badge -->
            <div style="margin-bottom: 4px;">
                <span class="form-step-badge">
                    <span class="step-dot"></span>
                    Step 1 of 2 — Borrower Form
                </span>
            </div>

            <form id="borrowerForm" class="form-container">
                <table class="guest-form-table">
                    <tr>
                        <td colspan="2" style="text-align:center;">
                            <h4 style="margin:4px 0;">EULOGIO "AMANG" RODRIGUEZ INSTITUTE OF SCIENCE AND TECHNOLOGY</h4>
                            <h4 style="margin:4px 0;">COLLEGE OF ARTS AND SCIENCES</h4>
                            <h4 style="margin:4px 0;">APPLIED PHYSICS DEPARTMENT</h4>
                            <h3 style="margin:10px 0;">Equipment-borrowing Form</h3>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:5px;text-align:left;">
                            <strong>Borrower Login Number:</strong>
                            <span id="borrowerGuestNumber">________________________</span>
                        </td>
                        <td style="padding:5px;text-align:left;">
                            <strong>Date:</strong>
                            <span id="borrowDate">________________________</span>
                            <input type="hidden" id="borrowDateForDB" name="borrowDateForDB"/>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:5px;text-align:left;">
                            <strong>Borrower's Name: <span class="required-indicator" id="borrowerRequired">*</span></strong>
                            <span style="display:inline-flex;align-items:center;gap:4px;margin-left:6px;">
                                <input type="text" id="lastName"      placeholder="Last Name"  style="width:80px;">
                                <input type="text" id="firstName"     placeholder="First Name" style="width:80px;">
                                <input type="text" id="middleInitial" placeholder="M.I."       maxlength="1" style="width:30px;">
                            </span>
                            <input type="hidden" id="borrowerName" name="borrowerName">
                        </td>
                        <td style="padding:5px;text-align:left;">
                            <strong>Instructor's Name: <span class="required-indicator" id="instructorRequired">*</span></strong>
                            <div id="instructorInputsContainer">
                                <select id="instructorName" style="width:200px;" disabled>
                                    <option value="" disabled selected>Select a department first</option>
                                </select>
                                <input type="text" id="instructorCustomInput" placeholder="Enter instructor name" style="width:200px;">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:5px;text-align:left;">
                            <strong>Student ID: <span class="required-indicator" id="studentIdRequired">*</span></strong>
                            <input type="text" id="studentID" maxlength="10" style="width:200px;" placeholder="Enter Student ID (max 10)">
                        </td>
                        <td style="padding:5px;text-align:left;">
                            <strong>Subject Code: <span class="required-indicator" id="subjectCodeRequired">*</span></strong>
                            <input type="text" id="subjectCode" style="width:200px;" placeholder="Enter Subject Code">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:5px;text-align:left;">
                            <strong>Date(s) of Usage of Equipment: <span class="required-indicator" id="usageDateRequired">*</span></strong>
                            <input type="date" id="usageDate" style="width:160px;">
                        </td>
                        <td style="padding:5px;text-align:left;">
                            <strong>Department: <span class="required-indicator" id="departmentRequired">*</span></strong>
                            <select id="departmentSelect" style="width:200px;">
                                <option value="" disabled selected>Select a department</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding:5px;text-align:left;">
                            <strong>Room: <span class="required-indicator" id="roomRequired">*</span></strong>
                            <div id="roomInputsContainer">
                                <select id="roomSelect" style="width:200px;" disabled>
                                    <option value="" disabled selected>Select a department first</option>
                                </select>
                                <input type="text" id="roomCustomInput" placeholder="Enter your room" style="width:200px;">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding-top:15px;">
                            <table style="width:100%;border-collapse:collapse;" border="1">
                                <thead>
                                    <tr style="text-align:center;">
                                        <th>Equipment / Material</th>
                                        <th>Quantity</th>
                                        <th>Available in the lab?</th>
                                        <th>Returned on</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody id="equipmentListInForm"></tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding-top:20px;">
                            <strong>Borrower's Declaration of Commitment:</strong><br>
                            <em>"I will be accountable to any damage incurred in the equipment and will return the equipment promptly and in the same working condition it was borrowed."</em>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding-top:30px;text-align:left;">
                            <p>Approved by:<br><br>
                            <span class="hiromi-esig-slot"></span>
                            <em style="display:block;margin-top:4px;">Mr. Hiromi Rivas</em>
                            <em style="display:block;">Applied Physics Professor</em></p>
                        </td>
                    </tr>
                </table>

                <div style="text-align:center;padding-top:20px;">
                    <!-- "Continue" replaces old "Submit Borrow Request" -->
                    <button id="submitButton" type="button"
                        onclick="combineBorrowerName(); openReviewModal()">
                        Continue →
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════ REVIEW MODAL (Step 2 of 2) ══════════ -->
    <div id="reviewModal" class="review-overlay">
        <div class="review-panel">
            <h2>
                Review your request
                <span class="review-badge">Step 2 of 2</span>
            </h2>

            <p class="review-section-title">Borrower details</p>
            <div class="review-grid">
                <div class="review-field"><span class="r-label">Borrower Login No.</span><span class="r-value" id="rv-guest"></span></div>
                <div class="review-field"><span class="r-label">Date</span><span class="r-value" id="rv-date"></span></div>
                <div class="review-field"><span class="r-label">Borrower's Name</span><span class="r-value" id="rv-name"></span></div>
                <div class="review-field"><span class="r-label">Instructor</span><span class="r-value" id="rv-instructor"></span></div>
                <div class="review-field"><span class="r-label">Student ID</span><span class="r-value" id="rv-student-id"></span></div>
                <div class="review-field"><span class="r-label">Subject Code</span><span class="r-value" id="rv-subject"></span></div>
                <div class="review-field"><span class="r-label">Department</span><span class="r-value" id="rv-department"></span></div>
                <div class="review-field"><span class="r-label">Date of Usage</span><span class="r-value" id="rv-usage-date"></span></div>
                <div class="review-field"><span class="r-label">Room</span><span class="r-value" id="rv-room"></span></div>
            </div>

            <p class="review-section-title">Equipment to borrow</p>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
                <table class="review-eq-table">
                    <thead>
                        <tr><th>#</th><th>Equipment / Material</th><th>Quantity</th></tr>
                    </thead>
                    <tbody id="rv-equipment-list"></tbody>
                </table>
            </div>

            <div class="review-actions">
                <button type="button" class="review-edit-btn" id="reviewEditBtn">← Edit</button>
                <button type="button" class="review-confirm-btn" id="reviewConfirmBtn">
                    Confirm &amp; Submit
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════ GUEST NUMBER CONFIRM MODAL ══════════ -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <h2>Confirm Borrowing Request</h2>
            <p>Please re-enter your Borrower Login Number to confirm:</p>
            <input type="text" id="confirmGuestNumber" placeholder="Enter Borrower Login Number">
            <button id="confirmBtn" onclick="submitBorrowRequest()">Confirm</button>
            <button id="cancelBtn" onclick="closeConfirmationModal()">Cancel</button>
        </div>
    </div>

    <!-- ══════════ FINAL (photo-this) MODAL ══════════ -->
    <div id="finalConfirmationModal" class="guest-final-overlay">
        <div class="guest-final-panel">
            <h2>Please Take a Picture of This Form</h2>
            <p>Make sure to capture this confirmation for your records.</p>
            <div id="finalFormData"></div>
            <button type="button" id="closeFinalModalBtn" class="guest-final-close">Close</button>
        </div>
    </div>

    <script src="guest.js?v=20260501b"></script>
</body>
</html>
