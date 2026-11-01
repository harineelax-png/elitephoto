@extends('layouts.main')

@section('title', 'Neelax Tech | Create Personalized Frames & Book Photoshoots')

@section('content')
<!-- Hero Section -->
<section class="position-relative py-5 overflow-hidden">
    <!-- Ambient Background Glow -->
    <div class="position-absolute top-0 start-50 translate-middle-x" style="width: 600px; height: 350px; background: radial-gradient(circle, rgba(245, 158, 11, 0.18) 0%, rgba(220, 38, 38, 0.08) 50%, transparent 80%); filter: blur(60px); z-index: -1;"></div>

    <div class="container py-lg-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 text-center text-lg-start">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.25);">
                    <i class="fa-solid fa-sparkles text-warning small"></i>
                    <span class="small fw-bold text-warning">Instant AI Framer & Studio Photography</span>
                </div>

                <h1 class="display-4 fw-bold text-white mb-3" style="line-height: 1.15;">
                    Cherish Life's Most Beautiful Moments in <span style="background: var(--gold-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Heirloom Frames</span>
                </h1>

                <p class="lead text-secondary mb-4" style="font-size: 1.1rem;">
                    Upload your family & celebration photos into bespoke designer themes. Add custom anniversary dates, banner ribbons, and download in crystal-clear High Definition after simple Razorpay payment.
                </p>

                <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-3">
                    <a href="{{ route('studio.index') }}" class="btn btn-gold btn-lg d-flex align-items-center gap-2">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Frame Your Photos Now
                    </a>
                    <a href="{{ route('photoshoot.index') }}" class="btn btn-outline-gold btn-lg d-flex align-items-center gap-2">
                        <i class="fa-solid fa-camera-retro"></i> Book Studio Photoshoot
                    </a>
                </div>

                <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-4 mt-4 pt-3 border-top border-secondary border-opacity-25 small text-secondary">
                    <div><i class="fa-solid fa-bolt text-warning me-1"></i> Starting at just <strong class="text-white">₹{{ number_format((float)$defaultPrice, 0) }}</strong></div>
                    <div><i class="fa-solid fa-shield-check text-warning me-1"></i> Razorpay Test Keys Active</div>
                    <div><i class="fa-solid fa-image text-warning me-1"></i> Full HD 300DPI Resolution</div>
                </div>
            </div>

            <!-- Hero Interactive Preview Showcase -->
            <div class="col-lg-6">
                <div class="position-relative mx-auto" style="max-width: 480px;">
                    <!-- Floating Badge -->
                    <div class="position-absolute top-0 start-0 translate-middle-y z-3 ms-2 p-2 px-3 rounded-pill shadow-lg bg-dark border border-warning" style="font-size: 0.85rem;">
                        <i class="fa-solid fa-star text-warning me-1"></i> Trending Celebratory Frame
                    </div>

                    <!-- Card Showcase with sample result -->
                    <div class="glass-card p-3 p-sm-4 shadow-lg text-center position-relative overflow-hidden">
                        @php
                            $featuredTemplate = $frames->first();
                        @endphp
                        @if ($featuredTemplate && file_exists(storage_path('app/public/' . $featuredTemplate->template_path)))
                            <img src="{{ asset('storage/' . $featuredTemplate->template_path) }}" class="img-fluid rounded-4 shadow-sm" style="max-height: 480px; width: 100%; object-fit: contain;" alt="Designer Photo Frame">
                        @else
                            <div class="py-5 text-secondary">
                                <i class="fa-solid fa-crop-simple fs-1 text-warning mb-2"></i>
                                <h5>Interactive Frame Canvas</h5>
                            </div>
                        @endif

                        <div class="mt-3 d-flex justify-content-between align-items-center px-2">
                            <div class="text-start">
                                <div class="text-white fw-bold">{{ $featuredTemplate ? $featuredTemplate->name : 'Designer Birthday Frame' }}</div>
                                <div class="text-secondary small">{{ $featuredTemplate ? $featuredTemplate->total_slots : 5 }} Photo Slots • Custom Date Calendar</div>
                            </div>
                            <div>
                                <a href="{{ route('studio.index', $featuredTemplate ? $featuredTemplate->id : null) }}" class="btn btn-gold btn-sm rounded-pill px-3">
                                    Try This &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Template Gallery Section -->
