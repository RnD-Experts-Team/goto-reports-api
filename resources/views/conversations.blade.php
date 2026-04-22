<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversations Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh; color: #e4e4e4; padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 0; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 24px;
        }
        h1 { font-size: 22px; color: #00d4ff; }
        h1 span { color: #ff6b6b; }
        a.back { color: #9aa4b2; text-decoration: none; font-size: 14px; }
        a.back:hover { color: #00d4ff; }

        .card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; padding: 20px; margin-bottom: 20px;
        }
        .card h2 { font-size: 15px; color: #00d4ff; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.5px; }

        .grid { display: grid; gap: 14px; }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); }
        @media (max-width: 900px) { .grid-3, .grid-4 { grid-template-columns: 1fr; } }

        label { display: block; font-size: 12px; color: #9aa4b2; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        input, select {
            width: 100%; padding: 10px 12px;
            background: rgba(0,0,0,0.3); color: #e4e4e4;
            border: 1px solid rgba(255,255,255,0.1); border-radius: 8px;
            font-size: 14px;
        }
        input:focus, select:focus { outline: none; border-color: #00d4ff; }

        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        .btn {
            padding: 10px 18px; border: none; border-radius: 8px;
            font-size: 14px; font-weight: 600; cursor: pointer;
            transition: all 0.2s ease; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary { background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%); color: #fff; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(0,212,255,0.3); }
        .btn-secondary { background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.15); }
        .btn-secondary:hover { background: rgba(255,255,255,0.12); }
        .btn-success { background: linear-gradient(135deg, #00d46a 0%, #00a050 100%); color: #fff; }
        .btn-warn { background: linear-gradient(135deg, #ffaa44 0%, #ff7700 100%); color: #fff; }
        .btn-danger { background: linear-gradient(135deg, #ff5577 0%, #cc1144 100%); color: #fff; }
        .btn-danger:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(255,85,119,0.35); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .stat {
            background: rgba(0,0,0,0.25); padding: 14px; border-radius: 8px;
            border-left: 3px solid #00d4ff;
        }
        .stat .label { font-size: 11px; color: #9aa4b2; text-transform: uppercase; }
        .stat .value { font-size: 22px; font-weight: 700; color: #fff; margin-top: 4px; }
        .stat.warn { border-left-color: #ffaa44; }
        .stat.err { border-left-color: #ff6b6b; }
        .stat.ok { border-left-color: #00d46a; }

        table {
            width: 100%; border-collapse: collapse; font-size: 13px;
            background: rgba(0,0,0,0.2); border-radius: 8px; overflow: hidden;
        }
        th, td {
            padding: 10px 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 280px;
        }
        th { background: rgba(0,212,255,0.08); color: #00d4ff; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        tr:hover td { background: rgba(255,255,255,0.03); }

        .table-wrap { overflow-x: auto; max-height: 520px; overflow-y: auto; }

        .audit-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .audit-row:last-child { border: none; }
        .audit-name { font-size: 14px; }
        .audit-detail { font-size: 12px; color: #9aa4b2; margin-top: 2px; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge.pass { background: rgba(0,212,106,0.18); color: #00d46a; }
        .badge.fail { background: rgba(255,107,107,0.18); color: #ff6b6b; }
        .badge.warn { background: rgba(255,170,68,0.18); color: #ffaa44; }
        .badge.run  { background: rgba(0,212,255,0.18); color: #00d4ff; }

        pre.log {
            background: #000; color: #9aa4b2; padding: 12px; border-radius: 6px;
            font-size: 12px; max-height: 220px; overflow: auto;
            font-family: 'SF Mono', Menlo, monospace; line-height: 1.5;
        }

        .toast {
            position: fixed; top: 20px; right: 20px;
            background: rgba(0,0,0,0.85); padding: 12px 20px; border-radius: 8px;
            border-left: 4px solid #00d4ff; color: #fff; font-size: 14px;
            z-index: 1000; opacity: 0; transition: opacity 0.3s; pointer-events: none;
        }
        .toast.show { opacity: 1; }
        .toast.err { border-left-color: #ff6b6b; }
        .toast.ok  { border-left-color: #00d46a; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Conversations <span>Report</span></h1>
            <a class="back" href="/dashboard">&larr; Back to Dashboard</a>
        </header>

        <!-- Filters -->
        <div class="card">
            <h2>Filters</h2>
            <div class="grid grid-4">
                <div>
                    <label>Start Time (UTC)</label>
                    <input type="datetime-local" id="startTime">
                </div>
                <div>
                    <label>End Time (UTC)</label>
                    <input type="datetime-local" id="endTime">
                </div>
                <div>
                    <label>Account Key</label>
                    <input type="text" id="accountKey" placeholder="all" value="all">
                </div>
                <div>
                    <label>Account Name</label>
                    <select id="accountName"><option value="">— Any —</option></select>
                </div>
            </div>
            <div class="grid grid-3" style="margin-top: 14px;">
                <div>
                    <label>Sync from GoTo</label>
                    <select id="sync">
                        <option value="1" selected>Yes — pull fresh + upsert</option>
                        <option value="0">No — read DB only</option>
                    </select>
                </div>
                <div>
                    <label>Limit (preview)</label>
                    <input type="number" id="limit" value="100" min="1" max="5000">
                </div>
                <div style="display:flex; align-items:flex-end;">
                    <button class="btn btn-secondary" onclick="loadAccountNames()" style="width:100%;">Refresh Account Names</button>
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-primary" onclick="runPreview()">Preview</button>
                <button class="btn btn-success" onclick="downloadCsv()">Download CSV</button>
                <button class="btn btn-secondary" onclick="downloadJson()">Download JSON</button>
                <button class="btn btn-warn" onclick="runAudit()">Run Audit</button>
                <button class="btn btn-danger" onclick="runBackfill()" title="Pull every account, past 365 days">Backfill All Data</button>
            </div>
        </div>

        <!-- Stats -->
        <div class="card">
            <h2>Result Summary</h2>
            <div class="stats" id="stats">
                <div class="stat"><div class="label">Rows Returned</div><div class="value" id="stat-count">—</div></div>
                <div class="stat ok"><div class="label">Newly Synced</div><div class="value" id="stat-synced">—</div></div>
                <div class="stat"><div class="label">Distinct Accounts</div><div class="value" id="stat-accounts">—</div></div>
                <div class="stat"><div class="label">Distinct Names</div><div class="value" id="stat-names">—</div></div>
            </div>
        </div>

        <!-- Preview Table -->
        <div class="card">
            <h2>Preview</h2>
            <div class="table-wrap">
                <table id="preview">
                    <thead>
                        <tr>
                            <th>Account Key</th>
                            <th>Organization ID</th>
                            <th>Account Name</th>
                            <th>Date</th>
                            <th>Duration</th>
                            <th>Call Result</th>
                            <th>From</th>
                            <th>Participants</th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        <tr><td colspan="8" style="text-align:center; color:#9aa4b2; padding:30px;">Run a preview to see data.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Audit -->
        <div class="card">
            <h2>Audit Checks</h2>
            <div id="audit"><div style="color:#9aa4b2;">Click <strong>Run Audit</strong> to validate the conversations endpoint and other reports.</div></div>
            <pre class="log" id="auditLog" style="display:none; margin-top:14px;"></pre>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        const API = '/api';

        function toast(msg, type = '') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = 'toast show ' + type;
            setTimeout(() => t.className = 'toast', 3000);
        }

        function buildParams(extra = {}) {
            const p = new URLSearchParams();
            const start = document.getElementById('startTime').value;
            const end   = document.getElementById('endTime').value;
            const ak    = document.getElementById('accountKey').value.trim();
            const an    = document.getElementById('accountName').value;
            const sync  = document.getElementById('sync').value;
            const limit = document.getElementById('limit').value;

            if (start) p.set('startTime', new Date(start).toISOString());
            if (end)   p.set('endTime',   new Date(end).toISOString());
            if (ak)    p.set('accountKey', ak);
            if (an)    p.set('accountName', an);
            if (sync)  p.set('sync', sync);
            if (limit) p.set('limit', limit);
            for (const [k, v] of Object.entries(extra)) p.set(k, v);
            return p.toString();
        }

        async function loadAccountNames() {
            try {
                const r = await fetch(`${API}/reports/conversations/account-names`);
                const j = await r.json();
                const sel = document.getElementById('accountName');
                const cur = sel.value;
                sel.innerHTML = '<option value="">— Any —</option>' +
                    (j.data || []).map(n => `<option value="${escapeHtml(n)}">${escapeHtml(n)}</option>`).join('');
                sel.value = cur;
                toast(`Loaded ${j.data?.length || 0} names`, 'ok');
            } catch (e) {
                toast('Failed to load account names', 'err');
            }
        }

        async function runPreview() {
            const url = `${API}/reports/conversations?${buildParams()}`;
            toast('Loading…');
            try {
                const r = await fetch(url);
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                const j = await r.json();
                renderRows(j.data || []);
                renderStats(j);
                toast(`Loaded ${j.count} rows (synced ${j.synced})`, 'ok');
            } catch (e) {
                toast(`Preview failed: ${e.message}`, 'err');
            }
        }

        function renderRows(rows) {
            const body = document.getElementById('rows');
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="8" style="text-align:center; color:#9aa4b2; padding:30px;">No rows.</td></tr>';
                return;
            }
            body.innerHTML = rows.map(r => `
                <tr>
                    <td title="${escapeHtml(r['Account Key'])}">${escapeHtml(r['Account Key'] || '')}</td>
                    <td title="${escapeHtml(r['Organization ID'] || '')}">${escapeHtml(r['Organization ID'] || '—')}</td>
                    <td>${escapeHtml(r['Account Name'] || '—')}</td>
                    <td>${escapeHtml((r['Date'] || '').replace('T', ' ').slice(0, 19))}</td>
                    <td>${escapeHtml(r['Duration'] || '')}</td>
                    <td>${escapeHtml(r['Call Result'] || '')}</td>
                    <td>${escapeHtml(r['From'] || '')}</td>
                    <td title="${escapeHtml(r['Participants'] || '')}">${escapeHtml(r['Participants'] || '')}</td>
                </tr>
            `).join('');
        }

        function renderStats(j) {
            const rows = j.data || [];
            const accounts = new Set(rows.map(r => r['Account Key']).filter(Boolean));
            const names    = new Set(rows.map(r => r['Account Name']).filter(Boolean));
            document.getElementById('stat-count').textContent = j.count ?? '—';
            document.getElementById('stat-synced').textContent = j.synced ?? '—';
            document.getElementById('stat-accounts').textContent = accounts.size;
            document.getElementById('stat-names').textContent = names.size;
        }

        function downloadCsv() {
            const url = `${API}/reports/conversations?${buildParams({ format: 'csv' })}`;
            window.location.href = url;
        }

        function downloadJson() {
            const url = `${API}/reports/conversations?${buildParams()}`;
            window.open(url, '_blank');
        }

        let backfillPoller = null;
        async function runBackfill() {
            const days = prompt('Backfill how many days back? (default 365, max 1825)', '365');
            if (days === null) return;
            const n = parseInt(days, 10);
            if (!n || n < 1) { toast('Invalid days', 'err'); return; }
            if (!confirm(`This will sync ALL accounts for the past ${n} days in the BACKGROUND.\nYou can keep using the UI; the audit log will show progress. Continue?`)) return;

            toast(`Starting background backfill (${n} days)…`, '');
            try {
                const r = await fetch(`${API}/reports/conversations/backfill?days=${n}&background=1`, { method: 'POST' });
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                const j = await r.json();
                if (!j.ok) throw new Error('Server refused to start backfill');
                toast(`Backfill running in background (PID ${j.pid || '?'})`, 'ok');
                startBackfillPolling();
            } catch (e) {
                toast(`Backfill failed to start: ${e.message}`, 'err');
            }
        }

        function startBackfillPolling() {
            if (backfillPoller) clearInterval(backfillPoller);
            const log = document.getElementById('auditLog');
            log.style.display = 'block';
            log.textContent = '[backfill] starting…\n';
            let lastRows = null;
            backfillPoller = setInterval(async () => {
                try {
                    const r = await fetch(`${API}/reports/conversations/backfill/status`);
                    const j = await r.json();
                    log.textContent = `[backfill] running=${j.running} | totalRows=${j.totalRows} | pid=${j.pid}\n\n--- log tail ---\n${j.logTail || '(no log yet)'}`;
                    log.scrollTop = log.scrollHeight;
                    document.getElementById('stat-count').textContent = j.totalRows;
                    if (lastRows !== null && j.totalRows !== lastRows) {
                        toast(`+${j.totalRows - lastRows} rows synced`, 'ok');
                    }
                    lastRows = j.totalRows;
                    if (!j.running) {
                        clearInterval(backfillPoller);
                        backfillPoller = null;
                        toast(`Backfill finished — total ${j.totalRows} rows`, 'ok');
                        loadAccountNames();
                        runPreview();
                    }
                } catch (e) { /* keep polling */ }
            }, 3000);
        }

        async function runAudit() {
            const audit = document.getElementById('audit');
            const log   = document.getElementById('auditLog');
            log.style.display = 'block';
            log.textContent = '';

            const checks = [
                {
                    name: 'Conversations endpoint reachable',
                    detail: 'GET /api/reports/conversations?sync=0&limit=1',
                    run: async () => {
                        const r = await fetch(`${API}/reports/conversations?sync=0&limit=1`);
                        if (!r.ok) throw new Error(`HTTP ${r.status}`);
                        const j = await r.json();
                        return { ok: typeof j.count === 'number', info: `count=${j.count}` };
                    }
                },
                {
                    name: 'Account names endpoint',
                    detail: 'GET /api/reports/conversations/account-names',
                    run: async () => {
                        const r = await fetch(`${API}/reports/conversations/account-names`);
                        const j = await r.json();
                        return { ok: Array.isArray(j.data), info: `${j.data?.length || 0} names` };
                    }
                },
                {
                    name: 'All rows have Account Key',
                    detail: 'Every row must include a non-empty Account Key',
                    run: async () => {
                        const r = await fetch(`${API}/reports/conversations?sync=0&limit=500`);
                        const j = await r.json();
                        const bad = (j.data || []).filter(x => !x['Account Key']);
                        return { ok: bad.length === 0, info: `${bad.length} missing of ${j.count}` };
                    }
                },
                {
                    name: 'Participants column populated',
                    detail: 'At least one participant per row (skip empty allowed if 0 participants)',
                    run: async () => {
                        const r = await fetch(`${API}/reports/conversations?sync=0&limit=500`);
                        const j = await r.json();
                        const rows = j.data || [];
                        const empty = rows.filter(x => !x['Participants']).length;
                        const pct = rows.length ? Math.round(100 * empty / rows.length) : 0;
                        return { ok: pct < 50, info: `${empty}/${rows.length} empty (${pct}%)`, warn: pct > 0 && pct < 50 };
                    }
                },
                {
                    name: 'Account Name resolved for every account',
                    detail: 'Every distinct account_key should have a name',
                    run: async () => {
                        const r = await fetch(`${API}/reports/conversations?sync=0&limit=2000`);
                        const j = await r.json();
                        const map = new Map();
                        for (const x of j.data || []) map.set(x['Account Key'], x['Account Name']);
                        const missing = [...map.entries()].filter(([, n]) => !n).map(([k]) => k);
                        return { ok: missing.length === 0, info: `${missing.length} unnamed of ${map.size}`, extra: missing.join(', ') };
                    }
                },
                {
                    name: 'Idempotency — re-sync inserts 0 new rows',
                    detail: 'Repeating the same sync window must not duplicate',
                    run: async () => {
                        const range = '?startTime=2026-04-21T00:00:00Z&endTime=2026-04-21T23:59:59Z&accountKey=8026784973782889224&limit=1';
                        await fetch(`${API}/reports/conversations${range}`); // warm
                        const r = await fetch(`${API}/reports/conversations${range}`);
                        const j = await r.json();
                        return { ok: j.synced === 0, info: `synced=${j.synced} (expected 0)` };
                    }
                },
                {
                    name: 'CSV download works',
                    detail: 'GET …&format=csv returns text/csv',
                    run: async () => {
                        const r = await fetch(`${API}/reports/conversations?sync=0&limit=5&format=csv`);
                        const ct = r.headers.get('Content-Type') || '';
                        const text = await r.text();
                        const ok = ct.includes('text/csv') && text.split('\n')[0].includes('Account Key');
                        return { ok, info: `Content-Type=${ct}` };
                    }
                },
                {
                    name: 'Existing: Call Events Summaries CSV',
                    detail: 'GET /api/reports/call-events/summaries returns CSV',
                    run: async () => {
                        const r = await fetch(`${API}/reports/call-events/summaries?startTime=2026-04-21T00:00:00Z&endTime=2026-04-21T01:00:00Z&accountKey=8026784973782889224`);
                        const ct = r.headers.get('Content-Type') || '';
                        const text = await r.text();
                        const header = text.split('\n')[0];
                        const ok = ct.includes('text/csv') && header.includes('Participants');
                        return { ok, info: `header has Participants=${header.includes('Participants')}` };
                    }
                },
                {
                    name: 'Existing: Call History CSV',
                    detail: 'GET /api/reports/call-history/calls returns CSV without Ring/Hold cols',
                    run: async () => {
                        const r = await fetch(`${API}/reports/call-history/calls?startTime=2026-04-21T00:00:00Z&endTime=2026-04-21T01:00:00Z&accountKey=8026784973782889224`);
                        const text = await r.text();
                        const header = text.split('\n')[0];
                        const hasRing = header.includes('Ring Duration');
                        const hasHold = header.includes('Hold Duration');
                        return { ok: !hasRing && !hasHold, info: `Ring=${hasRing} Hold=${hasHold} (both should be false)` };
                    }
                },
                {
                    name: 'OAuth status connected',
                    detail: 'GET /api/goto/status',
                    run: async () => {
                        const r = await fetch(`${API}/goto/status`);
                        const j = await r.json();
                        return { ok: !!(j.authenticated || j.connected || j.status === 'connected'), info: JSON.stringify(j).slice(0, 80) };
                    }
                }
            ];

            audit.innerHTML = checks.map((c, i) => `
                <div class="audit-row" id="audit-${i}">
                    <div>
                        <div class="audit-name">${escapeHtml(c.name)}</div>
                        <div class="audit-detail">${escapeHtml(c.detail)}</div>
                    </div>
                    <span class="badge run">running…</span>
                </div>
            `).join('');

            for (let i = 0; i < checks.length; i++) {
                const row = document.querySelector(`#audit-${i}`);
                const badge = row.querySelector('.badge');
                try {
                    const start = performance.now();
                    const res = await checks[i].run();
                    const ms = Math.round(performance.now() - start);
                    badge.className = 'badge ' + (res.ok ? (res.warn ? 'warn' : 'pass') : 'fail');
                    badge.textContent = (res.ok ? (res.warn ? 'warn' : 'pass') : 'fail') + ` · ${ms}ms`;
                    row.querySelector('.audit-detail').textContent = `${checks[i].detail}  →  ${res.info}`;
                    log.textContent += `[${res.ok ? (res.warn ? 'WARN' : 'PASS') : 'FAIL'}] ${checks[i].name} — ${res.info}\n`;
                    if (res.extra) log.textContent += `  ${res.extra}\n`;
                } catch (e) {
                    badge.className = 'badge fail';
                    badge.textContent = 'fail';
                    row.querySelector('.audit-detail').textContent = `${checks[i].detail}  →  ${e.message}`;
                    log.textContent += `[FAIL] ${checks[i].name} — ${e.message}\n`;
                }
                log.scrollTop = log.scrollHeight;
            }
            toast('Audit complete', 'ok');
        }

        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
        }

        // Bootstrap: defaults to last 30 days, populate name dropdown.
        // GoTo report-summaries API caps each query at 31 days; the backend
        // automatically chunks longer ranges, so wider windows are safe.
        (function init() {
            const now = new Date();
            const monthAgo = new Date(now.getTime() - 30 * 24 * 3600 * 1000);
            const fmt = d => d.toISOString().slice(0, 16);
            document.getElementById('endTime').value   = fmt(now);
            document.getElementById('startTime').value = fmt(monthAgo);
            loadAccountNames();
        })();
    </script>
</body>
</html>
