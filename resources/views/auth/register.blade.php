@extends('layouts.auth')

@section('title', 'Register')

@section('content')
    <h2 class="h5 fw-semibold mb-3">Create account</h2>
    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="post" action="{{ route('register') }}">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-control bg-dark text-white border-secondary" required autofocus autocomplete="name">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control bg-dark text-white border-secondary" required autocomplete="username">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" class="form-control bg-dark text-white border-secondary" required autocomplete="new-password">
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control bg-dark text-white border-secondary" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary w-100">Register</button>
    </form>
@endsection

@section('footer-links')
    <a href="{{ route('login') }}" class="text-decoration-none">Already have an account? Sign in</a>
@endsection
