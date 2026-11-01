@extends('layouts.admin')

@section('title', 'Manage Templates')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">Photo Frame Templates</h2>
        <p class="text-secondary small mb-0">Manage customer-facing frame themes, slot layouts & pricing.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.frames.create') }}" class="btn btn-warning rounded-pill btn-sm fw-bold d-flex align-items-center gap-2">
            <i class="fa-solid fa-plus"></i> Upload New Template
        </a>
    </div>
</div>

<!-- Category Filters & Search -->
<div class="admin-card mb-4 p-3">
    <form method="GET" action="{{ route('admin.frames.index') }}" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Search templates by name..." value="{{ request('search') }}">
            </div>
        </div>
        <div class="col-md-4">
            <select name="category" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                <option value="all">All Categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill flex-grow-1">Filter</button>
            <a href="{{ route('admin.frames.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
        </div>
    </form>
</div>

<!-- Templates Grid -->
<div class="row g-4 mb-4">
    @forelse ($frames as $frame)
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <div class="admin-card h-100 p-3 d-flex flex-column">
                <div class="position-relative mb-3 rounded-3 overflow-hidden bg-black text-center" style="height: 220px;">
                    @if ($frame->template_path && file_exists(storage_path('app/public/' . $frame->template_path)))
                        <img src="{{ asset('storage/' . $frame->template_path) }}" class="w-100 h-100 object-fit-contain" alt="{{ $frame->name }}">
                    @else
                        <div class="d-flex align-items-center justify-content-center h-100 text-secondary">
                            <i class="fa-regular fa-image fs-1"></i>
                        </div>
                    @endif

                    <div class="position-absolute top-0 start-0 m-2">
                        <span class="badge bg-warning text-dark fw-bold">{{ $frame->category }}</span>
                    </div>

                    <div class="position-absolute top-0 end-0 m-2">
                        <span class="badge bg-dark bg-opacity-75 text-white border border-secondary">
                            {{ $frame->total_slots }} Slots
                        </span>
                    </div>
                </div>

                <h6 class="fw-bold text-white mb-1 text-truncate" title="{{ $frame->name }}">{{ $frame->name }}</h6>
                <div class="d-flex justify-content-between align-items-center small mb-3">
                    <span class="text-secondary">Price:</span>
                    <span class="fw-bold text-warning">₹{{ number_format($frame->effective_price, 2) }}</span>
                </div>

                <div class="mt-auto pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                    <form action="{{ route('admin.frames.toggle', $frame) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm {{ $frame->is_active ? 'btn-success-subtle text-success' : 'btn-secondary-subtle text-secondary' }} rounded-pill" style="font-size:0.75rem;">
                            {{ $frame->is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </form>

                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.frames.edit', $frame) }}" class="btn btn-sm btn-outline-light rounded-circle" title="Edit Template & Slots" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <form action="{{ route('admin.frames.destroy', $frame) }}" method="POST" onsubmit="return confirm('Delete this template?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Delete Template" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;">
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
            <p>No templates found. Click "Upload New Template" to add one.</p>
        </div>
    @endforelse
</div>

{{ $frames->links('pagination::bootstrap-5') }}
@endsection
