@extends('error-manager::layouts.app')

@section('title', 'Error Statistics')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <a href="{{ route('error-manager.dashboard.index') }}" class="btn btn-secondary mb-3">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1 class="h3 mb-0 text-gray-800">Error Statistics</h1>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Time Range</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('error-manager.dashboard.statistics') }}" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="period">Group By</label>
                        <select class="form-control" id="period" name="period">
                            <option value="daily" {{ $period == 'daily' ? 'selected' : '' }}>Daily</option>
                            <option value="weekly" {{ $period == 'weekly' ? 'selected' : '' }}>Weekly</option>
                            <option value="monthly" {{ $period == 'monthly' ? 'selected' : '' }}>Monthly</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="days">Time Range</label>
                        <select class="form-control" id="days" name="days">
                            <option value="7" {{ $days == 7 ? 'selected' : '' }}>Last 7 days</option>
                            <option value="14" {{ $days == 14 ? 'selected' : '' }}>Last 14 days</option>
                            <option value="30" {{ $days == 30 ? 'selected' : '' }}>Last 30 days</option>
                            <option value="60" {{ $days == 60 ? 'selected' : '' }}>Last 60 days</option>
                            <option value="90" {{ $days == 90 ? 'selected' : '' }}>Last 90 days</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Top Error Codes Chart -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Top Error Codes</h6>
        </div>
        <div class="card-body">
            <div class="chart-bar">
                <canvas id="topErrorsChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Error Type Distribution -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Error Type Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie">
                        <canvas id="errorTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Trends -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Error Trends</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="errorTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Error Code Data -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Error Codes Breakdown</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Error Code</th>
                            <th>Count</th>
                            <th>% of Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalErrors = array_sum(array_column($topErrorCodes, 'count')); @endphp
                        @foreach($topErrorCodes as $index => $error)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $error['error_code'] }}</td>
                                <td>{{ $error['count'] }}</td>
                                <td>{{ round(($error['count'] / $totalErrors) * 100, 2) }}%</td>
                                <td>
                                    <a href="{{ route('error-manager.dashboard.index', ['code' => $error['error_code']]) }}" class="btn btn-sm btn-primary">
                                        View Errors
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Top Error Codes Chart
        var topErrorsChart = document.getElementById('topErrorsChart').getContext('2d');
        var topErrorsData = @json($topErrorCodes);
        
        new Chart(topErrorsChart, {
            type: 'bar',
            data: {
                labels: topErrorsData.map(function(item) { return item.error_code; }),
                datasets: [{
                    label: 'Error Count',
                    data: topErrorsData.map(function(item) { return item.count; }),
                    backgroundColor: 'rgba(78, 115, 223, 0.6)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                }
            }
        });
        
        // Error Type Distribution Chart
        var errorTypeChart = document.getElementById('errorTypeChart').getContext('2d');
        var errorTypeData = @json($errorsByType);
        
        // Generate random colors for each type
        function getRandomColor() {
            var letters = '0123456789ABCDEF';
            var color = '#';
            for (var i = 0; i < 6; i++) {
                color += letters[Math.floor(Math.random() * 16)];
            }
            return color;
        }
        
        var backgroundColor = errorTypeData.map(function() { return getRandomColor(); });
        
        new Chart(errorTypeChart, {
            type: 'pie',
            data: {
                labels: errorTypeData.map(function(item) { return item.type; }),
                datasets: [{
                    data: errorTypeData.map(function(item) { return item.count; }),
                    backgroundColor: backgroundColor,
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        
        // Error Trends Chart
        var errorTrendsChart = document.getElementById('errorTrendsChart').getContext('2d');
        var errorData = @json($errorData);
        
        // Prepare datasets for each error code
        var datasets = [];
        var colors = [
            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', 
            '#6f42c1', '#fd7e14', '#20c9a6', '#858796', '#5a5c69'
        ];
        
        var i = 0;
        for (var code in errorData) {
            var data = errorData[code];
            var color = colors[i % colors.length];
            
            datasets.push({
                label: code,
                data: data.map(function(item) { return item.count; }),
                borderColor: color,
                backgroundColor: 'transparent',
                pointBackgroundColor: color,
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: color,
                borderWidth: 2
            });
            
            i++;
        }
        
        // Find the first error code to get the time periods
        var firstCode = Object.keys(errorData)[0];
        var labels = [];
        
        if (firstCode) {
            labels = errorData[firstCode].map(function(item) { return item.period; });
        }
        
        new Chart(errorTrendsChart, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                }
            }
        });
    });
</script>
@endsection