<section class="py-5" id="templatesSection">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <span class="badge-studio mb-2 d-inline-block">Curated Themes</span>
                <h2 class="display-6 fw-bold text-white mb-1">Choose a Template for Your Moments</h2>
                <p class="text-secondary small mb-0">Select from birthday, wedding, anniversary, calendar and romance frames.</p>
            </div>
            <div>
                <a href="{{ route('studio.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill text-white">
                    Open Customizer Studio &rarr;
                </a>
            </div>
        </div>

        <!-- Category Filter Pills -->
        <div class="d-flex flex-wrap gap-2 mb-4 pb-2">
            <a href="{{ route('home') }}" class="btn btn-sm {{ !request('category') || request('category') === 'all' ? 'btn-gold' : 'btn-dark border-secondary text-secondary' }} rounded-pill px-3">
                All Designs
            </a>
            @foreach ($categories as $cat)
                <a href="{{ route('home', ['category' => $cat]) }}" class="btn btn-sm {{ request('category') === $cat ? 'btn-gold' : 'btn-dark border-secondary text-secondary' }} rounded-pill px-3">
                    {{ $cat }}
                </a>
            @endforeach
        </div>

        <!-- Frame Cards Grid -->
        <div class="row g-4">
            @forelse ($frames as $frame)
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <div class="glass-card h-100 p-3 d-flex flex-column">
                        <!-- Frame Image Thumbnail -->
                        <div class="position-relative mb-3 rounded-4 overflow-hidden bg-black text-center" style="height: 260px;">
                            <img src="{{ asset('storage/' . $frame->template_path) }}" class="w-100 h-100 object-fit-contain" alt="{{ $frame->name }}" loading="lazy">

                            <div class="position-absolute top-0 start-0 m-2">
                                <span class="badge bg-warning text-dark fw-bold rounded-pill" style="font-size: 0.72rem;">
                                    {{ $frame->category }}
                                </span>
                            </div>

                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge bg-dark bg-opacity-75 text-white border border-secondary rounded-pill" style="font-size: 0.72rem;">
                                    <i class="fa-solid fa-camera me-1"></i> {{ $frame->total_slots }} Slots
                                </span>
                            </div>
                        </div>

                        <h6 class="fw-bold text-white mb-1 text-truncate" title="{{ $frame->name }}">{{ $frame->name }}</h6>
                        <p class="text-secondary small mb-3 flex-grow-1" style="font-size: 0.8rem; line-height: 1.4;">
                            {{ Str::limit($frame->description ?: 'Personalize this frame with your photos, custom message and commemorative calendar date.', 75) }}
                        </p>

                        <div class="pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-secondary small" style="font-size: 0.75rem;">Price:</span>
                                <div class="fw-bold text-warning fs-5">₹{{ number_format($frame->effective_price, 2) }}</div>
                            </div>

                            <a href="{{ route('studio.index', $frame->id) }}" class="btn btn-gold btn-sm rounded-pill px-3 d-flex align-items-center gap-1">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Frame Photos
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5 text-secondary">
                    <i class="fa-regular fa-image fs-1 mb-2"></i>
                    <p>No templates found for this category.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>

