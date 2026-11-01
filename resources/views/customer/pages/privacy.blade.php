@extends('layouts.main')

@section('title', 'Privacy Policy | Neelax Tech')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="text-center mb-5">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold text-uppercase" style="letter-spacing:1px;">Data Protection</span>
                <h1 class="display-5 fw-bold text-white font-serif mt-3 mb-2">Privacy Policy</h1>
                <p class="text-secondary small">Effective Date: {{ date('F Y') }} • Neelax Tech</p>
            </div>

            <div class="glass-card p-4 p-md-5">
                @if ($privacyText)
                    <div class="text-light" style="line-height: 1.8;">
                        {!! nl2br(e($privacyText)) !!}
                    </div>
                @else
                    <div class="text-light" style="line-height: 1.8;">
                        <h4 class="text-warning font-serif mb-3">1. Information We Collect</h4>
                        <p class="text-secondary mb-4">
                            When you interact with <strong>Neelax Tech</strong>, we collect information necessary to deliver personalized framing and photoshoot appointments:
                        </p>
                        <ul class="text-secondary mb-4">
                            <li><strong>Uploaded Photographs:</strong> Digital image files provided for fitting into template openings.</li>
                            <li><strong>Order & Contact Details:</strong> Name, phone number, and email address provided during checkout, inquiry submission, or photoshoot bookings.</li>
                            <li><strong>Transaction Metadata:</strong> Razorpay transaction identifiers, payment status, and order totals. We never store credit/debit card numbers or UPI PINs directly on our servers.</li>
                        </ul>

                        <h4 class="text-warning font-serif mb-3">2. How Your Photos Are Handled</h4>
                        <p class="text-secondary mb-4">
                            Your personal photos are processed solely by automated image compositing algorithms to render your selected template. We do not sell, rent, monetize, or publicly display customer uploaded photos without explicit prior consent. Temporary preview files are automatically purged from our servers periodically.
                        </p>

                        <h4 class="text-warning font-serif mb-3">3. Payment Security & Encryption</h4>
                        <p class="text-secondary mb-4">
                            All payment transactions are conducted through Razorpay, a certified PCI-DSS Level 1 compliant payment gateway with 256-bit SSL encryption.
                        </p>

                        <h4 class="text-warning font-serif mb-3">4. Cookies and Session Storage</h4>
                        <p class="text-secondary mb-4">
                            We use functional session cookies solely to preserve your active template choices, uploaded slot photos, and preview states during your active browser session.
                        </p>

                        <h4 class="text-warning font-serif mb-3">5. Data Retention & Deletion Requests</h4>
                        <p class="text-secondary mb-4">
                            You may request deletion of your contact records or rendered digital image assets at any time by contacting our support team at <a href="mailto:support@neelaxtech.com" class="text-warning">support@neelaxtech.com</a>.
                        </p>

                        <h4 class="text-warning font-serif mb-3">6. Updates to This Policy</h4>
                        <p class="text-secondary mb-0">
                            We may periodically update this policy to reflect platform updates or legal standards. Changes will be posted on this page with an updated timestamp.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
