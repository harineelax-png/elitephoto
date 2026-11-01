@extends('layouts.admin')

@section('title', 'Manage CMS Pages & Policies')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">CMS Pages & Legal Policies</h2>
        <p class="text-secondary small mb-0">Customize website legal text, About Us story, refund guidelines, and privacy policies.</p>
    </div>
    <a href="{{ route('admin.inquiries.index') }}" class="btn btn-outline-warning btn-sm rounded-pill">
        <i class="fa-solid fa-inbox me-1"></i> View Customer Inquiries
    </a>
</div>

<form action="{{ route('admin.pages.update') }}" method="POST">
    @csrf
    <div class="row g-4">
        <!-- About Us Section -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                    <h5 class="fw-bold text-white mb-0"><i class="fa-solid fa-circle-info text-warning me-2"></i> About Us Page Content</h5>
                    <a href="{{ route('about') }}" target="_blank" class="small text-secondary text-decoration-none hover-gold"><i class="fa-solid fa-arrow-up-right-from-square"></i> Preview</a>
                </div>
                <div class="mb-3">
                    <label class="form-label text-white small fw-bold">Story / Introduction Text</label>
                    <textarea name="cms_about_content" rows="8" class="form-control bg-dark text-white border-secondary" placeholder="Leave empty to use default comprehensive story...">{{ old('cms_about_content', $about) }}</textarea>
                    <div class="form-text text-secondary" style="font-size:0.75rem;">Displayed publicly on the <code>/about</code> page.</div>
                </div>
            </div>
        </div>

        <!-- Refund Policy Section -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                    <h5 class="fw-bold text-white mb-0"><i class="fa-solid fa-rotate-left text-warning me-2"></i> Refund & Cancellation Policy</h5>
                    <a href="{{ route('refund-policy') }}" target="_blank" class="small text-secondary text-decoration-none hover-gold"><i class="fa-solid fa-arrow-up-right-from-square"></i> Preview</a>
                </div>
                <div class="mb-3">
                    <label class="form-label text-white small fw-bold">Refund Policy Terms</label>
                    <textarea name="cms_refund_content" rows="8" class="form-control bg-dark text-white border-secondary" placeholder="Leave empty to use default digital download terms...">{{ old('cms_refund_content', $refund) }}</textarea>
                    <div class="form-text text-secondary" style="font-size:0.75rem;">Displayed publicly on the <code>/refund-policy</code> page.</div>
                </div>
            </div>
        </div>

        <!-- Terms & Conditions Section -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                    <h5 class="fw-bold text-white mb-0"><i class="fa-solid fa-file-contract text-warning me-2"></i> Terms & Conditions</h5>
                    <a href="{{ route('terms') }}" target="_blank" class="small text-secondary text-decoration-none hover-gold"><i class="fa-solid fa-arrow-up-right-from-square"></i> Preview</a>
                </div>
                <div class="mb-3">
                    <label class="form-label text-white small fw-bold">Service & License Agreement</label>
                    <textarea name="cms_terms_content" rows="8" class="form-control bg-dark text-white border-secondary" placeholder="Leave empty to use default terms...">{{ old('cms_terms_content', $terms) }}</textarea>
                    <div class="form-text text-secondary" style="font-size:0.75rem;">Displayed publicly on the <code>/terms</code> page.</div>
                </div>
            </div>
        </div>

        <!-- Privacy Policy Section -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                    <h5 class="fw-bold text-white mb-0"><i class="fa-solid fa-user-shield text-warning me-2"></i> Privacy Policy</h5>
                    <a href="{{ route('privacy') }}" target="_blank" class="small text-secondary text-decoration-none hover-gold"><i class="fa-solid fa-arrow-up-right-from-square"></i> Preview</a>
                </div>
                <div class="mb-3">
                    <label class="form-label text-white small fw-bold">Privacy & Photo Retention Text</label>
                    <textarea name="cms_privacy_content" rows="8" class="form-control bg-dark text-white border-secondary" placeholder="Leave empty to use default privacy terms...">{{ old('cms_privacy_content', $privacy) }}</textarea>
                    <div class="form-text text-secondary" style="font-size:0.75rem;">Displayed publicly on the <code>/privacy</code> page.</div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-warning btn-lg rounded-pill px-5 fw-bold shadow">
                <i class="fa-solid fa-floppy-disk me-2"></i> Save All CMS Changes
            </button>
        </div>
    </div>
</form>
@endsection
