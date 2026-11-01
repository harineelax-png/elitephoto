@extends('layouts.admin')

@section('title', 'Admin Batch Generator (300 Images)')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">
            <i class="fa-solid fa-layer-group text-warning me-2"></i>Admin Batch Image Generator
        </h2>
        <p class="text-secondary small mb-0">
            Upload up to 300 images, select any frame theme, and automatically apply all images into the theme. Free for administrators with instant ZIP download!
        </p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-success py-2 px-3 rounded-pill">
            <i class="fa-solid fa-gift me-1"></i> 100% Free • No Payment
        </span>
    </div>
</div>

<div class="row g-4">
    <!-- Configuration & Upload Card -->
    <div class="col-lg-5">
        <div class="admin-card">
            <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                1. Select Theme & Options
            </h5>

            <!-- Theme Selection -->
            <div class="mb-3">
                <label class="form-label text-white small fw-bold">Select Frame Template / Theme</label>
                <select id="themeSelect" class="form-select bg-dark text-white border-secondary">
                    @foreach ($frames as $f)
                        <option value="{{ $f->id }}" data-slots="{{ $f->total_slots }}" data-img="{{ asset('storage/' . $f->template_path) }}">
                            {{ $f->name }} ({{ $f->total_slots }} Slots • {{ $f->category }})
                        </option>
                    @endforeach
                </select>
                <div class="mt-2 text-center p-2 rounded bg-black bg-opacity-25 border border-secondary border-opacity-25">
                    <img id="themePreviewImg" src="{{ $frames->first() ? asset('storage/' . $frames->first()->template_path) : '' }}" class="img-fluid rounded" style="max-height: 180px; object-fit: contain;">
                    <div id="themeSlotsBadge" class="small text-warning mt-1 fw-bold">
                        {{ $frames->first() ? $frames->first()->total_slots : 0 }} photo slots per frame
                    </div>
                </div>
            </div>

            <!-- Mode Selection -->
            <div class="mb-3">
                <label class="form-label text-white small fw-bold">Composition Mode</label>
                <div class="form-check text-secondary small mb-1">
                    <input class="form-check-input" type="radio" name="batchMode" id="modePack" value="pack" checked>
                    <label class="form-check-label text-white" for="modePack">
                        <strong>Pack Photos into Slots</strong> (Recommended)
                        <div class="text-secondary" style="font-size:0.75rem;">
                            E.g. If theme has 5 slots, 300 photos create 60 distinct framed pictures.
                        </div>
                    </label>
                </div>
                <div class="form-check text-secondary small">
                    <input class="form-check-input" type="radio" name="batchMode" id="modeSolo" value="solo">
                    <label class="form-check-label text-white" for="modeSolo">
                        <strong>1 Frame Per Photo</strong>
                        <div class="text-secondary" style="font-size:0.75rem;">
                            Places every single photo as the featured image (300 photos = 300 framed pictures).
                        </div>
                    </label>
                </div>
            </div>

            <!-- Banner Text (Optional) -->
            <div class="mb-3">
                <label class="form-label text-white small fw-bold">Custom Banner Ribbon Text (Optional)</label>
                <input type="text" id="bannerTextInput" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Annual Celebration 2026 / Happy Birthday">
            </div>

            <!-- Calendar Day (Optional) -->
            <div class="mb-4">
                <label class="form-label text-white small fw-bold">Calendar Day Highlight (Optional, 1-31)</label>
                <input type="number" id="calendarDayInput" min="1" max="31" class="form-control bg-dark text-white border-secondary" placeholder="e.g. 22">
            </div>

            <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                2. Upload Up to 300 Images
            </h5>

            <!-- File Upload Zone -->
            <div class="p-4 text-center rounded-3 border-2 border-dashed border-secondary bg-dark bg-opacity-50 mb-3" id="dropZone" style="cursor: pointer;">
                <i class="fa-solid fa-cloud-arrow-up text-warning fs-1 mb-2"></i>
                <div class="fw-bold text-white">Click or Drag & Drop Photos Here</div>
                <div class="text-secondary small">Select up to 300 JPG, PNG, WEBP files</div>
                <input type="file" id="batchFileInput" multiple accept="image/*" class="d-none">
            </div>

            <div id="fileSelectedInfo" class="alert alert-secondary py-2 small d-none d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-images text-warning me-1"></i> <strong id="fileCountSpan">0</strong> photos selected</span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" id="clearFilesBtn">Clear</button>
            </div>

            <!-- Progress Bar -->
            <div id="progressContainer" class="d-none mb-3">
                <div class="d-flex justify-content-between small text-secondary mb-1">
                    <span id="progressStatusText">Processing...</span>
                    <span id="progressPercentText" class="fw-bold text-warning">0%</span>
                </div>
                <div class="progress bg-dark border border-secondary" style="height: 10px;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width: 0%"></div>
                </div>
            </div>

            <!-- Action Button -->
            <button type="button" class="btn btn-warning w-100 fw-bold py-2 rounded-pill shadow" id="startBatchBtn" disabled>
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generate All Framed Images
            </button>
        </div>
    </div>

    <!-- Output & Results Card -->
    <div class="col-lg-7">
        <div class="admin-card h-100 d-flex flex-direction-column">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                <div>
                    <h5 class="fw-bold text-white mb-0">Generated Frames Gallery</h5>
                    <div class="text-secondary small"><span id="generatedCountBadge">0</span> images generated in this session</div>
                </div>
                <div id="zipDownloadContainer" class="d-none">
                    <a href="#" id="downloadZipBtn" class="btn btn-success btn-sm rounded-pill fw-bold px-3">
                        <i class="fa-solid fa-file-zipper me-1"></i> Download All as ZIP
                    </a>
                </div>
            </div>

            <!-- Empty Placeholder -->
            <div id="emptyGalleryNotice" class="text-center py-5 my-auto text-secondary">
                <div class="mb-3">
                    <i class="fa-solid fa-photo-film text-secondary opacity-50" style="font-size: 4rem;"></i>
                </div>
                <h5 class="text-white">No Batch Generated Yet</h5>
                <p class="small text-secondary max-w-sm mx-auto">
                    Select a theme on the left, upload your photos (up to 300), and click Generate. The framed images will appear here live with a single-click ZIP download option.
                </p>
            </div>

            <!-- Live Output Grid -->
            <div id="outputGrid" class="row g-3 overflow-auto" style="max-height: 650px;">
                <!-- Generated items will be dynamically injected here -->
            </div>
        </div>
    </div>
