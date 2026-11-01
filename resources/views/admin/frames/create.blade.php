@extends('layouts.admin')

@section('title', 'Create Frame Template')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold text-white mb-1">Create New Frame Template</h2>
        <p class="text-secondary small mb-0">Upload a background template and configure the photo openings.</p>
    </div>
    <a href="{{ route('admin.frames.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
        &larr; Back to Templates
    </a>
</div>

<form action="{{ route('admin.frames.store') }}" method="POST" enctype="multipart/form-data" id="frameForm">
    @csrf
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="admin-card">
                <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-25">Template Details</h5>

                <div class="mb-3">
                    <label for="name" class="form-label text-white small fw-bold">Template Name</label>
                    <input type="text" name="name" id="name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Royal Golden Birthday Frame" value="{{ old('name') }}" required>
                </div>

                <div class="mb-3">
                    <label for="category" class="form-label text-white small fw-bold">Category / Occasion</label>
                    <select name="category" id="category" class="form-select bg-dark text-white border-secondary" required>
                        <option value="Birthday">Birthday</option>
                        <option value="Wedding & Anniversary">Wedding & Anniversary</option>
                        <option value="Romantic / Love">Romantic / Love</option>
                        <option value="Calendar 2025">Calendar 2025</option>
                        <option value="Family & Kids">Family & Kids</option>
                        <option value="Festival & Special">Festival & Special</option>
                        <option value="General">General</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="price" class="form-label text-white small fw-bold">Custom Price (₹) <span class="text-secondary fw-normal">(Optional)</span></label>
                    <input type="number" step="0.01" name="price" id="price" class="form-control bg-dark text-white border-secondary" placeholder="Leave blank to use default (₹49)" value="{{ old('price') }}">
                    <div class="form-text text-secondary" style="font-size:0.75rem;">If empty, the system uses the global price per image set in Settings.</div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label text-white small fw-bold">Description</label>
                    <textarea name="description" id="description" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Theme styling notes...">{{ old('description') }}</textarea>
                </div>

                <div class="mb-4">
                    <label for="template" class="form-label text-white small fw-bold">Template Image or PSD File</label>
                    <input type="file" name="template" id="template" class="form-control bg-dark text-white border-secondary" accept="image/*,.psd" required>
                    <div class="form-text text-secondary" style="font-size:0.75rem;">Images and layered PSD files are supported. PSD processing requires PHP Imagick on the server.</div>
                </div>

                <input type="hidden" name="slot_positions" id="slot_positions">

                <button type="submit" class="btn btn-warning w-100 fw-bold py-2 rounded-pill shadow">
                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> Save Frame Template
                </button>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="admin-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                    <h5 class="fw-bold text-white mb-0">Interactive Slot Editor</h5>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-outline-warning btn-sm rounded-pill" id="redetectBtn">
                            <i class="fa-solid fa-wand-magic me-1"></i> Auto Detect
                        </button>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-success" id="addSlotBtn" title="Add Rectangle Slot">
                                <i class="fa-solid fa-plus me-1"></i> + Box
                            </button>
                            <button type="button" class="btn btn-outline-danger" id="addHeartBtn" title="Add Heart Slot">
                                <i class="fa-solid fa-heart me-1"></i> + Heart
                            </button>
                            <button type="button" class="btn btn-outline-info" id="addCircleBtn" title="Add Circle Slot">
                                <i class="fa-solid fa-circle me-1"></i> + Circle
                            </button>
                        </div>
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-outline-primary btn-sm rounded-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fa-solid fa-border-all me-1"></i> Presets
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark">
                                <li><a class="dropdown-item preset-opt" href="#" data-preset="3x3"><i class="fa-solid fa-table-cells me-2 text-warning"></i> 3x3 Grid (9 Slots)</a></li>
                                <li><a class="dropdown-item preset-opt" href="#" data-preset="2x2"><i class="fa-solid fa-table-cells-large me-2 text-info"></i> 2x2 Grid (4 Slots)</a></li>
                                <li><a class="dropdown-item preset-opt" href="#" data-preset="heart_family"><i class="fa-solid fa-heart me-2 text-danger"></i> Heart Collage (5 Hearts)</a></li>
                                <li><a class="dropdown-item preset-opt" href="#" data-preset="strip3"><i class="fa-solid fa-bars me-2 text-success"></i> 3-Photo Column</a></li>
                                <li><a class="dropdown-item preset-opt" href="#" data-preset="strip4"><i class="fa-solid fa-bars me-2 text-primary"></i> 4-Photo Column</a></li>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-outline-light btn-sm rounded-pill" id="duplicateSlot" title="Duplicate selected slot">
                            <i class="fa-solid fa-clone me-1"></i> Duplicate
                        </button>
                        <label class="d-flex align-items-center gap-1 mb-0 small text-secondary">
                            Sensitivity
                            <input type="range" id="sensitivity" min="10" max="60" value="28" style="width:70px;">
                        </label>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="sortSlots">Sort</button>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill" id="deleteSelected">
                            <i class="fa-solid fa-trash-can me-1"></i> Delete
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill" id="clearSlots">
                            <i class="fa-solid fa-eraser me-1"></i> Clear All
                        </button>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-2">
                    <span class="badge bg-secondary" id="detectStatus">Upload an image to start</span>
                    <span class="badge bg-info text-dark" id="uniformBadge"></span>
                </div>

                <div id="canvasWrapper" class="position-relative text-center p-2 rounded bg-black" style="min-height: 400px; overflow: auto;">
                    <canvas id="slotCanvas" style="max-width: 100%; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px;"></canvas>
                </div>

                <!-- Slot Properties Panel -->
                <div id="slotProps" class="d-flex flex-wrap gap-3 align-items-center mt-3 p-3 rounded bg-dark border border-secondary" style="display:none !important;">
                    <strong class="text-warning">Selected Slot #<span id="propIndex"></span>:</strong>
                    <label class="d-flex align-items-center gap-1 mb-0 small text-white">
                        Shape:
                        <select id="propShape" class="form-select form-select-sm bg-dark text-white border-secondary" style="width:auto;">
                            <option value="rectangle">Rectangle</option>
                            <option value="rounded_rectangle">Rounded Rectangle</option>
                            <option value="circle">Circle</option>
                            <option value="ellipse">Ellipse / Oval</option>
                            <option value="heart">Heart</option>
                            <option value="polygon">Custom outline (detected)</option>
                        </select>
                    </label>
                    <label class="d-flex align-items-center gap-1 mb-0 small text-white">
                        Rotation:
                        <input type="range" id="propRotation" min="-45" max="45" step="1" value="0" style="width:100px;">
                        <span id="propRotationVal" class="text-warning">0°</span>
                    </label>
                    <button type="button" class="btn btn-outline-warning btn-sm rounded-pill" id="editPointsBtn" title="Turn this slot into an outline whose corner points you can drag">
                        <i class="fa-solid fa-draw-polygon me-1"></i> Edit Points
                    </button>
                    <span class="text-secondary small" style="font-size:0.75rem;">Drag the round handles to sharpen or reshape any corner &middot; double-click an edge to add a point &middot; double-click a point to remove it</span>
                </div>

                <div class="form-text text-secondary mt-2">
                    <i class="fa-solid fa-circle-info text-warning me-1"></i>
                    Click a slot to select/resize/rotate. Click empty canvas and drag to draw a new slot manually.
                </div>

                <div class="mt-3 table-responsive">
                    <table class="table table-sm table-custom border-secondary" id="slotTable">
                        <thead>
                            <tr><th>#</th><th>X</th><th>Y</th><th>Width</th><th>Height</th><th>Shape</th><th>Rotation</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const templateInput = document.getElementById('template');
    const canvas = document.getElementById('slotCanvas');
    const ctx = canvas.getContext('2d');
    const tbody = document.querySelector('#slotTable tbody');
    const uniformBadge = document.getElementById('uniformBadge');
    const detectStatus = document.getElementById('detectStatus');
    const sensitivity = document.getElementById('sensitivity');
    const slotProps = document.getElementById('slotProps');
    const propIndex = document.getElementById('propIndex');
    const propShape = document.getElementById('propShape');
    const propRotation = document.getElementById('propRotation');
    const propRotationVal = document.getElementById('propRotationVal');

    let img = new Image();
    let naturalWidth = 0, naturalHeight = 0, scale = 1;
    let slots = [];
    let selectedIndex = -1;
    let mode = null;
    let dragStart = { x: 0, y: 0 };
    let moveOffset = { x: 0, y: 0 };
    let resizeCorner = null;
    let currentFile = null;

    function syncPropsPanel() {
        if (selectedIndex === -1 || !slots[selectedIndex]) {
            slotProps.style.setProperty('display', 'none', 'important');
            return;
        }
        const s = slots[selectedIndex];
        if (!s.shape) s.shape = 'rectangle';
        if (typeof s.rotation !== 'number') s.rotation = 0;

        propIndex.textContent = selectedIndex + 1;
        propShape.value = s.shape;
        propRotation.value = s.rotation;
        propRotationVal.textContent = Math.round(s.rotation) + '°';
        slotProps.style.setProperty('display', 'flex', 'important');
    }

    propShape.addEventListener('change', function () {
        if (selectedIndex === -1) return;
        slots[selectedIndex].shape = propShape.value;
        if (propShape.value !== 'heart' && propShape.value !== 'polygon') delete slots[selectedIndex].points;
        redraw();
        renderTable();
    });

    propRotation.addEventListener('input', function () {
        if (selectedIndex === -1) return;
        slots[selectedIndex].rotation = parseFloat(propRotation.value);
        propRotationVal.textContent = Math.round(slots[selectedIndex].rotation) + '°';
        redraw();
        renderTable();
    });

    templateInput.addEventListener('change', async function (e) {
        const file = e.target.files[0];
        if (!file) return;
        currentFile = file;
        if (file.name.toLowerCase().endsWith('.psd')) {
            const data = await runAutoDetect();
            if (!data || !data.preview) return;

            img = new Image();
            img.onload = function () {
                naturalWidth = img.naturalWidth;
                naturalHeight = img.naturalHeight;
                const maxDisplayWidth = 650;
                scale = naturalWidth > maxDisplayWidth ? maxDisplayWidth / naturalWidth : 1;
                canvas.width = naturalWidth * scale;
                canvas.height = naturalHeight * scale;
                slots = (data.slots || []).map(normSlot);
                selectedIndex = -1;
                redraw();
                renderTable();
                syncPropsPanel();
            };
            img.src = data.preview;
            return;
        }

        const reader = new FileReader();
        reader.onload = function (ev) {
            img = new Image();
            img.onload = function () {
                naturalWidth = img.naturalWidth;
                naturalHeight = img.naturalHeight;
                const maxDisplayWidth = 650;
                scale = naturalWidth > maxDisplayWidth ? maxDisplayWidth / naturalWidth : 1;
                canvas.width = naturalWidth * scale;
                canvas.height = naturalHeight * scale;
                slots = [];
                selectedIndex = -1;
                redraw();
                renderTable();
                syncPropsPanel();
                runAutoDetect();
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    document.getElementById('redetectBtn').addEventListener('click', runAutoDetect);

    async function runAutoDetect() {
        if (!currentFile) {
            detectStatus.textContent = 'Choose a template image first.';
            detectStatus.className = 'badge bg-warning text-dark';
            return;
        }
        detectStatus.textContent = 'Detecting slots...';
        detectStatus.className = 'badge bg-secondary';

        const fd = new FormData();
        fd.append('template', currentFile);
        fd.append('edge_threshold', sensitivity.value);

        try {
            const res = await fetch("{{ route('admin.frames.detectSlots') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                },
                body: fd,
            });
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.errors?.template?.[0] || data.message || 'Template processing failed.');
            }
            if (data.slots && data.slots.length) {
                slots = data.slots.map(normSlot);
                selectedIndex = -1;
                redraw();
                renderTable();
                syncPropsPanel();
                detectStatus.textContent = `Auto-detected ${slots.length} slot(s) — adjust if needed`;
                detectStatus.className = 'badge bg-success';
            } else {
                detectStatus.textContent = data.message || 'No slots detected — draw them manually.';
                detectStatus.className = 'badge bg-warning text-dark';
            }
            return data;
        } catch (err) {
            detectStatus.textContent = err.message || 'Auto-detect failed — draw slots manually.';
            detectStatus.className = 'badge bg-danger';
            return null;
        }
    }


    // ---- outline-aware helpers (detected heart / polygon shapes carry normalized "points") ----
    let rzCorner = null;
    let dragVertex = -1;

    function normSlot(s) {
        const o = Object.assign({}, s);
        o.shape = o.shape || 'rectangle';
        o.rotation = typeof o.rotation === 'number' ? o.rotation : (parseFloat(o.rotation) || 0);
        if (Array.isArray(o.points) && o.points.length >= 3 && (o.shape === 'heart' || o.shape === 'polygon')) {
            o.points = o.points.map(p => [Number(p[0]), Number(p[1])]);
        } else {
            delete o.points;
        }
        return o;
    }

    function hasOutline(s) {
        return (s.shape === 'heart' || s.shape === 'polygon') && Array.isArray(s.points) && s.points.length >= 3;
    }

    // Path of the real detected outline, centred on the slot (units: display pixels)
    function outlinePath(s, dw, dh) {
        ctx.beginPath();
        s.points.forEach((p, k) => {
            const px = -dw / 2 + p[0] * dw, py = -dh / 2 + p[1] * dh;
            if (k === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
        });
        ctx.closePath();
    }

    function pointInPoly(pts, x, y) {
        let inside = false;
        for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
            const xi = pts[i][0], yi = pts[i][1], xj = pts[j][0], yj = pts[j][1];
            if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / ((yj - yi) || 1e-9) + xi)) inside = !inside;
        }
        return inside;
    }

    function hitSlot(s, mx, my) {
        if (mx < s.x || mx > s.x + s.width || my < s.y || my > s.y + s.height) return false;
        if (hasOutline(s)) {
            const nx = (mx - s.x) / s.width, ny = (my - s.y) / s.height;
            return pointInPoly(s.points, nx, ny);
        }
        return true;
    }

    function cornerAt(s, mx, my) {
        const r = 10 / scale;
        const cs = { nw: [s.x, s.y], ne: [s.x + s.width, s.y], sw: [s.x, s.y + s.height], se: [s.x + s.width, s.y + s.height] };
        for (const k in cs) {
            if (Math.abs(mx - cs[k][0]) <= r && Math.abs(my - cs[k][1]) <= r) return k;
        }
        return null;
    }

    // ---- manual point editing (drag corners / curve points of any outline) ----
    function rotPt(s, x, y, sign) {
        const cx = s.x + s.width / 2, cy = s.y + s.height / 2;
        const r = sign * (s.rotation || 0) * Math.PI / 180;
        const dx = x - cx, dy = y - cy;
        return [cx + dx * Math.cos(r) - dy * Math.sin(r), cy + dx * Math.sin(r) + dy * Math.cos(r)];
    }
    function vertexImg(s, p) { return rotPt(s, s.x + p[0] * s.width, s.y + p[1] * s.height, 1); }
    function imgToNorm(s, mx, my) {
        const l = rotPt(s, mx, my, -1);
        return [(l[0] - s.x) / (s.width || 1), (l[1] - s.y) / (s.height || 1)];
    }
    function vertexAt(s, mx, my) {
        const r = 9 / scale;
        let best = -1, bd = r;
        s.points.forEach((p, k) => {
            const v = vertexImg(s, p);
            const d = Math.hypot(v[0] - mx, v[1] - my);
            if (d <= bd) { bd = d; best = k; }
        });
        return best;
    }
    function edgeAt(s, mx, my) {
        const r = 8 / scale;
        const n = s.points.length;
        let best = null, bd = r;
        for (let i = 0; i < n; i++) {
            const a = vertexImg(s, s.points[i]), b = vertexImg(s, s.points[(i + 1) % n]);
            const vx = b[0] - a[0], vy = b[1] - a[1];
            const len2 = vx * vx + vy * vy || 1e-9;
            let t = ((mx - a[0]) * vx + (my - a[1]) * vy) / len2;
            t = Math.max(0, Math.min(1, t));
            const px = a[0] + t * vx, py = a[1] + t * vy;
            const d = Math.hypot(mx - px, my - py);
            if (d <= bd) { bd = d; best = { index: i, x: px, y: py }; }
        }
        return best;
    }
    // After edits, rebuild the bounding box around the points so they stay normalised 0..1
    function renormalizeOutline(s) {
        const abs = s.points.map(p => vertexImg(s, p));
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        abs.forEach(a => { minX = Math.min(minX, a[0]); minY = Math.min(minY, a[1]); maxX = Math.max(maxX, a[0]); maxY = Math.max(maxY, a[1]); });
        const w = Math.max(2, maxX - minX), h = Math.max(2, maxY - minY);
        s.x = minX; s.y = minY; s.width = w; s.height = h; s.rotation = 0;
        s.points = abs.map(a => [(a[0] - minX) / w, (a[1] - minY) / h]);
    }
    function outlineFromShape(s) {
        const shape = s.shape || 'rectangle', pts = [];
        const arc = (cx, cy, r, a0, a1, steps) => { for (let i = 0; i <= steps; i++) { const a = a0 + (a1 - a0) * i / steps; pts.push([cx + r * Math.cos(a), cy + r * Math.sin(a)]); } };
        if (shape === 'circle' || shape === 'ellipse') {
            for (let i = 0; i < 48; i++) { const a = i / 48 * Math.PI * 2; pts.push([0.5 + 0.5 * Math.cos(a), 0.5 + 0.5 * Math.sin(a)]); }
        } else if (shape === 'rounded_rectangle') {
            const rx = Math.min(s.width, s.height) * 0.15 / s.width, ry = Math.min(s.width, s.height) * 0.15 / s.height;
            const corner = (cx, cy, a0) => { for (let i = 0; i <= 6; i++) { const a = a0 + (Math.PI / 2) * i / 6; pts.push([cx + rx * Math.cos(a), cy + ry * Math.sin(a)]); } };
            corner(1 - rx, 1 - ry, 0); corner(rx, 1 - ry, Math.PI / 2); corner(rx, ry, Math.PI); corner(1 - rx, ry, Math.PI * 1.5);
        } else if (shape === 'heart') {
            const bez = (p0, p1, p2, p3, n) => { for (let i = 0; i < n; i++) { const t = i / n, u = 1 - t; pts.push([u*u*u*p0[0] + 3*u*u*t*p1[0] + 3*u*t*t*p2[0] + t*t*t*p3[0], u*u*u*p0[1] + 3*u*u*t*p1[1] + 3*u*t*t*p2[1] + t*t*t*p3[1]]); } };
            bez([0.5, 1], [0, 0.6], [0, 0.3], [0.25, 0], 14);
            bez([0.25, 0], [0.5, 0], [0.5, 0.3], [0.5, 0.3], 6);
            bez([0.5, 0.3], [0.5, 0.3], [0.5, 0], [0.75, 0], 6);
            bez([0.75, 0], [1, 0.3], [1, 0.6], [0.5, 1], 14);
        } else {
            pts.push([0, 0], [1, 0], [1, 1], [0, 1]);
        }
        return pts;
    }

    function redraw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (img.src) ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        slots.forEach((s, i) => {
            const dx = s.x * scale, dy = s.y * scale, dw = s.width * scale, dh = s.height * scale;
            const isSel = i === selectedIndex;
            const rot = ((s.rotation || 0) * Math.PI) / 180;

            ctx.save();
            ctx.translate(dx + dw / 2, dy + dh / 2);
            ctx.rotate(rot);
            ctx.lineWidth = 2;
            ctx.strokeStyle = isSel ? '#0d6efd' : '#f59e0b';

            if (hasOutline(s)) {
                outlinePath(s, dw, dh);
                ctx.stroke();
            } else if (s.shape === 'circle' || s.shape === 'ellipse') {
                ctx.beginPath();
                ctx.ellipse(0, 0, dw / 2, dh / 2, 0, 0, Math.PI * 2);
                ctx.stroke();
            } else if (s.shape === 'rounded_rectangle') {
                const r = Math.min(dw, dh) * 0.15;
                ctx.beginPath();
                if (ctx.roundRect) {
                    ctx.roundRect(-dw / 2, -dh / 2, dw, dh, r);
                } else {
                    ctx.strokeRect(-dw / 2, -dh / 2, dw, dh);
                }
                ctx.stroke();
            } else if (s.shape === 'heart') {
                ctx.beginPath();
                const topCurveHeight = dh * 0.3;
                ctx.moveTo(0, dh / 2);
                ctx.bezierCurveTo(-dw / 2, dh * 0.1, -dw / 2, -dh / 2 + topCurveHeight, -dw / 4, -dh / 2);
                ctx.bezierCurveTo(0, -dh / 2, 0, -dh / 2 + topCurveHeight, 0, -dh / 2 + topCurveHeight);
                ctx.bezierCurveTo(0, -dh / 2 + topCurveHeight, 0, -dh / 2, dw / 4, -dh / 2);
                ctx.bezierCurveTo(dw / 2, -dh / 2 + topCurveHeight, dw / 2, dh * 0.1, 0, dh / 2);
                ctx.stroke();
            } else {
                ctx.strokeRect(-dw / 2, -dh / 2, dw, dh);
            }

            ctx.fillStyle = isSel ? '#0d6efd' : '#f59e0b';
            ctx.font = 'bold 14px sans-serif';
            ctx.fillText('#' + (i + 1), -dw / 2 + 6, -dh / 2 + 18);
            ctx.restore();

            if (isSel) {
                const hs = 8;
                [[dx, dy], [dx + dw, dy], [dx, dy + dh], [dx + dw, dy + dh]].forEach(([cx, cy]) => {
                    ctx.fillStyle = '#0d6efd';
                    ctx.fillRect(cx - hs / 2, cy - hs / 2, hs, hs);
                });
                if (hasOutline(s)) {
                    s.points.forEach((p, k) => {
                        const v = vertexImg(s, p);
                        ctx.beginPath();
                        ctx.arc(v[0] * scale, v[1] * scale, k === dragVertex ? 6 : 5, 0, Math.PI * 2);
                        ctx.fillStyle = '#ffffff';
                        ctx.fill();
                        ctx.lineWidth = 2;
                        ctx.strokeStyle = '#0d6efd';
                        ctx.stroke();
                    });
                }
            }
        });
    }

    function renderTable() {
        tbody.innerHTML = '';
        slots.forEach((s, i) => {
            const tr = document.createElement('tr');
            if (i === selectedIndex) tr.classList.add('table-active');
            tr.innerHTML = `
                <td>#${i + 1}</td>
                <td>${Math.round(s.x)}</td>
                <td>${Math.round(s.y)}</td>
                <td>${Math.round(s.width)}</td>
                <td>${Math.round(s.height)}</td>
                <td><span class="badge bg-secondary">${s.shape || 'rectangle'}${hasOutline(s) ? ' · outline' : ''}</span></td>
                <td>${Math.round(s.rotation || 0)}°</td>
            `;
            tr.addEventListener('click', () => { selectedIndex = i; redraw(); renderTable(); syncPropsPanel(); });
            tbody.appendChild(tr);
        });

        document.getElementById('slot_positions').value = JSON.stringify(slots);
    }

    // Canvas Mouse interaction
    canvas.addEventListener('mousedown', function (e) {
        const rect = canvas.getBoundingClientRect();
        const mx = (e.clientX - rect.left) / scale;
        const my = (e.clientY - rect.top) / scale;

        if (selectedIndex !== -1 && slots[selectedIndex] && hasOutline(slots[selectedIndex])) {
            const vi = vertexAt(slots[selectedIndex], mx, my);
            if (vi !== -1) { dragVertex = vi; mode = 'vertex'; redraw(); return; }
        }
        if (selectedIndex !== -1 && slots[selectedIndex]) {
            const c = cornerAt(slots[selectedIndex], mx, my);
            if (c) { rzCorner = c; mode = 'resize'; return; }
            if (hasOutline(slots[selectedIndex]) && edgeAt(slots[selectedIndex], mx, my)) {
                mode = 'move';
                moveOffset = { x: mx - slots[selectedIndex].x, y: my - slots[selectedIndex].y };
                return;
            }
        }

        selectedIndex = -1;
        for (let i = slots.length - 1; i >= 0; i--) {
            const s = slots[i];
            if (hitSlot(s, mx, my)) {
                selectedIndex = i;
                mode = 'move';
                moveOffset = { x: mx - s.x, y: my - s.y };
                break;
            }
        }

        if (selectedIndex === -1) {
            mode = 'draw';
            dragStart = { x: mx, y: my };
        }
        redraw();
        renderTable();
        syncPropsPanel();
    });

    canvas.addEventListener('mousemove', function (e) {
        if (!mode) return;
        const rect = canvas.getBoundingClientRect();
        const mx = (e.clientX - rect.left) / scale;
        const my = (e.clientY - rect.top) / scale;

        if (mode === 'vertex' && selectedIndex !== -1 && dragVertex !== -1) {
            const s = slots[selectedIndex];
            const cx = Math.max(0, Math.min(naturalWidth || 1e9, mx)), cy = Math.max(0, Math.min(naturalHeight || 1e9, my));
            s.points[dragVertex] = imgToNorm(s, cx, cy);
            redraw();
            return;
        }
        if (mode === 'resize' && selectedIndex !== -1) {
            const s = slots[selectedIndex];
            const right = s.x + s.width, bottom = s.y + s.height, min = 15;
            if (rzCorner.includes('w')) { const nx = Math.min(mx, right - min); s.width = right - nx; s.x = Math.max(0, nx); }
            if (rzCorner.includes('e')) { s.width = Math.max(min, mx - s.x); }
            if (rzCorner.includes('n')) { const ny = Math.min(my, bottom - min); s.height = bottom - ny; s.y = Math.max(0, ny); }
            if (rzCorner.includes('s')) { s.height = Math.max(min, my - s.y); }
            redraw();
        } else if (mode === 'move' && selectedIndex !== -1) {
            slots[selectedIndex].x = Math.max(0, mx - moveOffset.x);
            slots[selectedIndex].y = Math.max(0, my - moveOffset.y);
            redraw();
        } else if (mode === 'draw') {
            redraw();
            const w = mx - dragStart.x;
            const h = my - dragStart.y;
            ctx.strokeStyle = '#00ff00';
            ctx.lineWidth = 2;
            ctx.strokeRect(dragStart.x * scale, dragStart.y * scale, w * scale, h * scale);
        }
    });

    canvas.addEventListener('mouseup', function (e) {
        if (mode === 'vertex' && selectedIndex !== -1 && slots[selectedIndex] && hasOutline(slots[selectedIndex])) {
            renormalizeOutline(slots[selectedIndex]);
            dragVertex = -1;
            mode = null;
            redraw(); renderTable(); syncPropsPanel();
            return;
        }
        if (mode === 'draw') {
            const rect = canvas.getBoundingClientRect();
            const mx = (e.clientX - rect.left) / scale;
            const my = (e.clientY - rect.top) / scale;
            const x = Math.min(dragStart.x, mx);
            const y = Math.min(dragStart.y, my);
            const w = Math.abs(mx - dragStart.x);
            const h = Math.abs(my - dragStart.y);

            if (w > 15 && h > 15) {
                slots.push({ x: Math.round(x), y: Math.round(y), width: Math.round(w), height: Math.round(h), shape: 'rectangle', rotation: 0 });
                selectedIndex = slots.length - 1;
            }
        }
        mode = null;
        redraw();
        renderTable();
        syncPropsPanel();
    });

    document.getElementById('addSlotBtn').addEventListener('click', function () {
        const nw = naturalWidth || 600;
        const nh = naturalHeight || 800;
        const defaultW = Math.max(80, Math.round(nw * 0.28));
        const defaultH = Math.max(80, Math.round(nh * 0.25));
        const defaultX = Math.max(10, Math.round((nw - defaultW) / 2));
        const defaultY = Math.max(10, Math.round((nh - defaultH) / 2));

        slots.push({
            x: defaultX,
            y: defaultY,
            width: defaultW,
            height: defaultH,
            shape: 'rectangle',
            rotation: 0
        });
        selectedIndex = slots.length - 1;
        redraw();
        renderTable();
        syncPropsPanel();
        detectStatus.textContent = `${slots.length} slots configured`;
        detectStatus.className = 'badge bg-success';
    });

    canvas.addEventListener('dblclick', function (e) {
        if (selectedIndex === -1 || !slots[selectedIndex] || !hasOutline(slots[selectedIndex])) return;
        const rect = canvas.getBoundingClientRect();
        const mx = (e.clientX - rect.left) / scale;
        const my = (e.clientY - rect.top) / scale;
        const s = slots[selectedIndex];
        const vi = vertexAt(s, mx, my);
        if (vi !== -1) {
            if (s.points.length > 3) s.points.splice(vi, 1);
        } else {
            const ed = edgeAt(s, mx, my);
            if (!ed) return;
            s.points.splice(ed.index + 1, 0, imgToNorm(s, ed.x, ed.y));
        }
        renormalizeOutline(s);
        redraw(); renderTable(); syncPropsPanel();
    });

    document.getElementById('editPointsBtn').addEventListener('click', function () {
        if (selectedIndex === -1 || !slots[selectedIndex]) {
            Swal.fire('Select a Slot', 'Click a slot first, then click Edit Points.', 'info');
            return;
        }
        const s = slots[selectedIndex];
        if (!hasOutline(s)) {
            const pts = outlineFromShape(s);
            if ((s.rotation || 0) !== 0) {
                s.points = pts;
                renormalizeOutline(s);
            } else {
                s.points = pts;
            }
            if (s.shape !== 'heart') s.shape = 'polygon';
        }
        redraw(); renderTable(); syncPropsPanel();
        detectStatus.textContent = 'Drag the white handles to reshape slot #' + (selectedIndex + 1);
        detectStatus.className = 'badge bg-info text-dark';
    });

    document.getElementById('addHeartBtn').addEventListener('click', function () {
        const nw = naturalWidth || 600, nh = naturalHeight || 800;
        const dim = Math.max(120, Math.round(Math.min(nw, nh) * 0.32));
        slots.push({
            x: Math.max(10, Math.round((nw - dim) / 2)),
            y: Math.max(10, Math.round((nh - dim) / 2)),
            width: dim,
            height: dim,
            shape: 'heart',
            rotation: 0
        });
        selectedIndex = slots.length - 1;
        redraw(); renderTable(); syncPropsPanel();
        detectStatus.textContent = `${slots.length} slots configured`;
        detectStatus.className = 'badge bg-success';
    });

    document.getElementById('addCircleBtn').addEventListener('click', function () {
        const nw = naturalWidth || 600, nh = naturalHeight || 800;
        const dim = Math.max(100, Math.round(Math.min(nw, nh) * 0.28));
        slots.push({
            x: Math.max(10, Math.round((nw - dim) / 2)),
            y: Math.max(10, Math.round((nh - dim) / 2)),
            width: dim,
            height: dim,
            shape: 'circle',
            rotation: 0
        });
        selectedIndex = slots.length - 1;
        redraw(); renderTable(); syncPropsPanel();
        detectStatus.textContent = `${slots.length} slots configured`;
        detectStatus.className = 'badge bg-success';
    });

    document.getElementById('duplicateSlot').addEventListener('click', function () {
        if (selectedIndex === -1 || !slots[selectedIndex]) {
            Swal.fire('Select a Slot', 'Click any slot box to select it, then click Duplicate.', 'info');
            return;
        }
        const s = slots[selectedIndex];
        slots.push({
            x: s.x + 25,
            y: s.y + 25,
            width: s.width,
            height: s.height,
            shape: s.shape || 'rectangle',
            rotation: s.rotation || 0,
            points: Array.isArray(s.points) ? s.points.map(p => p.slice()) : undefined
        });
        selectedIndex = slots.length - 1;
        redraw(); renderTable(); syncPropsPanel();
        detectStatus.textContent = `${slots.length} slots configured`;
        detectStatus.className = 'badge bg-success';
    });

    document.querySelectorAll('.preset-opt').forEach(item => {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            const preset = this.getAttribute('data-preset');
            const nw = naturalWidth || 600, nh = naturalHeight || 800;
            slots = [];

            if (preset === '3x3') {
                const marginX = Math.round(nw * 0.1), marginY = Math.round(nh * 0.1);
                const gridW = nw - 2 * marginX, gridH = nh - 2 * marginY;
                const gapX = Math.round(gridW * 0.04), gapY = Math.round(gridH * 0.04);
                const slotW = Math.round((gridW - 2 * gapX) / 3);
                const slotH = Math.round((gridH - 2 * gapY) / 3);
                for (let r = 0; r < 3; r++) {
                    for (let c = 0; c < 3; c++) {
                        slots.push({
                            x: marginX + c * (slotW + gapX),
                            y: marginY + r * (slotH + gapY),
                            width: slotW,
                            height: slotH,
                            shape: 'rectangle',
                            rotation: 0
                        });
                    }
                }
            } else if (preset === '2x2') {
                const marginX = Math.round(nw * 0.12), marginY = Math.round(nh * 0.12);
                const gridW = nw - 2 * marginX, gridH = nh - 2 * marginY;
                const gap = Math.round(Math.min(gridW, gridH) * 0.05);
                const slotW = Math.round((gridW - gap) / 2);
                const slotH = Math.round((gridH - gap) / 2);
                for (let r = 0; r < 2; r++) {
                    for (let c = 0; c < 2; c++) {
                        slots.push({
                            x: marginX + c * (slotW + gap),
                            y: marginY + r * (slotH + gap),
                            width: slotW,
                            height: slotH,
                            shape: 'rectangle',
                            rotation: 0
                        });
                    }
                }
            } else if (preset === 'heart_family') {
                const mainSize = Math.round(Math.min(nw, nh) * 0.52);
                slots.push({
                    x: Math.round((nw - mainSize) / 2),
                    y: Math.round(nh * 0.15),
                    width: mainSize,
                    height: mainSize,
                    shape: 'heart',
                    rotation: 0
                });
                const subSize = Math.round(mainSize * 0.44);
                const positions = [
                    [Math.round(nw * 0.12), Math.round(nh * 0.58)],
                    [Math.round(nw * 0.32), Math.round(nh * 0.68)],
                    [Math.round(nw * 0.54), Math.round(nh * 0.68)],
                    [Math.round(nw * 0.74), Math.round(nh * 0.58)]
                ];
                positions.forEach(([x, y]) => {
                    slots.push({ x, y, width: subSize, height: subSize, shape: 'heart', rotation: 0 });
                });
            } else if (preset === 'strip3') {
                const slotW = Math.round(nw * 0.35);
                const slotH = Math.round(nh * 0.26);
                const startX = Math.round((nw - slotW) / 2);
                const gapY = Math.round(nh * 0.04);
                const startY = Math.round(nh * 0.08);
                for (let i = 0; i < 3; i++) {
                    slots.push({ x: startX, y: startY + i * (slotH + gapY), width: slotW, height: slotH, shape: 'rounded_rectangle', rotation: 0 });
                }
            } else if (preset === 'strip4') {
                const slotW = Math.round(nw * 0.32);
                const slotH = Math.round(nh * 0.20);
                const startX = Math.round((nw - slotW) / 2);
                const gapY = Math.round(nh * 0.03);
                const startY = Math.round(nh * 0.05);
                for (let i = 0; i < 4; i++) {
                    slots.push({ x: startX, y: startY + i * (slotH + gapY), width: slotW, height: slotH, shape: 'rounded_rectangle', rotation: 0 });
                }
            }

            selectedIndex = -1;
            redraw(); renderTable(); syncPropsPanel();
            detectStatus.textContent = `${slots.length} slots applied from preset`;
            detectStatus.className = 'badge bg-success';
        });
    });

    document.getElementById('deleteSelected').addEventListener('click', function () {
        if (selectedIndex !== -1 && slots[selectedIndex]) {
            slots.splice(selectedIndex, 1);
            selectedIndex = -1;
            redraw();
            renderTable();
            syncPropsPanel();
            detectStatus.textContent = `${slots.length} slots configured`;
            detectStatus.className = slots.length ? 'badge bg-success' : 'badge bg-secondary';
        } else {
            Swal.fire('No Slot Selected', 'Click on a slot box or table row to select it, then click Delete.', 'info');
        }
    });

    document.getElementById('clearSlots').addEventListener('click', function () {
        if (!slots.length) return;
        Swal.fire({
            title: 'Clear all slots?',
            text: 'All photo openings will be removed. You can draw new ones or click Auto Detect.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, clear all'
        }).then((result) => {
            if (result.isConfirmed) {
                slots = [];
                selectedIndex = -1;
                redraw();
                renderTable();
                syncPropsPanel();
                detectStatus.textContent = '0 slots configured';
                detectStatus.className = 'badge bg-secondary';
            }
        });
    });

    document.getElementById('frameForm').addEventListener('submit', function (e) {
        if (!slots.length) {
            e.preventDefault();
            Swal.fire('No Slots Defined', 'Please define at least one slot opening on the template image.', 'warning');
            return;
        }
        document.getElementById('slot_positions').value = JSON.stringify(slots);
    });
})();
</script>
@endpush