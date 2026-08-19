<style>
    .dt-hidden { position:absolute; left:-9999px; width:1px; height:1px; opacity:0; }

    /* Summary stat boxes */
    .dt-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:16px; }
    .dt-stat { position:relative; background:var(--pro-card); border:1px solid var(--pro-line); border-radius:16px; padding:17px 18px 15px; box-shadow:var(--pro-shadow-sm); overflow:hidden; transition:box-shadow .18s, transform .18s; }
    .dt-stat::before { content:""; position:absolute; top:0; left:0; right:0; height:4px; background:var(--dt-accent,#4f46e5); }
    .dt-stat:hover { box-shadow:0 10px 26px rgba(15,23,42,.10); transform:translateY(-2px); }
    .dt-stat-top { display:flex; align-items:center; gap:11px; margin-bottom:9px; }
    .dt-stat-ico { width:36px; height:36px; border-radius:11px; flex:none; display:inline-flex; align-items:center; justify-content:center; background:var(--dt-accent-soft,#eef2ff); color:var(--dt-accent,#4f46e5); }
    .dt-stat-ico svg { width:18px; height:18px; }
    .dt-stat-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--pro-muted); }
    .dt-stat-val { font-size:36px; font-weight:800; color:var(--pro-text); line-height:1; margin-top:2px; letter-spacing:-1.2px; }
    .dt-stat-sub { font-size:11.5px; color:var(--pro-muted); margin-top:6px; }
    .dt-accent-indigo { --dt-accent:#4f46e5; --dt-accent-soft:#eef2ff; }
    .dt-accent-green  { --dt-accent:#059669; --dt-accent-soft:#dcfce7; }
    .dt-accent-amber  { --dt-accent:#d97706; --dt-accent-soft:#fef3c7; }
    .dt-accent-teal   { --dt-accent:#0d9488; --dt-accent-soft:#ccfbf1; }
    @media (max-width:900px){ .dt-stats { grid-template-columns:1fr; } }

    /* Per-owner breakdown strip */
    .dt-strip-label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--pro-muted); margin-bottom:10px; }
    .dt-owner-strip { display:flex; flex-wrap:wrap; gap:8px; }
    .dt-owner-pill { display:inline-flex; align-items:center; gap:8px; background:var(--pro-line-soft); border:1px solid var(--pro-line); border-radius:999px; padding:5px 6px 5px 13px; font-size:13px; transition:transform .12s, box-shadow .12s; }
    .dt-owner-pill:hover { transform:translateY(-1px); box-shadow:var(--pro-shadow-sm); }
    .dt-owner-pill.is-top { background:linear-gradient(135deg,#fffbeb,#fef3c7); border-color:#fcd34d; }
    .dt-crown { font-size:13px; margin-right:-2px; }
    .dt-owner-pill-name { font-weight:600; color:var(--pro-text); }
    .dt-owner-pill-count { background:#4f46e5; color:#fff; font-weight:700; font-size:12px; min-width:22px; text-align:center; border-radius:999px; padding:1px 7px; }
    .dt-owner-pill.is-top .dt-owner-pill-count { background:#d97706; }

    /* Buttons */
    .dt-btn { border:1px solid var(--pro-line); background:var(--pro-card); color:var(--pro-text); border-radius:9px; padding:6px 14px; font-size:12.5px; font-weight:600; cursor:pointer; transition:border-color .15s, background .15s, transform .1s; }
    .dt-btn:hover { border-color:#94a3b8; }
    .dt-btn:active { transform:scale(.97); }
    .dt-btn-primary { background:#4f46e5; border-color:#4f46e5; color:#fff; box-shadow:0 5px 14px rgba(79,70,229,.28); }
    .dt-btn-primary:hover { background:#4338ca; border-color:#4338ca; }
    .dt-btn-wa { background:#25d366; border-color:#25d366; color:#0b2e13; font-weight:700; box-shadow:0 5px 14px rgba(37,211,102,.3); }
    .dt-btn-wa:hover { background:#1eb457; border-color:#1eb457; }

    /* Visible WhatsApp message box */
    .dt-wa-text {
        width:100%; min-height:220px; max-height:460px; box-sizing:border-box;
        padding:14px 16px; border:1px solid var(--pro-line); border-radius:12px;
        background:var(--pro-line-soft); color:var(--pro-text);
        font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
        font-size:13.5px; line-height:1.6; white-space:pre; overflow:auto; resize:vertical;
    }
    .dt-wa-text:focus { outline:none; border-color:#25d366; box-shadow:0 0 0 2px rgba(37,211,102,.2); }

    /* Owner cards */
    .dt-owner { margin-bottom:14px; overflow:hidden; transition:box-shadow .18s; }
    .dt-owner:hover { box-shadow:0 10px 26px rgba(15,23,42,.08); }
    .dt-owner-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; border-bottom:1px solid var(--pro-line-soft); }
    .dt-owner-id { display:flex; align-items:center; gap:12px; }
    .dt-avatar { width:44px; height:44px; border-radius:13px; flex:none; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:15px; letter-spacing:-.5px; box-shadow:0 4px 12px rgba(15,23,42,.18); }
    .dt-owner-name { font-size:15px; font-weight:800; color:var(--pro-text); text-transform:uppercase; letter-spacing:.02em; }
    .dt-owner-sub { font-size:11.5px; color:var(--pro-muted); margin-top:1px; }

    .dt-clients { list-style:none; margin:0; padding:8px 14px 12px; }
    .dt-clients li { display:flex; align-items:flex-start; gap:12px; padding:10px 8px; border-radius:11px; border-bottom:1px solid var(--pro-line-soft); transition:background .12s; }
    .dt-clients li:hover { background:var(--pro-line-soft); }
    .dt-clients li:last-child { border-bottom:0; }
    .dt-check { flex:none; width:24px; height:24px; margin-top:1px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:#eef2ff; color:#4f46e5; }
    .dt-check svg { width:13px; height:13px; }
    .dt-check.is-new { background:#dcfce7; color:#16a34a; }
    .dt-check.is-cfpb { background:#ccfbf1; color:#0d9488; }
    .dt-client-main { flex:1; min-width:0; }
    .dt-row-top { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .dt-name { font-size:14px; font-weight:600; color:var(--pro-text); }
    .dt-tasks { font-size:12px; color:var(--pro-muted); margin-top:3px; }
    .dt-tag { font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; }
    .dt-tag-new { background:#dcfce7; color:#166534; }
    .dt-tag-va  { background:#eef2ff; color:#4338ca; letter-spacing:.02em; }
</style>
