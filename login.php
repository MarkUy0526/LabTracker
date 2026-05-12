<?php
session_start();
session_unset();
session_destroy();

/* ════════════════════════════════════════════════════════════════
   SLIDESHOW IMAGES CONFIG
   Place images in:  xampp/htdocs/LabTracker/images/
   List paths relative to this file. Leave a slot '' to skip it.
   JS reads count automatically — no other change needed.
   ════════════════════════════════════════════════════════════════ */
$slideImages = [
    'images/anoto.jpg',
    'images/Function Generator.jpg',
    'images/Oscilloscope.jpg',
    'images/Physics Lab Manual.jpg',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EQUILAB — Login</title>
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
      --ease-sine: cubic-bezier(0.37, 0, 0.63, 1);
    }

    html, body {
      height: 100%;
      font-family: 'DM Sans', sans-serif;
      background-color: var(--paper);
      color: var(--ink);
      overflow: hidden;
    }

    body::before {
      content: '';
      position: fixed; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
      pointer-events: none; z-index: 0;
    }

    body::after {
      content: '';
      position: fixed;
      width: 700px; height: 700px; border-radius: 50%;
      background: radial-gradient(circle at 30% 30%, #e8a87c33 0%, #c8502a18 50%, transparent 70%);
      top: -200px; right: -200px;
      pointer-events: none; z-index: 0;
    }

    .page {
      position: relative; z-index: 1;
      display: grid; grid-template-columns: 1fr 1fr;
      height: 100vh;
    }

    /* ═══════════════════════════════
       LEFT PANEL
    ═══════════════════════════════ */
    .left-panel {
      display: flex; flex-direction: column;
      justify-content: space-between;
      padding: 52px 60px;
      position: relative; overflow: hidden;
    }

    /* original bottom-left circle */
    .left-panel::after {
      content: '';
      position: absolute; bottom: -80px; left: -80px;
      width: 400px; height: 400px;
      border-radius: 50%; border: 1.5px solid var(--border); opacity: 0.6;
      z-index: 2; pointer-events: none;
    }

    /* ── decorative orbs & rings ── */
    .orb-tl {
      position: absolute; top: -130px; left: -110px;
      width: 360px; height: 360px; border-radius: 50%;
      background: radial-gradient(circle at 62% 58%, rgba(200,80,42,0.07) 0%, transparent 68%);
      pointer-events: none; z-index: 2;
    }
    .orb-mr {
      position: absolute; top: 28%; right: -70px;
      width: 240px; height: 340px; border-radius: 50%;
      background: radial-gradient(circle at 38% 50%, rgba(232,168,124,0.08) 0%, transparent 62%);
      pointer-events: none; z-index: 2;
    }
    .ring-sm {
      position: absolute; top: 43%; left: 50%;
      width: 88px; height: 88px; border-radius: 50%;
      border: 1px solid rgba(200,80,42,0.11);
      pointer-events: none; z-index: 2;
    }
    .ring-lg {
      position: absolute; top: 18%; left: 58%;
      width: 160px; height: 160px; border-radius: 50%;
      border: 1px solid rgba(212,207,197,0.45);
      pointer-events: none; z-index: 2;
    }

    /* ═══════════════════════════════
       PHOTO LAYER — full bleed behind text
       (matches what you made in the screenshot)
    ═══════════════════════════════ */
    .photo-strip {
      position: absolute;
      inset: 0;               /* full bleed — covers entire left panel */
      overflow: hidden;
      z-index: 1;             /* behind text (z5) and orbs (z2), above panel bg */
      pointer-events: none;
    }

    .photo-track {
      display: flex;
      flex-direction: column;
      width: 100%;
      /* height set by JS: totalSlides × 100% */
      transition: transform 1s var(--ease-sine);
      will-change: transform;
    }

    .photo-slide {
      flex: 0 0 100vh;
      width: 100%; height: 100vh;
      position: relative; overflow: hidden;
    }

    .photo-slide img {
      position: absolute; inset: 0;
      width: 100%; height: 100%;
      object-fit: cover; object-position: center;
      display: block;
      opacity: 0.50;
      -webkit-user-drag: none;
    }

    /*
     * ── FOUR-SIDED FADE MASK ──
     * Protects brand (top), stats row (bottom), and
     * merges into the login card (right edge).
     * Uses a pseudo-element with a layered radial/linear gradient mask
     * so the photo fades out toward all four critical areas.
     */
    .photo-fade {
      position: absolute;
      inset: 0;
      z-index: 2;
      pointer-events: none;
      /* Stack four directional fades as a single background shorthand:
         top fade   → protects brand logo area
         bottom fade → protects stats row
         left fade  → soft left edge
         right fade → blurs into login card side */
      background:
        /* top: paper color fading down ~22% of panel height */
        linear-gradient(to bottom,  var(--paper) 0%, transparent 22%),
        /* bottom: paper color fading up ~28% of panel height */
        linear-gradient(to top,     var(--paper) 0%, transparent 28%),
        /* left: soft left-edge fade */
        linear-gradient(to right,   var(--paper) 0%, transparent 18%),
        /* right: wide fade merging into login card */
        linear-gradient(to left,    var(--paper) 0%, transparent 32%);
    }

    /*
     * ── RIGHT-EDGE BLUR ──
     * Sits above the fade, adds a backdrop blur only near the right
     * edge so the photo pixels soften before they hit the card.
     * The mask restricts the blur to the rightmost portion only.
     */
    .photo-blur-edge {
      position: absolute;
      top: 0; right: 0;
      width: 100px; height: 100%;
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      -webkit-mask-image: linear-gradient(to right, transparent 0%, black 100%);
      mask-image: linear-gradient(to right, transparent 0%, black 100%);
      z-index: 3;
      pointer-events: none;
    }

    /* ── LEFT PANEL CONTENT — always above photo layer ── */
    .brand, .hero-text, .stats-row {
      position: relative; z-index: 5;
    }

    .brand {
      display: flex; align-items: center; gap: 10px; animation: fadeUp 0.7s ease both;
      border: 0; background: transparent; padding: 0; cursor: default;
    }
    .brand-mark {
      width: 36px; height: 36px; background: var(--ink); border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
    }
    .brand-mark svg { width: 20px; height: 20px; }
    .brand-name {
      font-family: 'Syne', sans-serif; font-weight: 800;
      font-size: 1.2rem; letter-spacing: 0.04em; color: var(--ink);
    }
    .version-label {
      display: inline-flex;
      align-items: center;
      margin-left: 2px;
      padding: 4px 8px;
      border-radius: 999px;
      background: var(--cream);
      border: 1px solid var(--border);
      color: var(--accent);
      font-size: 0.64rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .hero-text { animation: fadeUp 0.7s 0.15s ease both; }
    .hero-label {
      font-size: 0.72rem; font-weight: 500;
      letter-spacing: 0.14em; text-transform: uppercase; color: var(--accent);
      margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
    }
    .hero-label::before { content: ''; width: 24px; height: 1.5px; background: var(--accent); }

    .hero-title {
      font-family: 'Syne', sans-serif;
      font-size: clamp(2.6rem, 4vw, 3.6rem);
      font-weight: 800; line-height: 1.08; color: var(--ink); letter-spacing: -0.02em;
    }
    .hero-title span { color: var(--accent); display: block; }

    .hero-sub {
      margin-top: 20px; font-size: 0.92rem; color: var(--muted);
      line-height: 1.65; max-width: 300px; font-weight: 300;
    }

    .bottom-stack {
      position: relative;
      z-index: 5;
      display: flex;
      flex-direction: column;
      gap: 18px;
      animation: fadeUp 0.7s 0.3s ease both;
    }
    .credits-strip {
      width: min(100%, 520px);
      height: 46px;
      overflow: hidden;
      border-left: 2px solid var(--accent);
      padding-left: 14px;
      position: relative;
    }
    .credit-line {
      position: absolute;
      left: 14px;
      right: 0;
      opacity: 0;
      transform: translateY(10px);
      animation: creditsFade 16s ease infinite;
    }
    .credit-line:nth-child(2) { animation-delay: 4s; }
    .credit-line:nth-child(3) { animation-delay: 8s; }
    .credit-line:nth-child(4) { animation-delay: 12s; }
    .credit-kicker {
      display: block;
      font-size: 0.64rem;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 3px;
    }
    .credit-main {
      display: block;
      font-size: 0.82rem;
      color: var(--ink);
      font-weight: 600;
    }

    .stats-row { display: flex; gap: 36px; }
    .stat { display: flex; flex-direction: column; gap: 3px; }
    .stat-num { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--ink); }
    .stat-label { font-size: 0.72rem; color: var(--muted); letter-spacing: 0.06em; text-transform: uppercase; }
    .stat-divider { width: 1px; background: var(--border); height: 40px; align-self: center; }

    /* ═══════════════════════════════
       RIGHT PANEL — original unchanged
    ═══════════════════════════════ */
    .right-panel {
      display: flex; align-items: center; justify-content: center;
      padding: 40px; position: relative;
    }

    .card {
      background: var(--white); border-radius: 24px;
      padding: 44px 48px; width: 100%; max-width: 420px;
      box-shadow: var(--shadow), 0 0 0 1px var(--border);
      animation: scaleIn 0.6s 0.1s cubic-bezier(0.34,1.56,0.64,1) both;
      position: relative; overflow: hidden;
    }
    .card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--accent), var(--accent2));
    }

    .tab-row {
      display: none;
    }
    .tab-btn {
      flex: 1; padding: 9px; border: none; background: transparent;
      border-radius: 7px; font-family: 'DM Sans', sans-serif;
      font-size: 0.84rem; font-weight: 500; color: var(--muted); cursor: pointer;
      transition: all 0.22s ease;
    }
    .tab-btn.active { background: var(--white); color: var(--ink); box-shadow: 0 1px 6px rgba(15,15,15,0.09); }

    .panel { display: none; }
    .panel.active { display: block; }

    .form-group { margin-bottom: 18px; }
    .form-label {
      display: block; font-size: 0.76rem; font-weight: 500;
      letter-spacing: 0.07em; text-transform: uppercase; color: var(--muted); margin-bottom: 7px;
    }
    .form-input {
      width: 100%; padding: 12px 16px;
      border: 1.5px solid var(--border); border-radius: var(--radius);
      font-family: 'DM Sans', sans-serif; font-size: 0.93rem;
      color: var(--ink); background: var(--paper); outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-input::placeholder { color: #bbb7ae; }
    .form-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(200,80,42,0.10);
      background: var(--white);
    }

    .btn-primary {
      width: 100%; padding: 13px; background: var(--ink); color: var(--white);
      border: none; border-radius: var(--radius);
      font-family: 'Syne', sans-serif; font-size: 0.9rem; font-weight: 600;
      letter-spacing: 0.04em; cursor: pointer; transition: all 0.22s ease;
      display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px;
    }
    .btn-primary:hover { background: var(--accent); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(200,80,42,0.28); }
    .btn-primary:active { transform: translateY(0); }

    .btn-guest {
      width: 100%; padding: 13px; background: transparent; color: var(--ink);
      border: 1.5px solid var(--border); border-radius: var(--radius);
      font-family: 'DM Sans', sans-serif; font-size: 0.9rem; font-weight: 500;
      cursor: pointer; transition: all 0.22s ease;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-guest:hover { border-color: var(--accent); color: var(--accent); background: rgba(200,80,42,0.04); }

    .divider {
      display: flex; align-items: center; gap: 12px;
      margin: 20px 0; font-size: 0.78rem; color: var(--muted);
    }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

    .recent-guests-list {
      list-style: none; display: flex; flex-direction: column;
      gap: 8px; max-height: 200px; overflow-y: auto; margin-top: 4px; padding-right: 2px;
    }
    .recent-guests-list::-webkit-scrollbar { width: 4px; }
    .recent-guests-list::-webkit-scrollbar-track { background: var(--cream); border-radius: 2px; }
    .recent-guests-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

    .guest-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 10px 14px; background: var(--paper);
      border-radius: 10px; border: 1px solid var(--border);
      font-size: 0.84rem; animation: fadeUp 0.3s ease both;
    }
    .guest-item .g-num { font-weight: 600; color: var(--ink); font-family: 'Syne', sans-serif; }
    .guest-badge {
      font-size: 0.69rem; font-weight: 600; letter-spacing: 0.06em;
      text-transform: uppercase; padding: 3px 9px; border-radius: 20px;
    }
    .guest-badge.accepted { background: #d4edda; color: #2e7d32; }
    .guest-badge.rejected { background: #fde8e8; color: #c62828; }
    .guest-badge.pending  { background: #fff3cd; color: #b45309; }

    .guest-tab-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
    .guest-tab-title { font-family: 'Syne', sans-serif; font-size: 1.05rem; font-weight: 700; color: var(--ink); }
    .guest-count-pill { font-size: 0.72rem; background: var(--cream); color: var(--muted); padding: 3px 9px; border-radius: 20px; font-weight: 500; }
    .guest-tab-sub { font-size: 0.82rem; color: var(--muted); margin-bottom: 12px; font-weight: 300; }

    #error-message,
    #guest-error-message { font-size: 0.8rem; color: var(--accent); margin-top: 10px; min-height: 18px; text-align: center; }
    .guest-login-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.35rem;
      font-weight: 800;
      letter-spacing: -0.01em;
      margin-bottom: 6px;
    }
    .guest-login-sub {
      font-size: 0.84rem;
      color: var(--muted);
      line-height: 1.55;
      margin-bottom: 24px;
      font-weight: 300;
    }
    .admin-access-row {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 8px;
      margin-top: 18px;
    }
    .admin-access-hint {
      color: #bbb7ae;
      font-size: 0.68rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .admin-access-btn {
      width: 28px;
      height: 28px;
      border-radius: 9px;
      border: 1px solid transparent;
      background: transparent;
      color: #bbb7ae;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: color 0.2s, border-color 0.2s, background 0.2s;
    }
    .admin-access-btn:hover,
    .admin-access-btn:focus-visible {
      color: var(--accent);
      border-color: var(--border);
      background: var(--paper);
      outline: none;
    }
    .modal-backdrop {
      position: fixed;
      inset: 0;
      z-index: 1000;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: rgba(15,15,15,0.44);
      backdrop-filter: blur(10px);
    }
    .modal-backdrop.open { display: flex; }
    .admin-modal {
      width: min(100%, 410px);
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 24px;
      box-shadow: var(--shadow), 0 18px 60px rgba(15,15,15,0.20);
      padding: 32px;
      animation: scaleIn 0.24s ease both;
      position: relative;
      overflow: hidden;
    }
    .admin-modal::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--accent), var(--accent2));
    }
    .admin-modal-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 24px;
    }
    .admin-modal-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.18rem;
      font-weight: 800;
      margin-bottom: 5px;
    }
    .admin-modal-sub {
      color: var(--muted);
      font-size: 0.82rem;
      line-height: 1.5;
      font-weight: 300;
    }
    .admin-close-btn,
    .admin-back-btn {
      border: 1px solid var(--border);
      background: var(--paper);
      color: var(--muted);
      cursor: pointer;
      transition: color 0.2s, border-color 0.2s, background 0.2s;
    }
    .admin-close-btn {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      font-size: 1.1rem;
      line-height: 1;
    }
    .admin-back-btn {
      width: 100%;
      padding: 11px;
      border-radius: var(--radius);
      margin-top: 10px;
      font-size: 0.84rem;
      font-weight: 600;
    }
    .admin-close-btn:hover,
    .admin-back-btn:hover {
      color: var(--ink);
      background: var(--cream);
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes scaleIn {
      from { opacity: 0; transform: scale(0.93) translateY(12px); }
      to   { opacity: 1; transform: scale(1) translateY(0); }
    }
    @keyframes creditsFade {
      0% { opacity: 0; transform: translateY(10px); }
      8% { opacity: 1; transform: translateY(0); }
      23% { opacity: 1; transform: translateY(0); }
      31% { opacity: 0; transform: translateY(-8px); }
      100% { opacity: 0; transform: translateY(-8px); }
    }
    .spinner {
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,0.3); border-top-color: white;
      border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 860px) {
      .page { grid-template-columns: 1fr; overflow-y: auto; }
      .left-panel { padding: 36px 32px 32px; min-height: 300px; }
      .right-panel { padding: 24px 20px 48px; }
      html, body { overflow: auto; }
    }
  </style>
