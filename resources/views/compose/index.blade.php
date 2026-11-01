@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Compose a Frame</h1>
    <p class="text-muted">Select a frame, upload your images, and we will automatically resize/crop them to perfectly fit the slots without overflow. Supports: JPG, PNG, GIF, WebP, BMP.</p>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('compose.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="frame_id" class="form-label">Choose a Frame</label>
                    <select name="frame_id" id="frame_id" class="form-select" required>
                        <option value="">-- Select Frame --</option>
                        @foreach ($frames as $frame)
                            <option value="{{ $frame->id }}" {{ old('frame_id') == $frame->id ? 'selected' : '' }}>
                                {{ $frame->name }} ({{ $frame->images_count }}/{{ $frame->total_slots }} filled)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label for="images" class="form-label">Upload Images</label>
                    <input type="file" name="images[]" id="images" class="form-control" accept="image/*" multiple required>
                    <small class="text-muted">
                        All uploaded images will be automatically resized to exactly match the frame's slot dimensions.
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">Generate Composite</button>
            </form>
        </div>
    </div>
</div>
@endsection