@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">Studio Overview</h2>
        <p class="text-secondary small mb-0">Live analytics, template status, customer frame purchases & photoshoot appointments.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.batch.index') }}" class="btn btn-warning fw-bold rounded-pill btn-sm d-flex align-items-center gap-2">
            <i class="fa-solid fa-layer-group"></i> Batch 300 Generator (Free)
        </a>
        <a href="{{ route('admin.frames.create') }}" class="btn btn-outline-light rounded-pill btn-sm d-flex align-items-center gap-2">
            <i class="fa-solid fa-plus"></i> New Template
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <!-- Active Templates -->
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-secondary small fw-semibold text-uppercase">Active Templates</span>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-images"></i>
                </div>
            </div>
            <div class="fs-2 fw-bold text-white">{{ $activeFrames }} <span class="fs-6 text-secondary fw-normal">/ {{ $totalFrames }} total</span></div>
            <div class="small text-secondary mt-1">
                <a href="{{ route('admin.frames.index') }}" class="text-warning text-decoration-none">Manage Frames &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Current Price per Image -->
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-secondary small fw-semibold text-uppercase">Price / Image</span>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-tag"></i>
                </div>
            </div>
            <div class="fs-2 fw-bold text-white">₹{{ number_format((float)$pricePerImage, 2) }}</div>
            <div class="small text-secondary mt-1">
                <a href="{{ route('admin.settings.index') }}" class="text-primary text-decoration-none">Adjust Pricing &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Photoshoot Appointments -->
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-secondary small fw-semibold text-uppercase">Photoshoot Bookings</span>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
            </div>
            <div class="fs-2 fw-bold text-white">
                {{ $totalBookingsCount }}
                @if ($pendingBookingsCount > 0)
                    <span class="badge bg-danger rounded-pill fs-6 align-middle">{{ $pendingBookingsCount }} Pending</span>
                @endif
            </div>
            <div class="small text-secondary mt-1">
                <a href="{{ route('admin.bookings.index') }}" class="text-danger text-decoration-none">View Appointments &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Paid Revenue -->
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-secondary small fw-semibold text-uppercase">Total Frame Revenue</span>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-indian-rupee-sign"></i>
                </div>
            </div>
            <div class="fs-2 fw-bold text-white">₹{{ number_format($totalRevenue, 2) }}</div>
            <div class="small text-secondary mt-1">
                <span class="text-success">{{ $paidOrdersCount }} completed orders</span> • <a href="{{ route('admin.orders.index') }}" class="text-success text-decoration-none">Logs &rarr;</a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Action Strip -->
<div class="admin-card mb-4 p-3">
    <div class="row g-3 align-items-center">
        <div class="col-lg-3">
            <div class="fw-bold text-white"><i class="fa-solid fa-bolt text-warning me-2"></i>Quick Actions</div>
            <div class="small text-secondary">Common management tasks</div>
        </div>
        <div class="col-lg-9 d-flex flex-wrap gap-2">
            <a href="{{ route('admin.batch.index') }}" class="btn btn-dark border border-secondary text-white btn-sm rounded-pill px-3">
                <i class="fa-solid fa-cloud-arrow-up text-warning me-1"></i> Upload 300 Images Batch
            </a>
            <a href="{{ route('admin.frames.create') }}" class="btn btn-dark border border-secondary text-white btn-sm rounded-pill px-3">
                <i class="fa-solid fa-crop-simple text-info me-1"></i> Add Image Template
            </a>
            <a href="{{ route('admin.plans.create') }}" class="btn btn-dark border border-secondary text-white btn-sm rounded-pill px-3">
                <i class="fa-solid fa-plus-circle text-success me-1"></i> Add Photoshoot Plan
            </a>
            <a href="{{ route('admin.settings.index') }}" class="btn btn-dark border border-secondary text-white btn-sm rounded-pill px-3">
                <i class="fa-solid fa-sliders text-danger me-1"></i> Update Pricing & Keys
            </a>
        </div>
    </div>
</div>

<!-- Two Column Tables: Recent Appointments & Recent Orders -->
<div class="row g-4">
    <!-- Recent Photoshoot Bookings (Page 2 data) -->
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-white mb-0">
                    <i class="fa-solid fa-camera-retro text-danger me-2"></i>Recent Photoshoot Bookings
                </h5>
                <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">View All</a>
            </div>

            @if ($recentBookings->isEmpty())
                <div class="text-center py-4 text-secondary">
                    <i class="fa-regular fa-calendar-xmark fs-2 mb-2"></i>
                    <p class="small mb-0">No photoshoot bookings yet. They will appear here when users submit the  form.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-custom align-middle table-sm">
                        <thead>
                            <tr>
                                <th>Booking #</th>
                                <th>Customer</th>
                                <th>Plan</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentBookings as $b)
                                <tr>
                                    <td class="fw-bold text-white">
                                        <a href="{{ route('admin.bookings.show', $b) }}" class="text-decoration-none text-warning">
                                            {{ $b->booking_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-white small">{{ $b->customer_name }}</div>
                                        <div class="text-secondary" style="font-size: 0.75rem;">{{ $b->customer_phone }}</div>
                                    </td>
                                    <td><span class="badge bg-secondary-subtle text-secondary small">{{ $b->plan_name }}</span></td>
                                    <td class="small text-secondary">{{ $b->booking_date->format('M d, Y') }}<br>{{ $b->booking_time }}</td>
                                    <td>
                                        @if ($b->status === 'pending')
                                            <span class="badge bg-danger">Pending</span>
                                        @elseif ($b->status === 'confirmed')
                                            <span class="badge bg-success">Confirmed</span>
                                        @elseif ($b->status === 'completed')
                                            <span class="badge bg-primary">Completed</span>
                                        @else
                                            <span class="badge bg-secondary">Cancelled</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Recent Frame Orders & Payments -->
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-white mb-0">
                    <i class="fa-solid fa-credit-card text-success me-2"></i>Recent Frame Purchases
                </h5>
                <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">View All</a>
            </div>

            @if ($recentOrders->isEmpty())
                <div class="text-center py-4 text-secondary">
                    <i class="fa-regular fa-receipt fs-2 mb-2"></i>
                    <p class="small mb-0">No frame purchases recorded yet. Razorpay orders appear here after customer payments.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-custom align-middle table-sm">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Template</th>
                                <th>Amount</th>
                                <th>Razorpay Status</th>
                                <th>Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentOrders as $order)
                                <tr>
                                    <td class="fw-bold text-white small">{{ $order->order_number }}</td>
                                    <td class="small text-secondary">{{ $order->frame->name ?? 'Custom Frame' }}</td>
                                    <td class="fw-bold text-white small">₹{{ number_format($order->amount, 2) }}</td>
                                    <td>
                                        @if ($order->payment_status === 'paid')
                                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Paid</span>
                                        @elseif ($order->payment_status === 'pending')
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        @else
                                            <span class="badge bg-danger">Failed</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($order->payment_status === 'paid' && $order->generated_image_path)
                                            <a href="{{ route('studio.download', $order->download_token) }}" class="btn btn-sm btn-outline-warning py-0 px-2" style="font-size:0.75rem;">
                                                <i class="fa-solid fa-download"></i> HD
                                            </a>
                                        @else
                                            <span class="text-secondary small">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
