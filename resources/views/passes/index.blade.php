@extends('layouts.app')
@section('title', 'Visitor Pass Management')


@section('content')

@php
    // Maps each building code to its image filename in public/images/buildings/.
    // SWA has no photo yet — falls back to RVM's until one is supplied.
    $buildingImages = [
        'NW'    => 'northwing.png',
        'SW'    => 'southwing.png',
        'RVM'   => 'rvm.png',
        'NG'    => 'northgate.png',
        'MB'    => 'main.png',
        'SWA'   => 'rvm.png', // TODO: swap once South Wing Annex photo is supplied
    ];
@endphp

{{-- Hero — reuses the exact segmented-border treatment from scanner/index.blade.php and logs/index.blade.php --}}
<div class="hero-govt">
    <div class="sunburst-red" aria-hidden="true"></div>

    <div class="hero-frame">
        <div class="hero-frame-rattan" aria-hidden="true"></div>

        <div class="hero-inner-panel flex items-center gap-5">
            <div class="hero-logo-badge hidden sm:flex">
                <img src="{{ asset('images/lsb-icon.png') }}" alt="LSB emblem">
            </div>
            <div>
                <p class="eyebrow">Perimeter Security Group &middot; Pass registry</p>
                <h1>Visitor Pass Management</h1>
                <p class="lead">5 passes per building - assign a visitor to issue and print a badge.</p>
            </div>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="alert-govt-success" role="status">
        <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="alert-govt-error" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i>
        {{ $errors->first() }}
    </div>
@endif

<div class="card-govt card-vivid p-5 shadow-sm flex flex-wrap justify-between items-center gap-4" style="--ribbon-color: var(--brand-gold)">
    <div>
                <label class="card-header-title text-2xl font-bold flex items-center gap-2">
            <i class="fa-solid fa-id-card-clip"></i> Visitor Pass Registry
        </label>
        <p class="text-xs mt-1 opacity-80">Register a visitor with their agenda, and details to a specific visitor pass.</p>
     </div>
    <button onclick="document.getElementById('registerModal').classList.remove('hidden')" class="btn-govt-cta px-4 py-2 text-xs flex-shrink-0">
        <i class="fa-solid fa-user-plus"></i> Register visitor &amp; issue pass
    </button>
</div>

{{-- Building grid — click a building to view/manage its passes. When the
     total count is odd, the last card spans both columns and is capped to
     half-width + centered so it doesn't sit awkwardly alone on the left.
     Subtext now shows live active/available counts instead of static copy,
     and cards lift on hover to signal clickability. --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @foreach ($buildings as $b)
        @php
            $buildingPasses = $passes->where('building_id', $b->id);
            $activeCount = $buildingPasses->whereNotNull('visitor_name')->count();
            $availableCount = $buildingPasses->whereNull('visitor_name')->count();
        @endphp
        <button type="button" onclick="openBuildingModal({{ $b->id }})"
                class="building-card-frame relative overflow-hidden rounded-2xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-200 ease-out text-left w-full p-2 cursor-pointer {{ $loop->last && $loop->count % 2 !== 0 ? 'md:col-span-2 md:max-w-[calc(50%-0.5rem)] md:mx-auto' : '' }}"
                style="background: {{ $b->color_hex }};">
            <div class="hero-frame-rattan" aria-hidden="true"></div>
            <div class="relative z-10 flex items-stretch h-32">
                <div class="bg-white rounded-l-xl px-4 flex flex-col justify-center flex-shrink-0 w-2/5">
                    <h3 class="text-base text-slate-900">{{ $b->name }}</h3>
                    <p class="text-xs text-slate-500 mt-1">{{ $activeCount }} Active &middot; {{ $availableCount }} Available</p>
                </div>
                <div class="flex-1 rounded-r-xl overflow-hidden">
                    <img src="{{ asset('images/buildings/' . ($buildingImages[$b->code] ?? 'main.png')) }}"
                         alt="{{ $b->name }}" class="w-full h-full object-cover opacity-85">
                </div>
            </div>
        </button>
    @endforeach
</div>

{{-- Per-building passes modal --}}
<div id="buildingPassesModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="modal-govt-panel rounded-2xl shadow-2xl max-w-5xl w-full max-h-[85vh] overflow-hidden flex flex-col bg-white">
        {{-- Color-coded accent strip, set per-building via JS in openBuildingModal() --}}
        <div id="buildingModalAccent" class="h-1.5 w-full flex-shrink-0"></div>

        <div class="p-6 pb-4 flex-shrink-0 border-b border-slate-100">
            <div class="flex justify-between items-start gap-4">
                <div>
                    <h3 id="buildingModalTitle" class="font-bold text-slate-800 text-lg"></h3>
                    <p id="buildingModalCount" class="text-xs text-slate-400 mt-0.5"></p>
                </div>
                <button type="button" onclick="closeBuildingModal()"
                        class="bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full p-2.5 transition-colors leading-none flex-shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="flex gap-2 mt-4">
                <button type="button" onclick="filterModalPasses('all')" class="modal-filter-pill is-active" data-filter="all">All</button>
                <button type="button" onclick="filterModalPasses('active')" class="modal-filter-pill" data-filter="active">Active</button>
                <button type="button" onclick="filterModalPasses('available')" class="modal-filter-pill" data-filter="available">Available</button>
            </div>
        </div>

        <div class="px-6 py-5 overflow-y-auto">
            @foreach ($buildings as $b)
                <div id="buildingPassGroup-{{ $b->id }}"
                     class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5"
                     data-building-color="{{ $b->color_hex }}">
                    @forelse ($passes->where('building_id', $b->id) as $p)
                        @php
                            $cardColor = $p->is_multi_building ? 'var(--badge-multi)' : $p->building->color_hex;
                            $buildingLabel = $p->is_multi_building ? 'Multiple Access' : $p->building->name;
                            $statusKey = $p->visitor_name ? 'active' : 'available';
                        @endphp
                        <div class="pass-card-item rounded-2xl border border-slate-200/80 overflow-hidden bg-white shadow-sm" data-status="{{ $statusKey }}">
                            <div class="p-4 flex justify-between items-start border-b border-slate-100">
                                <div>
                                    <span class="text-[0.65rem] font-bold tracking-wide uppercase" style="color: var(--seal-gold);">House of Reps</span>
                                    <div class="pass-card-building">{{ $buildingLabel }}</div>
                                    <div class="text-xs font-semibold text-slate-500">
                                        @if ($p->is_multi_building)
                                            @if ($p->buildings->isEmpty())
                                                Awaiting building assignment
                                            @else
                                                Multiple access: {{ $p->buildings->pluck('name')->join(', ') }}
                                            @endif
                                        @else
                                            Visitor pass
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                    @if ($statusKey === 'active')
                                        <span class="bg-emerald-100 text-emerald-800 font-bold px-3 py-1 rounded-full text-xs">Active</span>
                                    @else
                                        <span class="bg-blue-50 text-blue-700 font-bold px-3 py-1 rounded-full text-xs">Available</span>
                                    @endif
                                    @if ($p->pass_class === 'long_term' && $statusKey === 'active')
                                        <span class="badge-neutral">Long-term &middot; {{ $p->daysRemaining() }}d left</span>
                                    @endif
                                </div>
                            </div>

                            <div class="p-4 space-y-2">
                                <div class="flex justify-between items-center bg-slate-50 border border-slate-100 rounded-xl px-3 py-1.5">
                                    <span class="text-xs text-slate-400">Pass #</span>
                                    <span class="font-mono font-bold text-slate-900 text-sm">{{ $p->pass_number }}</span>
                                </div>
                                <div>
                                    @if ($p->visitor_name)
                                        <div class="text-slate-900 font-bold text-sm truncate">{{ $p->visitor_name }}</div>
                                    @else
                                        <span class="text-slate-400 italic bg-slate-50 px-2 py-0.5 rounded-md text-xs">Unassigned</span>
                                    @endif
                                </div>
                            </div>

                            <div class="px-4 py-3 flex justify-between items-center" style="border-top: 3px solid {{ $cardColor }};">
                                <div class="flex gap-1.5">
                                    @if ($p->visitor_name)
                                        <form method="POST" action="{{ route('passes.unassign', $p) }}"
                                              onsubmit="return confirm('Unassign {{ $p->visitor_name }} from Pass #{{ $p->pass_number }}? The card will be reset and returned to available stock.');">
                                            @csrf
                                            <button type="submit" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-1.5 rounded-full text-xs font-bold transition-colors">
                                                <i class="fa-solid fa-user-xmark"></i> Unassign
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                <a href="{{ route('passes.show', $p) }}"
                                   class="text-white px-3 py-1.5 rounded-full text-xs font-bold flex items-center gap-1.5 transition-transform active:scale-95"
                                   style="background: {{ $cardColor }};">
                                    <i class="fa-solid fa-qrcode"></i> View QR
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 col-span-full text-center py-8">No passes exist for this building yet.</p>
                    @endforelse
                </div>
            @endforeach
        </div>
    </div>
</div>

<div id="registerModal" class="fixed inset-0 bg-slate-900/60 z-50 hidden flex items-center justify-center p-4">
   <div class="modal-govt-panel shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto p-6 space-y-4">
        <h3 class="font-bold text-slate-800">Register visitor &amp; issue pass</h3>

        {{-- Pass type toggle --}}
        <div class="flex gap-2 text-xs">
            <label class="pass-type-option flex-1">
                <input type="radio" name="pass_type_radio" value="single" checked onchange="setPassType('single')" class="sr-only">
                <span class="pass-type-btn is-active" id="passTypeBtnSingle">Single-Building Pass</span>
            </label>
            <label class="pass-type-option flex-1">
                <input type="radio" name="pass_type_radio" value="multi" onchange="setPassType('multi')" class="sr-only">
                <span class="pass-type-btn" id="passTypeBtnMulti">Multiple Access Pass</span>
            </label>
        </div>

                <form method="POST" action="{{ route('passes.register') }}" class="space-y-3 text-xs" id="registerForm">
            @csrf
            <input type="hidden" name="pass_type" id="passTypeInput" value="single">

            <div>
                <label class="block mb-1">Visitor photo</label>
                <div id="photoCaptureArea">
                    <video id="photoVideo" autoplay playsinline class="w-full max-h-56 object-cover rounded border hidden"></video>
                    <canvas id="photoCanvas" class="hidden"></canvas>
                    <img id="photoPreview" class="w-full max-h-56 object-cover rounded border hidden" alt="Captured photo">

                    <div class="flex gap-2 mt-2">
                        <button type="button" id="startCameraBtn" onclick="startCamera()"
                                class="px-3 py-1.5 rounded bg-slate-700 text-white text-xs">
                            Open Camera
                        </button>
                        <button type="button" id="captureBtn" onclick="capturePhoto()"
                                class="px-3 py-1.5 rounded bg-red-600 text-white text-xs hidden">
                            Capture
                        </button>
                        <button type="button" id="retakeBtn" onclick="retakePhoto()"
                                class="px-3 py-1.5 rounded btn-govt-ghost text-xs hidden">
                            Retake
                        </button>
                    </div>
                </div>
                <input type="hidden" name="photo_data" id="photoDataInput">
            </div>

            {{-- ID photo capture — separate from the visitor face photo above.
                 Mirrors the same camera/canvas/preview pattern with id- prefixed
                 element IDs so both capture widgets can run independently. --}}
            <div>
                <label class="block mb-1">ID photo</label>
                <div id="idPhotoCaptureArea">
                    <video id="idPhotoVideo" autoplay playsinline class="w-full max-h-56 object-cover rounded border hidden"></video>
                    <canvas id="idPhotoCanvas" class="hidden"></canvas>
                    <img id="idPhotoPreview" class="w-full max-h-56 object-cover rounded border hidden" alt="Captured ID photo">

                    <div class="flex gap-2 mt-2">
                        <button type="button" id="startIdCameraBtn" onclick="startIdCamera()"
                                class="px-3 py-1.5 rounded bg-slate-700 text-white text-xs">
                            Open Camera
                        </button>
                        <button type="button" id="captureIdBtn" onclick="captureIdPhoto()"
                                class="px-3 py-1.5 rounded bg-red-600 text-white text-xs hidden">
                            Capture
                        </button>
                        <button type="button" id="retakeIdBtn" onclick="retakeIdPhoto()"
                                class="px-3 py-1.5 rounded btn-govt-ghost text-xs hidden">
                            Retake
                        </button>
                    </div>
                </div>
                <input type="hidden" name="id_photo_data" id="idPhotoDataInput">
            </div>

            <div>
                <label class="block mb-1">Visitor full name *</label>
                <input type="text" name="visitor_name" required class="w-full px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Visitor email</label>
                <input type="email" name="visitor_email" class="w-full px-3 py-2" placeholder="For check-out / expiry reminders">
            </div>
            <div>
                <label class="block mb-1">Government ID type *</label>
                <select name="id_type" required class="w-full px-3 py-2 bg-slate-50">
                    <option value="">Select ID type</option>
                    <option value="Driver's License">Driver's License</option>
                    <option value="UMID">UMID</option>
                    <option value="Passport">Passport</option>
                    <option value="SSS ID">SSS ID</option>
                    <option value="PhilHealth ID">PhilHealth ID</option>
                    <option value="PhilSys (National ID)">PhilSys (National ID)</option>
                    <option value="Company ID">Company ID</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div>
                <label class="block mb-1">ID number *</label>
                <input type="text" name="id_ref" required class="w-full px-3 py-2" placeholder="e.g. N01-23-456789">
            </div>
            <div>
                <label class="block mb-1">Purpose of visit *</label>
                <input type="text" name="purpose" required class="w-full px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Registered by</label>
                <input type="text" name="registered_by" class="w-full px-3 py-2" placeholder="Entrance personnel name">
            </div>

            {{-- Day vs Long-term toggle --}}
            <div>
                <label class="block mb-1">Pass duration *</label>
                <div class="flex gap-2">
                    <label class="pass-type-option flex-1">
                        <input type="radio" name="pass_class_radio" value="day" checked onchange="setPassClass('day')" class="sr-only">
                        <span class="pass-type-btn is-active" id="passClassBtnDay">Day</span>
                    </label>
                    <label class="pass-type-option flex-1">
                        <input type="radio" name="pass_class_radio" value="long_term" onchange="setPassClass('long_term')" class="sr-only">
                        <span class="pass-type-btn" id="passClassBtnLongTerm">Long-term</span>
                    </label>
                </div>
                <input type="hidden" name="pass_class" id="passClassInput" value="day">
            </div>

            {{-- Shown only for long_term. Client-side cap hint mirrors the
                 server's 30-*working*-day rule (Mon–Thu) so staff don't pick a
                 date the backend will reject — computed in JS below. --}}
            <div id="expectedReturnField" class="hidden">
                <label class="block mb-1">Expected return date *</label>
                <input type="date" name="expected_return_date" id="expectedReturnInput" class="w-full px-3 py-2">
                <p class="text-slate-400 mt-1" id="expectedReturnCapHint"></p>
            </div>

            {{-- Single-building: pick a building, and the backend auto-assigns
                 the next available pass in that building. Only buildings with
                 at least one free pass are listed. --}}
            <div id="singlePassField">
                <label class="block mb-1">Select building *</label>
                <select name="building_id" id="buildingIdSelect" class="w-full px-3 py-2 bg-slate-50">
                    @foreach ($buildings as $b)
                        @php
                            $available = $passes->where('building_id', $b->id)->where('is_multi_building', false)->whereNull('visitor_name')->count();
                        @endphp
                        @if ($available > 0)
                            <option value="{{ $b->id }}">{{ $b->name }} ({{ $available }} available)</option>
                        @endif
                    @endforeach
                </select>
                <p class="text-slate-400 mt-1">The next available pass in this building will be assigned automatically.</p>
            </div>
            {{-- Multi-building: check every building this pass should open --}}
            <div id="multiBuildingField" class="hidden">
                <label class="block mb-1">Authorized buildings * <span class="text-slate-400">(select 2 or more)</span></label>
                <div class="grid grid-cols-2 gap-2 border border-slate-200 rounded-lg p-3 max-h-40 overflow-y-auto">
                    @foreach ($buildings as $b)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="building_ids_multi" value="{{ $b->id }}" onchange="updateMultiCount()">
                            <span class="w-2 h-2 rounded-full" style="background:{{ $b->color_hex }}"></span>
                            {{ $b->name }}
                        </label>
                    @endforeach
                </div>
                <p class="text-slate-400 mt-1" id="multiCountHint">0 buildings selected</p>
            </div>

            <div class="pt-2 flex justify-end gap-2 sticky bottom-0 bg-white pb-1">
                <button type="button" onclick="closeRegisterModal()" class="px-4 py-2 rounded-lg btn-govt-ghost">Cancel</button>
                <button type="submit" id="registerSubmitBtn" class="px-4 py-2 rounded-lg btn-govt-cta">Assign &amp; issue</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function setPassType(type) {
        document.getElementById('passTypeInput').value = type;

        const single = document.getElementById('singlePassField');
        const multi = document.getElementById('multiBuildingField');
        const passIdSelect = document.getElementById('buildingIdSelect');
        const btnSingle = document.getElementById('passTypeBtnSingle');
        const btnMulti = document.getElementById('passTypeBtnMulti');

        if (type === 'multi') {
            single.classList.add('hidden');
            multi.classList.remove('hidden');
            passIdSelect.removeAttribute('required');
            btnSingle.classList.remove('is-active');
            btnMulti.classList.add('is-active');
        } else {
            single.classList.remove('hidden');
            multi.classList.add('hidden');
            passIdSelect.setAttribute('required', 'required');
            btnSingle.classList.add('is-active');
            btnMulti.classList.remove('is-active');
        }

        updateMultiCount();
    }

    function updateMultiCount() {
        const checked = document.querySelectorAll('input[name="building_ids_multi"]:checked').length;
        document.getElementById('multiCountHint').textContent = checked + ' building' + (checked === 1 ? '' : 's') + ' selected';

        const submitBtn = document.getElementById('registerSubmitBtn');
        const type = document.getElementById('passTypeInput').value;
        submitBtn.disabled = (type === 'multi' && checked < 2);
        submitBtn.style.opacity = submitBtn.disabled ? '0.5' : '1';
    }

    // ---- Day / Long-term pass class toggle ----
    // Mirrors the server's cap: 30 *working* days (Mon–Thu, HOR's compressed week).
    function addWorkingDaysClientSide(startDate, days) {
        const date = new Date(startDate);
        let added = 0;
        while (added < days) {
            date.setDate(date.getDate() + 1);
            const dow = date.getDay(); // 0=Sun..6=Sat
            if (dow >= 1 && dow <= 4) { // Mon–Thu
                added++;
            }
        }
        return date;
    }

    function setPassClass(passClass) {
        document.getElementById('passClassInput').value = passClass;

        const field = document.getElementById('expectedReturnField');
        const input = document.getElementById('expectedReturnInput');
        const btnDay = document.getElementById('passClassBtnDay');
        const btnLongTerm = document.getElementById('passClassBtnLongTerm');

        if (passClass === 'long_term') {
            field.classList.remove('hidden');
            input.setAttribute('required', 'required');
            btnDay.classList.remove('is-active');
            btnLongTerm.classList.add('is-active');

            const cap = addWorkingDaysClientSide(new Date(), 30);
            const capStr = cap.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            input.max = cap.toISOString().split('T')[0];
            document.getElementById('expectedReturnCapHint').textContent =
                'Max 30 working days from today — latest allowed: ' + capStr;
        } else {
            field.classList.add('hidden');
            input.removeAttribute('required');
            input.value = '';
            btnDay.classList.add('is-active');
            btnLongTerm.classList.remove('is-active');
        }
    }
    // ---- End pass class toggle ----

    function openBuildingModal(buildingId) {
        document.querySelectorAll('[id^="buildingPassGroup-"]').forEach(el => el.classList.add('hidden'));
        const group = document.getElementById('buildingPassGroup-' + buildingId);
        group.classList.remove('hidden');

        const card = document.querySelector('[onclick="openBuildingModal(' + buildingId + ')"] h3');
        const buildingName = card ? card.innerText : 'Building';
        document.getElementById('buildingModalTitle').innerText = buildingName + ' — Visitor Passes';

        const total = group.querySelectorAll('.pass-card-item').length;
        document.getElementById('buildingModalCount').innerText =
            total + (total === 1 ? ' pass total' : ' passes total');

        const color = group.dataset.buildingColor || '#1e3a8a';
        document.getElementById('buildingModalAccent').style.background = color;

        filterModalPasses('all');

        document.getElementById('buildingPassesModal').classList.remove('hidden');
    }

    function closeBuildingModal() {
        document.getElementById('buildingPassesModal').classList.add('hidden');
    }

    function filterModalPasses(status) {
        document.querySelectorAll('.modal-filter-pill').forEach(pill => {
            pill.classList.toggle('is-active', pill.dataset.filter === status);
        });

        const visibleGroup = document.querySelector('[id^="buildingPassGroup-"]:not(.hidden)');
        if (!visibleGroup) return;

        visibleGroup.querySelectorAll('.pass-card-item').forEach(card => {
            card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
        });
    }

    // ---- Photo capture (visitor face) ----
    let photoStream = null;

    async function startCamera() {
        const video = document.getElementById('photoVideo');
        try {
            photoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
            video.srcObject = photoStream;
            video.classList.remove('hidden');
            document.getElementById('startCameraBtn').classList.add('hidden');
            document.getElementById('captureBtn').classList.remove('hidden');
        } catch (err) {
            alert('Could not access camera: ' + err.message);
        }
    }

    function capturePhoto() {
        const video = document.getElementById('photoVideo');
        const canvas = document.getElementById('photoCanvas');
        const preview = document.getElementById('photoPreview');

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        document.getElementById('photoDataInput').value = dataUrl;

        preview.src = dataUrl;
        preview.classList.remove('hidden');
        video.classList.add('hidden');
        document.getElementById('captureBtn').classList.add('hidden');
        document.getElementById('retakeBtn').classList.remove('hidden');

        stopCameraStream();
    }

    function retakePhoto() {
        document.getElementById('photoPreview').classList.add('hidden');
        document.getElementById('retakeBtn').classList.add('hidden');
        document.getElementById('photoDataInput').value = '';
        startCamera();
    }

    function stopCameraStream() {
        if (photoStream) {
            photoStream.getTracks().forEach(track => track.stop());
            photoStream = null;
        }
    }

    function resetPhotoCapture() {
        stopCameraStream();
        document.getElementById('photoVideo').classList.add('hidden');
        document.getElementById('photoPreview').classList.add('hidden');
        document.getElementById('retakeBtn').classList.add('hidden');
        document.getElementById('captureBtn').classList.add('hidden');
        document.getElementById('startCameraBtn').classList.remove('hidden');
        document.getElementById('photoDataInput').value = '';
    }
    // ---- End visitor face photo capture ----

    // ---- Photo capture (ID) — separate stream/variable so it can run independently ----
    let idPhotoStream = null;

    async function startIdCamera() {
        const video = document.getElementById('idPhotoVideo');
        try {
            idPhotoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = idPhotoStream;
            video.classList.remove('hidden');
            document.getElementById('startIdCameraBtn').classList.add('hidden');
            document.getElementById('captureIdBtn').classList.remove('hidden');
        } catch (err) {
            alert('Could not access camera: ' + err.message);
        }
    }

    function captureIdPhoto() {
        const video = document.getElementById('idPhotoVideo');
        const canvas = document.getElementById('idPhotoCanvas');
        const preview = document.getElementById('idPhotoPreview');

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        document.getElementById('idPhotoDataInput').value = dataUrl;

        preview.src = dataUrl;
        preview.classList.remove('hidden');
        video.classList.add('hidden');
        document.getElementById('captureIdBtn').classList.add('hidden');
        document.getElementById('retakeIdBtn').classList.remove('hidden');

        stopIdCameraStream();
    }

    function retakeIdPhoto() {
        document.getElementById('idPhotoPreview').classList.add('hidden');
        document.getElementById('retakeIdBtn').classList.add('hidden');
        document.getElementById('idPhotoDataInput').value = '';
        startIdCamera();
    }

    function stopIdCameraStream() {
        if (idPhotoStream) {
            idPhotoStream.getTracks().forEach(track => track.stop());
            idPhotoStream = null;
        }
    }

    function resetIdPhotoCapture() {
        stopIdCameraStream();
        document.getElementById('idPhotoVideo').classList.add('hidden');
        document.getElementById('idPhotoPreview').classList.add('hidden');
        document.getElementById('retakeIdBtn').classList.add('hidden');
        document.getElementById('captureIdBtn').classList.add('hidden');
        document.getElementById('startIdCameraBtn').classList.remove('hidden');
        document.getElementById('idPhotoDataInput').value = '';
    }
    // ---- End ID photo capture ----

    function closeRegisterModal() {
        document.getElementById('registerModal').classList.add('hidden');
        document.getElementById('registerForm').reset();
        resetPhotoCapture();
        resetIdPhotoCapture();
        setPassType('single');
        setPassClass('day');
    }

    document.getElementById('registerForm').addEventListener('submit', function (e) {
        const type = document.getElementById('passTypeInput').value;
        if (type === 'multi') {
            const checked = document.querySelectorAll('input[name="building_ids_multi"]:checked');
            if (checked.length < 2) {
                e.preventDefault();
                alert('Select at least 2 buildings for a Multiple Access pass.');
                return;
            }
            checked.forEach(function (cb) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'building_ids[]';
                hidden.value = cb.value;
                this.appendChild(hidden);
            }, this);
        }
    });
</script>
@endsection