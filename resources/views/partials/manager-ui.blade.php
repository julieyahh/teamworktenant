{{--
    Shared manager-screen styles.

    Pulled out because the bills and collections screens were about to be the
    fourth and fifth copies of the same 200 lines of CSS that already exist in
    manager/utilities/index.blade.php and manager/tenants.blade.php. Those two
    were left alone deliberately — copying their styles here and deleting them
    there would be a restyle of working pages, which is a separate job.

    Use with: include this partial inside the page's styles stack.
--}}
<style>
    /* ---- Alerts ---- */
    .pr-alert { display:flex; align-items:flex-start; gap:12px; padding:14px 18px; border-radius:12px; font-size:.88rem; margin-bottom:20px; }
    .pr-alert--success { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
    .pr-alert--error   { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .pr-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .pr-alert--info    { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .pr-alert__icon    { font-size:1.1rem; flex-shrink:0; margin-top:1px; }

    /* ---- Page header ---- */
    .pr-dash-header { margin-bottom:24px; }
    .pr-dash-header__eyebrow { font-size:.72rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:6px; }
    .pr-dash-header__title { font-family:var(--pr-font-display); font-size:1.5rem; font-weight:700; color:var(--pr-navy); margin:0 0 4px; line-height:1.2; }
    .pr-dash-header__sub { font-size:.85rem; color:var(--pr-gray-500); margin:0; }

    /* ---- Stat tiles ---- */
    .pr-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:24px; }
    .pr-stat { background:var(--pr-white); border:1px solid rgba(12,26,58,.07); border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(12,26,58,.05); }
    .pr-stat__label { font-size:.7rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--pr-gray-500); margin-bottom:8px; }
    .pr-stat__value { font-family:var(--pr-font-display); font-size:1.5rem; font-weight:700; color:var(--pr-navy); line-height:1; }
    .pr-stat__value--danger { color:#b91c1c; }
    .pr-stat__value--warn   { color:#b45309; }
    .pr-stat__note  { font-size:.75rem; color:var(--pr-gray-500); margin-top:6px; }

    /* ---- Cards ---- */
    .pr-card { background:var(--pr-white); border:1px solid rgba(12,26,58,.07); border-radius:16px; box-shadow:0 2px 12px rgba(12,26,58,.06); margin-bottom:20px; overflow:hidden; }
    .pr-card__header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 22px; border-bottom:1px solid rgba(12,26,58,.06); }
    .pr-card__title { font-family:var(--pr-font-display); font-size:.98rem; font-weight:700; color:var(--pr-navy); margin:0; }
    .pr-card__count { font-size:.72rem; font-weight:600; padding:3px 10px; border-radius:999px; background:rgba(201,168,76,.1); color:var(--pr-gold); border:1px solid rgba(201,168,76,.2); }
    .pr-card__body { padding:20px 22px; }

    /* ---- Tables ---- */
    .pr-table-wrap { overflow-x:auto; }
    .pr-table { width:100%; border-collapse:collapse; font-size:.85rem; }
    .pr-table thead th { font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--pr-gray-500); padding:12px 18px; border-bottom:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); white-space:nowrap; text-align:left; }
    .pr-table tbody td { padding:13px 18px; color:var(--pr-gray-800); border-bottom:1px solid rgba(12,26,58,.04); vertical-align:middle; }
    .pr-table tbody tr:last-child td { border-bottom:0; }
    .pr-table tbody tr:hover td { background:rgba(12,26,58,.015); }
    .pr-table .num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }

    /* ---- Badges ---- */
    .pr-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:.72rem; font-weight:700; white-space:nowrap; }
    .pr-badge--paid    { background:#dcfce7; color:#166534; }
    .pr-badge--partial { background:#dbeafe; color:#1e40af; }
    .pr-badge--unpaid  { background:#fef3c7; color:#92400e; }
    .pr-badge--overdue { background:#fee2e2; color:#991b1b; }
    .pr-badge--void    { background:#f1f5f9; color:#64748b; text-decoration:line-through; }
    .pr-badge--est     { background:#ede9fe; color:#5b21b6; }
    .pr-badge--muted   { background:#f1f5f9; color:#475569; }

    /* ---- Buttons ---- */
    .pr-btn { padding:9px 16px; border-radius:10px; border:none; cursor:pointer; font-size:.85rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .pr-btn--navy    { background:var(--pr-navy); color:#fff; }
    .pr-btn--gold    { background:var(--pr-gold); color:var(--pr-navy); }
    .pr-btn--outline { background:#fff; color:var(--pr-navy); border:1px solid #cbd5e1; }
    .pr-btn--danger  { background:#fff; color:#b91c1c; border:1px solid #fecaca; }
    .pr-btn--sm      { padding:5px 11px; font-size:.76rem; border-radius:8px; }
    .pr-btn[disabled] { opacity:.5; cursor:not-allowed; }

    /* ---- Forms ---- */
    .pr-field { margin-bottom:14px; }
    .pr-field label { display:block; font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--pr-gray-500); margin-bottom:5px; }
    .pr-field input[type=text], .pr-field input[type=number], .pr-field input[type=date], .pr-field input[type=month],
    .pr-field input[type=datetime-local], .pr-field input[type=file], .pr-field select, .pr-field textarea {
        width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:10px; font-size:.88rem; font-family:inherit;
    }
    .pr-field textarea { min-height:90px; resize:vertical; }
    .pr-field__hint { font-size:.74rem; color:var(--pr-gray-500); margin-top:4px; }
    .pr-grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:0 16px; }

    /* ---- Definition rows ---- */
    .pr-dl { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; }
    .pr-dl__label { font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; font-weight:700; }
    .pr-dl__value { font-size:.92rem; color:var(--pr-navy); margin-top:3px; }

    /* ---- Timeline ---- */
    .pr-timeline { list-style:none; margin:0; padding:0; }
    .pr-timeline li { position:relative; padding:0 0 16px 22px; border-left:2px solid rgba(12,26,58,.08); }
    .pr-timeline li:last-child { border-left-color:transparent; padding-bottom:0; }
    .pr-timeline li::before { content:''; position:absolute; left:-6px; top:4px; width:10px; height:10px; border-radius:50%; background:var(--pr-gold); }
    .pr-timeline__when { font-size:.72rem; color:var(--pr-gray-500); }
    .pr-timeline__what { font-size:.88rem; color:var(--pr-navy); font-weight:600; }
    .pr-timeline__note { font-size:.82rem; color:var(--pr-gray-500); margin-top:2px; }

    .pr-empty { padding:36px 20px; text-align:center; color:var(--pr-gray-500); font-size:.88rem; }
    .pr-legal { background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:14px 18px; font-size:.82rem; color:#78350f; margin-bottom:20px; line-height:1.55; }
</style>
