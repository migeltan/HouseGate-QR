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
    $buildingPasses = $passes->filter(fn ($p) => $p->is_multi_building
        ? $p->buildings->contains('id', $b->id)
        : $p->building_id === $b->id);
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

                             $buildingLabel = $p->is_multi_building ? 'North Gate Access' : $b->name;
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

            <div id="registerModal" class="reg-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="registration-title">
    <section class="reg-modal">
        <header class="reg-modal-header">
            <svg class="header-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="6" y="8" width="31" height="24" rx="2" />
                <path d="M12 39h19M18 32v7M39 16h3v20H18" />
            </svg>
            <div>
                <p class="reg-eyebrow">Registration</p>
                <h1 id="registration-title">Register Visitor and Issue Pass</h1>
            </div>
            <button type="button" class="reg-close-button" aria-label="Close" onclick="closeRegisterModal()">&times;</button>
        </header>

        <form method="POST" action="{{ route('passes.register') }}" id="registerForm">
            @csrf

            <main class="reg-modal-body">

                {{-- Step 1: Photo capture — visitor face + ID, both styled to match the mockup's camera panel --}}
                <section class="reg-step-card">
                    <div class="reg-step-heading">
                        <svg class="step-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 13l3-5h9l3 5h6a4 4 0 014 4v20a4 4 0 01-4 4H10a4 4 0 01-4-4V17a4 4 0 014-4h7z" />
                            <circle cx="24" cy="26" r="8" />
                        </svg>
                        <div>
                            <p class="reg-step-label">Step 1: Camera Terminal</p>
                            <h2>Entrance Photo Capture</h2>
                        </div>
                    </div>

                    <div class="reg-camera-layout">
                        <div class="reg-camera-preview" id="photoCaptureArea">
                            <span class="reg-focus-corner tl"></span>
                            <span class="reg-focus-corner tr"></span>
                            <span class="reg-focus-corner bl"></span>
                            <span class="reg-focus-corner br"></span>
                            <video id="photoVideo" autoplay playsinline class="hidden"></video>
                            <img id="photoPreview" class="hidden" alt="Captured photo">
                            <span id="photoPlaceholderText">Awaiting Camera Feed</span>
                        </div>
                        <div>
                            <p class="reg-camera-help">Capture the photo of the visitor properly.</p>
                            <button type="button" id="startCameraBtn" class="reg-button reg-button-blue" onclick="startCamera()">
                                <span aria-hidden="true">&#9654;</span> Start Camera
                            </button>
                            <button type="button" id="captureBtn" class="reg-button reg-button-blue hidden" onclick="capturePhoto()">Capture</button>
                            <button type="button" id="retakeBtn" class="reg-button hidden" onclick="retakePhoto()">Retake</button>
                        </div>
                    </div>
                    <canvas id="photoCanvas" class="hidden"></canvas>
                    <input type="hidden" name="photo_data" id="photoDataInput">

                    <div class="reg-camera-layout" style="margin-top: 20px;">
                        <div class="reg-camera-preview" id="idPhotoCaptureArea">
                            <span class="reg-focus-corner tl"></span>
                            <span class="reg-focus-corner tr"></span>
                            <span class="reg-focus-corner bl"></span>
                            <span class="reg-focus-corner br"></span>
                            <video id="idPhotoVideo" autoplay playsinline class="hidden"></video>
                            <img id="idPhotoPreview" class="hidden" alt="Captured ID photo">
                            <span id="idPhotoPlaceholderText">Awaiting ID Photo</span>
                        </div>
                        <div>
                            <p class="reg-camera-help">Capture a clear photo of the visitor's ID.</p>
                            <button type="button" id="startIdCameraBtn" class="reg-button reg-button-blue" onclick="startIdCamera()">
                                <span aria-hidden="true">&#9654;</span> Start Camera
                            </button>
                            <button type="button" id="captureIdBtn" class="reg-button reg-button-blue hidden" onclick="captureIdPhoto()">Capture</button>
                            <button type="button" id="retakeIdBtn" class="reg-button hidden" onclick="retakeIdPhoto()">Retake</button>
                        </div>
                    </div>
                    <canvas id="idPhotoCanvas" class="hidden"></canvas>
                    <input type="hidden" name="id_photo_data" id="idPhotoDataInput">
                </section>

                {{-- Step 2: Visitor Information --}}
                <section class="reg-step-card">
                    <div class="reg-step-heading">
                        <svg class="step-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 5h18l7 7v31H13zM31 5v8h7M19 21h13M19 27h13M19 33h9" />
                        </svg>
                        <div>
                            <p class="reg-step-label">Step 2: Information</p>
                            <h2>Visitor Information Form</h2>
                        </div>
                    </div>

                    <div class="reg-form-grid">
                        <div class="reg-field">
                            <label>First Name <span class="reg-required">*</span></label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="reg-field">
                            <label class="optional">Middle Name</label>
                            <input type="text" name="middle_name">
                        </div>
                        <div class="reg-field">
                            <label>Last Name <span class="reg-required">*</span></label>
                            <input type="text" name="last_name" required>
                        </div>

                        <div class="reg-field">
                            <label>Gender / Sex <span class="reg-required">*</span></label>
                            <select name="gender" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="PNS">Prefer not to say</option>
                            </select>
                        </div>
                        <div class="reg-field">
                            <label>Contact No. <span class="reg-required">*</span></label>
                            <input type="text" name="contact_no" required placeholder="+(63) ...">
                        </div>
                        <div class="reg-field">
                            <label class="optional">Email Address</label>
                            <input type="email" name="visitor_email" placeholder="For check-out / expiry reminders">
                        </div>

                        <div class="reg-field half">
                            <label>Government ID Type <span class="reg-required">*</span></label>
                            <select name="id_type" required>
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
                        <div class="reg-field half">
                            <label>ID Number <span class="reg-required">*</span></label>
                            <input type="text" name="id_ref" required placeholder="e.g. N01-23-456789">
                        </div>
                    </div>
                </section>

                {{-- Step 3: Destination + buildings + pass duration --}}
                <section class="reg-step-card">
                    <div class="reg-step-heading">
                        <svg class="step-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 5h18l7 7v31H13zM31 5v8h7M19 21h13M19 27h13M19 33h9" />
                        </svg>
                        <div>
                            <p class="reg-step-label">Step 3: Reason and Other Details</p>
                            <h2>Destination</h2>
                        </div>
                    </div>

                    <div class="reg-form-grid">
                        <div class="reg-field half">
                            <label>Office to Visit/Manager <span class="reg-required">*</span></label>
                            <input type="text" name="office_to_visit" required placeholder="e.g. Congressman Dela Cruz">
                        </div>
                        <div class="reg-field half">
                            <label>Reason for Visiting <span class="reg-required">*</span></label>
                            <input type="text" name="purpose" required>
                        </div>

                        <div class="reg-field full">
                            <label>Destination Building(s) <span class="reg-required">*</span> <span class="optional">(select 1 for a single-building pass, or 2+ for North Gate Access)</span></label>
                            <div class="reg-building-grid" id="regBuildingGrid">
                                @foreach ($buildings as $b)
                                    <label class="reg-building-option">
                                        <input type="checkbox" name="building_ids[]" value="{{ $b->id }}" onchange="updateBuildingSelection()">
                                        <span class="reg-building-dot" style="background:{{ $b->color_hex }}"></span>
                                        {{ $b->name }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="reg-north-gate-hint" id="northGateHint">
                                2 or more buildings selected — this will be issued as a <strong>North Gate Access</strong> pass, valid at all selected buildings.
                            </p>
                        </div>

                        <div class="reg-field half">
                            <label class="optional">Vehicle</label>
                            <input type="text" name="vehicle" placeholder="Plate number, optional">
                        </div>
                        <div class="reg-field half">
                            <label class="optional">Registered by</label>
                            <input type="text" name="registered_by" placeholder="Entrance personnel name">
                        </div>

                        <div class="reg-field full">
                            <label>Pass duration <span class="reg-required">*</span></label>
                            <div style="display:flex; gap:8px;">
                                <label class="pass-type-option" style="flex:1;">
                                    <input type="radio" name="pass_class" value="day" checked onchange="setPassClass('day')" class="sr-only">
                                    <span class="pass-type-btn is-active" id="passClassBtnDay">Day</span>
                                </label>
                                <label class="pass-type-option" style="flex:1;">
                                    <input type="radio" name="pass_class" value="long_term" onchange="setPassClass('long_term')" class="sr-only">
                                    <span class="pass-type-btn" id="passClassBtnLongTerm">Long-term</span>
                                </label>
                            </div>
                        </div>
                        <div class="reg-field full hidden" id="expectedReturnField">
                            <label>Expected return date <span class="reg-required">*</span></label>
                            <input type="date" name="expected_return_date" id="expectedReturnInput">
                            <p class="optional" style="margin-top:6px; font-size:12.5px;" id="expectedReturnCapHint"></p>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="reg-modal-footer">
                <button type="button" class="reg-button" onclick="closeRegisterModal()">Cancel</button>
                <button type="submit" id="registerSubmitBtn" class="reg-button reg-button-green">Admit and Auto-assign</button>
            </footer>
        </form>
    </section>
</div>
@endsection

@section('scripts')
<script>

function updateBuildingSelection() {
    const checked = document.querySelectorAll('input[name="building_ids[]"]:checked').length;
    const hint = document.getElementById('northGateHint');
    const submitBtn = document.getElementById('registerSubmitBtn');

    hint.classList.toggle('is-active', checked >= 2);

    submitBtn.disabled = checked === 0;
    submitBtn.style.opacity = submitBtn.disabled ? '0.5' : '1';
}

function setPassClass(passClass) {
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
        input.max = cap.toISOString().split('T')[0];
        document.getElementById('expectedReturnCapHint').textContent =
            'Max 30 working days from today — latest allowed: ' +
            cap.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } else {
        field.classList.add('hidden');
        input.removeAttribute('required');
        input.value = '';
        btnDay.classList.add('is-active');
        btnLongTerm.classList.remove('is-active');
    }
}

function addWorkingDaysClientSide(startDate, days) {
    const date = new Date(startDate);
    let added = 0;
    while (added < days) {
        date.setDate(date.getDate() + 1);
        const dow = date.getDay();
        if (dow >= 1 && dow <= 4) added++;
    }
    return date;
}

// ---- Photo capture (visitor face) ----
let photoStream = null;

async function startCamera() {
    const video = document.getElementById('photoVideo');
    try {
        photoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        video.srcObject = photoStream;
        video.classList.remove('hidden');
        document.getElementById('photoPlaceholderText').classList.add('hidden');
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
    if (photoStream) { photoStream.getTracks().forEach(t => t.stop()); photoStream = null; }
}

function resetPhotoCapture() {
    stopCameraStream();
    document.getElementById('photoVideo').classList.add('hidden');
    document.getElementById('photoPreview').classList.add('hidden');
    document.getElementById('photoPlaceholderText').classList.remove('hidden');
    document.getElementById('retakeBtn').classList.add('hidden');
    document.getElementById('captureBtn').classList.add('hidden');
    document.getElementById('startCameraBtn').classList.remove('hidden');
    document.getElementById('photoDataInput').value = '';
}
// ---- End visitor face photo capture ----

// ---- Photo capture (ID) ----
let idPhotoStream = null;

async function startIdCamera() {
    const video = document.getElementById('idPhotoVideo');
    try {
        idPhotoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.srcObject = idPhotoStream;
        video.classList.remove('hidden');
        document.getElementById('idPhotoPlaceholderText').classList.add('hidden');
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
    if (idPhotoStream) { idPhotoStream.getTracks().forEach(t => t.stop()); idPhotoStream = null; }
}

function resetIdPhotoCapture() {
    stopIdCameraStream();
    document.getElementById('idPhotoVideo').classList.add('hidden');
    document.getElementById('idPhotoPreview').classList.add('hidden');
    document.getElementById('idPhotoPlaceholderText').classList.remove('hidden');
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
    setPassClass('day');
    updateBuildingSelection();
}

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

</script>
@endsection