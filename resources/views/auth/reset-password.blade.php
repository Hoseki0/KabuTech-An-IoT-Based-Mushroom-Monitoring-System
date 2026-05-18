@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
    <h2 class="h5 fw-semibold mb-1">Set a new password</h2>
    <p class="text-muted small mb-3">Choose a strong password for your account.</p>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('password.update') }}">
        @csrf

        {{-- Hidden fields --}}
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="mb-3">
            <label for="email_display" class="form-label">Email</label>
            <input type="email" id="email_display" value="{{ $email }}"
                   class="form-control bg-dark text-white border-secondary"
                   readonly disabled>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">New password</label>
            <div class="input-group">
                <input type="password" name="password" id="password"
                       class="form-control bg-dark text-white border-secondary"
                       required autocomplete="new-password"
                       placeholder="At least 8 characters">
                <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                        title="Show/hide password">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirm new password</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                   class="form-control bg-dark text-white border-secondary"
                   required autocomplete="new-password"
                   placeholder="Repeat your password">
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-lock me-1"></i>Reset Password
        </button>
    </form>
@endsection

@section('footer-links')
    <a href="{{ route('login') }}" class="text-decoration-none">
        <i class="fas fa-arrow-left me-1"></i>Back to sign in
    </a>
@endsection

@push('scripts')
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const input = document.getElementById('password');
        const icon  = document.getElementById('eyeIcon');
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        icon.classList.toggle('fa-eye', !isPassword);
        icon.classList.toggle('fa-eye-slash', isPassword);
    });
</script>
@endpush
