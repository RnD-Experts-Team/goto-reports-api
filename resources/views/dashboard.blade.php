<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoTo Reports Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #e4e4e4;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #00d4ff;
        }

        .logo span {
            color: #ff6b6b;
        }

        .auth-status {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-badge.connected {
            background: rgba(0, 212, 106, 0.2);
            color: #00d46a;
            border: 1px solid #00d46a;
        }

        .status-badge.disconnected {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
            border: 1px solid #ff6b6b;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.4);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a5a 100%);
            color: #fff;
        }

        .btn-success {
            background: linear-gradient(135deg, #00d46a 0%, #00b359 100%);
            color: #fff;
        }

        .card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #fff;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .report-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .report-card:hover {
            border-color: #00d4ff;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 212, 255, 0.15);
        }

        .report-card h3 {
            font-size: 16px;
            margin-bottom: 8px;
            color: #fff;
        }

        .report-card p {
            font-size: 13px;
            color: #888;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .report-card .category {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .category.call-reports { background: rgba(0, 212, 255, 0.2); color: #00d4ff; }
        .category.call-history { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .category.call-events { background: rgba(156, 39, 176, 0.2); color: #ce93d8; }
        .category.contact-center { background: rgba(76, 175, 80, 0.2); color: #81c784; }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: #aaa;
            margin-bottom: 6px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-size: 14px;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #00d4ff;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: #1a1a2e;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-header h2 {
            font-size: 20px;
        }

        .modal-close {
            background: none;
            border: none;
            color: #888;
            font-size: 24px;
            cursor: pointer;
        }

        .modal-close:hover {
            color: #fff;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(0, 212, 106, 0.15);
            border: 1px solid #00d46a;
            color: #00d46a;
        }

        .alert-error {
            background: rgba(255, 107, 107, 0.15);
            border: 1px solid #ff6b6b;
            color: #ff6b6b;
        }

        .alert-info {
            background: rgba(0, 212, 255, 0.15);
            border: 1px solid #00d4ff;
            color: #00d4ff;
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .token-info {
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .token-info p {
            font-size: 13px;
            color: #888;
            margin-bottom: 8px;
        }

        .token-info strong {
            color: #fff;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 16px;
        }

        .tab {
            padding: 10px 20px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #888;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab:hover {
            color: #fff;
            border-color: rgba(255,255,255,0.3);
        }

        .tab.active {
            background: rgba(0, 212, 255, 0.2);
            border-color: #00d4ff;
            color: #00d4ff;
        }

        .download-progress {
            margin-top: 12px;
            padding: 12px;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            display: none;
        }

        .download-progress.active {
            display: block;
        }

        .progress-bar {
            height: 4px;
            background: rgba(255,255,255,0.1);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #00d4ff, #00d46a);
            border-radius: 2px;
            animation: progress 2s ease-in-out infinite;
        }

        @keyframes progress {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        footer {
            text-align: center;
            padding: 30px 0;
            color: #555;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">GoTo<span>Reports</span></div>
            <div class="auth-status">
                <a href="/conversations" class="btn btn-secondary">Conversations Report &amp; Audit</a>
                <span id="statusBadge" class="status-badge disconnected">Disconnected</span>
                <button id="authBtn" class="btn btn-primary" onclick="authenticate()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Connect to GoTo
                </button>
            </div>
        </header>

        <div id="alertContainer">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
        </div>

        <!-- Auth Info Card -->
        <div id="authCard" class="card" style="display: none;">
            <div class="card-header">
                <h2 class="card-title">🔐 Authentication</h2>
                <div>
                    <button class="btn btn-secondary" onclick="refreshToken()">Refresh Token</button>
                    <button class="btn btn-danger" onclick="disconnect()" style="margin-left: 8px;">Disconnect</button>
                </div>
            </div>
            <div class="token-info">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Active Account</label>
                    <select id="accountSelect" onchange="changeAccount(this.value)" style="width: 100%;">
                        <option value="">Loading accounts...</option>
                    </select>
                </div>
                <p><strong>Token Expires:</strong> <span id="tokenExpiry">-</span></p>
                <p><strong>Status:</strong> <span id="tokenStatus" style="color: #00d46a;">Active</span></p>
            </div>
        </div>

        <!-- Reports Section -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📊 Export Reports</h2>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="filterReports('all')">All Reports</button>
                <button class="tab" onclick="filterReports('call-reports')">Call Reports</button>
                <button class="tab" onclick="filterReports('call-history')">Call History</button>
                <button class="tab" onclick="filterReports('call-events')">Call Events</button>
                <button class="tab" onclick="filterReports('contact-center')">Contact Center</button>
            </div>

            <div id="reportsGrid" class="reports-grid">
                <!-- Reports will be populated here -->
            </div>
        </div>

        <footer>
            GoTo Connect Report Extraction System &copy; 2024
        </footer>
    </div>

    <!-- Export Modal -->
    <div id="exportModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modalTitle">Export Report</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="exportForm" onsubmit="exportReport(event)">
                <input type="hidden" id="reportEndpoint" name="endpoint">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" id="startDate" name="startDate" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" id="endDate" name="endDate" required>
                    </div>
                </div>

                <div id="additionalFields"></div>

                <div class="download-progress" id="downloadProgress">
                    <span>Generating CSV file...</span>
                    <div class="progress-bar">
                        <div class="progress-bar-fill"></div>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Download CSV
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_BASE = '/api';
        let isAuthenticated = false;

        const reports = [
            {
                id: 'phone-number-activity',
                name: 'Phone Number Activity Summary',
                description: 'Aggregated call metrics by phone number including call counts, durations, and ring times.',
                category: 'call-reports',
                endpoint: '/reports/call-reports/phone-number-activity'
            },
            {
                id: 'caller-activity',
                name: 'Caller Activity Summary',
                description: 'Call activity metrics grouped by caller number with inbound/outbound breakdowns.',
                category: 'call-reports',
                endpoint: '/reports/call-reports/caller-activity'
            },
            {
                id: 'user-activity',
                name: 'User Activity Summary',
                description: 'User performance metrics including calls handled, talk time, and hold time.',
                category: 'call-reports',
                endpoint: '/reports/call-reports/user-activity'
            },
            {
                id: 'call-history',
                name: 'Call History',
                description: 'Detailed call records with caller/callee info, duration, result, and recordings.',
                category: 'call-history',
                endpoint: '/reports/call-history/calls',
                extraFields: [
                    { name: 'direction', label: 'Direction', type: 'select', options: ['', 'inbound', 'outbound'] },
                    { name: 'result', label: 'Result', type: 'select', options: ['', 'answered', 'missed', 'voicemail'] }
                ]
            },
            {
                id: 'call-events-summaries',
                name: 'Call Events Summaries',
                description: 'High-level summary of call events with conversation IDs and event counts.',
                category: 'call-events',
                endpoint: '/reports/call-events/summaries'
            },
            {
                id: 'queue-caller-details',
                name: 'Queue Caller Details',
                description: 'Detailed queue caller information including wait times, agent handling, and callbacks.',
                category: 'contact-center',
                endpoint: '/reports/contact-center/queue-caller-details',
                extraFields: [
                    { name: 'queueIds', label: 'Queue IDs (comma-separated)', type: 'text', placeholder: 'queue1,queue2' },
                    { name: 'timezone', label: 'Timezone', type: 'text', placeholder: 'UTC' }
                ]
            },
            {
                id: 'queue-metrics',
                name: 'Queue Metrics',
                description: 'Queue performance metrics including service levels, answer rates, and wait times.',
                category: 'contact-center',
                endpoint: '/reports/contact-center/queue-metrics',
                extraFields: [
                    { name: 'queueIds', label: 'Queue IDs (comma-separated)', type: 'text', placeholder: 'queue1,queue2' },
                    { name: 'interval', label: 'Interval', type: 'select', options: ['DAY', 'HOUR', 'WEEK'] },
                    { name: 'timezone', label: 'Timezone', type: 'text', placeholder: 'UTC' }
                ]
            },
            {
                id: 'agent-statuses',
                name: 'Agent Statuses',
                description: 'Agent status history and performance metrics including availability and call handling.',
                category: 'contact-center',
                endpoint: '/reports/contact-center/agent-statuses',
                extraFields: [
                    { name: 'agentIds', label: 'Agent IDs (comma-separated)', type: 'text', placeholder: 'agent1,agent2' },
                    { name: 'queueIds', label: 'Queue IDs (comma-separated)', type: 'text', placeholder: 'queue1,queue2' },
                    { name: 'timezone', label: 'Timezone', type: 'text', placeholder: 'UTC' }
                ]
            }
        ];

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            checkAuthStatus();
            renderReports(reports);
            setDefaultDates();
        });

        function setDefaultDates() {
            const today = new Date();
            const thirtyDaysAgo = new Date(today);
            thirtyDaysAgo.setDate(today.getDate() - 30);
            
            document.getElementById('startDate').value = thirtyDaysAgo.toISOString().split('T')[0];
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
        }

        async function checkAuthStatus() {
            try {
                const response = await fetch(`${API_BASE}/goto/status`);
                const data = await response.json();
                
                isAuthenticated = data.authenticated;
                updateAuthUI(data);
                
                // Load accounts if authenticated
                if (data.authenticated) {
                    loadAccounts(data.account_key);
                }
            } catch (error) {
                console.error('Failed to check auth status:', error);
                showAlert('Failed to check authentication status', 'error');
            }
        }

        async function loadAccounts(currentAccountKey) {
            try {
                const response = await fetch(`${API_BASE}/goto/accounts`);
                const data = await response.json();
                
                const select = document.getElementById('accountSelect');
                select.innerHTML = '';
                
                if (data.accounts && data.accounts.length > 0) {
                    // Add "All Accounts" option
                    const allOption = document.createElement('option');
                    allOption.value = 'all';
                    allOption.textContent = '📋 All Accounts (merged CSV)';
                    select.appendChild(allOption);

                    data.accounts.forEach((account, index) => {
                        const option = document.createElement('option');
                        option.value = account.accountKey;
                        option.textContent = `Account ${index + 1}: ${account.accountKey}`;
                        if (account.accountKey === currentAccountKey || account.accountKey === data.current_account) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                } else {
                    select.innerHTML = '<option value="">No accounts found</option>';
                }
            } catch (error) {
                console.error('Failed to load accounts:', error);
            }
        }

        async function changeAccount(accountKey) {
            if (!accountKey) return;
            
            // "All Accounts" doesn't need to set a single active account
            if (accountKey === 'all') {
                showAlert('All Accounts mode selected — exports will merge data from every account.', 'success');
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}/goto/accounts/set`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ account_key: accountKey })
                });
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Account changed successfully!', 'success');
                } else {
                    showAlert(data.message || 'Failed to change account', 'error');
                }
            } catch (error) {
                showAlert('Failed to change account', 'error');
            }
        }

        function updateAuthUI(data) {
            const statusBadge = document.getElementById('statusBadge');
            const authBtn = document.getElementById('authBtn');
            const authCard = document.getElementById('authCard');
            
            if (data.authenticated) {
                statusBadge.textContent = 'Connected';
                statusBadge.className = 'status-badge connected';
                authBtn.style.display = 'none';
                authCard.style.display = 'block';
                
                document.getElementById('tokenExpiry').textContent = data.expires_at || 'N/A';
            } else {
                statusBadge.textContent = 'Disconnected';
                statusBadge.className = 'status-badge disconnected';
                authBtn.style.display = 'inline-flex';
                authCard.style.display = 'none';
            }
        }

        function authenticate() {
            window.location.href = `${API_BASE}/goto/auth`;
        }

        async function refreshToken() {
            try {
                const response = await fetch(`${API_BASE}/goto/refresh`, { method: 'POST' });
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Token refreshed successfully!', 'success');
                    checkAuthStatus();
                } else {
                    showAlert(data.message || 'Failed to refresh token', 'error');
                }
            } catch (error) {
                showAlert('Failed to refresh token', 'error');
            }
        }

        function disconnect() {
            // Clear local state
            isAuthenticated = false;
            updateAuthUI({ authenticated: false });
            showAlert('Disconnected. You will need to re-authenticate to export reports.', 'info');
        }

        function renderReports(reportsToRender) {
            const grid = document.getElementById('reportsGrid');
            grid.innerHTML = reportsToRender.map(report => `
                <div class="report-card" data-category="${report.category}">
                    <span class="category ${report.category}">${report.category.replace('-', ' ')}</span>
                    <h3>${report.name}</h3>
                    <p>${report.description}</p>
                    <button class="btn btn-primary" onclick="openExportModal('${report.id}')" style="width: 100%;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Export CSV
                    </button>
                </div>
            `).join('');
        }

        function filterReports(category) {
            // Update active tab
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter reports
            const filtered = category === 'all' 
                ? reports 
                : reports.filter(r => r.category === category);
            renderReports(filtered);
        }

        function openExportModal(reportId) {
            const report = reports.find(r => r.id === reportId);
            if (!report) return;

            document.getElementById('modalTitle').textContent = `Export: ${report.name}`;
            document.getElementById('reportEndpoint').value = report.endpoint;
            
            // Render extra fields
            const additionalFields = document.getElementById('additionalFields');
            additionalFields.innerHTML = '';
            
            if (report.extraFields) {
                report.extraFields.forEach(field => {
                    let inputHtml = '';
                    if (field.type === 'select') {
                        const options = field.options.map(opt => 
                            `<option value="${opt}">${opt || 'All'}</option>`
                        ).join('');
                        inputHtml = `<select name="${field.name}" id="${field.name}">${options}</select>`;
                    } else {
                        inputHtml = `<input type="text" name="${field.name}" id="${field.name}" placeholder="${field.placeholder || ''}">`;
                    }
                    
                    additionalFields.innerHTML += `
                        <div class="form-group">
                            <label>${field.label}</label>
                            ${inputHtml}
                        </div>
                    `;
                });
            }

            document.getElementById('exportModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('exportModal').classList.remove('active');
            document.getElementById('downloadProgress').classList.remove('active');
        }

        async function exportReport(event) {
            event.preventDefault();
            
            if (!isAuthenticated) {
                showAlert('Please authenticate first before exporting reports.', 'error');
                return;
            }

            const form = event.target;
            const endpoint = document.getElementById('reportEndpoint').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            // Build query string
            const params = new URLSearchParams();
            params.append('startTime', `${startDate}T00:00:00Z`);
            params.append('endTime', `${endDate}T23:59:59Z`);

            // Include active account selection
            const activeAccount = document.getElementById('accountSelect').value;
            if (activeAccount) {
                params.append('accountKey', activeAccount);
            }

            // Add extra fields
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                if (value && key !== 'endpoint' && key !== 'startDate' && key !== 'endDate') {
                    params.append(key, value);
                }
            }

            // Show progress
            document.getElementById('downloadProgress').classList.add('active');

            try {
                const response = await fetch(`${API_BASE}${endpoint}?${params.toString()}`);
                
                if (!response.ok) {
                    let errorMsg = `HTTP ${response.status}: ${response.statusText}`;
                    try {
                        const text = await response.text();
                        const json = JSON.parse(text);
                        if (json.message) errorMsg = json.message;
                    } catch (_) {}
                    throw new Error(errorMsg);
                }

                // Verify the response is actually CSV, not an HTML error page
                const contentType = response.headers.get('Content-Type') || '';
                if (contentType.includes('text/html')) {
                    const text = await response.text();
                    throw new Error('Server returned an error instead of CSV data. Please check your authentication and try again.');
                }

                // Get filename from header or generate one
                const contentDisposition = response.headers.get('Content-Disposition');
                let filename = 'report.csv';
                if (contentDisposition) {
                    const match = contentDisposition.match(/filename="(.+)"/);
                    if (match) filename = match[1];
                }

                // Download the file
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

                showAlert(`Report downloaded successfully: ${filename}`, 'success');
                closeModal();
            } catch (error) {
                console.error('Export failed:', error);
                showAlert(`Export failed: ${error.message}`, 'error');
                document.getElementById('downloadProgress').classList.remove('active');
            }
        }

        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // Check for OAuth callback
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('code')) {
            // Redirect handled by backend, just refresh status
            window.history.replaceState({}, document.title, window.location.pathname);
            checkAuthStatus();
        }
    </script>
</body>
</html>
