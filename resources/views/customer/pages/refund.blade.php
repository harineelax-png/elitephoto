@extends('layouts.main')

@section('title', 'Refund & Cancellation Policy | Neelax Tech')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="text-center mb-5">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold text-uppercase" style="letter-spacing:1px;">Customer Protection</span>
                <h1 class="display-5 fw-bold text-white font-serif mt-3 mb-2">Refund & Cancellation Policy</h1>
                <p class="text-secondary small">Applicable to all Neelax Tech Digital Downloads & Photography Services</p>
            </div>

            <div class="glass-card p-4 p-md-5">
                @if ($refundText)
                    <div class="text-light" style="line-height: 1.8;">
                        {!! nl2br(e($refundText)) !!}
                    </div>
                @else
                    <div class="text-light" style="line-height: 1.8;">
                        <h4 class="text-warning font-serif mb-3">1. Nature of Digital Products (No Physical Shipment)</h4>
                        <p class="text-secondary mb-4">
                            <strong>Neelax Tech</strong> offers instant digital image generation and downloads. We allow every customer to view a comprehensive <strong>watermarked preview</strong> of their framed photos, ribbon text, and calendar highlights before committing to payment.
                        </p>

                        <h4 class="text-warning font-serif mb-3">2. Digital Download Refund Eligibility</h4>
                        <p class="text-secondary mb-4">
                            Due to the immutable and immediate delivery of digital goods:
                        </p>
                        <ul class="text-secondary mb-4">
                            <li><strong>Standard Transactions:</strong> Once payment is confirmed and the unwatermarked High-Definition image is generated and made accessible for download, transactions are generally non-refundable.</li>
                            <li><strong>Technical Failures:</strong> In the rare event that payment was debited via Razorpay but a server error prevented image generation or the download link malfunctioned, our support desk will immediately regenerate the high-definition file or issue a <strong>100% full refund</strong> within 3-5 business days.</li>
                            <li><strong>Duplicate Debits:</strong> Multiple debits for the same order due to network timeouts will be automatically detected and refunded to the original payment source.</li>
                        </ul>

                        <h4 class="text-warning font-serif mb-3">3. Photoshoot Appointment Rescheduling</h4>
                        <p class="text-secondary mb-4">
                            For professional studio photoshoot packages, appointments may be rescheduled up to 24 hours prior to the booked session date with zero penalty. Cancellations are handled in accordance with the customized arrangement confirmed with our studio manager.
                        </p>

                        <h4 class="text-warning font-serif mb-3">4. How to Request Assistance or Refunds</h4>
                        <p class="text-secondary mb-4">
                            To report a technical problem or request a refund for a failed digital download:
                        </p>
                        <ol class="text-secondary mb-4">
                            <li>Email <a href="mailto:support@neelaxtech.com" class="text-warning">support@neelaxtech.com</a>.</li>
                            <li>Include your <strong>Order Number</strong> (e.g., ORD-20261012-XXXXXX) or Razorpay Payment ID.</li>
                            <li>Our support team will verify the payment log within 24 hours.</li>
                        </ol>

                        <h4 class="text-warning font-serif mb-3">5. Processing Timeline</h4>
                        <p class="text-secondary mb-0">
                            Approved refunds are credited back to the original method of payment (UPI, NetBanking, Debit/Credit Card) within 3 to 7 banking days as per standard RBI and Razorpay processing guidelines.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
