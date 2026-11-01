@extends('layouts.admin')

@section('title', 'Customer Contact Inquiries')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">Customer Inquiries</h2>
        <p class="text-secondary small mb-0">Review incoming questions and booking inquiries submitted via the Contact Us page.</p>
    </div>
    <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
        &larr; Back to CMS Pages
    </a>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-custom border-secondary align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Phone / Email</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($inquiries as $inq)
                    <tr>
                        <td class="text-secondary small" style="white-space: nowrap;">
                            {{ $inq->created_at->format('d M Y, h:i A') }}
                        </td>
                        <td>
                            <strong class="text-white">{{ $inq->name }}</strong>
                        </td>
                        <td class="small">
                            <div><i class="fa-solid fa-envelope text-warning me-1"></i> <a href="mailto:{{ $inq->email }}" class="text-light text-decoration-none">{{ $inq->email }}</a></div>
                            @if ($inq->phone)
                                <div class="text-secondary"><i class="fa-solid fa-phone text-warning me-1"></i> {{ $inq->phone }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-dark border border-secondary text-light">{{ $inq->subject ?: 'General Inquiry' }}</span>
                        </td>
                        <td class="small text-secondary" style="max-width: 320px;">
                            {{ Str::limit($inq->message, 120) }}
                        </td>
                        <td>
                            @if ($inq->status === 'new')
                                <span class="badge bg-danger rounded-pill">NEW</span>
                            @else
                                <span class="badge bg-secondary rounded-pill">READ</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <form action="{{ route('admin.inquiries.toggle', $inq) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning btn-sm rounded-pill" title="Toggle Status">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.inquiries.destroy', $inq) }}" method="POST" onsubmit="return confirm('Delete this inquiry?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-secondary">
                            <i class="fa-regular fa-envelope-open fs-2 mb-2 d-block opacity-50"></i>
                            No inquiries received yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($inquiries->hasPages())
        <div class="mt-4">
            {{ $inquiries->links() }}
        </div>
    @endif
</div>
@endsection
