@extends('layouts.admin')

@section('title', 'Frame Orders & Razorpay Payments')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">
            <i class="fa-solid fa-credit-card text-warning me-2"></i>Frame Orders & Payments
        </h2>
        <p class="text-secondary small mb-0">Live log of user purchases, Razorpay transaction IDs, and generated HD frames.</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-success py-2 px-3 rounded-pill fs-6">
            Total Revenue: ₹{{ number_format($totalPaidRevenue, 2) }} ({{ $paidCount }} Paid)
        </span>
    </div>
</div>

<!-- Search & Filter -->
<div class="admin-card mb-4 p-3">
    <form method="GET" action="{{ route('admin.orders.index') }}" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Search order #, customer, phone, payment ID..." value="{{ request('search') }}">
            </div>
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                <option value="all">All Payment Statuses</option>
                <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill flex-grow-1">Filter</button>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
        </div>
    </form>
</div>

<!-- Orders Table -->
<div class="admin-card p-0 overflow-hidden">
    @if ($orders->isEmpty())
        <div class="text-center py-5 text-secondary">
            <i class="fa-regular fa-receipt fs-1 mb-2"></i>
            <p>No orders found.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Order #</th>
                        <th>Customer</th>
                        <th>Template Frame</th>
                        <th>Amount</th>
                        <th>Razorpay IDs</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end pe-4">HD Image</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td class="ps-4 fw-bold text-warning small">{{ $order->order_number }}</td>
                            <td>
                                <div class="text-white small fw-bold">{{ $order->customer_name ?? 'Customer' }}</div>
                                <div class="text-secondary" style="font-size:0.75rem;">{{ $order->customer_phone ?? $order->customer_email }}</div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary small">
                                    {{ $order->frame->name ?? 'Custom Frame' }}
                                </span>
                            </td>
                            <td class="fw-bold text-white">₹{{ number_format($order->amount, 2) }}</td>
                            <td class="small text-secondary">
                                <div><span class="text-secondary opacity-75">Order:</span> {{ $order->razorpay_order_id ?? '—' }}</div>
                                <div><span class="text-secondary opacity-75">Pay ID:</span> {{ $order->razorpay_payment_id ?? '—' }}</div>
                            </td>
                            <td>
                                @if ($order->payment_status === 'paid')
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Paid</span>
                                @elseif ($order->payment_status === 'pending')
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @else
                                    <span class="badge bg-danger">Failed</span>
                                @endif
                            </td>
                            <td class="small text-secondary">{{ $order->created_at->format('M d, Y h:i A') }}</td>
                            <td class="text-end pe-4">
                                @if ($order->payment_status === 'paid' && $order->generated_image_path)
                                    <a href="{{ route('studio.download', $order->download_token) }}" class="btn btn-sm btn-outline-warning rounded-pill py-1 px-3" style="font-size:0.78rem;">
                                        <i class="fa-solid fa-download me-1"></i> Download HD
                                    </a>
                                @else
                                    <span class="text-secondary small">Not generated</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="mt-4">
    {{ $orders->links('pagination::bootstrap-5') }}
</div>
@endsection