<!-- Studio Photoshoot Preview Section (Page 2 highlight) -->
<section class="py-5 bg-dark bg-opacity-50 border-top border-bottom border-secondary border-opacity-25">
    <div class="container py-lg-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <span class="badge bg-danger rounded-pill px-3 py-1 mb-2" style="font-size: 0.75rem;">BEST PLANS</span>
                <h2 class="display-6 fw-bold text-white mb-3">Professional Studio & Outdoor Photoshoots</h2>
                <p class="text-secondary mb-4">
                    Looking for a comprehensive photo session? We offer luxury studio portraits, birthday milestones, pre-wedding shoots, and grand event coverage. Select a curated plan and schedule an appointment in seconds.
                </p>
                <div class="d-flex flex-column gap-2 mb-4">
                    <div class="d-flex align-items-center gap-2 text-white small">
                        <i class="fa-solid fa-circle-check text-warning"></i> Award-winning photographers and creative sets
                    </div>
                    <div class="d-flex align-items-center gap-2 text-white small">
                        <i class="fa-solid fa-circle-check text-warning"></i> Unlimited outfit changes & customized props
                    </div>
                    <div class="d-flex align-items-center gap-2 text-white small">
                        <i class="fa-solid fa-circle-check text-warning"></i> Instant print-ready  digital file generation (No shipping wait)
                    </div>
                </div>
                <a href="{{ route('photoshoot.index') }}" class="btn btn-gold btn-lg d-inline-flex align-items-center gap-2">
                    <i class="fa-solid fa-calendar-check"></i> Explore Photoshoot Packages & Book
                </a>
            </div>

            <div class="col-lg-7">
                <div class="row g-3">
                    @foreach ($plans as $plan)
                        <div class="col-sm-6">
                            <div class="glass-card p-3 p-sm-4 h-100 position-relative overflow-hidden">

                                {{-- NEW: Plan image as background layer --}}
                                @if ($plan->image_url)
                                    <div class="position-absolute top-0 start-0 w-100 h-100"
                                         style="background: url('{{ $plan->image_url }}') center/cover no-repeat; opacity: 0.55; z-index: 0; pointer-events: none;"></div>
                                    <div class="position-absolute top-0 start-0 w-100 h-100"
                                         style="background: linear-gradient(180deg, rgba(11, 15, 25, 0.45) 0%, rgba(11, 15, 25, 0.88) 55%, rgba(11, 15, 25, 0.98) 100%); z-index: 0; pointer-events: none;"></div>
                                @endif

                                {{-- Existing card content, now layered above the image --}}
                                <div class="d-flex flex-column h-100 position-relative" style="z-index: 2;">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="fw-bold text-white mb-0">{{ $plan->name }}</h6>
                                        @if ($plan->badge)
                                            <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">{{ $plan->badge }}</span>
                                        @endif
                                    </div>
                                    <div class="text-warning fw-bold fs-4 mb-2">₹{{ number_format($plan->price, 0) }}</div>
                                    <div class="small text-secondary mb-3"><i class="fa-solid fa-clock text-warning me-1"></i> {{ $plan->duration }} • {{ $plan->deliverables }}</div>
                                    <div class="mt-auto">
                                        <a href="{{ route('photoshoot.index', ['plan' => $plan->id]) }}" class="btn btn-outline-gold btn-sm w-100 rounded-pill">
                                            Select & Book &rarr;
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-5">
    <div class="container text-center py-4">
        <span class="badge-studio mb-2 d-inline-block">Effortless Experience</span>
        <h2 class="display-6 fw-bold text-white mb-5">How Image Framing Works</h2>

        <div class="row g-4 justify-content-center">
            <div class="col-md-4">
                <div class="glass-card p-4 h-100">
                    <div class="brand-icon mx-auto mb-3" style="width: 54px; height: 54px; font-size: 1.5rem;">
                        1
                    </div>
                    <h5 class="fw-bold text-white mb-2">Pick a Theme & Upload</h5>
                    <p class="text-secondary small mb-0">
                        Choose from our stunning floral, wedding, birthday or calendar frames. Drag and drop your photos into each slot.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="glass-card p-4 h-100">
                    <div class="brand-icon mx-auto mb-3" style="width: 54px; height: 54px; font-size: 1.5rem;">
                        2
                    </div>
                    <h5 class="fw-bold text-white mb-2">Preview with Watermark</h5>
                    <p class="text-secondary small mb-0">
                        See your generated personalized composite instantly. Add custom banner messages and highlight your special date on the calendar.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="glass-card p-4 h-100">
                    <div class="brand-icon mx-auto mb-3" style="width: 54px; height: 54px; font-size: 1.5rem;">
                        3
                    </div>
                    <h5 class="fw-bold text-white mb-2">Pay & Download in HD</h5>
                    <p class="text-secondary small mb-0">
                        Complete fast payment via Razorpay. The watermark is instantly removed and your high-resolution image is available to download!
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection