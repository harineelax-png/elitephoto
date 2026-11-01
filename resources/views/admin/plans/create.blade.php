@extends('layouts.admin')

@section('title', 'Add Photoshoot Plan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">Add Photoshoot Plan</h2>
        <p class="text-secondary small mb-0">Create a photography package to be displayed on the customer photoshoot page.</p>
    </div>
    <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
        &larr; Back to Plans
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="admin-card">
            <form action="{{ route('admin.plans.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">

                    {{-- Name --}}
                    <div class="col-md-8">
                        <label class="form-label text-white small fw-bold">Plan Name</label>
                        <input type="text" name="name"
                               class="form-control bg-dark text-white border-secondary @error('name') is-invalid @enderror"
                               placeholder="e.g. Diamond Wedding & Pre-Wedding Shoot"
                               value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Price --}}
                    <div class="col-md-4">
                        <label class="form-label text-white small fw-bold">Price (₹)</label>
                        <input type="number" step="0.01" name="price"
                               class="form-control bg-dark text-white border-secondary @error('price') is-invalid @enderror"
                               placeholder="e.g. 4999"
                               value="{{ old('price') }}" required>
                        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Duration --}}
                    <div class="col-md-6">
                        <label class="form-label text-white small fw-bold">Session Duration</label>
                        <input type="text" name="duration"
                               class="form-control bg-dark text-white border-secondary @error('duration') is-invalid @enderror"
                               placeholder="e.g. 2 Hours / Full Day"
                               value="{{ old('duration') }}" required>
                        @error('duration')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Badge --}}
                    <div class="col-md-6">
                        <label class="form-label text-white small fw-bold">Badge / Ribbon (Optional)</label>
                        <input type="text" name="badge"
                               class="form-control bg-dark text-white border-secondary"
                               placeholder="e.g. Most Popular / Best Value"
                               value="{{ old('badge') }}">
                    </div>

                    {{-- Deliverables --}}
                    <div class="col-12">
                        <label class="form-label text-white small fw-bold">Deliverables Summary</label>
                        <input type="text" name="deliverables"
                               class="form-control bg-dark text-white border-secondary"
                               placeholder="e.g. 35 Retouched Photos + 2 Canvas Wall Frames"
                               value="{{ old('deliverables') }}">
                    </div>

                    {{-- Description --}}
                    <div class="col-12">
                        <label class="form-label text-white small fw-bold">Description</label>
                        <textarea name="description" rows="2"
                                  class="form-control bg-dark text-white border-secondary"
                                  placeholder="Brief marketing overview...">{{ old('description') }}</textarea>
                    </div>

                    {{-- Features --}}
                    <div class="col-12">
                        <label class="form-label text-white small fw-bold">Inclusions & Features (One per line)</label>
                        <textarea name="features" rows="5"
                                  class="form-control bg-dark text-white border-secondary"
                                  placeholder="Studio and outdoor garden setup&#10;2 outfit changes included&#10;Props, lighting & backdrops&#10;High-speed private download link">{{ old('features') }}</textarea>
                        <div class="form-text text-secondary" style="font-size:0.75rem;">Enter each benefit or inclusion on a new line.</div>
                    </div>

                    {{-- Plan Image --}}
                    <div class="col-12">
                        <label class="form-label text-white small fw-bold">Plan Image</label>
                        <input type="file" name="image" id="image"
                               accept="image/jpeg,image/png,image/webp"
                               class="form-control bg-dark text-white border-secondary @error('image') is-invalid @enderror">
                        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text text-secondary" style="font-size:0.75rem;">
                            JPG, PNG or WEBP — max 4 MB. Recommended ratio 4:3 (e.g. 800×600).
                        </div>

                        {{-- Live preview --}}
                        <div class="mt-3 d-none" id="imagePreviewWrap">
                            <img id="imagePreview"
                                 src="" alt="Preview"
                                 class="rounded-3 border border-secondary"
                                 style="max-height:180px;">
                        </div>
                    </div>

                    {{-- Sort order --}}
                    <div class="col-md-6">
                        <label class="form-label text-white small fw-bold">Display Sort Order</label>
                        <input type="number" name="sort_order"
                               class="form-control bg-dark text-white border-secondary"
                               value="{{ old('sort_order', 1) }}">
                    </div>

                    {{-- Active --}}
                    <div class="col-md-6 d-flex align-items-center pt-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                                   {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label text-white small" for="is_active">Active & Available for Booking</label>
                        </div>
                    </div>

                    {{-- Submit --}}
                    <div class="col-12 pt-3">
                        <button type="submit" class="btn btn-warning fw-bold px-4 rounded-pill shadow">
                            <i class="fa-solid fa-plus-circle me-1"></i> Save Photoshoot Plan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('image')?.addEventListener('change', function (e) {
        const wrap = document.getElementById('imagePreviewWrap');
        const img  = document.getElementById('imagePreview');
        const file = e.target.files && e.target.files[0];

        if (!file) {
            wrap.classList.add('d-none');
            return;
        }

        const reader = new FileReader();
        reader.onload = ev => {
            img.src = ev.target.result;
            wrap.classList.remove('d-none');
        };
        reader.readAsDataURL(file);
    });
</script>
@endpush