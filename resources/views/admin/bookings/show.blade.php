@extends('layouts.admin')

@section('title', 'Appointment Details: ' . $booking->booking_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">Appointment #{{ $booking->booking_number }}</h2>
        <p class="text-secondary small mb-0">Submitted on {{ $booking->created_at->format('M d, Y \a\t h:i A') }}</p>
    </div>
    <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
        &larr; Back to Appointments
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="admin-card mb-4">
            <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-25">Customer & Appointment Info</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-secondary small d-block">Customer Full Name</span>
                    <strong class="text-white fs-5">{{ $booking->customer_name }}</strong>
                </div>
                <div class="col-md-6">
                    <span class="text-secondary small d-block">Selected Package</span>
                    <strong class="text-warning fs-5">{{ $booking->plan_name }}</strong>
                    @if ($booking->plan)
                        <div class="text-secondary small">₹{{ number_format($booking->plan->price, 2) }} • {{ $booking->plan->duration }}</div>
                    @endif
                </div>

                <div class="col-md-6">
                    <span class="text-secondary small d-block">Phone Number</span>
                    <strong class="text-white">
                        <a href="tel:{{ $booking->customer_phone }}" class="text-warning text-decoration-none">
                            <i class="fa-solid fa-phone me-1"></i> {{ $booking->customer_phone }}
                        </a>
                    </strong>
                </div>
                <div class="col-md-6">
                    <span class="text-secondary small d-block">Email Address</span>
                    <strong class="text-white">
                        <a href="mailto:{{ $booking->customer_email }}" class="text-info text-decoration-none">
                            <i class="fa-solid fa-envelope me-1"></i> {{ $booking->customer_email }}
                        </a>
                    </strong>
                </div>

                <div class="col-md-6">
                    <span class="text-secondary small d-block">Appointment Date</span>
                    <strong class="text-white">{{ $booking->booking_date->format('l, F d, Y') }}</strong>
                </div>
                <div class="col-md-6">
                    <span class="text-secondary small d-block">Time Slot</span>
                    <strong class="text-white">{{ $booking->booking_time }}</strong>
                </div>

                <div class="col-12">
                    <span class="text-secondary small d-block">Preferred Venue / Location</span>
                    <div class="text-white">{{ $booking->location ?? 'Main Studio HQ' }}</div>
                </div>

                @if ($booking->notes)
                    <div class="col-12">
                        <span class="text-secondary small d-block">Customer Notes & Special Requests</span>
                        <div class="p-3 rounded bg-dark border border-secondary text-secondary small">
                            "{{ $booking->notes }}"
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card">
            <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-25">Update Status</h5>
            <form action="{{ route('admin.bookings.status', $booking) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="mb-3">
                    <label class="form-label text-white small fw-bold">Booking Status</label>
                    <select name="status" class="form-select bg-dark text-white border-secondary">
                        <option value="pending" {{ $booking->status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="confirmed" {{ $booking->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                        <option value="completed" {{ $booking->status === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ $booking->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-bold rounded-pill mb-3">
                    Update Status
                </button>
            </form>

            <hr class="border-secondary border-opacity-25 my-3">

            <div class="d-flex flex-column gap-2">
                <a href="tel:{{ $booking->customer_phone }}" class="btn btn-outline-success btn-sm rounded-pill text-start">
                    <i class="fa-solid fa-phone me-2"></i> Call Customer Directly
                </a>
                <a href="mailto:{{ $booking->customer_email }}?subject=Appointment%20Confirmation%20{{ $booking->booking_number }}" class="btn btn-outline-info btn-sm rounded-pill text-start">
                    <i class="fa-solid fa-envelope me-2"></i> Send Confirmation Email
                </a>
                <form action="{{ route('admin.bookings.destroy', $booking) }}" method="POST" onsubmit="return confirm('Permanently delete this appointment?');" class="mt-2">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill w-100">
                        <i class="fa-solid fa-trash me-1"></i> Delete Booking Record
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