</head>
<body>

<div class="page">

  <!-- ══ LEFT PANEL ══ -->
  <div class="left-panel">

    <!-- decorative objects -->
    <div class="orb-tl"></div>
    <div class="orb-mr"></div>
    <div class="ring-sm"></div>
    <div class="ring-lg"></div>

    <!-- FULL-BLEED PHOTO LAYER
         Slides top-to-bottom (translateY).
         Four-sided fade mask protects brand, stats, and right edge.
         To add photos: edit $slideImages at top of this file only. -->
    <div class="photo-strip" id="photoStrip">
      <div class="photo-track" id="photoTrack">
        <?php foreach ($slideImages as $i => $src): ?>
        <div class="photo-slide">
          <?php if (!empty($src)): ?>
            <img src="<?= htmlspecialchars($src) ?>"
                 alt=""
                 loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"
                 aria-hidden="true">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <!-- four-sided gradient fade mask -->
      <div class="photo-fade"></div>
      <!-- right-edge blur merge into login card -->
      <div class="photo-blur-edge"></div>
    </div>

    <!-- content — z-index 5, always above photo layer -->
    <button class="brand" id="brandSecret" type="button" aria-label="EquiLab">
      <div class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4"/>
          <path d="M15 3h6v6"/><path d="M10 14 21 3"/>
        </svg>
      </div>
      <span class="brand-name">EQUILAB</span>
      <span class="version-label">v2</span>
    </button>

    <div class="hero-text">
      <p class="hero-label">Applied Physics Dept.</p>
      <h1 class="hero-title">
        Lab Equipment
        <span>Tracker.</span>
      </h1>
      <p class="hero-sub">
        Manage, borrow, and track laboratory equipment with clarity.
        Built for EARIST College of Arts and Sciences.
      </p>
    </div>

    <div class="bottom-stack">
      <div class="credits-strip" aria-label="Credits">
        <div class="credit-line">
          <span class="credit-kicker">Developed by</span>
          <span class="credit-main">Kyrie Eleison Abarro, Mark Christian T. Uy, Matthew Broderick Echavez, Harold Pineda</span>
        </div>
        <div class="credit-line">
          <span class="credit-kicker">For</span>
          <span class="credit-main">EARIST College of Arts and Sciences</span>
        </div>
        <div class="credit-line">
          <span class="credit-kicker">System</span>
          <span class="credit-main">EquiLab Multi-Purpose Laboratory Tracker</span>
        </div>
        <div class="credit-line">
          <span class="credit-kicker">Release</span>
          <span class="credit-main">EquiLab Version 2</span>
        </div>
      </div>

      <div class="stats-row">
        <div class="stat">
          <span class="stat-num" id="statInventory">—</span>
          <span class="stat-label">Equipment</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat">
          <span class="stat-num" id="statPending">—</span>
          <span class="stat-label">Pending</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat">
          <span class="stat-num" id="statGuests">—</span>
          <span class="stat-label">Borrowers today</span>
        </div>
      </div>
    </div>

  </div>

  <!-- ══ RIGHT PANEL ══ -->
  <div class="right-panel">
    <div class="card">

      <div class="tab-row">
        <button class="tab-btn active" type="button">Borrower</button>
      </div>

      <div id="panel-guest" class="panel active">
        <div class="guest-login-title">Borrower Login</div>
        <p class="guest-login-sub">Start a laboratory equipment request session directly as a borrower.</p>

        <button class="btn-guest" id="guestLoginBtn" type="button">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
          </svg>
          Continue as Borrower
        </button>
        <p id="guest-error-message"></p>

        <div class="divider">recent login numbers</div>
        <div class="guest-tab-header">
          <span class="guest-tab-title">Submitted Requests</span>
          <span class="guest-count-pill" id="borrowerCountPill">0 today</span>
        </div>
        <ul class="recent-guests-list" id="recent-guests-list">
          <li style="color:#bbb7ae;font-size:0.82rem;text-align:center;padding:12px 0;">Loading borrowers...</li>
        </ul>

        <div class="admin-access-row">
          <span class="admin-access-hint">EquiLab v2</span>
          <button class="admin-access-btn" id="adminAccessBtn" type="button" title="Admin access" aria-label="Admin access">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="3"/>
              <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.04.04a2 2 0 1 1-2.83 2.83l-.04-.04A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.05a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.88.34l-.04.04a2 2 0 1 1-2.83-2.83l.04-.04A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.05A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.88l-.04-.04a2 2 0 1 1 2.83-2.83l.04.04A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3a2 2 0 1 1 4 0v.05a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.88-.34l.04-.04a2 2 0 1 1 2.83 2.83l-.04.04A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.55 1H21a2 2 0 1 1 0 4h-.05A1.7 1.7 0 0 0 19.4 15Z"/>
            </svg>
          </button>
        </div>
      </div>

    </div>
  </div>

