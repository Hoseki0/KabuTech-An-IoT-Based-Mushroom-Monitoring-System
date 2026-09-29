@php
    $base = rtrim(request()->getSchemeAndHttpHost() . request()->getBaseUrl(), '/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Data Retention</title>
    <link href="{{ $base }}/vendor/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ $base }}/vendor/fontawesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ $base }}/css/dashboard.css">
    <style>
        .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .55rem 1.1rem;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 600;
            border: 1px solid var(--color-border);
            background: var(--color-surface);
        }
        .stat-pill .pill-val {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-accent);
        }
        .stat-pill.danger .pill-val { color: #ef5350; }
        .retention-card { max-width: 780px; }
        .meta-row { font-size: .8rem; color: var(--color-text-muted); }
    </style>
</head>
<body>
<nav class="navbar navbar-light navbar-expand-lg mb-4">
    <div class="container-fluid">
        <span class="navbar-brand"><i class="fas fa-user-shield me-2"></i>Admin Panel</span>
        <div class="navbar-nav ms-auto flex-row flex-wrap gap-2 align-items-center">
            <a class="nav-link" href="{{ route('dashboard') }}"><i class="fas fa-gauge-high me-1"></i>Dashboard</a>
            <a class="nav-link" href="{{ route('admin.index') }}"><i class="fas fa-users me-1"></i>Users</a>
            <a class="nav-link active" href="{{ route('admin.retention.index') }}"><i class="fas fa-database me-1"></i>Retention</a>
            <form method="post" action="{{ route('logout') }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary">Logout</button></form>
        </div>
    </div>
</nav>

<div class="container">

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
    @endif

    <div class="retention-card mx-auto">

        {{-- ── Stats row ── --}}
        <div class="d-flex flex-wrap gap-3 mb-4">
            <div class="stat-pill">
                <i class="fas fa-database text-muted"></i>
                <span>Total rows</span>
                <span class="pill-val">{{ number_format($totalRows) }}</span>
            </div>
            <div class="stat-pill {{ $eligibleRows > 0 ? 'danger' : '' }}">
                <i class="fas fa-trash-can text-muted"></i>
                <span>Eligible for deletion</span>
                <span class="pill-val">{{ number_format($eligibleRows) }}</span>
            </div>
            @if ($oldestRow)
            <div class="stat-pill">
                <i class="fas fa-clock-rotate-left text-muted"></i>
                <span>Oldest record</span>
                <span class="pill-val" style="font-size:.95rem;">{{ \Carbon\Carbon::parse($oldestRow)->diffForHumans() }}</span>
            </div>
            @endif
        </div>

        {{-- ── Configure retention ── --}}
        <div class="card glass-card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-sliders"></i>
                <span class="fw-semibold">Retention Policy</span>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.retention.update') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-sm-6">
                        <label for="retention_days" class="form-label small">Keep data for (days)</label>
                        <input type="number" id="retention_days" name="retention_days"
                               class="form-control"
                               value="{{ $days }}" min="7" max="3650" required>
                        <div class="form-text">
                            Rows older than this will be automatically deleted every night at 02:00.
                            Min 7 days, max 3650 days (~10 years).
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded border border-secondary" style="background:rgba(76,175,80,0.05);">
                            <div class="small fw-semibold mb-1"><i class="fas fa-info-circle me-1 text-info"></i>Current setting</div>
                            <div>Keep <strong>{{ $days }} days</strong> of data</div>
                            <div class="meta-row mt-1">
                                Cutoff date: <strong>{{ now()->subDays($days)->toFormattedDateString() }}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fas fa-save me-2"></i>Save Policy
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Manual prune ── --}}
        <div class="card glass-card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-broom"></i>
                <span class="fw-semibold">Manual Prune</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Run the cleanup right now instead of waiting for the nightly schedule.
                    This will permanently delete all sensor rows older than <strong>{{ $days }} days</strong>
                    (@if($eligibleRows > 0)<span style="color:#ef5350;font-weight:600;">{{ number_format($eligibleRows) }} rows will be deleted</span>@else<span style="color:var(--color-accent);">nothing to delete</span>@endif).
                </p>
                <form method="post" action="{{ route('admin.retention.prune') }}"
                      onsubmit="return confirm('Delete {{ number_format($eligibleRows) }} row(s) older than {{ $days }} days? This cannot be undone.')">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger px-4" {{ $eligibleRows === 0 ? 'disabled' : '' }}>
                        <i class="fas fa-trash-can me-2"></i>Prune Now
                    </button>
                </form>

                @if($lastRun)
                <hr class="border-secondary my-3">
                <div class="meta-row">
                    <i class="fas fa-history me-1"></i>
                    Last run: <strong>{{ \Carbon\Carbon::parse($lastRun)->diffForHumans() }}</strong>
                    ({{ \Carbon\Carbon::parse($lastRun)->toFormattedDateString() }})
                    &mdash;
                    <strong>{{ number_format((int)$lastCount) }}</strong> row(s) deleted
                </div>
                @endif
            </div>
        </div>

        {{-- ── Scheduler note ── --}}
        <div class="p-3 rounded border border-secondary mb-4" style="background:rgba(76,175,80,0.04); font-size:.82rem;">
            <div class="fw-semibold mb-1"><i class="fas fa-circle-info me-1 text-info"></i>Automatic Schedule</div>
            <div class="text-muted">
                The prune command runs automatically every night at <strong>02:00</strong> via the Laravel scheduler.
                For this to work on your server, a cron entry must call <code>php artisan schedule:run</code> every minute:
            </div>
            <pre class="mt-2 mb-0 p-2 rounded" style="background:rgba(0,0,0,0.3); color:var(--color-accent); font-size:.78rem;">* * * * * cd /path/to/thesis-ui && php artisan schedule:run >> /dev/null 2>&1</pre>
            <div class="text-muted mt-2">
                On Windows / XAMPP, you can run <code>php artisan schedule:work</code> in a terminal to simulate the scheduler locally.
            </div>
        </div>

    </div>{{-- /.retention-card --}}
</div>

<script src="{{ $base }}/vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
