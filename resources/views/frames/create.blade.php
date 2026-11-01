@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Create New Frame</h1>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('frames.store') }}" method="POST" enctype="multipart/form-data" id="frameForm">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label">Frame Name</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
        </div>

        <div class="mb-3">
            <label for="template" class="form-label">Frame Template Image</label>
            <input type="file" name="template" id="template" class="form-control" accept="image/*" required>
            <small class="text-muted">Openings are detected automatically after you choose the image. Adjust the boxes if needed.</small>
        </div>

        <div class="mb-3">
            <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                <button type="button" class="btn btn-outline-primary btn-sm" id="redetectBtn">Re-detect</button>
                <label class="d-flex align-items-center gap-1 mb-0 small">
                    Sensitivity
                    <input type="range" id="sensitivity" min="10" max="60" value="28" style="width:100px;">
                </label>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="sortSlots">Sort by Position</button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="deleteSelected">Delete Selected</button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="undoSlot">Undo Last</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="clearSlots">Clear All</button>
            </div>
            <div class="d-flex gap-2 mb-2">
                <span class="badge bg-secondary" id="detectStatus"></span>
                <span class="badge bg-info" id="uniformBadge"></span>
            </div>
            <div id="canvasWrapper" style="position:relative; display:inline-block; border:1px solid #ccc;">
                <canvas id="slotCanvas"></canvas>
            </div>

            <!-- Slot Properties Panel (hidden by default) -->
            <div id="slotProps" class="d-flex gap-3 align-items-center mt-2 p-2 border rounded" style="display:none !important;">
                <strong>Selected slot #<span id="propIndex"></span>:</strong>
                <label class="d-flex align-items-center gap-1 mb-0">
                    Shape
                    <select id="propShape" class="form-select form-select-sm" style="width:auto;">
                        <option value="rectangle">Rectangle</option>
                        <option value="circle">Circle</option>
                        <option value="ellipse">Ellipse / Oval</option>
                    </select>
                </label>
                <label class="d-flex align-items-center gap-1 mb-0">
                    Rotation
                    <input type="range" id="propRotation" min="-45" max="45" step="1" value="0" style="width:120px;">
                    <span id="propRotationVal">0°</span>
                </label>
            </div>

            <div class="form-text">Click a box to select it (drag corners to resize, drag body to move, click the × to delete). Click empty space and drag to add a new box.</div>
        </div>

        <div class="mb-3">
            <table class="table table-sm table-bordered" id="slotTable">
                <thead><tr><th>#</th><th>X</th><th>Y</th><th>Width</th><th>Height</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>

        <input type="hidden" name="slot_positions" id="slot_positions">

        <button type="submit" class="btn btn-primary">Create Frame</button>
        <a href="{{ route('frames.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script>
(function () {
    const templateInput = document.getElementById('template');
    const canvas = document.getElementById('slotCanvas');
    const ctx = canvas.getContext('2d');
    const tbody = document.querySelector('#slotTable tbody');
    const uniformBadge = document.getElementById('uniformBadge');
    const detectStatus = document.getElementById('detectStatus');
    const sensitivity = document.getElementById('sensitivity');

    let img = new Image();
    let naturalWidth = 0, naturalHeight = 0, scale = 1;
    let slots = [];
    let selectedIndex = -1;
    let mode = null; // 'draw' | 'move' | 'resize'
    let dragStart = { x: 0, y: 0 };
    let moveOffset = { x: 0, y: 0 };
    let resizeCorner = null;
    let currentFile = null;

    // ---- Properties Panel elements ----
    const slotProps = document.getElementById('slotProps');
    const propIndex = document.getElementById('propIndex');
    const propShape = document.getElementById('propShape');
    const propRotation = document.getElementById('propRotation');
    const propRotationVal = document.getElementById('propRotationVal');

    // ---- Synchronise the properties panel with the selected slot ----
    function syncPropsPanel() {
        if (selectedIndex === -1 || !slots[selectedIndex]) {
            slotProps.style.setProperty('display', 'none', 'important');
            return;
        }
        const s = slots[selectedIndex];
        // Ensure default properties exist
        if (!s.shape) s.shape = 'rectangle';
        if (typeof s.rotation !== 'number') s.rotation = 0;

        propIndex.textContent = selectedIndex + 1;
        propShape.value = s.shape;
        propRotation.value = s.rotation;
        propRotationVal.textContent = Math.round(s.rotation) + '°';
        slotProps.style.setProperty('display', 'flex', 'important');
    }

    // ---- Listen for property changes ----
    propShape.addEventListener('change', function () {
        if (selectedIndex === -1) return;
        slots[selectedIndex].shape = propShape.value;
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

    // ---- Image loading ----
    templateInput.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        currentFile = file;
        const reader = new FileReader();
        reader.onload = function (ev) {
            img = new Image();
            img.onload = function () {
                naturalWidth = img.naturalWidth;
                naturalHeight = img.naturalHeight;
                const maxDisplayWidth = 700;
                scale = naturalWidth > maxDisplayWidth ? maxDisplayWidth / naturalWidth : 1;
                canvas.width = naturalWidth * scale;
                canvas.height = naturalHeight * scale;
                slots = [];
                selectedIndex = -1;
                redraw();
                renderTable();
                syncPropsPanel();
                runAutoDetect(); // auto-run immediately after image loads
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
            const res = await fetch("{{ route('frames.detectSlots') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: fd,
            });
            const data = await res.json();
            if (data.slots && data.slots.length) {
                slots = data.slots.map(s => ({ ...s, shape: 'rectangle', rotation: 0 }));
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
        } catch (err) {
            detectStatus.textContent = 'Auto-detect failed — draw slots manually.';
            detectStatus.className = 'badge bg-danger';
        }
    }

    // ---- Drawing (with shape & rotation) ----
    function redraw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        slots.forEach((s, i) => {
            const dx = s.x * scale, dy = s.y * scale, dw = s.width * scale, dh = s.height * scale;
            const isSel = i === selectedIndex;
            const rot = ((s.rotation || 0) * Math.PI) / 180;

            ctx.save();
            ctx.translate(dx + dw / 2, dy + dh / 2);
            ctx.rotate(rot);
            ctx.lineWidth = 2;
            ctx.strokeStyle = isSel ? '#0d6efd' : '#ff0000';

            if (s.shape === 'circle' || s.shape === 'ellipse') {
                ctx.beginPath();
                ctx.ellipse(0, 0, dw / 2, dh / 2, 0, 0, Math.PI * 2);
                ctx.stroke();
            } else {
                ctx.strokeRect(-dw / 2, -dh / 2, dw, dh);
            }

            ctx.fillStyle = isSel ? '#0d6efd' : '#ff0000';
            ctx.font = 'bold 14px sans-serif';
            ctx.fillText('#' + (i + 1), -dw / 2 + 4, -dh / 2 + 16);
            ctx.restore();

            if (isSel) {
                const hs = 8;
                [[dx, dy], [dx + dw, dy], [dx, dy + dh], [dx + dw, dy + dh]].forEach(([cx, cy]) => {
                    ctx.fillStyle = '#0d6efd';
                    ctx.fillRect(cx - hs / 2, cy - hs / 2, hs, hs);
                });
                ctx.fillStyle = '#dc3545';
                ctx.fillRect(dx + dw - 18, dy, 18, 18);
                ctx.fillStyle = '#fff';
                ctx.font = '14px sans-serif';
                ctx.fillText('×', dx + dw - 13, dy + 14);
            }
        });
    }

    // ---- Hit detection helpers (bounding‑box based) ----
    function insideBox(px, py, s) {
        const dx = s.x * scale, dy = s.y * scale, dw = s.width * scale, dh = s.height * scale;
        return px >= dx && px <= dx + dw && py >= dy && py <= dy + dh;
    }

    function hitDeleteIcon(px, py, s) {
        const dx = s.x * scale + s.width * scale - 18, dy = s.y * scale;
        return px >= dx && px <= dx + 18 && py >= dy && py <= dy + 18;
    }

    function cornerAt(px, py, s) {
        const hs = 10;
        const dx = s.x * scale, dy = s.y * scale, dw = s.width * scale, dh = s.height * scale;
        const corners = { tl: [dx, dy], tr: [dx + dw, dy], bl: [dx, dy + dh], br: [dx + dw, dy + dh] };
        for (const key in corners) {
            const [cx, cy] = corners[key];
            if (Math.abs(px - cx) <= hs && Math.abs(py - cy) <= hs) return key;
        }
        return null;
    }

    // ---- Mouse events ----
    canvas.addEventListener('mousedown', function (e) {
        if (!naturalWidth) return;
        const rect = canvas.getBoundingClientRect();
        const px = e.clientX - rect.left, py = e.clientY - rect.top;

        if (selectedIndex !== -1) {
            const s = slots[selectedIndex];
            if (hitDeleteIcon(px, py, s)) {
                slots.splice(selectedIndex, 1);
                selectedIndex = -1;
                redraw();
                renderTable();
                syncPropsPanel();
                return;
            }
            const corner = cornerAt(px, py, s);
            if (corner) { mode = 'resize'; resizeCorner = corner; return; }
            if (insideBox(px, py, s)) {
                mode = 'move';
                moveOffset = { x: px - s.x * scale, y: py - s.y * scale };
                return;
            }
        }

        for (let i = slots.length - 1; i >= 0; i--) {
            if (insideBox(px, py, slots[i])) {
                selectedIndex = i;
                mode = 'move';
                moveOffset = { x: px - slots[i].x * scale, y: py - slots[i].y * scale };
                redraw();
                renderTable();
                syncPropsPanel();
                return;
            }
        }

        selectedIndex = -1;
        mode = 'draw';
        dragStart = { x: px, y: py };
        redraw();
        syncPropsPanel();
    });

    canvas.addEventListener('mousemove', function (e) {
        if (!mode) return;
        const rect = canvas.getBoundingClientRect();
        const px = e.clientX - rect.left, py = e.clientY - rect.top;

        if (mode === 'draw') {
            redraw();
            ctx.strokeStyle = '#0d6efd'; ctx.lineWidth = 2;
            ctx.strokeRect(Math.min(dragStart.x, px), Math.min(dragStart.y, py), Math.abs(px - dragStart.x), Math.abs(py - dragStart.y));
        } else if (mode === 'move' && selectedIndex !== -1) {
            const s = slots[selectedIndex];
            let nx = (px - moveOffset.x) / scale;
            let ny = (py - moveOffset.y) / scale;
            nx = Math.max(0, Math.min(nx, naturalWidth - s.width));
            ny = Math.max(0, Math.min(ny, naturalHeight - s.height));
            s.x = Math.round(nx); s.y = Math.round(ny);
            redraw();
        } else if (mode === 'resize' && selectedIndex !== -1) {
            const s = slots[selectedIndex];
            let x1 = s.x * scale, y1 = s.y * scale, x2 = (s.x + s.width) * scale, y2 = (s.y + s.height) * scale;
            if (resizeCorner.includes('t')) y1 = py;
            if (resizeCorner.includes('b')) y2 = py;
            if (resizeCorner.includes('l')) x1 = px;
            if (resizeCorner.includes('r')) x2 = px;
            s.x = Math.round(Math.min(x1, x2) / scale);
            s.y = Math.round(Math.min(y1, y2) / scale);
            s.width = Math.max(5, Math.round(Math.abs(x2 - x1) / scale));
            s.height = Math.max(5, Math.round(Math.abs(y2 - y1) / scale));
            redraw();
        }
    });

    canvas.addEventListener('mouseup', function (e) {
        if (mode === 'draw') {
            const rect = canvas.getBoundingClientRect();
            const px = e.clientX - rect.left, py = e.clientY - rect.top;
            const dx = Math.min(dragStart.x, px), dy = Math.min(dragStart.y, py);
            const dw = Math.abs(px - dragStart.x), dh = Math.abs(py - dragStart.y);
            if (dw >= 8 && dh >= 8) {
                slots.push({
                    x: Math.round(dx / scale), y: Math.round(dy / scale),
                    width: Math.round(dw / scale), height: Math.round(dh / scale),
                    shape: 'rectangle',
                    rotation: 0
                });
                selectedIndex = slots.length - 1;
            }
        }
        mode = null; resizeCorner = null;
        redraw();
        renderTable();
        syncPropsPanel();
    });

    // ---- Keyboard delete ----
    document.addEventListener('keydown', function (e) {
        const tag = document.activeElement.tagName;
        if ((e.key === 'Delete' || e.key === 'Backspace') && selectedIndex !== -1 && tag !== 'INPUT' && tag !== 'TEXTAREA') {
            slots.splice(selectedIndex, 1);
            selectedIndex = -1;
            redraw();
            renderTable();
            syncPropsPanel();
        }
    });

    // ---- Button actions ----
    document.getElementById('deleteSelected').addEventListener('click', function () {
        if (selectedIndex === -1) return;
        slots.splice(selectedIndex, 1);
        selectedIndex = -1;
        redraw();
        renderTable();
        syncPropsPanel();
    });

    document.getElementById('undoSlot').addEventListener('click', function () {
        slots.pop();
        selectedIndex = -1;
        redraw();
        renderTable();
        syncPropsPanel();
    });

    document.getElementById('clearSlots').addEventListener('click', function () {
        slots = [];
        selectedIndex = -1;
        redraw();
        renderTable();
        syncPropsPanel();
    });

    document.getElementById('sortSlots').addEventListener('click', function () {
        if (!slots.length) return;
        const avgH = slots.reduce((a, s) => a + s.height, 0) / slots.length;
        const tol = avgH * 0.6;
        const sorted = [...slots].sort((a, b) => a.y - b.y);
        const rows = [];
        sorted.forEach(s => {
            let row = rows.find(r => Math.abs(r.y - s.y) <= tol);
            if (!row) { row = { y: s.y, items: [] }; rows.push(row); }
            row.items.push(s);
        });
        rows.sort((a, b) => a.y - b.y);
        const result = [];
        rows.forEach(r => { r.items.sort((a, b) => a.x - b.x); result.push(...r.items); });
        slots = result;
        selectedIndex = -1;
        redraw();
        renderTable();
        syncPropsPanel();
    });

    // ---- Table rendering ----
    function renderTable() {
        tbody.innerHTML = '';
        slots.forEach((s, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${i + 1}</td><td>${s.x}</td><td>${s.y}</td><td>${s.width}</td><td>${s.height}</td>`;
            tbody.appendChild(tr);
        });
        if (slots.length > 0) {
            const w = slots[0].width, h = slots[0].height;
            const uniform = slots.every(s => Math.abs(s.width - w) <= 1 && Math.abs(s.height - h) <= 1);
            uniformBadge.textContent = uniform
                ? `All ${slots.length} blocks are the same size (${w}×${h}px)`
                : `${slots.length} blocks — different sizes`;
            uniformBadge.className = 'badge ' + (uniform ? 'bg-success' : 'bg-warning text-dark');
        } else {
            uniformBadge.textContent = '';
        }
    }

    // ---- Form submit ----
    document.getElementById('frameForm').addEventListener('submit', function (e) {
        if (slots.length < 1) {
            e.preventDefault();
            alert('Please have at least one slot (auto-detected or manually drawn).');
            return;
        }
        document.getElementById('slot_positions').value = JSON.stringify(slots);
    });
})();
</script>
@endsection