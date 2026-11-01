@extends('layouts.admin')

@section('title', 'Photoshoot Appointments')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">
            <i class="fa-solid fa-calendar-check text-warning me-2"></i>Photoshoot Appointments
        </h2>
        <p class="text-secondary small mb-0">Customer photoshoot appointment bookings submitted through the form.</p>
    </div>
</div>

<!-- Status Filters & Search Strip -->
<div class="admin-card mb-4 p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.bookings.index') }}" class="btn btn-sm {{ !request('status') || request('status') === 'all' ? 'btn-warning fw-bold' : 'btn-dark border-secondary text-secondary' }} rounded-pill">
                All ({{ $counts['all'] }})
            </a>
            <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}" class="btn btn-sm {{ request('status') === 'pending' ? 'btn-danger fw-bold' : 'btn-dark border-secondary text-secondary' }} rounded-pill">
                Pending ({{ $counts['pending'] }})
            </a>
            <a href="{{ route('admin.bookings.index', ['status' => 'confirmed']) }}" class="btn btn-sm {{ request('status') === 'confirmed' ? 'btn-success fw-bold' : 'btn-dark border-secondary text-secondary' }} rounded-pill">
                Confirmed ({{ $counts['confirmed'] }})
            </a>
            <a href="{{ route('admin.bookings.index', ['status' => 'completed']) }}" class="btn btn-sm {{ request('status') === 'completed' ? 'btn-primary fw-bold' : 'btn-dark border-secondary text-secondary' }} rounded-pill">
                Completed ({{ $counts['completed'] }})
            </a>
            <a href="{{ route('admin.bookings.index', ['status' => 'cancelled']) }}" class="btn btn-sm {{ request('status') === 'cancelled' ? 'btn-secondary fw-bold text-white' : 'btn-dark border-secondary text-secondary' }} rounded-pill">
                Cancelled ({{ $counts['cancelled'] }})
            </a>
        </div>

        <form method="GET" action="{{ route('admin.bookings.index') }}" class="d-flex gap-2">
            @if (request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <input type="text" name="search" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Search customer, phone, ref..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-3">Search</button>
        </form>
    </div>
</div>

<!-- Bookings Table -->
<div class="admin-card p-0 overflow-hidden">
    @if ($bookings->isEmpty())
        <div class="text-center py-5 text-secondary">
            <i class="fa-regular fa-calendar-xmark fs-1 mb-2"></i>
            <p>No photoshoot appointments found.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Ref #</th>
                        <th>Customer Details</th>
                        <th>Selected Plan</th>
                        <th>Appointment Date & Slot</th>
                        <th>Location / Venue</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bookings as $b)
                        <tr>
                            <td class="ps-4 fw-bold text-warning">{{ $b->booking_number }}</td>
                            <td>
                                <div class="fw-bold text-white">{{ $b->customer_name }}</div>
                                <div class="small text-secondary">
                                    <i class="fa-solid fa-phone text-warning me-1"></i> <a href="tel:{{ $b->customer_phone }}" class="text-secondary text-decoration-none">{{ $b->customer_phone }}</a>
                                </div>
                                <div class="small text-secondary">
                                    <i class="fa-solid fa-envelope text-info me-1"></i> {{ $b->customer_email }}
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary fw-semibold">
                                    {{ $b->plan_name }}
                                </span>
                            </td>
                            <td>
                                <div class="text-white small fw-bold"><i class="fa-regular fa-calendar text-warning me-1"></i> {{ $b->booking_date->format('l, M d, Y') }}</div>
                                <div class="text-secondary small"><i class="fa-regular fa-clock text-info me-1"></i> {{ $b->booking_time }}</div>
                            </td>
                            <td class="small text-secondary">
                                {{ $b->location ?? 'Studio Session' }}
                                @if ($b->notes)
                                    <div class="text-secondary fst-italic mt-1" style="font-size:0.75rem;" title="{{ $b->notes }}">
                                        "{{ Str::limit($b->notes, 35) }}"
                                    </div>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('admin.bookings.status', $b) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary rounded-pill py-1 px-2" style="font-size:0.78rem; width: auto;" onchange="this.form.submit()">
                                        <option value="pending" {{ $b->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="confirmed" {{ $b->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                        <option value="completed" {{ $b->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="cancelled" {{ $b->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                </form>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('admin.bookings.show', $b) }}" class="btn btn-sm btn-outline-light rounded-circle" title="View Booking Details" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <form action="{{ route('admin.bookings.destroy', $b) }}" method="POST" onsubmit="return confirm('Delete this appointment record?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Delete Booking" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="mt-4">
    {{ $bookings->links('pagination::bootstrap-5') }}
</div>
@endsection
