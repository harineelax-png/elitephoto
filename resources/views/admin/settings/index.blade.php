@extends('layouts.admin')

@section('title', 'Pricing & Razorpay Settings')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">
            <i class="fa-solid fa-sliders text-warning me-2"></i>Pricing & Payment Gateway Settings
        </h2>
        <p class="text-secondary small mb-0">Configure the customer price per framed image and live Razorpay credentials.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <form action="{{ route('admin.settings.update') }}" method="POST">
            @csrf

            <!-- Price Setting -->
            <div class="admin-card mb-4">
                <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                    <i class="fa-solid fa-tag text-warning me-2"></i>Image Generation Pricing
                </h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-white small fw-bold">Price per Generated Image (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-warning fw-bold">₹</span>
                            <input type="number" step="0.01" name="price_per_image" class="form-control bg-dark text-white border-secondary fs-5 fw-bold" value="{{ old('price_per_image', $settings['price_per_image']) }}" required>
                        </div>
                        <div class="form-text text-secondary" style="font-size:0.75rem;">
                            This is the default charge applied to users on the website before downloading high-definition frames.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-white small fw-bold">Currency Code</label>
                        <input type="text" name="currency" class="form-control bg-dark text-white border-secondary" value="{{ old('currency', $settings['currency']) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-white small fw-bold">Currency Symbol</label>
                        <input type="text" name="currency_symbol" class="form-control bg-dark text-white border-secondary" value="{{ old('currency_symbol', $settings['currency_symbol']) }}" required>
                    </div>
                </div>
            </div>

            <!-- Razorpay Gateway Keys -->
            <div class="admin-card mb-4">
                <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                    <i class="fa-solid fa-shield-halved text-success me-2"></i>Razorpay Payment Gateway Credentials
                </h5>

                <div class="alert alert-dark border border-secondary text-secondary small mb-3">
                    <i class="fa-solid fa-circle-info text-warning me-1"></i> Configured with the test keys provided. You can change to live production keys anytime.
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label text-white small fw-bold">Razorpay Key ID</label>
                        <input type="text" name="razorpay_key" class="form-control bg-dark text-white border-secondary" value="{{ old('razorpay_key', $settings['razorpay_key']) }}" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-white small fw-bold">Razorpay Key Secret</label>
                        <input type="text" name="razorpay_secret" class="form-control bg-dark text-white border-secondary" value="{{ old('razorpay_secret', $settings['razorpay_secret']) }}" required>
                    </div>
                </div>
            </div>

            <!-- Studio Profile & Contact Info -->
            <div class="admin-card mb-4">
                <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                    <i class="fa-solid fa-building text-info me-2"></i>Studio Profile & Contact Information
                </h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-white small fw-bold">Studio Name</label>
                        <input type="text" name="studio_name" class="form-control bg-dark text-white border-secondary" value="{{ old('studio_name', $settings['studio_name']) }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-white small fw-bold">Studio Tagline</label>
                        <input type="text" name="studio_tagline" class="form-control bg-dark text-white border-secondary" value="{{ old('studio_tagline', $settings['studio_tagline']) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-white small fw-bold">Phone Number</label>
                        <input type="text" name="studio_phone" class="form-control bg-dark text-white border-secondary" value="{{ old('studio_phone', $settings['studio_phone']) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-white small fw-bold">Support Email</label>
                        <input type="email" name="studio_email" class="form-control bg-dark text-white border-secondary" value="{{ old('studio_email', $settings['studio_email']) }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label text-white small fw-bold">Physical Studio Address</label>
                        <input type="text" name="studio_location" class="form-control bg-dark text-white border-secondary" value="{{ old('studio_location', $settings['studio_location']) }}">
                    </div>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-warning fw-bold px-4 py-2 rounded-pill shadow">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes & Update Price
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