</div>

<div class="modal-backdrop" id="adminModal" role="dialog" aria-modal="true" aria-labelledby="adminModalTitle">
  <div class="admin-modal">
    <div class="admin-modal-top">
      <div>
        <div class="admin-modal-title" id="adminModalTitle">Admin Login</div>
        <p class="admin-modal-sub">Restricted access for laboratory administrators.</p>
      </div>
      <button class="admin-close-btn" id="closeAdminModal" type="button" aria-label="Back to Borrower Login">&times;</button>
    </div>

    <form id="login-form" autocomplete="off">
      <div class="form-group">
        <label class="form-label" for="username">Admin Username</label>
        <input class="form-input" type="text" id="username"
               placeholder="Enter admin username" autocomplete="username" required />
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input class="form-input" type="password" id="password"
               placeholder="••••••••" autocomplete="current-password" required />
      </div>
      <button class="btn-primary" type="submit" id="loginBtn">
        <span id="loginBtnInner">Login</span>
      </button>
      <button class="admin-back-btn" id="backToGuestBtn" type="button">Back to Borrower Login</button>
      <p id="error-message"></p>
    </form>
  </div>
</div>

<script>
  function openAdminModal() {
    const modal = document.getElementById('adminModal');
    modal.classList.add('open');
    document.getElementById('error-message').textContent = '';
    setTimeout(() => document.getElementById('username').focus(), 40);
  }

  function closeAdminModal() {
    const modal = document.getElementById('adminModal');
    modal.classList.remove('open');
    document.getElementById('login-form').reset();
    document.getElementById('error-message').textContent = '';
  }

  document.getElementById('adminAccessBtn').addEventListener('click', openAdminModal);
  document.getElementById('closeAdminModal').addEventListener('click', closeAdminModal);
  document.getElementById('backToGuestBtn').addEventListener('click', closeAdminModal);
  document.getElementById('adminModal').addEventListener('click', function(e) {
    if (e.target === this) closeAdminModal();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('adminModal').classList.contains('open')) closeAdminModal();
    if (e.ctrlKey && e.altKey && e.key.toLowerCase() === 'a') openAdminModal();
  });

  let brandClicks = 0;
  let brandTimer = null;
  document.getElementById('brandSecret').addEventListener('click', function() {
    brandClicks += 1;
    clearTimeout(brandTimer);
    brandTimer = setTimeout(() => { brandClicks = 0; }, 1300);
    if (brandClicks >= 5) {
      brandClicks = 0;
      openAdminModal();
    }
  });

  document.getElementById('login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn      = document.getElementById('loginBtn');
    const errEl    = document.getElementById('error-message');
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    if (!username || !password) { errEl.textContent = 'Please fill in all fields.'; return; }
    btn.innerHTML = '<span class="spinner"></span>';
    btn.disabled  = true;
    errEl.textContent = '';
    fetch('admin_login.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password })
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        window.location.href = 'admin.php';
      } else {
        btn.innerHTML = 'Login';
        btn.disabled  = false;
        errEl.textContent = data.message || 'Incorrect credentials.';
      }
    })
    .catch(() => { btn.innerHTML = 'Login'; btn.disabled = false; errEl.textContent = 'Network error. Try again.'; });
  });

  document.getElementById('guestLoginBtn').addEventListener('click', () => {
    const btn = document.getElementById('guestLoginBtn');
    const errEl = document.getElementById('guest-error-message');
    const guestBtnHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span>';
    btn.disabled = true;
    errEl.textContent = '';
    fetch('guest_login.php')
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') { window.location.href = 'guest.php'; }
      else {
        btn.innerHTML = guestBtnHtml;
        btn.disabled = false;
        errEl.textContent = data.message || 'Borrower login failed.';
      }
    })
    .catch(() => {
      btn.innerHTML = guestBtnHtml;
      btn.disabled = false;
      errEl.textContent = 'Borrower login failed.';
    });
  });

  function fetchRecentGuests() {
    fetch('fetch_recent_guests.php').then(r => r.json()).then(data => {
      if (!data.success || !Array.isArray(data.data)) return;
      const list  = document.getElementById('recent-guests-list');
      const pill  = document.getElementById('borrowerCountPill');
      if (!list || !pill) return;
      const today = new Date().toISOString().slice(0, 10);
      pill.textContent = data.data.filter(g => g.created_at && g.created_at.startsWith(today)).length + ' today';
      list.innerHTML = '';
      if (!data.data.length) {
        list.innerHTML = '<li style="color:#bbb7ae;font-size:0.82rem;text-align:center;padding:12px 0;">No recent borrowers</li>';
        return;
      }
      data.data.forEach((item, i) => {
        const li = document.createElement('li');
        li.className = 'guest-item';
        li.style.animationDelay = (i * 0.05) + 's';
        const s = (item.status || 'pending').toLowerCase();
        li.innerHTML = `<span class="g-num">${item.guest_number}</span><span class="guest-badge ${s}">${s.charAt(0).toUpperCase()+s.slice(1)}</span>`;
        list.appendChild(li);
      });
    }).catch(() => {});
  }

  function fetchStats() {
    fetch('get_equipment.php').then(r=>r.json()).then(d=>{
      document.getElementById('statInventory').textContent = Array.isArray(d) ? d.length : '—';
    }).catch(()=>{});
    fetch('fetch_borrow_requests.php').then(r=>r.json()).then(d=>{
      document.getElementById('statPending').textContent = (d.success && Array.isArray(d.data)) ? d.data.length : '—';
    }).catch(()=>{});
    fetch('fetch_recent_guests.php').then(r=>r.json()).then(d=>{
      if (!d.success || !Array.isArray(d.data)) return;
      const today = new Date().toISOString().slice(0,10);
      document.getElementById('statGuests').textContent =
        d.data.filter(g=>g.created_at&&g.created_at.startsWith(today)).length;
    }).catch(()=>{});
  }

  fetchRecentGuests();
  fetchStats();
  setInterval(fetchRecentGuests, 30000);

  /* ── SLIDESHOW — top-to-bottom, sine-in-out, no dots ── */
  (function () {
    const track = document.getElementById('photoTrack');
    const total = <?= count($slideImages) ?>;
    if (!track || total === 0) return;

    track.style.height = (total * 100) + 'vh';

    let current = 0, timer = null;

    function goTo(i) {
      current = (i + total) % total;
      track.style.transform = 'translateY(-' + (current * 100) + 'vh)';
    }

    function start() {
      clearInterval(timer);
      timer = setInterval(() => goTo(current + 1), 4500);
    }

    const panel = document.querySelector('.left-panel');
    if (panel) {
      panel.addEventListener('mouseenter', () => clearInterval(timer));
      panel.addEventListener('mouseleave', start);
    }

    start();
  })();
</script>

</body>
</html>
