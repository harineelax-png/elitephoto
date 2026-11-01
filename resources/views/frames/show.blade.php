@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Frame: {{ $frame->name }}</h1>
    <p><strong>Total Slots:</strong> {{ $frame->total_slots }}</p>
    <p><strong>Images:</strong> {{ $frame->images->count() }}</p>

    <a href="{{ route('frames.index') }}" class="btn btn-secondary mb-3">Back to Frames</a>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('info')) <div class="alert alert-info">{{ session('info') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

    @if($frame->template_path && !empty($frame->slot_positions))
    <div class="card mb-4">
        <div class="card-header">Preview & Generate</div>
        <div class="card-body">
            <canvas id="previewCanvas" style="max-width:100%; border:1px solid #ddd;"></canvas>
            <div class="mt-3">
                <a href="{{ route('frames.generate', $frame) }}" class="btn btn-success">Generate Final Image</a>
            </div>
        </div>
    </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">Upload Multiple Images (Auto-placed into free slots)</div>
        <div class="card-body">
            <form action="{{ route('frames.images.store', $frame) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <input type="file" name="images[]" class="form-control" accept="image/*" multiple required>
                    <small class="text-muted">You can upload up to {{ $frame->total_slots - $frame->images->count() }} images.</small>
                </div>
                <button type="submit" class="btn btn-primary">Upload & Place</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Images in this Frame</div>
        <div class="card-body">
            @if ($frame->images->isEmpty())
                <p>No images uploaded yet.</p>
            @else
                <table class="table table-bordered">
                    <thead>
                        <tr><th>#</th><th>Image</th><th>Position</th><th>Change Position</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($frame->images as $image)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><img src="{{ asset('storage/' . $image->image_path) }}" alt="Image" style="max-height: 80px;"></td>
                                <td>{{ $image->position }}</td>
                                <td>
                                    <form action="{{ route('frames.images.updatePosition', [$frame, $image]) }}" method="POST" class="d-flex">
                                        @csrf @method('PATCH')
                                        <input type="number" name="position" value="{{ $image->position }}" min="1" max="{{ $frame->total_slots }}" class="form-control form-control-sm w-50 me-2">
                                        <button type="submit" class="btn btn-sm btn-primary">Move</button>
                                    </form>
                                </td>
                                <td>
                                    <form action="{{ route('frames.images.destroy', [$frame, $image]) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this image?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>

<script>
(function () {
    const canvas = document.getElementById('previewCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    const templateUrl = @json($frame->template_path ? asset('storage/' . $frame->template_path) : null);
    const naturalWidth = {{ (int) ($frame->template_width ?? 0) }};
    const naturalHeight = {{ (int) ($frame->template_height ?? 0) }};
    const slotRects = @json(collect($frame->slot_positions ?? [])->keys()->map(fn ($i) => $frame->slotRect($i))->values());
    const placedImages = @json($frame->images->mapWithKeys(fn ($img) => [$img->position => asset('storage/' . $img->image_path)]));

    if (!templateUrl || !naturalWidth) return;

    const maxDisplayWidth = 700;
    const scale = naturalWidth > maxDisplayWidth ? maxDisplayWidth / naturalWidth : 1;
    canvas.width = naturalWidth * scale;
    canvas.height = naturalHeight * scale;

    const templateImg = new Image();
    templateImg.crossOrigin = 'anonymous';
    templateImg.onload = draw;
    templateImg.src = templateUrl;

    const loadedUserImgs = {};

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(templateImg, 0, 0, canvas.width, canvas.height);

        slotRects.forEach((rect, idx) => {
            if (!rect) return;
            const position = idx + 1;
            const url = placedImages[position];
            if (!url) return;

            const dx = rect.x * scale, dy = rect.y * scale, dw = rect.width * scale, dh = rect.height * scale;

            if (loadedUserImgs[url] && loadedUserImgs[url].complete) {
                drawShaped(loadedUserImgs[url], dx, dy, dw, dh, rect.shape, rect.rotation || 0);
            } else if (!loadedUserImgs[url]) {
                const im = new Image();
                im.crossOrigin = 'anonymous';
                im.onload = draw;
                loadedUserImgs[url] = im;
                im.src = url;
            }
        });
    }

    function drawShaped(image, dx, dy, dw, dh, shape, rotationDeg) {
        ctx.save();
        ctx.translate(dx + dw / 2, dy + dh / 2);
        ctx.rotate((rotationDeg * Math.PI) / 180);

        if (shape === 'circle' || shape === 'ellipse') {
            ctx.beginPath();
            ctx.ellipse(0, 0, dw / 2, dh / 2, 0, 0, Math.PI * 2);
            ctx.clip();
        }

        const srcRatio = image.naturalWidth / image.naturalHeight;
        const destRatio = dw / dh;
        let sx, sy, sw, sh;
        if (srcRatio > destRatio) {
            sh = image.naturalHeight; sw = sh * destRatio;
            sx = (image.naturalWidth - sw) / 2; sy = 0;
        } else {
            sw = image.naturalWidth; sh = sw / destRatio;
            sx = 0; sy = (image.naturalHeight - sh) / 2;
        }
        ctx.drawImage(image, sx, sy, sw, sh, -dw / 2, -dh / 2, dw, dh);
        ctx.restore();
    }
})();
</script>
@endsection