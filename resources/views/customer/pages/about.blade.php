@extends('layouts.main')

@section('title', 'About Us | Neelax Tech - Photo Framing & Photography Studio')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold text-uppercase" style="letter-spacing:1px;">Our Story</span>
                <h1 class="display-5 fw-bold text-white font-serif mt-3 mb-3">About Neelax Tech</h1>
                <p class="text-secondary lead mx-auto" style="max-width: 680px;">
                    Innovating photo memory preservation through cutting-edge digital frame rendering and studio photography excellence.
                </p>
            </div>

            <div class="glass-card p-4 p-md-5 mb-5">
                @if ($aboutText)
                    <div class="text-light" style="line-height: 1.8;">
                        {!! nl2br(e($aboutText)) !!}
                    </div>
                @else
                    <h3 class="text-warning font-serif mb-4">Precision Framing & Digital Mastery</h3>
                    <p class="text-secondary leading-relaxed mb-4">
                        At <strong>Neelax Tech</strong>, we believe every memory tells a distinct story. Founded to bridge high-resolution digital image compositing with photography artistry, our web application allows customers to take their favorite portraits and composite them seamlessly into designer themes, birthday calendars, anniversary collages, and festive templates.
                    </p>

                    <div class="row g-4 my-4">
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 bg-dark border border-secondary border-opacity-25 h-100 text-center">
                                <div class="brand-icon mx-auto mb-3" style="width:48px; height:48px;">
                                    <i class="fa-solid fa-wand-magic-sparkles text-white fs-5"></i>
                                </div>
                                <h5 class="text-white fw-bold mb-2">Smart Auto-Fitting</h5>
                                <p class="text-secondary small mb-0">Our compositing engine automatically scales, centers, and clips your portraits with mathematical perfection into every slot.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 bg-dark border border-secondary border-opacity-25 h-100 text-center">
                                <div class="brand-icon mx-auto mb-3" style="width:48px; height:48px;">
                                    <i class="fa-solid fa-calendar-day text-white fs-5"></i>
                                </div>
                                <h5 class="text-white fw-bold mb-2">Full Calendar Grids</h5>
                                <p class="text-secondary small mb-0">Custom birth date or anniversary highlighting with automated weekday alignment and commemorative heart badges.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 bg-dark border border-secondary border-opacity-25 h-100 text-center">
                                <div class="brand-icon mx-auto mb-3" style="width:48px; height:48px;">
                                    <i class="fa-solid fa-bolt text-white fs-5"></i>
                                </div>
                                <h5 class="text-white fw-bold mb-2">Instant </h5>
                                <p class="text-secondary small mb-0">High-resolution print-ready files generated in milliseconds with zero shipping wait time. Download straight to your devices.</p>
                            </div>
                        </div>
                    </div>

                    <h4 class="text-white font-serif mt-5 mb-3">Professional Studio Photoshoots</h4>
                    <p class="text-secondary leading-relaxed mb-0">
                        In addition to our digital frame generator, Neelax Tech hosts professional indoor and outdoor studio photoshoot sessions tailored for birthdays, weddings, baby milestones, and family reunions. Choose from flexible curated plans and book an appointment online with our team.
                    </p>
                @endif
            </div>

            <div class="text-center">
                <a href="{{ route('studio.index') }}" class="btn btn-gold btn-lg rounded-pill px-5 shadow me-3">
                    <i class="fa-solid fa-crop-simple me-2"></i> Launch Frame Studio
                </a>
                <a href="{{ route('photoshoot.index') }}" class="btn btn-outline-gold btn-lg rounded-pill px-5">
                    <i class="fa-solid fa-camera-retro me-2"></i> View Photoshoot Plans
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
