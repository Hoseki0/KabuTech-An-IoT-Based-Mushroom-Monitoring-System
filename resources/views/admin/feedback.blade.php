@php
    $base = rtrim(request()->getSchemeAndHttpHost() . request()->getBaseUrl(), '/');
    $starColors = ['#ef4444','#f97316','#eab308','#22c55e','#3b82f6'];
    $ratingLabels = [1=>'Poor',2=>'Fair',3=>'Good',4=>'Great',5=>'Excellent'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin — User Feedback</title>
    <link href="{{ $base }}/vendor/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ $base }}/vendor/fontawesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ $base }}/css/dashboard.css">
    <style>
        .star-display { color: #eab308; letter-spacing: 2px; font-size: 1rem; }
        .star-display .empty { color: rgba(255,255,255,0.2); }
        .feedback-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            transition: background 0.2s;
        }
        .feedback-card:hover { background: rgba(255,255,255,0.09); }
        .rating-filter-btn.active,
        .rating-filter-btn:hover { background: rgba(234,179,8,0.18); border-color: #eab308; color: #fde047; }
        .rating-filter-btn { border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.7); transition: all .15s; }
        .stats-pill {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            border-radius: 20px; padding: .35rem 1rem; font-size: .88rem; color: rgba(255,255,255,0.85);
        }
        .avg-score { font-size: 2.5rem; font-weight: 700; color: #fde047; line-height: 1; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark navbar-expand-lg mb-4">
    <div class="container-fluid">
        <span class="navbar-brand"><i class="fas fa-user-shield me-2"></i>Admin Panel</span>
        <div class="navbar-nav ms-auto flex-row flex-wrap gap-2 align-items-center">
            <a class="nav-link" href="{{ route('admin.index') }}"><i class="fas fa-users me-1"></i>Users</a>
            <a class="nav-link active" href="{{ route('admin.feedback.index') }}"><i class="fas fa-comments me-1"></i>Feedback</a>
            <form method="post" action="{{ route('logout') }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-light">Logout</button></form>
        </div>
    </div>
</nav>

<div class="container pb-5">
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stats bar --}}
    <div class="d-flex flex-wrap gap-3 mb-4 align-items-center">
        @php
            $total = $feedbacks->total();
            $avg   = $feedbacks->count() > 0
                ? round($feedbacks->getCollection()->avg('rating'), 1)
                : null;
        @endphp
        <div class="stats-pill">
            <i class="fas fa-comments text-info"></i>
            <strong>{{ $total }}</strong> submission{{ $total !== 1 ? 's' : '' }}
        </div>
        @if($avg)
        <div class="stats-pill">
            <span class="avg-score">{{ number_format($avg, 1) }}</span>
            <span>
                <div class="star-display" style="font-size:.85rem;">
                    @for($i=1;$i<=5;$i++)
                        <i class="fas fa-star{{ $i <= round($avg) ? '' : ' empty' }}"></i>
                    @endfor
                </div>
                <small class="text-muted">avg rating</small>
            </span>
        </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="card glass-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.feedback.index') }}" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Search by user or content</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control bg-dark text-white border-secondary"
                               placeholder="Name, email or keyword…" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Filter by rating</label>
                    <div class="d-flex gap-1 flex-wrap">
                        <a href="{{ route('admin.feedback.index', array_merge(request()->except('rating','page'), [])) }}"
                           class="btn btn-sm rating-filter-btn {{ !request('rating') ? 'active' : '' }}">All</a>
                        @for($r=5;$r>=1;$r--)
                            <a href="{{ route('admin.feedback.index', array_merge(request()->except('rating','page'), ['rating'=>$r])) }}"
                               class="btn btn-sm rating-filter-btn {{ request('rating') == $r ? 'active' : '' }}">
                                {{ $r }}★
                            </a>
                        @endfor
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-outline-light flex-fill">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.feedback.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-xmark"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Feedback list --}}
    @forelse ($feedbacks as $fb)
        <div class="feedback-card p-3 mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-circle"
                         style="width:44px;height:44px;background:rgba(255,255,255,0.1);flex-shrink:0;">
                        <i class="fas fa-user text-muted"></i>
                    </div>
                    <div>
                        <div class="fw-semibold text-white">{{ $fb->user->name ?? '—' }}</div>
                        <div class="small text-muted">{{ $fb->user->email ?? '' }}</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end">
                        <div class="star-display">
                            @for($i=1;$i<=5;$i++)
                                @if($i <= $fb->rating)
                                    <i class="fas fa-star"></i>
                                @else
                                    <i class="fas fa-star empty"></i>
                                @endif
                            @endfor
                        </div>
                        <div class="small" style="color: {{ $starColors[$fb->rating] ?? '#fff' }}; font-weight:600;">
                            {{ $ratingLabels[$fb->rating] ?? '' }}
                        </div>
                    </div>
                    <form method="post" action="{{ route('admin.feedback.destroy', $fb) }}"
                          onsubmit="return confirm('Delete this feedback entry?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="mt-3 ps-1" style="color:rgba(255,255,255,0.85); line-height:1.6;">
                {{ $fb->content }}
            </div>
            <div class="mt-2 small text-muted">
                <i class="fas fa-clock me-1"></i>{{ $fb->created_at->diffForHumans() }}
                <span class="mx-1 opacity-50">·</span>{{ $fb->created_at->format('M d, Y g:i A') }}
            </div>
        </div>
    @empty
        <div class="card glass-card">
            <div class="card-body text-center py-5 text-muted">
                <i class="fas fa-comment-slash fa-3x mb-3 opacity-50"></i>
                <p class="mb-0">No feedback submissions yet.</p>
                <p class="small mt-1">Users can submit feedback from the dashboard.</p>
            </div>
        </div>
    @endforelse

    {{-- Pagination --}}
    @if ($feedbacks->hasPages())
        <div class="mt-4">
            {{ $feedbacks->links() }}
        </div>
    @endif
</div>

<script src="{{ $base }}/vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
