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
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
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
            </div>

            <div class="chart-card">
                <h2>📊 Usage Trend (Last 30 Days)</h2>
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

        const gradient2 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient2.addColorStop(0, 'rgba(75, 192, 192, 0.5)');
        gradient2.addColorStop(1, 'rgba(75, 192, 192, 0.1)');

        new Chart(ctx, {
            type: 'line',
            data: {
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
                        label: 'Requests Remaining',
                        data: {!! json_encode($chartData['remaining']) !!},
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: gradient2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
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
    </script>
    @endif
</body>
</html>
