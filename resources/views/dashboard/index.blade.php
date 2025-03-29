@extends('error-manager::layouts.app')

@section('title', 'Error Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0 text-gray-800">Error Dashboard</h1>
        </div>
    </div>

    <!-- Dashboard Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Errors</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Unresolved Errors</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['unresolved'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Critical Errors</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['critical'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-skull-crossbones fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Today's Errors</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['today'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Error Filters</h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                    <a class="dropdown-item" href="{{ route('error-manager.dashboard.statistics') }}">View Statistics</a>
                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#purgeModal">Purge Resolved Errors</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form action="{{ route('error-manager.dashboard.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="type">Error Type</label>
                        <select class="form-control" id="type" name="type">
                            <option value="">All Types</option>
                            @foreach(array_keys($errorTypes) as $errorType)
                                <option value="{{ $errorType }}" {{ $type == $errorType ? 'selected' : '' }}>
                                    {{ ucfirst($errorType) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="code">Error Code</label>
                        <select class="form-control" id="code" name="code">
                            <option value="">All Codes</option>
                            @foreach($errorCodes as $errorCode)
                                <option value="{{ $errorCode }}" {{ $code == $errorCode ? 'selected' : '' }}>
                                    {{ $errorCode }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="all" {{ $status == 'all' ? 'selected' : '' }}>All</option>
                            <option value="unresolved" {{ $status == 'unresolved' ? 'selected' : '' }}>Unresolved</option>
                            <option value="resolved" {{ $status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="from_date">From Date</label>
                        <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $fromDate }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="to_date">To Date</label>
                        <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $toDate }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="{{ route('error-manager.dashboard.index') }}" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Error List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Error Logs</h6>
            <form id="bulkActionForm" action="{{ route('error-manager.dashboard.bulk-resolve') }}" method="POST">
                @csrf
                <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="bulkActionDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Bulk Actions
                    </button>
                    <div class="dropdown-menu" aria-labelledby="bulkActionDropdown">
                        <a class="dropdown-item" href="#" onclick="document.getElementById('bulkActionForm').submit();">Mark Selected as Resolved</a>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th style="width: 20px;">
                                <input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes()">
                            </th>
                            <th>Error Code</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th>URL</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($errors as $error)
                            <tr class="{{ $error->type === 'critical' ? 'table-danger' : ($error->resolved ? 'table-success' : '') }}">
                                <td>
                                    <input type="checkbox" name="error_ids[]" form="bulkActionForm" value="{{ $error->id }}" class="errorCheckbox">
                                </td>
                                <td>{{ $error->error_code }}</td>
                                <td>
                                    <span class="badge badge-{{ $error->type === 'critical' ? 'danger' : ($error->type === 'warning' ? 'warning' : 'primary') }}">
                                        {{ ucfirst($error->type) }}
                                    </span>
                                </td>
                                <td>{{ Str::limit($error->message, 50) }}</td>
                                <td>{{ Str::limit($error->request_url, 30) }}</td>
                                <td>{{ $error->created_at->format('Y-m-d H:i:s') }}</td>
                                <td>
                                    <span class="badge badge-{{ $error->resolved ? 'success' : 'secondary' }}">
                                        {{ $error->resolved ? 'Resolved' : 'Unresolved' }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('error-manager.dashboard.show', $error->id) }}" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if(!$error->resolved)
                                        <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#resolveModal" data-error-id="{{ $error->id }}">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    @else
                                        <a href="{{ route('error-manager.dashboard.unresolve', $error->id) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                    @endif
                                    <a href="#" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#deleteModal" data-error-id="{{ $error->id }}">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">No errors found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                {{ $errors->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1" role="dialog" aria-labelledby="resolveModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="" id="resolveForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="resolveModalLabel">Mark Error as Resolved</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="notes">Resolution Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark as Resolved</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="" id="deleteForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Delete Error Log</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this error log? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Purge Modal -->
<div class="modal fade" id="purgeModal" tabindex="-1" role="dialog" aria-labelledby="purgeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('error-manager.dashboard.purge-resolved') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="purgeModalLabel">Purge Resolved Errors</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="older_than">Delete resolved errors older than:</label>
                        <select class="form-control" id="older_than" name="older_than">
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="90">90 days</option>
                            <option value="180">180 days</option>
                        </select>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> This action cannot be undone. All resolved errors older than the selected period will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Purge Errors</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Set the resolve form action URL when the modal is shown
    $('#resolveModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var errorId = button.data('error-id');
        var form = $('#resolveForm');
        form.attr('action', '/error-manager/dashboard/' + errorId + '/resolve');
    });

    // Set the delete form action URL when the modal is shown
    $('#deleteModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var errorId = button.data('error-id');
        var form = $('#deleteForm');
        form.attr('action', '/error-manager/dashboard/' + errorId);
    });

    // Toggle all checkboxes
    function toggleAllCheckboxes() {
        var checked = $('#selectAll').prop('checked');
        $('.errorCheckbox').prop('checked', checked);
    }
</script>
@endsection