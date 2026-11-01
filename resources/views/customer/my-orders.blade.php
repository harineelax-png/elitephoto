@extends('layouts.main')

@section('title', 'My Orders & Frame Downloads')

@section('content')
<div class="container py-5">
    <div class="text-center mb-5">
        <span class="badge-studio mb-2 d-inline-block">Retrieve Purchases</span>
        <h2 class="display-6 fw-bold text-white mb-2 font-serif">My Framed Photos & Downloads</h2>
        <p class="text-secondary small max-w-sm mx-auto">
            Enter your Order Reference Number (e.g. ORD-202610-XXXXXX) or the phone number used during checkout to access your high-definition downloads.
        </p>

        <!-- Search Form -->
        <div class="row justify-content-center mt-4">
            <div class="col-md-7 col-lg-5">
                <form method="GET" action="{{ route('customer.orders') }}" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control bg-dark text-white border-secondary rounded-pill px-3" placeholder="Order # or Phone number..." value="{{ request('search') }}" required>
                    <button type="submit" class="btn btn-gold rounded-pill px-4 fw-bold">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Search
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if (request()->filled('search'))
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <h5 class="fw-bold text-white mb-3">Search Results for "{{ request('search') }}"</h5>

                @if ($orders->isEmpty())
                    <div class="glass-card p-5 text-center text-secondary">
                        <i class="fa-solid fa-receipt fs-1 mb-3 text-secondary"></i>
                        <h5>No Matching Orders Found</h5>
                        <p class="small text-secondary mb-3">Please check your order number or phone number and try again.</p>
                        <a href="{{ route('studio.index') }}" class="btn btn-gold btn-sm rounded-pill">Create a New Frame</a>
                    </div>
                @else
                    <div class="d-flex flex-column gap-3">
                        @foreach ($orders as $order)
                            <div class="glass-card p-3 p-sm-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-3 bg-black overflow-hidden text-center border border-secondary" style="width: 70px; height: 90px;">
                                        @if ($order->generated_image_path && file_exists(storage_path('app/public/' . $order->generated_image_path)))
                                            <img src="{{ asset('storage/' . $order->generated_image_path) }}" class="w-100 h-100 object-fit-contain" alt="Frame">
                                        @else
                                            <div class="d-flex align-items-center justify-content-center h-100 text-secondary">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <h6 class="fw-bold text-white mb-0">{{ $order->order_number }}</h6>
                                            @if ($order->payment_status === 'paid')
                                                <span class="badge bg-success rounded-pill" style="font-size:0.68rem;"><i class="fa-solid fa-check me-1"></i> Paid</span>
                                            @else
                                                <span class="badge bg-warning text-dark rounded-pill" style="font-size:0.68rem;">Pending</span>
                                            @endif
                                        </div>
                                        <div class="text-secondary small">{{ $order->frame->name ?? 'Custom Frame' }} • ₹{{ number_format($order->amount, 2) }}</div>
                                        <div class="text-secondary small" style="font-size:0.75rem;">
                                            Ordered on {{ $order->created_at->format('M d, Y h:i A') }} • Downloaded {{ $order->download_count }} times
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    @if ($order->payment_status === 'paid' && $order->generated_image_path)
                                        <a href="{{ route('studio.download', $order->download_token) }}" class="btn btn-gold btn-sm rounded-pill px-4 fw-bold">
                                            <i class="fa-solid fa-download me-1"></i> Download HD Frame
                                        </a>
                                    @else
                                        <span class="badge bg-secondary">Payment Incomplete</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
