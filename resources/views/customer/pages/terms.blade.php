@extends('layouts.main')

@section('title', 'Terms & Conditions | Neelax Tech')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="text-center mb-5">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold text-uppercase" style="letter-spacing:1px;">Legal Agreement</span>
                <h1 class="display-5 fw-bold text-white font-serif mt-3 mb-2">Terms & Conditions</h1>
                <p class="text-secondary small">Last Updated: {{ date('F Y') }} • Neelax Tech</p>
            </div>

            <div class="glass-card p-4 p-md-5">
                @if ($termsText)
                    <div class="text-light" style="line-height: 1.8;">
                        {!! nl2br(e($termsText)) !!}
                    </div>
                @else
                    <div class="text-light" style="line-height: 1.8;">
                        <h4 class="text-warning font-serif mb-3">1. Scope of Service & Digital Delivery</h4>
                        <p class="text-secondary mb-4">
                            Neelax Tech provides a web-based automated digital image generation and photo framing platform. By using our website and purchasing frame generations, you explicitly acknowledge and agree that our service constitutes <strong>instant digital goods and digital downloads only</strong>. We do not manufacture, package, ship, or deliver any physical wooden, glass, acrylic, or plastic photo frames.
                        </p>

                        <h4 class="text-warning font-serif mb-3">2. User Uploads & Rights</h4>
                        <p class="text-secondary mb-4">
                            You represent and warrant that you own or possess all requisite rights, licenses, and permissions for any photographs uploaded to our platform. You agree not to upload defamatory, illegal, or infringing content. Neelax Tech does not claim ownership over customer photos and uses them solely to fulfill automated frame rendering.
                        </p>

                        <h4 class="text-warning font-serif mb-3">3. Payments & Razorpay Integration</h4>
                        <p class="text-secondary mb-4">
                            All transactions are securely processed through Razorpay. Prior to payment, a watermarked preview is displayed to allow you to inspect the composition. Payment unlocks removal of the watermark and grants access to high-resolution (HD/) digital downloads.
                        </p>

                        <h4 class="text-warning font-serif mb-3">4. Photoshoot Bookings & Appointments</h4>
                        <p class="text-secondary mb-4">
                            When reserving studio photography appointments through our platform, you agree to provide truthful contact information. Appointments are confirmed upon schedule coordination with our team.
                        </p>

                        <h4 class="text-warning font-serif mb-3">5. Intellectual Property</h4>
                        <p class="text-secondary mb-4">
                            All graphic template artwork, borders, floral accents, calendar layouts, code, branding, and logos associated with Neelax Tech remain the intellectual property of Neelax Tech.
                        </p>

                        <h4 class="text-warning font-serif mb-3">6. Governing Law & Contact</h4>
                        <p class="text-secondary mb-0">
                            These terms shall be governed by and construed in accordance with the laws of India. For questions concerning these terms, reach us at <a href="mailto:support@neelaxtech.com" class="text-warning">support@neelaxtech.com</a>.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
