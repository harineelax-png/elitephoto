@extends('layouts.main')

@section('title', 'Book a Professional Photoshoot Session | Neelax Tech')

@section('content')
<!-- Hero Section -->
<section class="position-relative py-5 overflow-hidden text-center">
    <div class="position-absolute top-0 start-50 translate-middle-x" style="width: 700px; height: 350px; background: radial-gradient(circle, rgba(239, 68, 68, 0.15) 0%, rgba(245, 158, 11, 0.1) 50%, transparent 80%); filter: blur(70px); z-index: -1;"></div>

    <div class="container py-lg-4">
        <h1 class="display-4 fw-bold text-white mb-3 font-serif">
            Capture Your Golden Milestones With Our <span style="background: var(--gold-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Master Photographers</span>
        </h1>
        <p class="lead text-secondary mx-auto mb-4" style="max-width: 700px; font-size: 1.15rem;">
            From intimate portraiture and joyful birthdays to grand weddings and family celebrations. Choose a plan below and reserve your session.
        </p>

        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="#plansPricing" class="btn btn-gold btn-lg rounded-pill px-4">
                <i class="fa-solid fa-tags me-1"></i> Browse Shoot Plans & Pricing
            </a>
            <button type="button" class="btn btn-outline-gold btn-lg rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#bookingModal">
                <i class="fa-solid fa-calendar-plus me-1"></i> Book Direct Appointment
            </button>
        </div>
    </div>
</section>

<!-- Success Alert if redirected with booking_success -->
@if (session('booking_success'))
    <div class="container mb-4">
        <div class="glass-card p-4 border-warning shadow-lg text-center" style="border-width: 2px;">
            <div class="brand-icon mx-auto mb-2 text-dark fs-3">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h4 class="text-white fw-bold mb-1 font-serif">Appointment Successfully Reserved!</h4>
            <p class="text-secondary mb-3">
                Thank you <strong>{{ session('booking_success')['name'] }}</strong>. Your photoshoot appointment for <strong>{{ session('booking_success')['plan'] }}</strong> has been booked for <strong>{{ session('booking_success')['date'] }} at {{ session('booking_success')['time'] }}</strong>.
            </p>
            <div class="d-inline-block p-2 px-4 rounded-pill bg-dark border border-warning text-warning fw-bold mb-2">
                Booking Reference #: {{ session('booking_success')['number'] }}
            </div>
            <div class="small text-secondary">
                Our studio coordinator will call you shortly to confirm wardrobe and setup details.
            </div>
        </div>
    </div>
@endif

