@php
    $base = rtrim(request()->getSchemeAndHttpHost() . request()->getBaseUrl(), '/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin — Users</title>
    <link href="{{ $base }}/vendor/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ $base }}/vendor/fontawesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ $base }}/css/dashboard.css">
    <style>
        .badge-verified   { background: rgba(34,197,94,0.25); color:#86efac; border:1px solid rgba(34,197,94,0.4); }
        .badge-pending    { background: rgba(234,179,8,0.2);  color:#fde047; border:1px solid rgba(234,179,8,0.35); }
        .btn-verify-on    { border-color:#22c55e; color:#86efac; }
        .btn-verify-on:hover { background:rgba(34,197,94,0.15); color:#86efac; }
        .btn-verify-off   { border-color:#fde047; color:#fde047; }
        .btn-verify-off:hover { background:rgba(234,179,8,0.1); color:#fde047; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark navbar-expand-lg mb-4">
    <div class="container-fluid">
        <span class="navbar-brand"><i class="fas fa-user-shield me-2"></i>Admin Panel</span>
        <div class="navbar-nav ms-auto flex-row flex-wrap gap-2 align-items-center">
            <a class="nav-link active" href="{{ route('admin.index') }}"><i class="fas fa-users me-1"></i>Users</a>
            <a class="nav-link" href="{{ route('admin.feedback.index') }}"><i class="fas fa-comments me-1"></i>Feedback</a>
            <form method="post" action="{{ route('logout') }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-light">Logout</button></form>
        </div>
    </div>
</nav>
<div class="container">
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif        <div class="card glass-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fas fa-users me-2"></i>Registered Users</span>
            <small class="text-muted">{{ $users->total() }} total</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            <tr>
                                <td class="text-muted small">{{ $u->id }}</td>
                                <td class="fw-semibold">{{ $u->name }}</td>
                                <td class="small text-muted">{{ $u->email }}</td>
                                <td>
                                    @if ($u->is_verified)
                                        <span class="badge badge-verified rounded-pill px-3 py-1">
                                            <i class="fas fa-circle-check me-1"></i>Verified
                                        </span>
                                    @else
                                        <span class="badge badge-pending rounded-pill px-3 py-1">
                                            <i class="fas fa-clock me-1"></i>Pending
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                        {{-- Verify toggle --}}
                                        <form method="post" action="{{ route('admin.users.verify', $u) }}">
                                            @csrf
                                            @method('PATCH')
                                            @if ($u->is_verified)
                                                <button type="submit" class="btn btn-sm btn-outline-warning btn-verify-off"
                                                        title="Click to revoke verification"
                                                        onclick="return confirm('Unverify {{ addslashes($u->name) }}? They will be locked out.')">
                                                    <i class="fas fa-user-xmark me-1"></i>Unverify
                                                </button>
                                            @else
                                                <button type="submit" class="btn btn-sm btn-outline-success btn-verify-on"
                                                        title="Grant access to this user">
                                                    <i class="fas fa-user-check me-1"></i>Verify
                                                </button>
                                            @endif
                                        </form>

                                        {{-- Delete / Decline --}}
                                        <form method="post" action="{{ route('admin.users.destroy', $u) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Permanently delete {{ addslashes($u->name) }}? This cannot be undone.')">
                                                <i class="fas fa-trash me-1"></i>{{ $u->is_verified ? 'Delete' : 'Decline' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-transparent border-secondary">
            {{ $users->links() }}
        </div>
    </div>
</div>
<script src="{{ $base }}/vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
