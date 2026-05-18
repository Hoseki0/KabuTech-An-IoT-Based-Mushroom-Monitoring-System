@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <h2 class="h5 fw-semibold mb-1">Forgot your password?</h2>
    <p class="text-muted small mb-3">Enter your email and we'll send you a link to reset it.</p>

    @if (session('status'))
        <div class="alert alert-success py-2 small">
            <i class="fas fa-circle-check me-1"></i>{{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                   class="form-control bg-dark text-white border-secondary"
                   required autofocus autocomplete="email"
                   placeholder="you@example.com">
        </div>
        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-paper-plane me-1"></i>Send Reset Link
        </button>
    </form>
@endsection

@section('footer-links')
    <a href="{{ route('login') }}" class="text-decoration-none">
        <i class="fas fa-arrow-left me-1"></i>Back to sign in
    </a>
@endsection
