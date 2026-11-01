@extends('layouts.app')

@section('title', 'Login | Neelax Tech')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">

            <div class="glass-card p-4 p-md-5">

                <div class="text-center mb-4">
                    <div class="brand-icon mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;">
                        <i class="fa-solid fa-right-to-bracket"></i>
                    </div>
                    <h2 class="font-serif text-white mb-1">Welcome Back</h2>
                    <p class="text-secondary small mb-0">
                        Sign in to your Neelax Tech account
                    </p>
                </div>

                @if(session('error'))
                    <div class="alert alert-danger bg-danger-subtle text-danger border-danger-subtle small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ session('error') }}
                    </div>
                @endif

                <a href="{{ route('userauth.google') }}"
                   class="btn btn-danger w-100 py-2 fw-bold d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="fab fa-google fs-5"></i>
                    Login with Google
                </a>

                <div class="text-center mt-4 small text-secondary">
                    Don't have an account?
                    <a href="{{ route('userauth.register') }}" class="text-warning fw-semibold text-decoration-none">
                        Register here
                    </a>
                </div>

            </div>

        </div>
    </div>
</div>
@endsection