<!-- Plans & Pricing Grid (Dynamic from Admin!) -->
<section class="py-5" id="plansPricing">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge-studio mb-2 d-inline-block">Our Packages</span>
            <h2 class="display-6 fw-bold text-white mb-2">Photoshoot Packages in Our Studio</h2>
            <p class="text-secondary small">Transparent pricing, comprehensive deliverables & handcrafted heirloom frames included.</p>
        </div>

        <div class="row g-4 justify-content-center">
            @forelse ($plans as $plan)
                <div class="col-md-6 col-lg-3">
                    <div class="glass-card h-100 d-flex flex-column position-relative overflow-hidden {{ $plan->badge ? 'border-warning' : '' }}" style="{{ $plan->badge ? 'box-shadow: 0 0 25px rgba(245, 158, 11, 0.2);' : '' }}">

                        {{-- NEW: Plan image (hero at top of card) --}}
                        @if ($plan->image_url)
                            <div class="position-relative" style="aspect-ratio: 4 / 3; width: 100%; overflow: hidden; background: #1e293b;">
                                <img src="{{ $plan->image_url }}"
                                     alt="{{ $plan->name }}"
                                     loading="lazy"
                                     style="width: 100%; height: 100%; object-fit: cover; display: block;"
                                     onerror="this.parentElement.style.display='none';">
                                {{-- Soft gradient for the badge area --}}
                                <div style="position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(11,15,25,0.35) 0%, transparent 35%, transparent 65%, rgba(11,15,25,0.65) 100%); pointer-events: none;"></div>
                            </div>
                        @endif

                        @if ($plan->badge)
                            <div class="position-absolute top-0 end-0 m-3" style="z-index: 5;">
                                <span class="badge bg-warning text-dark fw-bold rounded-pill px-3 py-1 shadow" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-crown me-1"></i> {{ $plan->badge }}
                                </span>
                            </div>
                        @endif

                        {{-- Card body (padded) --}}
                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <h4 class="fw-bold text-white mb-1 font-serif">{{ $plan->name }}</h4>
                            <div class="small text-secondary mb-3">
                                <i class="fa-regular fa-clock text-warning me-1"></i> {{ $plan->duration }}
                            </div>

                            <div class="my-3 pb-3 border-bottom border-secondary border-opacity-25">
                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="fs-1 fw-bold text-warning font-serif">{{ $currencySymbol }}{{ number_format($plan->price, 0) }}</span>
                                    <span class="text-secondary small">/ package</span>
                                </div>
                                @if ($plan->deliverables)
                                    <div class="text-secondary small mt-1 fw-semibold">
                                        <i class="fa-solid fa-box-archive text-warning me-1"></i> {{ $plan->deliverables }}
                                    </div>
                                @endif
                            </div>

                            @if ($plan->description)
                                <p class="small text-secondary mb-3" style="font-size: 0.82rem; line-height: 1.5;">
                                    {{ $plan->description }}
                                </p>
                            @endif

                            @if (!empty($plan->features))
                                <div class="mb-4 flex-grow-1">
                                    <span class="text-white small fw-bold d-block mb-2 text-uppercase" style="font-size: 0.72rem; letter-spacing: 1px;">What's Included:</span>
                                    <ul class="list-unstyled small text-secondary d-flex flex-column gap-2 mb-0" style="font-size: 0.82rem;">
                                        @foreach ($plan->features as $feat)
                                            <li class="d-flex align-items-start gap-2">
                                                <i class="fa-solid fa-check text-warning mt-1"></i>
                                                <span>{{ $feat }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="mt-auto pt-3">
                                <button type="button" class="btn {{ $plan->badge ? 'btn-gold' : 'btn-outline-gold' }} w-100 rounded-pill fw-bold select-plan-btn" data-plan-id="{{ $plan->id }}" data-plan-name="{{ $plan->name }}" data-plan-price="{{ $currencySymbol }}{{ number_format($plan->price, 0) }}">
                                    <i class="fa-solid fa-calendar-check me-1"></i> Select & Book Plan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5 text-secondary">
                    <i class="fa-regular fa-folder-open fs-1 mb-2"></i>
                    <p>No photoshoot plans currently published by administrator.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>

<!-- Studio Features & Trust Section -->
<section class="py-5 bg-dark bg-opacity-40 border-top border-secondary border-opacity-25">
    <div class="container py-4">
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <div class="p-3">
                    <i class="fa-solid fa-camera text-warning fs-1 mb-3"></i>
                    <h5 class="text-white fw-bold mb-1 font-serif">Sony & Leica Cinema Optics</h5>
                    <p class="text-secondary small mb-0">High-end sensors capturing rich color rendition and organic bokeh.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <i class="fa-solid fa-lightbulb text-warning fs-1 mb-3"></i>
                    <h5 class="text-white fw-bold mb-1 font-serif">Profoto Studio Lighting</h5>
                    <p class="text-secondary small mb-0">Soft, flattering Hollywood studio light modifiers for timeless portraits.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <i class="fa-solid fa-palette text-warning fs-1 mb-3"></i>
                    <h5 class="text-white fw-bold mb-1 font-serif">Artisanal Color Grading</h5>
                    <p class="text-secondary small mb-0">Skin tones meticulously retouched and color-matched by senior artists.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <i class="fa-solid fa-cloud-arrow-down text-warning fs-1 mb-3"></i>
                    <h5 class="text-white fw-bold mb-1 font-serif">Rapid Digital Delivery</h5>
                    <p class="text-secondary small mb-0">Full digital master gallery delivered in 48 hours in resolution.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Interactive Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content glass-card border-secondary text-white">
            <div class="modal-header border-secondary border-opacity-25 pb-3">
                <div>
                    <h5 class="modal-title font-serif fw-bold text-white mb-0" id="bookingModalLabel">
                        <i class="fa-solid fa-calendar-check text-warning me-2"></i>Schedule Your Photoshoot Session
                    </h5>
                    <span class="small text-secondary">Fill in your details below and our studio team will confirm your slot.</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('photoshoot.book') }}" method="POST" id="appointmentForm">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Chosen Plan -->
                        <div class="col-12">
                            <label class="form-label text-white small fw-bold">1. Select Photoshoot Package</label>
                            <select name="photoshoot_plan_id" id="modalPlanSelect" class="form-select bg-dark text-white border-secondary" required>
                                @foreach ($plans as $p)
                                    <option value="{{ $p->id }}" {{ ($selectedPlan && $selectedPlan->id == $p->id) ? 'selected' : '' }}>
                                        {{ $p->name }} — {{ $currencySymbol }}{{ number_format($p->price, 0) }} ({{ $p->duration }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Customer Details -->
                        <div class="col-md-6">
                            <label class="form-label text-white small fw-bold">2. Your Full Name</label>
                            <input type="text" name="customer_name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. John Doe" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-white small fw-bold">3. Phone Number</label>
                            <input type="tel" name="customer_phone" class="form-control bg-dark text-white border-secondary" placeholder="e.g. +91 98765 43210" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-white small fw-bold">4. Email Address</label>
                            <input type="email" name="customer_email" class="form-control bg-dark text-white border-secondary" placeholder="e.g. john@example.com" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-white small fw-bold">5. Preferred Appointment Date</label>
                            <input type="date" name="booking_date" class="form-control bg-dark text-white border-secondary" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d', strtotime('+2 days')) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-white small fw-bold">6. Preferred Time Slot</label>
                            <select name="booking_time" class="form-select bg-dark text-white border-secondary" required>
                                <option value="Morning 10:00 AM - 12:00 PM">Morning (10:00 AM - 12:00 PM)</option>
                                <option value="Afternoon 02:00 PM - 04:00 PM">Afternoon (02:00 PM - 04:00 PM)</option>
                                <option value="Evening 05:00 PM - 07:00 PM">Evening (05:00 PM - 07:00 PM)</option>
                                <option value="Golden Hour Sunset (05:30 PM - 06:45 PM)">Golden Hour Sunset (05:30 PM - 06:45 PM)</option>
                                <option value="Flexible / Full Day">Flexible / Full Day Schedule</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-white small fw-bold">7. Preferred Location / Setup</label>
                            <select name="location" class="form-select bg-dark text-white border-secondary">
                                <option value="Main Studio Headquarters (Jubilee Hills)">Main Studio Headquarters (Jubilee Hills)</option>
                                <option value="Outdoor Nature / Garden Location">Outdoor Nature / Garden Location</option>
                                <option value="Client Residence / Home Setup">Client Residence / Home Setup</option>
                                <option value="Destination / Event Venue">Destination / Event Venue</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-white small fw-bold">8. Special Requests or Occasion Details (Optional)</label>
                            <textarea name="notes" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Tell us about the occasion (Birthday, Anniversary, Pre-wedding), number of people, preferred themes or props..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary border-opacity-25 p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold rounded-pill px-4 fw-bold">
                        <i class="fa-solid fa-paper-plane me-1"></i> Submit Appointment Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bookingModalEl = document.getElementById('bookingModal');
    const bookingModal = new bootstrap.Modal(bookingModalEl);
    const modalPlanSelect = document.getElementById('modalPlanSelect');

    // Click "Select & Book Plan" on any plan card
    document.querySelectorAll('.select-plan-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const planId = this.dataset.planId;
            if (modalPlanSelect) {
                modalPlanSelect.value = planId;
            }
            bookingModal.show();
        });
    });

    @if (request('plan'))
        bookingModal.show();
    @endif
});
</script>
@endpush