@extends('layouts.app')

@section('title', 'Complete Your Profile')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">

            <div class="card shadow-sm">
                <div class="card-body p-4">

                    <div class="text-center mb-4">
                        <div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                             style="width:56px;height:56px;">
                            <i class="fa-solid fa-phone fs-4 text-dark"></i>
                        </div>
                        <h3 class="fw-bold mb-1">Almost Done!</h3>
                        <p class="text-muted small mb-0">Add your phone number to complete your profile</p>
                    </div>

                    <form method="POST" action="{{ route('userauth.phone.save') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="phone" class="form-label fw-semibold">Phone Number</label>
                            <input type="tel" name="phone" id="phone"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   placeholder="+91 98765 43210"
                                   pattern="[0-9+\s\-]{7,20}"
                                   value="{{ old('phone') }}"
                                   required>
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                We'll never share your number with anyone.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning w-100 fw-semibold text-dark">
                            <i class="fa-solid fa-check me-1"></i> Save & Continue
                        </button>
                    </form>

                    <form method="POST" action="{{ route('userauth.logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Logout
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection