@extends('layouts.main')

@section('title', 'Interactive Frame Studio | Custom Framing & HD Generation')

@section('content')
<div class="container py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 small text-secondary mb-1">
                <a href="{{ route('home') }}" class="text-secondary text-decoration-none hover-gold"><i class="fa-solid fa-house"></i> Home</a>
                <span>/</span>
                <span class="text-warning">Interactive Frame Studio</span>
            </div>
            <h2 class="h3 fw-bold text-white mb-0">Personalized Photo Framer</h2>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- <span class="badge bg-dark border border-secondary text-secondary py-2 px-3 rounded-pill">
                <i class="fa-solid fa-shield-halved text-success me-1"></i> Razorpay Test Checkout
            </span> -->
            <span class="badge bg-warning text-dark py-2 px-3 rounded-pill fw-bold">
                Price: {{ $currencySymbol }}{{ number_format($frame ? $frame->effective_price : (float)$defaultPrice, 2) }}
            </span>
        </div>
    </div>

    @if (!$frame)
        <div class="alert alert-warning text-center py-5">
            <h5>No Active Frame Selected</h5>
            <p>Please select a template from the gallery to begin.</p>
            <a href="{{ route('home') }}" class="btn btn-warning rounded-pill">Browse Templates</a>
        </div>
    @else
        <div class="row g-4">
            <!-- Left Controls Column -->
            <div class="col-lg-5 col-xl-4">
                <div class="glass-card p-3 p-sm-4">
                    <!-- Template Switcher -->
                    <div class="mb-4 pb-3 border-bottom border-secondary border-opacity-25">
                        <label class="form-label text-white small fw-bold d-flex justify-content-between">
                            <span>1. Selected Frame Theme</span>
                            <span class="text-warning">{{ $frame->total_slots }} Slots</span>
                        </label>
                        <select id="templateSelector" class="form-select bg-dark text-white border-secondary mb-2" onchange="window.location.href='{{ url('/studio') }}/' + this.value">
                            @foreach ($allFrames as $f)
                                <option value="{{ $f->id }}" {{ $f->id == $frame->id ? 'selected' : '' }}>
                                    {{ $f->name }} ({{ $f->total_slots }} Slots • {{ $f->category }})
                                </option>
                            @endforeach
                        </select>
                        <div class="d-flex align-items-center gap-2 p-2 rounded bg-black bg-opacity-40 border border-secondary border-opacity-25">
                            <img src="{{ asset('storage/' . $frame->template_path) }}" class="rounded" style="width: 50px; height: 60px; object-fit: contain;">
                            <div class="small">
                                <div class="text-white fw-bold text-truncate" style="max-width: 200px;">{{ $frame->name }}</div>
                                <span class="badge bg-warning text-dark" style="font-size: 0.68rem;">{{ $frame->category }}</span>
                                <span class="text-secondary" style="font-size: 0.72rem;">{{ $currencySymbol }}{{ number_format($frame->effective_price, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Slot Uploaders Form -->
                    <form id="studioForm" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="frame_id" id="frameId" value="{{ $frame->id }}">

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="bulkPhotos" class="form-label text-white small fw-bold mb-0">2. Upload Photos</label>
                                <span class="small text-secondary" style="font-size:0.75rem;">Up to {{ $frame->total_slots }} images</span>
                            </div>
                            <input type="file" id="bulkPhotos" class="form-control form-control-sm bg-dark text-white border-secondary mb-2" accept="image/jpeg,image/png,image/webp,image/bmp,image/gif" multiple>
                            <input type="file" id="slotPhotoInput" accept="image/jpeg,image/png,image/webp,image/bmp,image/gif" hidden>
                            <div class="form-text text-secondary mb-3" style="font-size:0.75rem;">Select up to {{ $frame->total_slots }} photos. They’ll fill each opening in upload order and be converted to PNG.</div>
                            <div class="form-text text-secondary mb-3" style="font-size:0.75rem;">Use each opening’s remove or upload control. Drag a photo to move or swap it, or tap one photo then another opening.</div>

                            <div class="mb-3">
                                <label for="placementStartSlot" class="form-label text-white small fw-bold mb-1">Start placing at</label>
                                <select id="placementStartSlot" class="form-select form-select-sm bg-dark text-white border-secondary">
                                    @for ($i = 0; $i < $frame->total_slots; $i++)
                                        <option value="{{ $i }}">Slot #{{ $i + 1 }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="d-flex justify-content-between align-items-center small text-secondary">
                                <span id="selectedPhotosStatus">No photos selected</span>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 d-none" id="clearPhotosBtn">Clear photos</button>
                            </div>
                        </div>

                        <!-- Custom Ribbon Text & Calendar Options -->
                        <div class="mb-4 pb-3 border-bottom border-secondary border-opacity-25">
                            <label class="form-label text-white small fw-bold mb-2">3. Personalization & Date</label>

                            <div class="mb-3">
                                <label class="text-secondary small d-block mb-1">Ribbon Banner Text (Optional)</label>
                                <input type="text" name="banner_text" id="bannerText" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="e.g. Happy Birthday / Happy Married Life" maxlength="80">
                            </div>

                            <div class="mb-2">
                                <label class="text-secondary small d-block mb-1">Special Date / DOB / Anniversary (Calendar Frame)</label>
                                <input type="date" name="calendar_date" id="calendarDate" class="form-control form-control-sm bg-dark text-white border-secondary">
                                <div class="form-text text-secondary" style="font-size:0.75rem;">Select complete date (DD-MM-YYYY). The calendar grid will automatically populate month days and highlight your special date with a heart marker!</div>
                            </div>
                        </div>

                        <!-- Service Notice: Digital HD Download -->
                        <div class="p-2 mb-3 rounded bg-dark border border-secondary border-opacity-25 text-secondary small" style="font-size:0.75rem;">
                            <i class="fa-solid fa-cloud-arrow-down text-warning me-1"></i>
                            <strong>Digital HD Download:</strong> Payment is strictly for instant high-definition image generation and print-ready HD file download (no physical delivery).
                        </div>

                        <!-- Generate Preview Button -->
                        <button type="button" id="generatePreviewBtn" class="btn btn-outline-warning w-100 fw-bold py-2 rounded-pill mb-2">
                            <i class="fa-solid fa-eye me-1"></i> Step 1: Generate Watermark Preview
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Preview & Razorpay Checkout Column -->
            <div class="col-lg-7 col-xl-8">
                <div class="glass-card p-3 p-sm-4 h-100 d-flex flex-column">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                        <div>
                            <h5 class="fw-bold text-white mb-0">Live Frame Canvas Preview</h5>
                            <span class="text-secondary small">Your photos appear here as you choose them. Generate a preview to continue.</span>
                        </div>
                        <div id="previewStatusBadge">
                            <span class="badge bg-secondary rounded-pill py-2 px-3">
                                <i class="fa-solid fa-info-circle me-1"></i> Awaiting Photos
                            </span>
                        </div>
                    </div>

                    <!-- Canvas / Preview Image Box -->
                    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-2 rounded-3 bg-black position-relative overflow-hidden" style="min-height: 480px;">
                        <!-- Loading Spinner Overlay -->
                        <div id="previewSpinner" class="position-absolute top-0 start-0 w-100 h-100 bg-black bg-opacity-75 d-flex flex-column align-items-center justify-content-center d-none z-3">
                            <div class="spinner-border text-warning mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                            <h6 class="text-white fw-bold mb-1">Preparing Your Preview...</h6>
                            <p class="small text-secondary mb-0" id="previewProgressText">Uploading photos securely</p>
                        </div>

                        <!-- Active Preview Image -->
                        <div id="previewImageStage" class="position-relative">
                            <img id="mainPreviewImg" src="{{ asset('storage/' . $frame->template_path) }}" class="img-fluid rounded shadow-lg d-block" style="width: 100%; height: auto;" alt="Frame Live Preview">
                            <div id="slotControls" class="position-absolute top-0 start-0 w-100 h-100" aria-label="Frame photo slots"></div>
                        </div>
                        <div id="previewHint" class="position-absolute bottom-0 start-0 end-0 p-3 text-center small fw-semibold text-white bg-black bg-opacity-75 d-none">
                            Live draft preview. Generate a watermarked preview to continue.
                        </div>
                    </div>

                    <!-- Checkout & Action Bar -->
                    <div class="mt-3 p-3 rounded-4 bg-dark border border-secondary">
                        <div class="row align-items-center g-3">
                            <div class="col-sm-6 text-center text-sm-start">
                                <div class="text-secondary small">Total for High-Definition Frame:</div>
                                <div class="d-flex align-items-baseline gap-2">
                                    <span class="fs-3 fw-bold text-warning">{{ $currencySymbol }}{{ number_format($frame->effective_price, 2) }}</span>
                                    <span class="text-secondary small">/ single generation</span>
                                </div>
                                <div style="font-size:0.75rem;" class="text-secondary">
                                    <i class="fa-solid fa-shield-check text-success me-1"></i> Watermark removed instantly upon payment
                                </div>
                            </div>

                            <div class="col-sm-6 text-center text-sm-end">
                                <!-- Big Razorpay Pay Button -->
                                <button type="button" id="payAndDownloadBtn" class="btn btn-gold btn-lg w-100 w-sm-auto rounded-pill px-4 shadow-lg fw-bold" disabled>
                                    <i class="fa-solid fa-lock me-2"></i> Pay & Download HD Photo
                                </button>
                                <div class="text-secondary mt-1" style="font-size:0.7rem;">
                                    Secured by Razorpay • UPI / Cards / NetBanking
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
@php
    // Slot rectangles for the live preview. Heart / polygon slots also carry their detected outline
    // ("points", normalised 0..1 inside the slot's bounding box).
    $slotPositions = [];
    if ($frame) {
        $rawSlots = $frame->slot_positions ?? [];
        foreach (array_keys($rawSlots) as $slotIndex) {
            $rect = $frame->slotRect($slotIndex) ?? [];
            $outline = data_get($rawSlots, $slotIndex . '.points');
            $slotShape = $rect['shape'] ?? data_get($rawSlots, $slotIndex . '.shape');
            if (!empty($outline) && in_array($slotShape, ['heart', 'polygon'], true)) {
                $rect['points'] = $outline;
            }
            $slotPositions[] = $rect;
        }
    }
@endphp
<style>
    #slotControls {
        z-index: 2;
        pointer-events: none;
    }

    #slotControls.d-none {
        display: none !important;
    }

    .frame-slot-control {
        position: absolute;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px dashed rgba(255, 255, 255, 0.65);
        border-radius: 4px;
        pointer-events: auto;
        touch-action: none;
    }

    .frame-slot-target {
        width: 100%;
        height: 100%;
        padding: 0;
        border: 0;
        border-radius: inherit;
        color: white;
        background: transparent;
        font-size: clamp(8px, 1vw, 13px);
        overflow: hidden;
        cursor: grab;
    }

    .frame-slot-control.is-empty .frame-slot-target {
        background: rgba(0, 0, 0, 0.38);
        border: 1px dashed rgba(255, 255, 255, 0.9);
        cursor: pointer;
    }

    .frame-slot-target:active {
        cursor: grabbing;
    }

    .frame-slot-control.is-selected {
        outline: 3px solid #fbbf24;
        outline-offset: 2px;
        z-index: 3;
    }

    .frame-slot-outline {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        overflow: visible;
        pointer-events: none;
    }

    .frame-slot-outline polygon {
        fill: none;
        stroke: rgba(255, 255, 255, 0.85);
        stroke-width: 1.5;
        stroke-dasharray: 5 4;
        vector-effect: non-scaling-stroke;
    }

    .frame-slot-control.is-selected .frame-slot-outline polygon {
        stroke: #fbbf24;
        stroke-width: 3;
        stroke-dasharray: none;
    }

    .frame-slot-number {
        position: absolute;
        top: 2px;
        left: 2px;
        z-index: 1;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 9px;
        color: #fff;
        background: rgba(185, 28, 28, 0.92);
        border: 1px solid rgba(255, 255, 255, 0.9);
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
        pointer-events: none;
    }

    .frame-slot-control:not(.is-empty) .frame-slot-target { touch-action: none; user-select: none; -webkit-user-select: none; }

    .frame-slot-tools {
        position: absolute;
        left: 50%;
        bottom: 3px;
        transform: translateX(-50%);
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 2px;
        padding: 2px 3px;
        border-radius: 14px;
        background: rgba(0, 0, 0, 0.62);
        opacity: 0;
        transition: opacity .15s;
    }

    .frame-slot-control:hover .frame-slot-tools,
    .frame-slot-control.is-selected .frame-slot-tools,
    .frame-slot-control:focus-within .frame-slot-tools { opacity: 1; }

    @media (hover: none) { .frame-slot-tools { opacity: 1; } }

    .frame-slot-tools button {
        width: 20px;
        height: 20px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 50%;
        color: #fff;
        background: rgba(255, 255, 255, 0.18);
        font-size: 10px;
        line-height: 1;
        cursor: pointer;
    }

    .frame-slot-tools button:hover { background: rgba(251, 191, 36, 0.85); color: #000; }
    .frame-slot-zoom { min-width: 28px; text-align: center; color: #fff; font-size: 9px; font-weight: 700; }

    .frame-slot-swap {
        position: absolute;
        left: 2px;
        bottom: 3px;
        z-index: 2;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        color: #fff;
        background: rgba(0, 0, 0, 0.62);
        font-size: 10px;
        cursor: grab;
        opacity: 0;
        transition: opacity .15s;
    }

    .frame-slot-control:hover .frame-slot-swap,
    .frame-slot-control.is-selected .frame-slot-swap { opacity: 1; }
    @media (hover: none) { .frame-slot-swap { opacity: 1; } }

    .frame-slot-remove {
        position: absolute;
        top: 2px;
        right: 2px;
        z-index: 1;
        width: 25px;
        height: 25px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 50%;
        color: white;
        background: #dc3545;
        font-size: 12px;
        line-height: 1;
        cursor: pointer;
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const frameId = document.getElementById('frameId')?.value;
    if (!frameId) return;

    const generatePreviewBtn = document.getElementById('generatePreviewBtn');
    const payAndDownloadBtn = document.getElementById('payAndDownloadBtn');
    const previewSpinner = document.getElementById('previewSpinner');
    const mainPreviewImg = document.getElementById('mainPreviewImg');
    const previewStatusBadge = document.getElementById('previewStatusBadge');
    const bulkPhotos = document.getElementById('bulkPhotos');
    const placementStartSlot = document.getElementById('placementStartSlot');
    const selectedPhotosStatus = document.getElementById('selectedPhotosStatus');
    const clearPhotosBtn = document.getElementById('clearPhotosBtn');
    const previewProgressText = document.getElementById('previewProgressText');
    const previewHint = document.getElementById('previewHint');
    const previewImageStage = document.getElementById('previewImageStage');
    const slotControls = document.getElementById('slotControls');
    const slotPhotoInput = document.getElementById('slotPhotoInput');
    const slotCount = {{ (int) ($frame?->total_slots ?? 0) }};
    const slotFiles = Array(slotCount).fill(null);
    const fileUrls = Array(slotCount).fill(null);
    const slotPositions = @json($slotPositions);
    const templateUrl = @json($frame ? asset('storage/' . $frame->template_path) : '');
    const templateImage = new Image();
    templateImage.src = templateUrl;
    // zoom / position of the photo inside each slot: zoom 1 = fills the slot, x / y = photo offset in slot widths / heights
    const slotTransforms = Array(slotCount).fill(null);
    const imageCache = new Map();
    let gestureActive = false;
    let draftTimer = null;
    let selectedFiles = [];
    let previewRevision = 0;
    let pendingMoveSlot = null;
    let activeUploadSlot = null;

    let isPreviewGenerated = false;
    let currentDownloadToken = null;
    let currentOrderNumber = null;

    function updatePreviewState(redraw = true) {
        if (redraw) previewRevision++;
        isPreviewGenerated = false;
        payAndDownloadBtn.disabled = true;
        if (slotFiles.some(Boolean)) {
            slotControls.classList.remove('d-none');
            previewHint.classList.remove('d-none');
            previewStatusBadge.innerHTML = '<span class="badge bg-info text-dark rounded-pill py-2 px-3"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Draft Preview</span>';
            if (redraw) renderDraftPreview(previewRevision);
        } else {
            slotControls.classList.remove('d-none');
            mainPreviewImg.src = templateUrl;
            previewHint.classList.add('d-none');
            previewStatusBadge.innerHTML = '<span class="badge bg-secondary rounded-pill py-2 px-3"><i class="fa-solid fa-info-circle me-1"></i> Awaiting Photos</span>';
        }
    }

    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error('A selected photo could not be previewed.'));
            image.src = src;
        });
    }

    function hasOutline(slot) {
        return Array.isArray(slot.points) && slot.points.length >= 3 && (slot.shape === 'heart' || slot.shape === 'polygon');
    }

    function getTransform(index) {
        return slotTransforms[index] || { zoom: 1, x: 0, y: 0 };
    }

    function setTransform(index, next) {
        const zoom = Math.max(0.3, Math.min(5, next.zoom));
        const x = Math.max(-1.5, Math.min(1.5, next.x));
        const y = Math.max(-1.5, Math.min(1.5, next.y));
        const isDefault = Math.abs(zoom - 1) < 0.001 && Math.abs(x) < 0.001 && Math.abs(y) < 0.001;
        slotTransforms[index] = isDefault ? null : { zoom, x, y };
    }

    function scheduleDraft() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(() => updatePreviewState(), 70);
    }

    function loadImageCached(url) {
        if (!imageCache.has(url)) {
            const promise = loadImage(url);
            promise.catch(() => imageCache.delete(url));
            imageCache.set(url, promise);
        }
        return imageCache.get(url);
    }

    // Draws the photo centred on (0,0) of a width x height slot with the customer's zoom / position.
    // Slot areas the photo does not reach get a soft, blurred wash of the photo's own colours.
    function drawPhoto(context, image, width, height, transform) {
        const t = transform || { zoom: 1, x: 0, y: 0 };
        const cover = Math.max(width / image.naturalWidth, height / image.naturalHeight);
        const drawW = image.naturalWidth * cover * t.zoom;
        const drawH = image.naturalHeight * cover * t.zoom;
        const left = t.x * width - drawW / 2;
        const top = t.y * height - drawH / 2;
        const covers = left <= -width / 2 + 0.5 && top <= -height / 2 + 0.5
            && left + drawW >= width / 2 - 0.5 && top + drawH >= height / 2 - 0.5;

        if (!covers) {
            const tw = Math.max(2, Math.round(width / 28));
            const th = Math.max(2, Math.round(height / 28));
            const tiny = document.createElement('canvas');
            tiny.width = tw;
            tiny.height = th;
            const tctx = tiny.getContext('2d');
            const cropW = width / cover, cropH = height / cover;
            tctx.drawImage(image, (image.naturalWidth - cropW) / 2, (image.naturalHeight - cropH) / 2, cropW, cropH, 0, 0, tw, th);
            context.save();
            context.imageSmoothingEnabled = true;
            context.imageSmoothingQuality = 'high';
            context.drawImage(tiny, -width / 2, -height / 2, width, height);
            context.fillStyle = 'rgba(255,255,255,0.17)';
            context.fillRect(-width / 2, -height / 2, width, height);
            context.restore();
        }
        context.drawImage(image, left, top, drawW, drawH);
    }

    function syncSelectedFiles() {
        selectedFiles = slotFiles.filter(Boolean);
        const selectedCount = selectedFiles.length;
        selectedPhotosStatus.textContent = `${selectedCount} of ${slotCount} photos selected`;
        clearPhotosBtn.classList.toggle('d-none', selectedCount === 0);
    }

    function refreshSlotControls() {
        if (gestureActive) return;
        const width = templateImage.naturalWidth;
        const height = templateImage.naturalHeight;
        if (!width || !height) return;

        const maxWidth = previewImageStage.parentElement.clientWidth;
        previewImageStage.style.width = `${Math.min(maxWidth, 600 * width / height)}px`;
        previewImageStage.style.aspectRatio = `${width} / ${height}`;
        slotControls.replaceChildren();
        slotPositions.forEach((slot, index) => {
            const control = document.createElement('div');
            control.className = `frame-slot-control${slotFiles[index] ? '' : ' is-empty'}`;
            control.dataset.slot = String(index);
            control.style.left = `${Number(slot.x) / width * 100}%`;
            control.style.top = `${Number(slot.y) / height * 100}%`;
            control.style.width = `${Number(slot.width) / width * 100}%`;
            control.style.height = `${Number(slot.height) / height * 100}%`;
            control.style.transform = `rotate(${Number(slot.rotation) || 0}deg)`;
            if (slot.shape === 'circle' || slot.shape === 'ellipse') {
                control.style.borderRadius = '50%';
            } else if (slot.shape === 'rounded_rectangle') {
                control.style.borderRadius = '12px';
            }
            const outline = hasOutline(slot) ? slot.points : null;
            if (outline) {
                control.style.border = '0';
                control.style.borderRadius = '0';
            }

            const target = document.createElement('button');
            target.type = 'button';
            target.className = 'frame-slot-target';
            target.draggable = false;
            target.setAttribute('aria-label', slotFiles[index] ? `Move or replace photo in slot ${index + 1}` : `Upload photo to slot ${index + 1}`);
            target.innerHTML = slotFiles[index] ? '' : '<i class="fa-solid fa-plus"></i> Upload';
            let suppressClick = false;
            target.addEventListener('click', () => {
                if (suppressClick) { suppressClick = false; return; }
                handleSlotClick(index);
            });
            if (slotFiles[index]) {
                attachPhotoAdjust(target, control, index, () => { suppressClick = true; });
            }
            control.addEventListener('dragover', event => {
                if (pendingMoveSlot !== null && pendingMoveSlot !== index) event.preventDefault();
            });
            control.addEventListener('drop', event => {
                event.preventDefault();
                const from = Number(event.dataTransfer.getData('text/plain'));
                if (Number.isInteger(from) && from >= 0 && from < slotCount && from !== index) {
                    movePhoto(from, index);
                }
            });
            control.appendChild(target);

            if (outline) {
                // the clickable area follows the heart / polygon, and the dashed outline is drawn as SVG
                const clip = 'polygon(' + outline.map(p => (p[0] * 100).toFixed(2) + '% ' + (p[1] * 100).toFixed(2) + '%').join(',') + ')';
                target.style.clipPath = clip;
                target.style.webkitClipPath = clip;

                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('class', 'frame-slot-outline');
                svg.setAttribute('viewBox', '0 0 1 1');
                svg.setAttribute('preserveAspectRatio', 'none');
                const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                polygon.setAttribute('points', outline.map(p => p[0] + ',' + p[1]).join(' '));
                svg.appendChild(polygon);
                control.insertBefore(svg, target);
            }

            if (slotFiles[index]) {
                // drag this handle onto another opening to swap photos
                const swap = document.createElement('span');
                swap.className = 'frame-slot-swap';
                swap.draggable = true;
                swap.title = 'Drag to another opening to swap photos';
                swap.innerHTML = '<i class="fa-solid fa-arrows-up-down-left-right"></i>';
                swap.addEventListener('dragstart', event => {
                    event.dataTransfer.setData('text/plain', String(index));
                    event.dataTransfer.effectAllowed = 'move';
                    pendingMoveSlot = index;
                    control.classList.add('is-selected');
                });
                swap.addEventListener('dragend', () => {
                    pendingMoveSlot = null;
                    refreshSlotControls();
                });
                control.appendChild(swap);

                const tools = document.createElement('div');
                tools.className = 'frame-slot-tools';
                const zoomLabel = document.createElement('span');
                zoomLabel.className = 'frame-slot-zoom';
                zoomLabel.textContent = Math.round(getTransform(index).zoom * 100) + '%';
                const makeTool = (icon, label, handler) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.title = label;
                    button.setAttribute('aria-label', `${label} (slot ${index + 1})`);
                    button.innerHTML = `<i class="fa-solid ${icon}"></i>`;
                    button.addEventListener('click', event => { event.stopPropagation(); handler(); });
                    return button;
                };
                tools.append(
                    makeTool('fa-magnifying-glass-minus', 'Zoom out', () => zoomSlot(index, 1 / 1.15)),
                    zoomLabel,
                    makeTool('fa-magnifying-glass-plus', 'Zoom in', () => zoomSlot(index, 1.15)),
                    makeTool('fa-arrow-rotate-left', 'Reset zoom and position', () => { setTransform(index, { zoom: 1, x: 0, y: 0 }); refreshSlotControls(); scheduleDraft(); })
                );
                control.appendChild(tools);
            }

            const numberBadge = document.createElement('span');
            numberBadge.className = 'frame-slot-number';
            numberBadge.textContent = String(index + 1);
            control.appendChild(numberBadge);

            if (slotFiles[index]) {
                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'frame-slot-remove';
                removeButton.setAttribute('aria-label', `Remove photo from slot ${index + 1}`);
                removeButton.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                removeButton.addEventListener('click', event => {
                    event.stopPropagation();
                    removePhoto(index);
                });
                control.appendChild(removeButton);
            }
            slotControls.appendChild(control);
        });

        if (pendingMoveSlot !== null) {
            slotControls.querySelector(`[data-slot="${pendingMoveSlot}"]`)?.classList.add('is-selected');
        }
    }

    function zoomSlot(index, factor) {
        const t = getTransform(index);
        setTransform(index, { zoom: t.zoom * factor, x: t.x * factor, y: t.y * factor });
        const label = slotControls.querySelector(`[data-slot="${index}"] .frame-slot-zoom`);
        if (label) label.textContent = Math.round(getTransform(index).zoom * 100) + '%';
        scheduleDraft();
    }

    // drag inside a filled opening = move the photo, wheel / pinch = zoom
    function attachPhotoAdjust(target, control, index, markMoved) {
        const pointers = new Map();
        let travelled = 0;
        let lastPinch = 0;

        const pinchDistance = () => {
            const [a, b] = Array.from(pointers.values());
            return Math.hypot(a.x - b.x, a.y - b.y);
        };

        target.addEventListener('pointerdown', event => {
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            target.setPointerCapture(event.pointerId);
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            gestureActive = true;
            travelled = 0;
            if (pointers.size === 2) lastPinch = pinchDistance();
        });

        target.addEventListener('pointermove', event => {
            const previous = pointers.get(event.pointerId);
            if (!previous) return;
            const current = { x: event.clientX, y: event.clientY };
            pointers.set(event.pointerId, current);

            if (pointers.size >= 2) {
                const distance = pinchDistance();
                if (lastPinch > 0 && distance > 0) {
                    const t = getTransform(index);
                    const factor = distance / lastPinch;
                    setTransform(index, { zoom: t.zoom * factor, x: t.x * factor, y: t.y * factor });
                    travelled += 10;
                    scheduleDraft();
                }
                lastPinch = distance;
                return;
            }

            let dx = current.x - previous.x;
            let dy = current.y - previous.y;
            travelled += Math.abs(dx) + Math.abs(dy);
            if (travelled < 4) return;
            const rotation = -(Number(slotPositions[index]?.rotation) || 0) * Math.PI / 180;
            const rx = dx * Math.cos(rotation) - dy * Math.sin(rotation);
            const ry = dx * Math.sin(rotation) + dy * Math.cos(rotation);
            const t = getTransform(index);
            setTransform(index, { zoom: t.zoom, x: t.x + rx / (control.offsetWidth || 1), y: t.y + ry / (control.offsetHeight || 1) });
            scheduleDraft();
        });

        const finish = event => {
            if (!pointers.has(event.pointerId)) return;
            pointers.delete(event.pointerId);
            lastPinch = 0;
            if (pointers.size === 0) {
                gestureActive = false;
                if (travelled >= 4) {
                    markMoved();
                    const label = control.querySelector('.frame-slot-zoom');
                    if (label) label.textContent = Math.round(getTransform(index).zoom * 100) + '%';
                }
            }
        };
        target.addEventListener('pointerup', finish);
        target.addEventListener('pointercancel', finish);

        target.addEventListener('wheel', event => {
            event.preventDefault();
            zoomSlot(index, event.deltaY < 0 ? 1.08 : 1 / 1.08);
        }, { passive: false });
    }

    function handleSlotClick(index) {
        if (pendingMoveSlot !== null) {
            if (pendingMoveSlot !== index) movePhoto(pendingMoveSlot, index);
            pendingMoveSlot = null;
            refreshSlotControls();
            return;
        }

        if (slotFiles[index]) {
            pendingMoveSlot = index;
            refreshSlotControls();
            return;
        }

        activeUploadSlot = index;
        slotPhotoInput.value = '';
        slotPhotoInput.click();
    }

    function movePhoto(from, to) {
        [slotFiles[from], slotFiles[to]] = [slotFiles[to], slotFiles[from]];
        [fileUrls[from], fileUrls[to]] = [fileUrls[to], fileUrls[from]];
        [slotTransforms[from], slotTransforms[to]] = [slotTransforms[to], slotTransforms[from]];
        syncSelectedFiles();
        pendingMoveSlot = null;
        refreshSlotControls();
        updatePreviewState();
    }

    function removePhoto(index) {
        if (fileUrls[index]) URL.revokeObjectURL(fileUrls[index]);
        if (slotFiles[index]) preparedUploads.delete(slotFiles[index]);
        slotFiles[index] = null;
        fileUrls[index] = null;
        slotTransforms[index] = null;
        pendingMoveSlot = null;
        syncSelectedFiles();
        refreshSlotControls();
        updatePreviewState();
    }

    function replacePhoto(index, file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire('Unsupported photo', 'Choose a JPG, PNG, WebP, BMP, or GIF photo.', 'warning');
            return;
        }
        if (fileUrls[index]) URL.revokeObjectURL(fileUrls[index]);
        if (slotFiles[index]) preparedUploads.delete(slotFiles[index]);
        slotFiles[index] = file;
        fileUrls[index] = URL.createObjectURL(file);
        slotTransforms[index] = null;
        pendingMoveSlot = null;
        syncSelectedFiles();
        refreshSlotControls();
        updatePreviewState();
    }

    async function renderDraftPreview(revision) {
        try {
            if (!templateImage.complete) {
                await new Promise((resolve, reject) => {
                    templateImage.onload = resolve;
                    templateImage.onerror = () => reject(new Error('The frame template could not be loaded.'));
                });
            }
            const canvas = document.createElement('canvas');
            const scale = Math.min(1, 1000 / Math.max(templateImage.naturalWidth, templateImage.naturalHeight));
            canvas.width = Math.max(1, Math.round(templateImage.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(templateImage.naturalHeight * scale));
            const context = canvas.getContext('2d');
            context.drawImage(templateImage, 0, 0, canvas.width, canvas.height);
            mainPreviewImg.src = canvas.toDataURL('image/jpeg', 0.78);

            let renderedPhotos = 0;
            for (let index = 0; index < slotPositions.length; index++) {
                if (!fileUrls[index]) continue;
                const photo = await loadImageCached(fileUrls[index]);
                if (revision !== previewRevision) return;
                const slot = slotPositions[index];
                const width = Number(slot.width) * scale;
                const height = Number(slot.height) * scale;
                context.save();
                context.translate((Number(slot.x) + Number(slot.width) / 2) * scale, (Number(slot.y) + Number(slot.height) / 2) * scale);
                context.rotate((Number(slot.rotation) || 0) * Math.PI / 180);
                context.beginPath();
                if (hasOutline(slot)) {
                    // detected outline of the opening (heart / any shape)
                    slot.points.forEach((p, pointIndex) => {
                        const px = -width / 2 + Number(p[0]) * width;
                        const py = -height / 2 + Number(p[1]) * height;
                        if (pointIndex === 0) context.moveTo(px, py);
                        else context.lineTo(px, py);
                    });
                    context.closePath();
                } else if (slot.shape === 'circle' || slot.shape === 'ellipse') {
                    context.ellipse(0, 0, width / 2, height / 2, 0, 0, Math.PI * 2);
                } else if (slot.shape === 'rounded_rectangle') {
                    const r = Math.min(width, height) * 0.15;
                    if (context.roundRect) {
                        context.roundRect(-width / 2, -height / 2, width, height, r);
                    } else {
                        context.rect(-width / 2, -height / 2, width, height);
                    }
                } else if (slot.shape === 'heart') {
                    const topCurveHeight = height * 0.3;
                    context.moveTo(0, height / 2);
                    context.bezierCurveTo(-width / 2, height * 0.1, -width / 2, -height / 2 + topCurveHeight, -width / 4, -height / 2);
                    context.bezierCurveTo(0, -height / 2, 0, -height / 2 + topCurveHeight, 0, -height / 2 + topCurveHeight);
                    context.bezierCurveTo(0, -height / 2 + topCurveHeight, 0, -height / 2, width / 4, -height / 2);
                    context.bezierCurveTo(width / 2, -height / 2 + topCurveHeight, width / 2, height * 0.1, 0, height / 2);
                } else {
                    context.rect(-width / 2, -height / 2, width, height);
                }
                context.clip();
                drawPhoto(context, photo, width, height, slotTransforms[index]);
                context.restore();
                renderedPhotos++;
                if (renderedPhotos % 5 === 0) {
                    mainPreviewImg.src = canvas.toDataURL('image/jpeg', 0.78);
                }
            }
            if (revision === previewRevision) mainPreviewImg.src = canvas.toDataURL('image/jpeg', 0.78);
            if (revision === previewRevision) refreshSlotControls();
        } catch (error) {
            if (revision === previewRevision) {
                Swal.fire('Draft preview unavailable', error.message, 'warning');
            }
        }
    }

    function selectPhotos(files) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp', 'image/gif'];
        const images = files.filter(file => allowedTypes.includes(file.type));
        const rejectedCount = files.length - images.length;
        if (rejectedCount) {
            Swal.fire('Unsupported photo', `${rejectedCount} file(s) were skipped. Choose JPG, PNG, WebP, BMP, or GIF photos.`, 'warning');
        }
        if (images.length > slotCount) {
            Swal.fire('Slot limit reached', `This frame has ${slotCount} slots. Only the first ${slotCount} photos will be used.`, 'info');
        }

        fileUrls.forEach(url => {
            if (url) URL.revokeObjectURL(url);
        });
        preparedUploads.clear();
        slotFiles.fill(null);
        fileUrls.fill(null);
        slotTransforms.fill(null);
        selectedFiles = images.slice(0, slotCount);
        const startSlot = Number(placementStartSlot.value) || 0;
        selectedFiles.forEach((file, index) => {
            const slot = (startSlot + index) % slotCount;
            slotFiles[slot] = file;
            fileUrls[slot] = URL.createObjectURL(file);
        });
        selectedPhotosStatus.textContent = `${selectedFiles.length} of ${slotCount} photos selected`;
        clearPhotosBtn.classList.toggle('d-none', selectedFiles.length === 0);
        bulkPhotos.value = '';
        pendingMoveSlot = null;
        refreshSlotControls();
        updatePreviewState();
    }

    bulkPhotos.addEventListener('change', function () {
        selectPhotos(Array.from(this.files || []));
    });
    slotPhotoInput.addEventListener('change', function () {
        if (activeUploadSlot !== null && this.files?.[0]) replacePhoto(activeUploadSlot, this.files[0]);
        activeUploadSlot = null;
    });
    placementStartSlot.addEventListener('change', function () {
        if (selectedFiles.length) selectPhotos(selectedFiles);
    });
    clearPhotosBtn.addEventListener('click', function () {
        fileUrls.forEach(url => {
            if (url) URL.revokeObjectURL(url);
        });
        preparedUploads.clear();
        slotFiles.fill(null);
        fileUrls.fill(null);
        slotTransforms.fill(null);
        selectedFiles = [];
        bulkPhotos.value = '';
        pendingMoveSlot = null;
        selectedPhotosStatus.textContent = 'No photos selected';
        clearPhotosBtn.classList.add('d-none');
        refreshSlotControls();
        updatePreviewState();
    });
    mainPreviewImg.addEventListener('load', refreshSlotControls);
    window.addEventListener('resize', refreshSlotControls);
    templateImage.addEventListener('load', refreshSlotControls);
    refreshSlotControls();
    document.getElementById('bannerText').addEventListener('input', () => updatePreviewState(false));
    document.getElementById('calendarDate').addEventListener('change', () => updatePreviewState(false));

    const maxUploadBytes = 1200 * 1024;
    const uploadChunkSize = 5;
    const preparedUploads = new Map();

    function canvasToBlob(canvas, quality) {
        return new Promise((resolve, reject) => {
            canvas.toBlob(blob => {
                if (blob) resolve(blob);
                else reject(new Error('A photo could not be prepared for upload.'));
            }, 'image/jpeg', quality);
        });
    }

    async function prepareUploadFile(file) {
        if (file.size <= maxUploadBytes) return file;

        let bitmap = null;
        let sourceUrl = null;
        let source;
        try {
            if (typeof createImageBitmap === 'function') {
                bitmap = await createImageBitmap(file);
                source = bitmap;
            } else {
                throw new Error('ImageBitmap is unavailable');
            }
        } catch (error) {
            sourceUrl = URL.createObjectURL(file);
            try {
                source = await loadImage(sourceUrl);
            } catch (imageError) {
                URL.revokeObjectURL(sourceUrl);
                throw new Error(`${file.name} could not be opened. Please choose a JPG, PNG, WebP, or BMP image.`);
            }
        }

        try {
            const imageWidth = source.width || source.naturalWidth;
            const imageHeight = source.height || source.naturalHeight;
            let scale = Math.min(1, 2400 / Math.max(imageWidth, imageHeight));
            for (let attempt = 0; attempt < 8; attempt++) {
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(imageWidth * scale));
                canvas.height = Math.max(1, Math.round(imageHeight * scale));
                canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height);

                for (const quality of [0.86, 0.76, 0.66]) {
                    const blob = await canvasToBlob(canvas, quality);
                    if (blob.size <= maxUploadBytes) {
                        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                        return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
                    }
                }

                scale *= 0.8;
            }
        } finally {
            bitmap?.close();
            if (sourceUrl) URL.revokeObjectURL(sourceUrl);
        }

        throw new Error(`${file.name} is too large to prepare for upload.`);
    }

    function responseMessage(data, fallback) {
        const validationMessages = Object.values(data.errors || {}).flat();
        return validationMessages[0] || data.message || fallback;
    }

    async function readJsonResponse(response) {
        try {
            return await response.json();
        } catch (error) {
            throw new Error(`The server could not process the request (HTTP ${response.status}). Please try again with fewer or smaller photos.`);
        }
    }

    // Step 1: Generate Watermarked Preview
    generatePreviewBtn?.addEventListener('click', async function () {
        const form = document.getElementById('studioForm');
        const hasPhoto = slotFiles.some(Boolean);

        if (!hasPhoto) {
            Swal.fire({
                icon: 'info',
                title: 'Please Select Photos',
                text: 'Please select at least one photo to place in the frame.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        previewSpinner.classList.remove('d-none');
        generatePreviewBtn.disabled = true;
        payAndDownloadBtn.disabled = true;
        [bulkPhotos, placementStartSlot, document.getElementById('bannerText'), document.getElementById('calendarDate'), clearPhotosBtn].forEach(control => {
            control.disabled = true;
        });

        try {
            const selectedSlots = slotFiles
                .map((file, index) => file ? index : null)
                .filter(index => index !== null);
            for (let offset = 0; offset < selectedSlots.length; offset += uploadChunkSize) {
                const chunk = selectedSlots.slice(offset, offset + uploadChunkSize);
                previewProgressText.textContent = `Preparing photos ${Math.min(offset + chunk.length, selectedSlots.length)} of ${selectedSlots.length}`;
                for (const index of chunk) {
                    if (!preparedUploads.has(slotFiles[index])) {
                        preparedUploads.set(slotFiles[index], await prepareUploadFile(slotFiles[index]));
                    }
                }
                const uploadData = new FormData();
                uploadData.append('frame_id', frameId);
                chunk.forEach(index => uploadData.append(`photos[${index}]`, preparedUploads.get(slotFiles[index])));
                if (offset === 0) {
                    for (let index = 0; index < slotCount; index++) {
                        uploadData.append('clear_slots[]', String(index));
                    }
                }
                previewProgressText.textContent = `Uploading photos ${Math.min(offset + chunk.length, selectedSlots.length)} of ${selectedSlots.length}`;
                const uploadResponse = await fetch("{{ route('studio.photos') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: uploadData
                });
                const uploadResult = await readJsonResponse(uploadResponse);
                if (!uploadResponse.ok || !uploadResult.success) {
                    throw new Error(responseMessage(uploadResult, 'Photo upload failed.'));
                }
            }

            const formData = new FormData();
            formData.append('frame_id', frameId);
            formData.append('banner_text', document.getElementById('bannerText').value);
            formData.append('calendar_date', document.getElementById('calendarDate').value);
            const transformsToSend = {};
            slotTransforms.forEach((value, index) => {
                if (value && slotFiles[index]) transformsToSend[index] = value;
            });
            formData.append('transforms', JSON.stringify(transformsToSend));
            previewProgressText.textContent = 'Generating the final watermarked preview';
            const response = await fetch("{{ route('studio.preview') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await readJsonResponse(response);
            previewSpinner.classList.add('d-none');
            generatePreviewBtn.disabled = false;
            [bulkPhotos, placementStartSlot, document.getElementById('bannerText'), document.getElementById('calendarDate'), clearPhotosBtn].forEach(control => {
                control.disabled = false;
            });

            if (response.ok && data.success) {
                mainPreviewImg.src = data.preview_url + '?t=' + Date.now();
                slotControls.classList.add('d-none');
                previewHint.classList.add('d-none');
                isPreviewGenerated = true;
                payAndDownloadBtn.disabled = false;

                previewStatusBadge.innerHTML = `
                    <span class="badge bg-warning text-dark rounded-pill py-2 px-3 fw-bold">
                        <i class="fa-solid fa-watermark me-1"></i> Watermarked Preview Active
                    </span>
                `;

                Swal.fire({
                    icon: 'success',
                    title: 'Preview Ready!',
                    text: 'Your watermarked preview has been generated. Complete payment to remove watermark and download the crystal-clear High-Definition copy!',
                    confirmButtonColor: '#f59e0b'
                });
            } else {
                throw new Error(responseMessage(data, 'Failed to render preview.'));
            }
        } catch (err) {
            previewSpinner.classList.add('d-none');
            generatePreviewBtn.disabled = false;
            [bulkPhotos, placementStartSlot, document.getElementById('bannerText'), document.getElementById('calendarDate'), clearPhotosBtn].forEach(control => {
                control.disabled = false;
            });
            Swal.fire('Error', err.message || 'An error occurred while generating the preview.', 'error');
        }
    });

    // Step 2: Pay with Razorpay & Download HD
    payAndDownloadBtn?.addEventListener('click', async function () {
        payAndDownloadBtn.disabled = true;
        payAndDownloadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Preparing Razorpay...';

        try {
            if (typeof window.Razorpay !== 'function') {
                throw new Error('Razorpay Checkout could not be loaded. Check your internet connection and reload the page.');
            }
            // Create pending order on server
            const orderRes = await fetch("{{ route('studio.order') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    frame_id: frameId,
                    customer_name: 'Customer',
                    customer_phone: '9999999999',
                    customer_email: 'customer@neelaxtech.com',
                })
            });

            const orderData = await readJsonResponse(orderRes);

            if (!orderRes.ok || !orderData.success) {
                payAndDownloadBtn.disabled = false;
                payAndDownloadBtn.innerHTML = '<i class="fa-solid fa-lock me-2"></i> Pay & Download HD Photo';
                Swal.fire('Order Error', responseMessage(orderData, 'Could not initiate order.'), 'error');
                return;
            }

            currentDownloadToken = orderData.download_token;
            currentOrderNumber = orderData.order_number;

            // Initialize Razorpay Checkout Modal
            const options = {
                key: orderData.key,
                amount: orderData.amount,
                currency: orderData.currency,
                name: "Neelax Tech Studio",
                description: "HD Digital Photo Frame: " + orderData.order_number,
                image: "https://cdn-icons-png.flaticon.com/512/1040/1040244.png",
                order_id: orderData.razorpay_order_id,
                handler: async function (response) {
                    // Payment successful! Verify signature on server
                    Swal.fire({
                        title: 'Verifying Payment...',
                        text: 'Rendering pristine high-definition frame without watermark...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    try {
                        const verifyRes = await fetch("{{ route('studio.verify') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature,
                                download_token: currentDownloadToken,
                            })
                        });

                        const verifyData = await readJsonResponse(verifyRes);

                        if (verifyData.success) {
                            // Update preview with clean HD version
                            mainPreviewImg.src = verifyData.preview_url + '?t=' + Date.now();
                            slotControls.classList.add('d-none');
                            previewStatusBadge.innerHTML = `
                                <span class="badge bg-success rounded-pill py-2 px-3 fw-bold">
                                    <i class="fa-solid fa-circle-check me-1"></i> Paid & Watermark Removed
                                </span>
                            `;

                            // Celebration Modal & Automatic Download
                            Swal.fire({
                                icon: 'success',
                                title: 'Payment Successful!',
                                html: `
                                    <p class="text-secondary small mb-2">Order Reference: <strong>${verifyData.order_number}</strong></p>
                                    <p class="mb-3">Your high-definition, crystal-clear framed photo has been rendered without watermark.</p>
                                    <a href="${verifyData.download_url}" class="btn btn-warning fw-bold py-2 px-4 rounded-pill shadow">
                                        <i class="fa-solid fa-download me-1"></i> Download HD Framed Image
                                    </a>
                                `,
                                confirmButtonColor: '#f59e0b',
                                confirmButtonText: 'Done'
                            });

                            // Trigger immediate download automatically
                            window.location.href = verifyData.download_url;

                            payAndDownloadBtn.innerHTML = '<i class="fa-solid fa-circle-check text-success me-2"></i> Downloaded';
                        } else {
                            Swal.fire('Verification Failed', verifyData.message || 'Signature mismatch.', 'error');
                            payAndDownloadBtn.disabled = false;
                            payAndDownloadBtn.innerHTML = '<i class="fa-solid fa-lock me-2"></i> Pay & Download HD Photo';
                        }
                    } catch (err) {
                        Swal.fire('Error', 'Payment verification failed.', 'error');
                        payAndDownloadBtn.disabled = false;
                        payAndDownloadBtn.innerHTML = '<i class="fa-solid fa-lock me-2"></i> Pay & Download HD Photo';
                    }
                },
                prefill: {
                    name: 'Customer',
                    email: 'customer@neelaxtech.com',
                    contact: '9999999999'
                },
                theme: {
                    color: "#f59e0b"
                },
                modal: {
                    ondismiss: function () {
                        payAndDownloadBtn.disabled = false;
                        payAndDownloadBtn.innerHTML = '<i class="fa-solid fa-lock me-2"></i> Pay & Download HD Photo';
                    }
                }
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (response) {
                Swal.fire('Payment Failed', response.error.description || 'Payment was not completed.', 'error');
                payAndDownloadBtn.disabled = false;
                payAndDownloadBtn.innerHTML = '<i class="fa-solid fa-lock me-2"></i> Pay & Download HD Photo';
            });
            rzp.open();

        } catch (err) {
            payAndDownloadBtn.disabled = false;
            payAndDownloadBtn.innerHTML = '<i class="fa-solid fa-lock me-2"></i> Pay & Download HD Photo';
            Swal.fire('Error', err.message || 'Unable to initiate Razorpay checkout.', 'error');
        }
    });
});
</script>
@endpush