@extends('layouts.main')

@section('title', 'Contact Us | Neelax Tech - Support & Inquiries')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <div class="text-center mb-5">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold text-uppercase" style="letter-spacing:1px;">Get in Touch</span>
                <h1 class="display-5 fw-bold text-white font-serif mt-3 mb-2">Contact Neelax Tech</h1>
                <p class="text-secondary lead mx-auto" style="max-width: 600px;">
                    Have questions about frame templates, high-resolution downloads, or photoshoot reservations? We're here to assist.
                </p>
            </div>

            <div class="row g-4">
                <!-- Contact Info Cards -->
                <div class="col-lg-4">
                    <div class="d-flex flex-column gap-3 h-100">
                        <div class="glass-card p-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="brand-icon" style="width:44px; height:44px;">
                                    <i class="fa-solid fa-envelope text-white"></i>
                                </div>
                                <div>
                                    <h6 class="text-white fw-bold mb-0">Email Support</h6>
                                    <div class="text-warning fw-bold">support@neelaxtech.com</div>
                                </div>
                            </div>
                            <p class="text-secondary small mb-0">Send us inquiries, custom frame requests, or payment questions anytime.</p>
                        </div>

                        <div class="glass-card p-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="brand-icon" style="width:44px; height:44px;">
                                    <i class="fa-solid fa-cloud-arrow-down text-white"></i>
                                </div>
                                <div>
                                    <h6 class="text-white fw-bold mb-0">Instant Delivery</h6>
                                    <span class="badge bg-success-subtle text-success">Digital Download Only</span>
                                </div>
                            </div>
                            <p class="text-secondary small mb-0">
                                <strong>Important Note:</strong> All frame purchases deliver instant  image files directly to your device. We do not dispatch physical frames by post.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Inquiry Form -->
                <div class="col-lg-8">
                    <div class="glass-card p-4 p-md-5">
                        <h4 class="text-white font-serif mb-2">Send Us a Message</h4>
                        <p class="text-secondary small mb-4">Fill out the form below and an administrator will review your message promptly.</p>

                        <form action="{{ route('contact.submit') }}" method="POST">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-white small fw-bold">Your Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control bg-dark text-white border-secondary" placeholder="John Doe" value="{{ old('name') }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-white small fw-bold">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control bg-dark text-white border-secondary" placeholder="john@example.com" value="{{ old('email') }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-white small fw-bold">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control bg-dark text-white border-secondary" placeholder="+91 98765 43210" value="{{ old('phone') }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-white small fw-bold">Subject</label>
                                    <input type="text" name="subject" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Frame Customization Inquiry" value="{{ old('subject') }}">
                                </div>

                                <div class="col-12">
                                    <label class="form-label text-white small fw-bold">Your Message <span class="text-danger">*</span></label>
                                    <textarea name="message" rows="5" class="form-control bg-dark text-white border-secondary" placeholder="Write your inquiry or question here..." required>{{ old('message') }}</textarea>
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-gold btn-lg rounded-pill px-5 fw-bold shadow">
                                        <i class="fa-solid fa-paper-plane me-2"></i> Submit Inquiry
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
