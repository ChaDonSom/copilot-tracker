<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Copilot Usage Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        .topbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 16px 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .topbar h1 {
            font-size: 20px;
            font-weight: 600;
        }

        .topbar-right {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 13px;
        }

        .topbar-right span { opacity: 0.85; }

        .btn {
            border: none;
            padding: 7px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: opacity .15s;
        }

        .btn:hover { opacity: 0.85; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-light {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .btn-danger {
            background: rgba(220,53,69,0.85);
            color: white;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* Hero stat */
        .hero-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .hero-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            text-align: center;
        }

        .hero-card .label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 8px;
        }

        .hero-card .big-value {
            font-size: 40px;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 6px;
        }

        .hero-card .detail {
            font-size: 13px;
            color: #777;
        }

        .color-green { color: #22c55e; }
        .color-blue { color: #667eea; }
        .color-orange { color: #f59e0b; }
        .color-red { color: #ef4444; }

        /* Secondary stats */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .stat-card .stat-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #999;
            margin-bottom: 6px;
        }

        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 3px;
        }

        .stat-card .stat-sub {
            font-size: 12px;
            color: #aaa;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #f59e0b 0%, #ef4444 100%);
        }

        /* Chart card */
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .chart-header h2 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .chart-controls {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .toggle-group {
            display: flex;
            background: #f3f4f6;
            padding: 3px;
            border-radius: 6px;
        }

        .toggle-btn {
            background: transparent;
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            color: #888;
            transition: all .15s;
        }

        .toggle-btn.active {
            background: white;
            color: #667eea;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        }

        .nav-btn {
            background: #f3f4f6;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .15s;
        }

        .nav-btn:hover { background: #e5e7eb; }
        .nav-btn:disabled { opacity: 0.3; cursor: not-allowed; }

        .range-label {
            font-size: 12px;
            color: #888;
            min-width: 80px;
            text-align: center;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .alert {
            background: #fffbeb;
            border: 1px solid #fbbf24;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert.info {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1e40af;
        }

        @media (max-width: 768px) {
            .hero-stats { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>🚀 Copilot Usage</h1>
        <div class="topbar-right">
            <span>{{ $user->github_username }}@if($user->copilot_plan) · {{ $user->copilot_plan }}@endif</span>
            <button id="refreshBtn" class="btn btn-light">🔄 Refresh</button>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-danger">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        @if(!$snapshot)
            <div class="alert info">
                <strong>Welcome!</strong> Your usage data is being fetched. Please refresh the page in a moment.
            </div>
        @else
            @if($snapshot->percent_remaining < 25)
                <div class="alert">
                    ⚠️ You've used {{ number_format(100 - $snapshot->percent_remaining, 1) }}% of your monthly quota. Consider slowing down.
                </div>
            @endif

            {{-- ====== PRIMARY HERO STATS ====== --}}
            <div class="hero-stats">
                {{-- End-of-day % left --}}
                @if($recommendation && $recommendation['endOfDayPercentageLeft'] !== null)
                <div class="hero-card" data-stat="end-of-day-percentage">
                    <div class="label">End-of-Day Quota Left</div>
                    <div class="big-value {{ $recommendation['endOfDayPercentageLeft'] < 25 ? 'color-orange' : 'color-green' }}">
                        {{ number_format($recommendation['endOfDayPercentageLeft'], 1) }}%
                    </div>
                    <div class="detail">Projected: {{ number_format($recommendation['endOfDayUsage']) }} / {{ number_format($snapshot->quota_limit) }} used</div>
                </div>
                @endif

                {{-- Requests used / left --}}
                <div class="hero-card" data-stat="used-left">
                    <div class="label">Requests Used / Remaining</div>
                    <div class="big-value color-blue">{{ number_format($snapshot->used) }}</div>
                    <div class="detail">{{ number_format($snapshot->remaining) }} remaining of {{ number_format($snapshot->quota_limit) }}</div>
                    <div class="progress-bar" style="margin-top:12px">
                        <div class="progress-fill {{ $snapshot->percent_remaining < 25 ? 'warning' : '' }}"
                             style="width: {{ 100 - $snapshot->percent_remaining }}%"></div>
                    </div>
                </div>

                {{-- Percentage used / left --}}
                <div class="hero-card" data-stat="percent-used">
                    <div class="label">Percentage Used</div>
                    @php $percentUsed = $snapshot->quota_limit > 0 ? round(($snapshot->used / $snapshot->quota_limit) * 100, 1) : round(100 - $snapshot->percent_remaining, 1); @endphp
                    <div class="big-value {{ $percentUsed > 75 ? 'color-red' : ($percentUsed > 50 ? 'color-orange' : 'color-blue') }}">
                        {{ number_format($percentUsed, 1) }}%
                    </div>
                    <div class="detail">{{ number_format($snapshot->percent_remaining, 1) }}% left</div>
                </div>
            </div>

            {{-- ====== SECONDARY STATS ROW ====== --}}
            <div class="stats-row">
                @if($recommendation)
                <div class="stat-card" data-stat="daily-recommended">
                    <div class="stat-label">Recommended / Day</div>
                    <div class="stat-value">{{ number_format($recommendation['dailyRecommended']) }}</div>
                    <div class="stat-sub">requests/day · {{ number_format($recommendation['daysRemaining']) }} days left</div>
                </div>
                @endif

                <div class="stat-card" data-stat="today-used">
                    <div class="stat-label">Used Today</div>
                    <div class="stat-value">{{ number_format($todayUsed) }}</div>
                    <div class="stat-sub">requests so far today</div>
                </div>

                <div class="stat-card" data-stat="resets-on">
                    <div class="stat-label">Resets On</div>
                    <div class="stat-value">{{ $snapshot->reset_date->format('M d') }}</div>
                    <div class="stat-sub">{{ $snapshot->reset_date->diffForHumans() }}</div>
                </div>

                @if($recommendation)
                <div class="stat-card" data-stat="ideal-daily-rate">
                    <div class="stat-label">Ideal Daily Rate</div>
                    <div class="stat-value">{{ number_format($recommendation['dailyIdealUsage']) }}</div>
                    <div class="stat-sub">even distribution / day</div>
                </div>
                @endif
            </div>

            {{-- ====== CHART ====== --}}
            <div class="chart-card">
                <div class="chart-header">
                    <h2>📊 Usage Trend</h2>
                    <div class="chart-controls">
                        {{-- Granularity toggle --}}
                        <div class="toggle-group">
                            <button class="toggle-btn active" data-view="daily">Daily</button>
                            <button class="toggle-btn" data-view="per-check">Per-Check</button>
                        </div>
                        {{-- Range selector --}}
                        <div class="toggle-group" id="rangeToggle">
                            <button class="toggle-btn {{ $chartRange == 1 ? 'active' : '' }}" data-range="1">1D</button>
                            <button class="toggle-btn {{ $chartRange == 7 ? 'active' : '' }}" data-range="7">7D</button>
                            <button class="toggle-btn {{ $chartRange == 30 ? 'active' : '' }}" data-range="30">30D</button>
                        </div>
                        {{-- Navigation --}}
                        <button class="nav-btn" id="chartPrev" title="Previous period">◀</button>
                        <span class="range-label" id="rangeLabel">
                            @if($chartOffset == 0)
                                Last {{ $chartRange }} day{{ $chartRange > 1 ? 's' : '' }}
                            @else
                                {{ now()->subDays(($chartOffset + 1) * $chartRange)->format('M d') }} – {{ now()->subDays($chartOffset * $chartRange)->format('M d') }}
                            @endif
                        </span>
                        <button class="nav-btn" id="chartNext" title="Next period" {{ $chartOffset == 0 ? 'disabled' : '' }}>▶</button>
                    </div>
                </div>
                @if(count($chartData['labels']) > 0 || count($perCheckData['timestamps'] ?? []) > 0)
                    <canvas id="usageChart"></canvas>
                @else
                    <div class="no-data">
                        No data for this period. Try a different range or navigate to another period.
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if($snapshot)
    <script>
        // -------- State --------
        let currentView = 'daily';
        let chartRange = {{ $chartRange }};
        let chartOffset = {{ $chartOffset }};
        let isRefreshing = false;
        let isFetchingChart = false;

        // -------- Chart setup --------
        @if(count($chartData['labels']) > 0 || count($perCheckData['timestamps'] ?? []) > 0)
        const ctx = document.getElementById('usageChart').getContext('2d');

        const gradient1 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient1.addColorStop(0, 'rgba(102, 126, 234, 0.4)');
        gradient1.addColorStop(1, 'rgba(118, 75, 162, 0.05)');

        let dailyData = {
            labels: {!! json_encode($chartData['labels']) !!},
            datasets: [
                {
                    label: 'Requests Used',
                    data: {!! json_encode($chartData['used']) !!},
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: gradient1,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 3
                },
                {
                    label: 'Recommended',
                    data: {!! json_encode($chartData['recommendation']) !!},
                    borderColor: 'rgba(245, 158, 11, 0.7)',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0,
                    pointRadius: 0,
                    borderWidth: 1.5
                }
            ]
        };

        let perCheckData = {
            datasets: [
                {
                    label: 'Cumulative Used',
                    data: {!! json_encode(array_map(fn($t, $v) => ['x' => $t, 'y' => $v], $perCheckData['timestamps'], $perCheckData['used'])) !!},
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: gradient1,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 2
                },
                {
                    label: 'Recommended',
                    data: {!! json_encode(array_map(fn($t, $v) => ['x' => $t, 'y' => $v], $perCheckData['timestamps'], $perCheckData['recommendation'])) !!},
                    borderColor: 'rgba(245, 158, 11, 0.7)',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0,
                    pointRadius: 0,
                    borderWidth: 1.5
                }
            ]
        };

        const chart = new Chart(ctx, {
            type: 'line',
            data: dailyData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, padding: 16, font: { size: 12 } } },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => v.toLocaleString(), font: { size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.04)' }
                    },
                    x: {
                        ticks: { maxRotation: 45, minRotation: 0, font: { size: 11 } },
                        grid: { display: false }
                    }
                }
            }
        });

        function applyViewToChart() {
            if (currentView === 'daily') {
                chart.data = dailyData;
                chart.options.scales.x.type = 'category';
                delete chart.options.scales.x.time;
            } else {
                chart.data = perCheckData;
                chart.options.scales.x.type = 'time';
                chart.options.scales.x.time = {
                    displayFormats: { hour: 'MMM d HH:mm', day: 'MMM d' },
                    tooltipFormat: 'MMM d, yyyy HH:mm'
                };
            }
            chart.update();
        }

        // Granularity toggle
        document.querySelectorAll('[data-view]').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('[data-view]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentView = this.dataset.view;
                applyViewToChart();
            });
        });
        @endif

        // -------- Range & Navigation --------
        function updateRangeLabel() {
            const label = document.getElementById('rangeLabel');
            const nextBtn = document.getElementById('chartNext');
            if (chartOffset === 0) {
                label.textContent = 'Last ' + chartRange + ' day' + (chartRange > 1 ? 's' : '');
            } else {
                const end = new Date();
                end.setDate(end.getDate() - chartOffset * chartRange);
                const start = new Date(end);
                start.setDate(start.getDate() - chartRange);
                label.textContent = start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' – ' + end.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }
            if (nextBtn) nextBtn.disabled = (chartOffset === 0);
        }

        function fetchChartData() {
            if (isFetchingChart) return;
            isFetchingChart = true;

            fetch('{{ route('dashboard.chart-data') }}?range=' + chartRange + '&offset=' + chartOffset, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                @if(count($chartData['labels']) > 0 || count($perCheckData['timestamps'] ?? []) > 0)
                if (data.chartData) {
                    dailyData.labels = data.chartData.labels;
                    dailyData.datasets[0].data = data.chartData.used;
                    dailyData.datasets[1].data = data.chartData.recommendation;
                }
                if (data.perCheckData && data.perCheckData.timestamps) {
                    perCheckData.datasets[0].data = data.perCheckData.timestamps.map((t, i) => ({ x: t, y: data.perCheckData.used[i] }));
                    perCheckData.datasets[1].data = data.perCheckData.timestamps.map((t, i) => ({ x: t, y: data.perCheckData.recommendation[i] }));
                }
                applyViewToChart();
                @endif
                updateRangeLabel();
                isFetchingChart = false;
            })
            .catch(err => {
                console.error('Chart fetch error:', err);
                isFetchingChart = false;
            });
        }

        document.querySelectorAll('[data-range]').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('[data-range]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                chartRange = parseInt(this.dataset.range);
                chartOffset = 0;
                fetchChartData();
            });
        });

        const prevBtn = document.getElementById('chartPrev');
        const nextBtn = document.getElementById('chartNext');
        if (prevBtn) prevBtn.addEventListener('click', () => { chartOffset++; fetchChartData(); });
        if (nextBtn) nextBtn.addEventListener('click', () => { if (chartOffset > 0) { chartOffset--; fetchChartData(); } });

        // -------- Helpers --------
        function formatRelativeTime(date) {
            const now = new Date();
            const diffMs = date.getTime() - now.getTime();
            const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));
            if (diffDays > 0) return 'in ' + diffDays + ' day' + (diffDays === 1 ? '' : 's');
            if (diffDays < 0) { const a = Math.abs(diffDays); return a + ' day' + (a === 1 ? '' : 's') + ' ago'; }
            return 'today';
        }

        function n(v, fb = 0) { const p = Number(v); return Number.isFinite(p) ? p : fb; }

        // -------- Refresh --------
        function refreshDashboard() {
            if (isRefreshing) return;
            const btn = document.getElementById('refreshBtn');
            isRefreshing = true;
            btn.disabled = true;
            btn.textContent = '⏳ Refreshing...';

            fetch('{{ route('dashboard.refresh') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ range: chartRange, offset: chartOffset })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.snapshot || typeof data.snapshot.quota_limit === 'undefined') {
                    throw new Error('Invalid response');
                }

                const s = data.snapshot;
                const quotaLimit = n(s.quota_limit);
                const remaining = n(s.remaining);
                const percentRemaining = n(s.percent_remaining);
                const used = n(s.used);
                const percentUsed = quotaLimit > 0 ? ((used / quotaLimit) * 100) : (100 - percentRemaining);

                // Hero: end-of-day
                const eodCard = document.querySelector('[data-stat="end-of-day-percentage"]');
                if (eodCard && data.recommendation && data.recommendation.endOfDayPercentageLeft !== null) {
                    const eodPct = n(data.recommendation.endOfDayPercentageLeft);
                    const eodUsage = n(data.recommendation.endOfDayUsage);
                    const v = eodCard.querySelector('.big-value');
                    const d = eodCard.querySelector('.detail');
                    if (v) {
                        v.textContent = eodPct.toFixed(1) + '%';
                        v.className = 'big-value ' + (eodPct < 25 ? 'color-orange' : 'color-green');
                    }
                    if (d) d.textContent = 'Projected: ' + Math.round(eodUsage).toLocaleString() + ' / ' + quotaLimit.toLocaleString() + ' used';
                }

                // Hero: used/left
                const ulCard = document.querySelector('[data-stat="used-left"]');
                if (ulCard) {
                    const v = ulCard.querySelector('.big-value');
                    const d = ulCard.querySelector('.detail');
                    const pf = ulCard.querySelector('.progress-fill');
                    if (v) v.textContent = used.toLocaleString();
                    if (d) d.textContent = remaining.toLocaleString() + ' remaining of ' + quotaLimit.toLocaleString();
                    if (pf) {
                        pf.style.width = Math.min(100, 100 - percentRemaining) + '%';
                        pf.classList.toggle('warning', percentRemaining < 25);
                    }
                }

                // Hero: percent used
                const puCard = document.querySelector('[data-stat="percent-used"]');
                if (puCard) {
                    const v = puCard.querySelector('.big-value');
                    const d = puCard.querySelector('.detail');
                    if (v) {
                        v.textContent = percentUsed.toFixed(1) + '%';
                        v.className = 'big-value ' + (percentUsed > 75 ? 'color-red' : (percentUsed > 50 ? 'color-orange' : 'color-blue'));
                    }
                    if (d) d.textContent = percentRemaining.toFixed(1) + '% left';
                }

                // Secondary: recommended
                const drCard = document.querySelector('[data-stat="daily-recommended"]');
                if (drCard && data.recommendation) {
                    const v = drCard.querySelector('.stat-value');
                    const sub = drCard.querySelector('.stat-sub');
                    if (v) v.textContent = n(data.recommendation.dailyRecommended).toLocaleString();
                    if (sub) sub.textContent = 'requests/day · ' + Math.round(n(data.recommendation.daysRemaining)) + ' days left';
                }

                // Secondary: today used
                const tuCard = document.querySelector('[data-stat="today-used"]');
                if (tuCard && typeof data.todayUsed !== 'undefined') {
                    const v = tuCard.querySelector('.stat-value');
                    if (v) v.textContent = n(data.todayUsed).toLocaleString();
                }

                // Secondary: resets on
                const roCard = document.querySelector('[data-stat="resets-on"]');
                if (roCard && s.reset_date) {
                    const rd = new Date(s.reset_date);
                    const v = roCard.querySelector('.stat-value');
                    const sub = roCard.querySelector('.stat-sub');
                    if (v && !isNaN(rd.getTime())) v.textContent = rd.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    if (sub && !isNaN(rd.getTime())) sub.textContent = formatRelativeTime(rd);
                }

                // Secondary: ideal daily
                const idCard = document.querySelector('[data-stat="ideal-daily-rate"]');
                if (idCard && data.recommendation) {
                    const v = idCard.querySelector('.stat-value');
                    if (v) v.textContent = n(data.recommendation.dailyIdealUsage).toLocaleString();
                }

                // Chart
                @if(count($chartData['labels']) > 0 || count($perCheckData['timestamps'] ?? []) > 0)
                if (data.chartData) {
                    dailyData.labels = data.chartData.labels;
                    dailyData.datasets[0].data = data.chartData.used;
                    dailyData.datasets[1].data = data.chartData.recommendation;
                }
                if (data.perCheckData && data.perCheckData.timestamps) {
                    perCheckData.datasets[0].data = data.perCheckData.timestamps.map((t, i) => ({ x: t, y: data.perCheckData.used[i] }));
                    perCheckData.datasets[1].data = data.perCheckData.timestamps.map((t, i) => ({ x: t, y: data.perCheckData.recommendation[i] }));
                }
                applyViewToChart();
                @endif

                isRefreshing = false;
                btn.disabled = false;
                btn.textContent = '🔄 Refresh';
            })
            .catch(err => {
                console.error('Refresh error:', err);
                isRefreshing = false;
                btn.disabled = false;
                btn.textContent = '🔄 Refresh';
                alert('Failed to refresh. Please try again.');
            });
        }

        document.getElementById('refreshBtn').addEventListener('click', refreshDashboard);

        // Auto-refresh every 5 minutes (only when visible)
        setInterval(() => {
            if (document.visibilityState === 'visible') refreshDashboard();
        }, 5 * 60 * 1000);
    </script>
    @else
    <script>
        function refreshDashboard() { window.location.reload(); }
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) refreshBtn.addEventListener('click', refreshDashboard);
    </script>
    @endif
</body>
</html>
