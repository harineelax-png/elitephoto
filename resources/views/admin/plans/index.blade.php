@extends('layouts.admin')

@section('title', 'Manage Photoshoot Plans')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">
            <i class="fa-solid fa-tags text-warning me-2"></i>Photoshoot Plans & Packages
        </h2>
        <p class="text-secondary small mb-0">These plans are offered to customers on the Photoshoot Booking form.</p>
    </div>
    <a href="{{ route('admin.plans.create') }}" class="btn btn-warning rounded-pill btn-sm fw-bold d-flex align-items-center gap-2">
        <i class="fa-solid fa-plus"></i> Add New Plan
    </a>
</div>

<div class="row g-4">
    @forelse ($plans as $plan)
        <div class="col-md-6 col-xl-3">
            <div class="admin-card h-100 d-flex flex-column position-relative">
                @if ($plan->badge)
                    <div class="position-absolute top-0 end-0 m-3">
                        <span class="badge bg-warning text-dark fw-bold rounded-pill">{{ $plan->badge }}</span>
                    </div>
                @endif

                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning" style="width:38px; height:38px; font-size:1.1rem;">
                        <i class="fa-solid fa-camera"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-white mb-0">{{ $plan->name }}</h5>
                        <span class="small text-secondary">{{ $plan->duration }}</span>
                    </div>
                </div>

                <div class="my-3">
                    <div class="fs-3 fw-bold text-warning">₹{{ number_format($plan->price, 2) }}</div>
                    @if ($plan->deliverables)
                        <div class="small text-secondary fw-semibold">
                            <i class="fa-solid fa-box-open text-warning me-1"></i> {{ $plan->deliverables }}
                        </div>
                    @endif
                </div>

                @if ($plan->description)
                    <p class="small text-secondary mb-3">{{ $plan->description }}</p>
                @endif

                @if (!empty($plan->features))
                    <div class="mb-4 flex-grow-1">
                        <ul class="list-unstyled small text-secondary d-flex flex-column gap-1 mb-0">
                            @foreach ($plan->features as $feat)
                                <li><i class="fa-solid fa-check text-success me-1"></i> {{ $feat }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-auto pt-3 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                    <form action="{{ route('admin.plans.toggle', $plan) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm {{ $plan->is_active ? 'btn-success-subtle text-success' : 'btn-secondary-subtle text-secondary' }} rounded-pill" style="font-size:0.75rem;">
                            {{ $plan->is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </form>

                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-light rounded-circle" title="Edit Plan" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" onsubmit="return confirm('Delete this photoshoot plan?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Delete Plan" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center py-5 text-secondary">
            <i class="fa-regular fa-folder-open fs-1 mb-2"></i>
            <p>No photoshoot plans configured. Click "Add New Plan" to create one.</p>
        </div>
    @endforelse
</div>
@endsection
