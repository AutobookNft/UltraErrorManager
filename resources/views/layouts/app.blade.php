<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title', 'Ultra Error Manager')</title>

    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">

    <style>
        /* Sidebar */
        .sidebar {
            min-height: 100vh;
            background-color: #4e73df;
            background-image: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            background-size: cover;
        }

        .sidebar-brand {
            height: 4.375rem;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 800;
            padding: 1.5rem 1rem;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            z-index: 1;
        }

        .sidebar-brand-icon i {
            font-size: 2rem;
        }

        .sidebar-brand-text {
            display: inline;
            margin-left: 0.5rem;
        }

        .sidebar hr.sidebar-divider {
            margin: 0 1rem 1rem;
        }

        .sidebar-heading {
            text-align: left;
            padding: 0 1rem;
            font-weight: 800;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.4);
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: block;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 700;
            font-size: 0.85rem;
        }

        .nav-link:hover {
            color: #fff;
        }

        .nav-link i {
            margin-right: 0.25rem;
            font-size: 0.85rem;
        }

        .nav-link.active {
            color: #fff;
            font-weight: 700;
        }

        /* Content */
        .content {
            flex: 1 0 auto;
        }

        .navbar {
            background-color: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .navbar-search {
            width: 25rem;
        }

        .topbar .dropdown-list {
            width: 20rem !important;
        }

        .topbar .dropdown-list .dropdown-item {
            white-space: normal;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            border-left: 1px solid #e3e6f0;
            border-right: 1px solid #e3e6f0;
            border-bottom: 1px solid #e3e6f0;
            line-height: 1.3rem;
        }

        /* Card */
        .card {
            margin-bottom: 24px;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .card .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }

        /* Chart containers */
        .chart-area {
            position: relative;
            height: 20rem;
            width: 100%;
        }

        .chart-bar {
            position: relative;
            height: 20rem;
            width: 100%;
        }

        .chart-pie {
            position: relative;
            height: 20rem;
            width: 100%;
        }
    </style>

    @yield('styles')
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ route('error-manager.dashboard.index') }}">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="sidebar-brand-text mx-3">Ultra Error</div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('error-manager.dashboard.index') ? 'active' : '' }}" href="{{ route('error-manager.dashboard.index') }}">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Analysis
            </div>

            <!-- Nav Item - Statistics -->
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('error-manager.dashboard.statistics') ? 'active' : '' }}" href="{{ route('error-manager.dashboard.statistics') }}">
                    <i class="fas fa-fw fa-chart-bar"></i>
                    <span>Statistics</span>
                </a>
            </li>

            <!-- Nav Item - Error Types -->
            <li class="nav-item">
                <a class="nav-link" href="{{ route('error-manager.dashboard.index', ['type' => 'critical']) }}">
                    <i class="fas fa-fw fa-exclamation-circle"></i>
                    <span>Critical Errors</span>
                </a>
            </li>

            <!-- Nav Item - Error Simulations -->
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('error-manager.dashboard.simulations') ? 'active' : '' }}" href="{{ route('error-manager.dashboard.simulations') }}">
                    <i class="fas fa-fw fa-flask"></i>
                    <span>Error Simulations</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Quick Filters
            </div>

            <!-- Nav Item - Recent -->
            <li class="nav-item">
                <a class="nav-link" href="{{ route('error-manager.dashboard.index', ['from_date' => now()->subDays(1)->format('Y-m-d')]) }}">
                    <i class="fas fa-fw fa-clock"></i>
                    <span>Last 24 Hours</span>
                </a>
            </li>

            <!-- Nav Item - Unresolved -->
            <li class="nav-item">
                <a class="nav-link" href="{{ route('error-manager.dashboard.index', ['status' => 'unresolved']) }}">
                    <i class="fas fa-fw fa-exclamation"></i>
                    <span>Unresolved</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Sidebar Toggler -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle">
                    <i class="fas fa-angle-left text-white"></i>
                </button>
            </div>
        </ul>

        <!-- Content Wrapper -->
        <div class="d-flex flex-column content">
            <!-- Topbar -->
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <!-- Sidebar Toggle (Topbar) -->
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                    <i class="fa fa-bars"></i>
                </button>

                <!-- Topbar Title -->
                <div class="d-none d-sm-inline-block ml-1">
                    <h1 class="h5 mb-0 text-gray-800">Ultra Error Manager</h1>
                </div>

                <!-- Topbar Navbar -->
                <ul class="navbar-nav ml-auto">
                    @if(app()->environment() !== 'production')
                    <!-- Nav Item - Simulations -->
                    <li class="nav-item dropdown no-arrow mx-1">
                        <a class="nav-link" href="{{ route('error-manager.dashboard.simulations') }}" role="button">
                            <i class="fas fa-flask fa-fw"></i>
                            <!-- Counter - Active Simulations -->
                            @php
                                $activeSimCount = count(\Ultra\ErrorManager\Facades\TestingConditions::getActiveConditions());
                            @endphp
                            @if($activeSimCount > 0)
                                <span class="badge badge-danger badge-counter">{{ $activeSimCount }}</span>
                            @endif
                        </a>
                    </li>
                    @endif

                    <!-- Nav Item - Back to Main App -->
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link" href="/" role="button">
                            <span class="mr-2 d-none d-lg-inline text-gray-600 small">Back to Main App</span>
                            <i class="fas fa-arrow-circle-left fa-fw"></i>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Begin Page Content -->
            <div class="container-fluid">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                <!-- Page Content -->
                @yield('content')
            </div>

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Ultra Error Manager &copy; {{ date('Y') }}</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('body').classList.toggle('sidebar-toggled');
            document.querySelector('.sidebar').classList.toggle('toggled');
        });

        // Close sidebar on smaller screens when toggler is clicked
        document.getElementById('sidebarToggleTop').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('body').classList.toggle('sidebar-toggled');
            document.querySelector('.sidebar').classList.toggle('toggled');
        });

        // Auto-close alerts after 5 seconds
        window.setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    </script>

    @yield('scripts')
</body>
</html>
