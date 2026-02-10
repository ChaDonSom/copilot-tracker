<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Copilot Usage Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .user-info {
            color: #666;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .refresh-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .refresh-btn:hover {
            background: #5568d3;
        }

        .refresh-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .logout-btn:hover {
            background: #c82333;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            color: #888;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            color: #333;
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .subtitle {
            color: #999;
            font-size: 12px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #f39c12 0%, #e74c3c 100%);
        }

        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .chart-card h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .granularity-toggle {
            display: flex;
            gap: 5px;
            background: #f0f0f0;
            padding: 4px;
            border-radius: 5px;
        }

        .granularity-btn {
            background: transparent;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
            color: #666;
        }

        .granularity-btn.active {
            background: white;
            color: #667eea;
            font-weight: 500;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .alert {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert.info {
            background: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 GitHub Copilot Usage Dashboard</h1>
            <div class="user-info">
                <span>
                    Logged in as <strong>{{ $user->github_username }}</strong>
                    @if($user->copilot_plan)
                        | Plan: <strong>{{ $user->copilot_plan }}</strong>
                    @endif
                </span>
                 <div class="header-actions">
                    <button id="refreshBtn" class="refresh-btn">🔄 Refresh Data</button>
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
                </div>
            </div>
        </div>

        @if(!$snapshot)
            <div class="alert info">
                <strong>Welcome!</strong> Your usage data is being fetched. Please refresh the page in a moment.
            </div>
        @else
            @if($snapshot->percent_remaining < 25)
                <div class="alert">
                    <strong>⚠️ Warning:</strong> You've used {{ 100 - $snapshot->percent_remaining }}% of your monthly quota. Consider reducing usage to avoid running out.
                </div>
            @endif

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Limit</h3>
                    <div class="value">{{ number_format($snapshot->quota_limit) }}</div>
                    <div class="subtitle">requests/month</div>
                </div>

                <div class="stat-card">
                    <h3>Remaining</h3>
                    <div class="value">{{ number_format($snapshot->remaining) }}</div>
                    <div class="subtitle">{{ number_format($snapshot->percent_remaining, 1) }}% left</div>
                    <div class="progress-bar">
                        <div class="progress-fill {{ $snapshot->percent_remaining < 25 ? 'warning' : '' }}"
                             style="width: {{ $snapshot->percent_remaining }}%"></div>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Used This Month</h3>
                    <div class="value">{{ number_format($snapshot->used) }}</div>
                    <div class="subtitle">{{ number_format((($snapshot->used / $snapshot->quota_limit) * 100), 1) }}% consumed</div>
                </div>

                <div class="stat-card">
                    <h3>Resets On</h3>
                    <div class="value">{{ $snapshot->reset_date->format('M d') }}</div>
                    <div class="subtitle">{{ $snapshot->reset_date->diffForHumans() }}</div>
                </div>

                @if($recommendation)
                <div class="stat-card">
                    <h3>Recommended Daily Usage</h3>
                    <div class="value">{{ number_format($recommendation['dailyRecommended']) }}</div>
                    <div class="subtitle">requests/day for {{ $recommendation['daysRemaining'] }} days</div>
                </div>

                <div class="stat-card">
                    <h3>Ideal Daily Rate</h3>
                    <div class="value">{{ number_format($recommendation['dailyIdealUsage']) }}</div>
                    <div class="subtitle">avg requests/day</div>
                </div>
                @endif
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h2>📊 Usage Trend (Last 30 Days)</h2>
                    @if(count($chartData['labels']) > 0)
                    <div class="granularity-toggle">
                        <button class="granularity-btn active" data-view="daily">Daily View</button>
                        <button class="granularity-btn" data-view="per-check">Per-Check View</button>
                    </div>
                    @endif
                </div>
                @if(count($chartData['labels']) > 0)
                    <canvas id="usageChart"></canvas>
                @else
                    <div class="no-data">
                        No historical data available yet. Check back after using Copilot for a few days.
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if($snapshot && count($chartData['labels']) > 0)
    <script>
        const ctx = document.getElementById('usageChart').getContext('2d');

        const gradient1 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient1.addColorStop(0, 'rgba(102, 126, 234, 0.5)');
        gradient1.addColorStop(1, 'rgba(118, 75, 162, 0.1)');

        // Daily view data
        const dailyData = {
            labels: {!! json_encode($chartData['labels']) !!},
            datasets: [
                {
                    label: 'Requests Used',
                    data: {!! json_encode($chartData['used']) !!},
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: gradient1,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Recommended Usage',
                    data: {!! json_encode($chartData['recommendation']) !!},
                    borderColor: 'rgb(255, 159, 64)',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0,
                    pointRadius: 0
                }
            ]
        };

        // Per-check view data
        const perCheckData = {
            labels: {!! json_encode($perCheckData['labels']) !!},
            datasets: [
                {
                    label: 'Cumulative Requests Used',
                    data: {!! json_encode($perCheckData['used']) !!},
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: gradient1,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Recommended Usage',
                    data: {!! json_encode($perCheckData['recommendation']) !!},
                    borderColor: 'rgb(255, 159, 64)',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0,
                    pointRadius: 0
                }
            ]
        };

        const chart = new Chart(ctx, {
            type: 'line',
            data: dailyData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });

        // Handle granularity toggle
        document.querySelectorAll('.granularity-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.granularity-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const view = this.dataset.view;
                if (view === 'daily') {
                    chart.data = dailyData;
                } else {
                    chart.data = perCheckData;
                }
                chart.update();
            });
        });

        // Manual refresh function using JSON API
        function refreshDashboard() {
            const btn = document.getElementById('refreshBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Refreshing...';
            
            fetch('{{ route('dashboard.refresh') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                // Update stats
                if (data.snapshot) {
                    const snapshot = data.snapshot;
                    
                    // Update stat cards
                    document.querySelectorAll('.stat-card').forEach((card, index) => {
                        const valueEl = card.querySelector('.value');
                        const subtitleEl = card.querySelector('.subtitle');
                        
                        if (index === 0) { // Total Limit
                            valueEl.textContent = snapshot.quota_limit.toLocaleString();
                        } else if (index === 1) { // Remaining
                            valueEl.textContent = snapshot.remaining.toLocaleString();
                            subtitleEl.textContent = snapshot.percent_remaining.toFixed(1) + '% left';
                            const progressBar = card.querySelector('.progress-fill');
                            if (progressBar) {
                                progressBar.style.width = snapshot.percent_remaining + '%';
                                if (snapshot.percent_remaining < 25) {
                                    progressBar.classList.add('warning');
                                } else {
                                    progressBar.classList.remove('warning');
                                }
                            }
                        } else if (index === 2) { // Used This Month
                            valueEl.textContent = snapshot.used.toLocaleString();
                            subtitleEl.textContent = ((snapshot.used / snapshot.quota_limit) * 100).toFixed(1) + '% consumed';
                        } else if (index === 3) { // Resets On
                            const resetDate = new Date(snapshot.reset_date);
                            valueEl.textContent = resetDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                        }
                    });
                    
                    // Update recommendation cards if they exist
                    if (data.recommendation) {
                        const recCards = document.querySelectorAll('.stat-card');
                        if (recCards.length > 4) {
                            recCards[4].querySelector('.value').textContent = data.recommendation.dailyRecommended.toLocaleString();
                            recCards[4].querySelector('.subtitle').textContent = 'requests/day for ' + data.recommendation.daysRemaining + ' days';
                        }
                        if (recCards.length > 5) {
                            recCards[5].querySelector('.value').textContent = data.recommendation.dailyIdealUsage.toLocaleString();
                        }
                    }
                }
                
                // Update chart data
                if (data.chartData && data.perCheckData) {
                    // Update daily data
                    dailyData.labels = data.chartData.labels;
                    dailyData.datasets[0].data = data.chartData.used;
                    dailyData.datasets[1].data = data.chartData.recommendation;
                    
                    // Update per-check data
                    perCheckData.labels = data.perCheckData.labels;
                    perCheckData.datasets[0].data = data.perCheckData.used;
                    perCheckData.datasets[1].data = data.perCheckData.recommendation;
                    
                    // Update current chart
                    chart.update();
                }
                
                // Restore button state
                btn.disabled = false;
                btn.textContent = '🔄 Refresh Data';
            })
            .catch(error => {
                console.error('Error refreshing dashboard:', error);
                btn.disabled = false;
                btn.textContent = '🔄 Refresh Data';
                alert('Failed to refresh dashboard. Please try again.');
            });
        }

        // Attach refresh handler to button
        document.getElementById('refreshBtn').addEventListener('click', refreshDashboard);

        // Auto-refresh every 5 minutes - set up only once
        setInterval(() => {
            console.log('Auto-refreshing dashboard...');
            refreshDashboard();
        }, 5 * 60 * 1000);
    </script>
    @else
    <script>
        // Global refresh function for when no snapshot/chart exists yet
        function refreshDashboard() {
            window.location.reload();
        }

        // Attach refresh handler to button
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', refreshDashboard);
        }
    </script>
    @endif
</body>
</html>
