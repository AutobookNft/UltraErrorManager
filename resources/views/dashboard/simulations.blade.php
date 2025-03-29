@section('styles')
<style>
    /* Migliora visibilità delle tab non attive */
    .nav-tabs .nav-link {
        color: #495057;
        background-color: #f1f3f5;
        border: 1px solid #dee2e6;
        margin-right: 3px;
        font-weight: 500;
    }

    .nav-tabs .nav-link:hover {
        border-color: #4e73df;
        background-color: #eaecf4;
    }

    .nav-tabs .nav-link.active {
        color: #4e73df;
        background-color: #fff;
        border-bottom-color: #fff;
        font-weight: 600;
    }
</style>
@endsection
@extends('error-manager::layouts.app')

@section('title', 'Error Simulations')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <a href="{{ route('error-manager.dashboard.index') }}" class="btn btn-secondary mb-3">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1 class="h3 mb-0 text-gray-800">Error Simulations</h1>
            <p class="mt-2">Activate error simulations to test how your application handles different error scenarios.</p>
        </div>
    </div>

    <!-- Warning for Production -->
    @if(app()->environment() === 'production')
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Warning:</strong> Error simulations are disabled in production environment. Please use staging or development environment for testing.
        </div>
    @endif

    <!-- Active Simulations Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Active Simulations</h6>
            @if(count($activeSimulations) > 0)
                <form action="{{ route('error-manager.dashboard.simulations.deactivate') }}" method="POST">
                    @csrf
                    <input type="hidden" name="error_code" value="all">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="fas fa-power-off"></i> Deactivate All
                    </button>
                </form>
            @endif
        </div>
        <div class="card-body">
            @if(count($activeSimulations) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Error Code</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeSimulations as $code => $value)
                                @if($value)
                                    @php
                                        $config = \Ultra\ErrorManager\Facades\UltraError::getErrorConfig($code);
                                        $type = $config['type'] ?? 'unknown';
                                        $description = $config['dev_message'] ?? ($config['dev_message_key'] ?? 'No description available');
                                    @endphp
                                    <tr>
                                        <td>{{ $code }}</td>
                                        <td>
                                            <span class="badge badge-{{ $type === 'critical' ? 'danger' : ($type === 'warning' ? 'warning' : 'primary') }}">
                                                {{ ucfirst($type) }}
                                            </span>
                                        </td>
                                        <td>{{ $description }}</td>
                                        <td>
                                            <form action="{{ route('error-manager.dashboard.simulations.deactivate') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="error_code" value="{{ $code }}">
                                                <button type="submit" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-power-off"></i> Deactivate
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-center">No active error simulations.</p>
            @endif
        </div>
    </div>

    <!-- Available Errors Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Available Error Simulations</h6>
        </div>
        <div class="card-body">
            <!-- Tabs for error types -->
            <ul class="nav nav-tabs" id="errorTypeTabs" role="tablist">
                @foreach($errorsByType as $type => $errors)
                    <li class="nav-item">
                        <a class="nav-link {{ $loop->first ? 'active' : '' }}" id="{{ $type }}-tab" data-toggle="tab" href="#{{ $type }}" role="tab">
                            {{ ucfirst($type) }} ({{ count($errors) }})
                        </a>
                    </li>
                @endforeach
            </ul>

            <!-- Tab content -->
            <div class="tab-content mt-3" id="errorTypeContent">
                @foreach($errorsByType as $type => $errors)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $type }}" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Error Code</th>
                                        <th>Description</th>
                                        <th>Blocking Level</th>
                                        <th>UI Display</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($errors as $error)
                                        <tr class="{{ $error['active'] ? 'table-warning' : '' }}">
                                            <td>{{ $error['code'] }}</td>
                                            <td>
                                                {{ $error['config']['dev_message'] ?? ($error['config']['dev_message_key'] ?? 'No description available') }}
                                            </td>
                                            <td>{{ ucfirst($error['config']['blocking'] ?? 'unknown') }}</td>
                                            <td>{{ $error['config']['msg_to'] ?? 'default' }}</td>
                                            <td>
                                                @if($error['active'])
                                                    <form action="{{ route('error-manager.dashboard.simulations.deactivate') }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="error_code" value="{{ $error['code'] }}">
                                                        <button type="submit" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-power-off"></i> Deactivate
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('error-manager.dashboard.simulations.activate') }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="error_code" value="{{ $error['code'] }}">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-play"></i> Activate
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- How to Test Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">How to Test</h6>
        </div>
        <div class="card-body">
            <p>Follow these steps to test error scenarios:</p>
            <ol>
                <li>Activate one or more error simulations from the tables above</li>
                <li>Navigate to the part of your application that might trigger the error</li>
                <li>Observe how the error is handled and displayed to users</li>
                <li>Return to this page to deactivate simulations when testing is complete</li>
            </ol>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Tip:</strong> Error simulations are only active for your current session and will not affect other users.
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Enable tooltips
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>
@endsection