</div>

<!-- High Resolution Preview Lightbox Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h6 class="modal-title text-white fw-bold" id="previewModalTitle">Framed Output Preview</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-3 bg-black">
                <img id="previewModalImg" src="" class="img-fluid rounded shadow" style="max-height: 75vh; object-fit: contain;">
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <span class="small text-secondary" id="previewModalInfo">Pristine High-Definition Render</span>
                <a href="" id="previewModalDownload" download="" class="btn btn-warning btn-sm rounded-pill px-4 fw-bold">
                    <i class="fa-solid fa-download me-1"></i> Download HD Image
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const themeSelect = document.getElementById('themeSelect');
    const themePreviewImg = document.getElementById('themePreviewImg');
    const themeSlotsBadge = document.getElementById('themeSlotsBadge');
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('batchFileInput');
    const fileSelectedInfo = document.getElementById('fileSelectedInfo');
    const fileCountSpan = document.getElementById('fileCountSpan');
    const clearFilesBtn = document.getElementById('clearFilesBtn');
    const startBatchBtn = document.getElementById('startBatchBtn');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressStatusText = document.getElementById('progressStatusText');
    const progressPercentText = document.getElementById('progressPercentText');
    const emptyNotice = document.getElementById('emptyGalleryNotice');
    const outputGrid = document.getElementById('outputGrid');
    const generatedCountBadge = document.getElementById('generatedCountBadge');
    const zipContainer = document.getElementById('zipDownloadContainer');
    const downloadZipBtn = document.getElementById('downloadZipBtn');

    let selectedFiles = [];
    let currentBatchId = 'batch_' + Date.now();
    let totalGeneratedCount = 0;

    // Theme preview update
    themeSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (opt) {
            themePreviewImg.src = opt.dataset.img || '';
            themeSlotsBadge.textContent = opt.dataset.slots + ' photo slots per frame';
        }
    });

    // File selection trigger
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-warning'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-warning'));
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-warning');
        if (e.dataTransfer.files && e.dataTransfer.files.length) {
            handleFiles(Array.from(e.dataTransfer.files));
        }
    });

    fileInput.addEventListener('change', function () {
        if (this.files && this.files.length) {
            handleFiles(Array.from(this.files));
        }
    });

    function handleFiles(files) {
        // filter only images
        const imageFiles = files.filter(f => f.type.startsWith('image/'));
        if (imageFiles.length > 300) {
            Swal.fire('Limit Notice', 'Capping at the first 300 images.', 'info');
            selectedFiles = imageFiles.slice(0, 300);
        } else {
            selectedFiles = imageFiles;
        }

        if (selectedFiles.length > 0) {
            fileCountSpan.textContent = selectedFiles.length;
            fileSelectedInfo.classList.remove('d-none');
            startBatchBtn.disabled = false;
        }
    }

    clearFilesBtn.addEventListener('click', function () {
        selectedFiles = [];
        fileInput.value = '';
        fileSelectedInfo.classList.add('d-none');
        startBatchBtn.disabled = true;
    });

    // Batch Generation Processing
    startBatchBtn.addEventListener('click', async function () {
        if (!selectedFiles.length) return;

        const frameId = themeSelect.value;
        const mode = document.querySelector('input[name="batchMode"]:checked').value;
        const bannerText = document.getElementById('bannerTextInput').value;
        const calendarDay = document.getElementById('calendarDayInput').value;

        currentBatchId = 'batch_' + Date.now();
        totalGeneratedCount = 0;
        generatedCountBadge.textContent = '0';
        outputGrid.innerHTML = '';
        emptyNotice.classList.add('d-none');
        zipContainer.classList.add('d-none');

        startBatchBtn.disabled = true;
        progressContainer.classList.remove('d-none');

        // Chunk files in groups of 10 to never exceed PHP max_file_uploads or upload limits
        const chunkSize = 10;
        const totalFiles = selectedFiles.length;
        let processedFiles = 0;

        for (let i = 0; i < totalFiles; i += chunkSize) {
            const chunk = selectedFiles.slice(i, i + chunkSize);
            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('frame_id', frameId);
            formData.append('batch_id', currentBatchId);
            formData.append('mode', mode);
            if (bannerText) formData.append('banner_text', bannerText);
            if (calendarDay) formData.append('calendar_day', calendarDay);

            chunk.forEach((file, idx) => {
                formData.append('photos[' + idx + ']', file);
            });

            progressStatusText.textContent = `Processing ${processedFiles} / ${totalFiles} photos...`;
            const pct = Math.round((processedFiles / totalFiles) * 100);
            progressBar.style.width = pct + '%';
            progressPercentText.textContent = pct + '%';

            try {
                const response = await fetch("{{ route('admin.batch.chunk') }}", {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();

                if (res.status === 'success' && res.generated) {
                    res.generated.forEach(item => {
                        totalGeneratedCount++;
                        appendResultItem(item);
                    });
                    generatedCountBadge.textContent = totalGeneratedCount;
                }
            } catch (err) {
                console.error("Chunk error:", err);
            }

            processedFiles += chunk.length;
        }

        // Complete!
        progressBar.style.width = '100%';
        progressPercentText.textContent = '100%';
        progressStatusText.textContent = `Completed! ${totalGeneratedCount} frames generated.`;
        startBatchBtn.disabled = false;

        // Setup Zip download button
        downloadZipBtn.href = "{{ url('admin/batch-generate/zip') }}/" + currentBatchId;
        zipContainer.classList.remove('d-none');

        Swal.fire({
            icon: 'success',
            title: 'Batch Generation Complete!',
            text: `Successfully generated ${totalGeneratedCount} high-definition framed photos. You can download them all in a single ZIP file or individually.`,
            confirmButtonColor: '#f59e0b'
        });
    });

    function appendResultItem(item) {
        const col = document.createElement('div');
        col.className = 'col-sm-6 col-md-4';
        col.innerHTML = `
            <div class="card bg-dark border-secondary overflow-hidden h-100 shadow-sm">
                <div class="position-relative bg-black text-center" style="cursor: pointer;" onclick="openPreviewModal('${item.url}', '${item.file_name}')">
                    <img src="${item.url}" class="img-fluid" style="height: 220px; width: 100%; object-fit: contain; padding: 6px;" alt="Framed Photo">
                    <span class="position-absolute top-0 end-0 m-2 badge bg-dark bg-opacity-75 border border-secondary text-warning" style="font-size: 0.68rem;">
                        <i class="fa-solid fa-magnifying-glass-plus me-1"></i> Preview HD
                    </span>
                </div>
                <div class="card-body p-2 d-flex justify-content-between align-items-center">
                    <span class="text-secondary small text-truncate me-1" style="font-size:0.75rem;" title="${item.file_name}">${item.file_name}</span>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-info py-0 px-2" onclick="openPreviewModal('${item.url}', '${item.file_name}')" title="Inspect Full Screen">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <a href="${item.url}" download="${item.file_name}" class="btn btn-sm btn-outline-warning py-0 px-2" title="Download Image">
                            <i class="fa-solid fa-download"></i>
                        </a>
                    </div>
                </div>
            </div>
        `;
        outputGrid.appendChild(col);
    }

    window.openPreviewModal = function (url, filename) {
        document.getElementById('previewModalImg').src = url;
        document.getElementById('previewModalTitle').textContent = filename;
        document.getElementById('previewModalDownload').href = url;
        document.getElementById('previewModalDownload').download = filename;
        const modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    };
});
</script>
@endpush
