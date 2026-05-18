@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    <h2 class="h5 fw-semibold mb-3">Sign in</h2>
    @if (session('status'))
        <div class="alert alert-info py-2 small">
            <i class="fas fa-circle-info me-1"></i>{{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
    @endif
    <form method="post" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control bg-dark text-white border-secondary" required autofocus autocomplete="username">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" class="form-control bg-dark text-white border-secondary" required autocomplete="current-password">
        </div>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="form-check mb-0">
                <input type="checkbox" name="remember" id="remember" value="1" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <a href="{{ route('password.request') }}" class="text-decoration-none small">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>
@endsection

@section('footer-links')
    <a href="{{ route('register') }}" class="text-decoration-none">Create an account</a>
@endsection
