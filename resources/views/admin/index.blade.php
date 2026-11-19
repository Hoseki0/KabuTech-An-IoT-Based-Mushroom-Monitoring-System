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
        .badge-verified   { background: #dcfce7; color:#15803d; border:1px solid #bbf7d0; }
        .badge-pending    { background: #fef9c3; color:#a16207; border:1px solid #fde047; }
        .btn-verify-on    { border-color:#16a34a; color:#15803d; }
        .btn-verify-on:hover { background:#dcfce7; color:#15803d; }
        .btn-verify-off   { border-color:#eab308; color:#a16207; }
        .btn-verify-off:hover { background:#fef9c3; color:#a16207; }
    </style>
</head>
<body>
<nav class="navbar navbar-light navbar-expand-lg mb-4">
    <div class="container-fluid">
        <span class="navbar-brand"><i class="fas fa-user-shield me-2"></i>Admin Panel</span>
        <div class="navbar-nav ms-auto flex-row flex-wrap gap-2 align-items-center">
            <a class="nav-link" href="{{ route('dashboard') }}"><i class="fas fa-gauge-high me-1"></i>Mushroom Dashboard</a>
            <a class="nav-link active" href="{{ route('admin.index') }}"><i class="fas fa-users me-1"></i>Users</a>
            <a class="nav-link" href="{{ route('admin.retention.index') }}"><i class="fas fa-database me-1"></i>Retention</a>
            <form method="post" action="{{ route('logout') }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary">Logout</button></form>
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
                <table class="table table-hover mb-0 align-middle">
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

    {{-- Harvested Mushroom Inventory Section --}}
    <div class="card glass-card mt-4 mb-5" id="admin-inventory">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <span class="fw-semibold"><i class="fas fa-seedling me-2 text-success"></i>Harvested Mushroom Inventory</span>
                <small class="text-muted ms-2">({{ $inventorySummary['count'] ?? 0 }} harvests — {{ $inventorySummary['total_kg'] ?? 0 }} kg total)</small>
            </div>
            <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#addAdminInventoryCollapse" aria-expanded="false" aria-controls="addAdminInventoryCollapse">
                <i class="fas fa-plus me-1"></i>Add Harvested Mushroom
            </button>
        </div>

        {{-- Harvested Stock Summary Bar --}}
        <div class="card-body border-bottom border-secondary py-2">
            <div class="row g-2 text-center">
                <div class="col-md-4 col-6">
                    <div class="p-2 rounded border border-secondary">
                        <div class="small text-muted">White Oyster Harvested</div>
                        <div class="fw-bold text-success">{{ $inventorySummary['white_kg'] ?? 0 }} kg <small class="text-muted">({{ $inventorySummary['white_grams'] ?? 0 }} g)</small></div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="p-2 rounded border border-secondary">
                        <div class="small text-muted">Brown Oyster Harvested</div>
                        <div class="fw-bold text-warning">{{ $inventorySummary['brown_kg'] ?? 0 }} kg <small class="text-muted">({{ $inventorySummary['brown_grams'] ?? 0 }} g)</small></div>
                    </div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="p-2 rounded border border-secondary">
                        <div class="small text-muted">Total Harvested Stock</div>
                        <div class="fw-bold">{{ $inventorySummary['total_kg'] ?? 0 }} kg <small class="text-muted">({{ $inventorySummary['total_grams'] ?? 0 }} g)</small></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse border-bottom border-secondary" id="addAdminInventoryCollapse">
            <div class="card-body">
                <form method="post" action="{{ route('admin.inventory.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label small mb-1">Harvested Mushroom</label>
                        <select name="mushroom_type" class="form-select form-select-sm" required>
                            <option value="oyster_white">White Oyster Mushroom</option>
                            <option value="oyster_brown">Brown Oyster Mushroom</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label small mb-1">Batch / Flush Label</label>
                        <input type="text" name="batch_name" class="form-control form-control-sm" placeholder="e.g. Box A Harvest (4 bags)" required>
                    </div>
                    <div class="col-md-1 col-4">
                        <label class="form-label small mb-1">Flush #</label>
                        <input type="number" name="flush_number" min="1" max="10" value="1" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-2 col-4">
                        <label class="form-label small mb-1">Harvest Weight</label>
                        <input type="number" name="qty" step="0.1" min="0.1" placeholder="e.g. 1850" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-1 col-4">
                        <label class="form-label small mb-1">Unit</label>
                        <select name="unit" class="form-select form-select-sm" required>
                            <option value="g">g</option>
                            <option value="kg">kg</option>
                        </select>
                    </div>
                    <div class="col-md-1 col-6">
                        <label class="form-label small mb-1">Grade</label>
                        <select name="quality" class="form-select form-select-sm">
                            <option value="Grade A">Grade A</option>
                            <option value="Grade B">Grade B</option>
                        </select>
                    </div>
                    <div class="col-md-1 col-6">
                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-check"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Harvested Mushroom</th>
                            <th>Batch</th>
                            <th>Flush</th>
                            <th>Harvested Weight</th>
                            <th>Quality</th>
                            <th>Harvest Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($harvestInventory ?? [] as $item)
                            <tr>
                                <td class="fw-semibold">
                                    @if ($item->mushroom_type === 'oyster_brown')
                                        <span class="badge rounded-pill bg-warning bg-opacity-10 text-warning px-3 py-1">Brown Oyster Mushroom</span>
                                    @else
                                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success px-3 py-1">White Oyster Mushroom</span>
                                    @endif
                                </td>
                                <td>{{ $item->batch_name }}</td>
                                <td>Flush {{ $item->flush_number }}</td>
                                <td class="fw-semibold">{{ number_format((float) $item->weight_grams, 1) }} g <small class="text-muted">({{ number_format((float) $item->weight_grams / 1000, 2) }} kg)</small></td>
                                <td><span class="badge bg-secondary bg-opacity-25 text-light">{{ $item->quality ?? 'Grade A' }}</span></td>
                                <td class="small text-muted">{{ $item->harvested_at ? $item->harvested_at->format('M d, Y') : '' }}</td>
                                <td class="text-end">
                                    <form method="post" action="{{ route('admin.inventory.destroy', $item->id) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this harvested mushroom entry?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">No harvested mushrooms in inventory yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="{{ $base }}/vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
