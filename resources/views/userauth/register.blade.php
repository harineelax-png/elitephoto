@extends('layouts.app')

@section('title', 'Register | Neelax Tech')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">

            <div class="glass-card p-4 p-md-5">

                <div class="text-center mb-4">
                    <div class="brand-icon mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <h2 class="font-serif text-white mb-1">Create Account</h2>
                    <p class="text-secondary small mb-0">
                        Sign up with your Google account
                    </p>
                </div>

                @if(session('error'))
                    <div class="alert alert-danger bg-danger-subtle text-danger border-danger-subtle small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ session('error') }}
                    </div>
                @endif

                <form method="GET" action="{{ route('userauth.google') }}">

                    {{-- Full Name --}}
                    <div class="mb-3">
                        <label for="name" class="form-label-gold d-block">
                            <i class="fa-solid fa-user text-warning me-1"></i> Full Name
                        </label>
                        <input type="text" name="name" id="name"
                               class="form-control auth-input @error('name') is-invalid @enderror"
                               placeholder="John Doe"
                               value="{{ old('name') }}"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Phone Number --}}
                    <div class="mb-4">
                        <label for="phone" class="form-label-gold d-block">
                            <i class="fa-solid fa-phone text-warning me-1"></i> Phone Number
                        </label>
                        <input type="tel" name="phone" id="phone"
                               class="form-control auth-input @error('phone') is-invalid @enderror"
                               placeholder="+91 98765 43210"
                               pattern="[0-9+\s\-]{7,20}"
                               value="{{ old('phone') }}"
                               required>
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-secondary small mt-1">
                            We'll use this to reach you about your orders.
                        </div>
                    </div>

                    <button type="submit"
                            class="btn btn-danger w-100 py-2 fw-bold d-inline-flex align-items-center justify-content-center gap-2">
                        <i class="fab fa-google fs-5"></i>
                        Continue with Google
                    </button>

                </form>

                <div class="text-center mt-4 small text-secondary">
                    Already have an account?
                    <a href="{{ route('userauth.login') }}" class="text-warning fw-semibold text-decoration-none">
                        Login here
                    </a>
                </div>

            </div>

        </div>
    </div>
</div>
@endsection