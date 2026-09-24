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
        'SWA'   => 'swa.jpg',
    ];

    $buildingColorNames = [
        'MB'  => 'Blue',
        'NG'  => 'Pink',
        'NW'  => 'Red',
        'SW'  => 'Orange',
        'RVM' => 'Green',
        'SWA' => 'Yellow',
    ];
@endphp

{{-- Hero — reuses the exact segmented-border treatment from scanner/index.blade.php and logs/index.blade.php --}}
<div class="gov-card">
    <div class="gov-card-header">
        <div class="gov-card-header-left">
            <i class="fa-solid fa-clipboard-list gov-card-header-icon"></i>
            <div>
                <span class="gov-eyebrow">Instructions</span>
                <span class="gov-card-title">Visitor Pass Registry</span>
            </div>
        </div>
        <div class="gov-corner-accent" aria-hidden="true"></div>
    </div>
    <div class="gov-card-body gov-card-body-split">
        <div>
            <p>Register a visitor with their agenda and detail for access to a certain building. The personnel must:</p>
            <ol class="gov-steps">
                <li>Register the visitor</li>
                <li>Assign the specific building/s they need</li>
                <li>Unassign after return of visitor pass</li>
            </ol>
        </div>
        <button onclick="document.getElementById('registerModal').classList.remove('hidden')" class="gov-btn-camera flex-shrink-0">
            <i class="fa-solid fa-user-plus"></i> Register Visitor
        </button>
    </div>
</div>


<div class="gov-legend">
    <span class="gov-legend-item"><span class="gov-legend-dot is-active"></span> Active Passes</span>

    <span class="gov-legend-item"><span class="gov-legend-dot is-available"></span> Available Passes</span>
    <span class="gov-legend-item"><span class="gov-legend-dot is-inactive"></span> Inactive Passes</span>
</div>

{{-- Building grid — click a building to view/manage its passes. When the
     total count is odd, the last card spans both columns and is capped to
     half-width + centered so it doesn't sit awkwardly alone on the left.
     Subtext now shows live active/available counts instead of static copy,
     and cards lift on hover to signal clickability. --}}

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @foreach ($displayBuildings as $b)
        @php
       // North Gate's own pool must only ever contain multi-building passes;
    // any single-building pass homed there is stale legacy data and is
    // deliberately excluded rather than counted.
        $buildingPasses = $passes->where('building_id', $b->id)
            ->where('is_multi_building', $b->code === 'NG');
        $activeCount = $buildingPasses->where('status', 'active')->count();
        $availableCount = $buildingPasses->where('status', 'available')->count();
        $inactiveCount = $buildingPasses->whereIn('status', ['expired', 'revoked'])->count();
@endphp
            <button type="button" onclick="openBuildingModal({{ $b->id }}, '{{ $b->name }}', '{{ $buildingColorNames[$b->code] ?? '' }}')"
            class="gov-building-card {{ $loop->last && $loop->count % 2 !== 0 ? 'md:col-span-2 md:max-w-[calc(50%-0.5rem)] md:mx-auto' : '' }}">
            <div class="gov-building-card-info">
                <h3>{{ $b->name }}</h3>
                <p class="is-active">{{ $activeCount }} Active Passes</p>
                <p class="is-available">{{ $availableCount }} Available Passes</p>
                <p class="is-inactive">{{ $inactiveCount }} Inactive Passes</p>
            </div>
            <div class="gov-building-card-photo">
                <img src="{{ asset('images/buildings/' . ($buildingImages[$b->code] ?? 'main.png')) }}"
                     alt="{{ $b->name }}">
            </div>
        </button>
    @endforeach
</div>

{{-- User Journey --}}
<div class="gov-card">
    <div class="gov-card-header">
        <div class="gov-card-header-left">
            <i class="fa-solid fa-people-arrows gov-card-header-icon"></i>
            <div>
                <span class="gov-eyebrow">User Journey</span>
                <span class="gov-card-title">Diagram of User Journey of the System</span>
            </div>
        </div>
        <div class="gov-corner-accent" aria-hidden="true"></div>
    </div>
    <div class="gov-card-body">
        <div class="relative max-w-2xl mx-auto">
            <img id="journeyImage" src="{{ asset('images/carousel/step1.svg') }}"
                alt="User journey step"
                class="w-full h-auto">
            <button type="button" id="journeyPrevBtn" class="gov-journey-nav is-prev" aria-label="Previous step" onclick="prevJourneyStep()" disabled>
                <i class="fa-solid fa-caret-left"></i>
            </button>
            <button type="button" id="journeyNextBtn" class="gov-journey-nav" aria-label="Next step" onclick="nextJourneyStep()">
                <i class="fa-solid fa-caret-right"></i>
            </button>
        </div>
    </div>
</div>

{{-- Per-building passes modal --}}
<div id="buildingPassesModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="modal-govt-panel rounded-2xl shadow-2xl max-w-5xl w-full max-h-[85vh] overflow-hidden flex flex-col bg-white">
        {{-- Color-coded accent strip, set per-building via JS in openBuildingModal() --}}
        

                <div class="p-6 pb-4 flex-shrink-0 border-b border-slate-100">
            <div class="flex justify-between items-start gap-4">
                <div class="flex items-center gap-3">
                    <span id="buildingModalSwatch" class="gov-pass-modal-swatch"></span>
                    <div>
                        <span class="gov-eyebrow">Visitor Passes</span>
                        <h3 id="buildingModalTitle" class="gov-pass-modal-title"></h3>
                    </div>
                </div>
                <button type="button" onclick="closeBuildingModal()"
                        class="bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full p-2.5 transition-colors leading-none flex-shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                <div class="flex gap-2">
                    <button type="button" onclick="filterModalPasses('all')" class="modal-filter-pill is-active" data-filter="all">All</button>
                    <button type="button" onclick="filterModalPasses('available')" class="modal-filter-pill" data-filter="available">Available</button>
                    <button type="button" onclick="filterModalPasses('active')" class="modal-filter-pill" data-filter="active">Active</button>
                    <button type="button" onclick="filterModalPasses('inactive')" class="modal-filter-pill" data-filter="inactive">Inactive</button>        
                </div>
                <div class="gov-modal-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="buildingModalSearch" placeholder="Search by name or pass #..." autocomplete="off" oninput="applyModalFilters()">
                </div>
            </div>
        </div>

              <div class="px-6 py-5 overflow-y-auto">
            @foreach ($displayBuildings as $b)
                <div id="buildingPassGroup-{{ $b->id }}"
                     class="hidden flex flex-col gap-2"
                     data-building-color="{{ $b->color_hex }}">
                    @forelse ($passes->where('building_id', $b->id)->where('is_multi_building', $b->code === 'NG') as $p)
                       
                                                @php
                            $cardColor = $p->is_multi_building ? 'var(--badge-multi)' : $p->building->color_hex;
                            $searchText = strtolower($p->pass_number . ' ' . ($p->visitor_name ?? 'unassigned'));
                            $badgeMap = [
                                'active'    => ['is-active', 'Active'],
                                'available' => ['is-available', 'Available'],
                                'expired'   => ['is-expired', 'Expired'],
                                'revoked'   => ['is-revoked', 'Revoked'],
                            ];
                            [$badgeClass, $badgeLabel] = $badgeMap[$p->status] ?? ['is-available', 'Available'];
                            $infoPayload = [
                                'pass_number' => $p->pass_number,
                                'visitor_name' => $p->visitor_name,
                                'gender' => $p->gender,
                                'contact_no' => $p->contact_no,
                                'visitor_email' => $p->visitor_email,
                                'id_type' => $p->id_type,
                                'id_ref' => $p->id_ref,
                                'office_to_visit' => $p->office_to_visit,
                                'purpose' => $p->purpose,
                                'vehicle' => $p->vehicle,
                                'registered_by' => $p->registered_by,
                                'pass_class' => $p->pass_class,
                                'issued_at' => $p->issued_at?->format('M j, Y g:i A'),
                                'expected_return_date' => $p->expected_return_date?->format('M j, Y'),
                                'photo_url' => $p->photo_path ? asset('storage/' . $p->photo_path) : null,
                                'id_photo_url' => $p->id_photo_path ? asset('storage/' . $p->id_photo_path) : null,
                            ];
                        @endphp
                        <div class="gov-pass-card" data-status="{{ $p->status }}" data-search="{{ $searchText }}">
                            <div class="gov-pass-card-info">
                                <span class="gov-pass-card-number">#{{ $p->pass_number }}</span>
                                <div class="gov-pass-card-details">
                                    @if ($p->visitor_name)
                                        <div class="gov-pass-card-name">{{ $p->visitor_name }}</div>
                                        @if ($p->pass_class === 'day')
                                            <div class="gov-pass-card-meta">1 Day Access</div>
                                        @else
                                            <div class="gov-pass-card-meta">{{ $p->issued_at?->format('n/j/Y') }} - {{ $p->expected_return_date?->format('n/j/Y') }}</div>
                                        @endif
                                        @if ($p->is_multi_building)
                                            <div class="gov-pass-card-buildings">
                                                @if ($p->buildings->isEmpty())
                                                    Awaiting building assignment
                                                @elseif ($p->buildings->count() >= $buildings->count())
                                                    All Buildings
                                                @else
                                                    {{ $p->buildings->pluck('name')->join(', ') }}
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <span class="gov-pass-card-unassigned">Unassigned</span>
                                    @endif
                                </div>
                            </div>

                            <div class="gov-pass-card-right">
                                <span class="gov-pass-badge {{ $badgeClass }}">{{ $badgeLabel }}</span>

                                <div class="gov-pass-card-actions">
                                    @if ($p->visitor_name)
                                        <form method="POST" action="{{ route('passes.unassign', $p) }}"
                                              onsubmit="return confirm('Unassign {{ $p->visitor_name }} from Pass #{{ $p->pass_number }}? The card will be reset and returned to available stock.');">
                                            @csrf
                                            <button type="submit" class="gov-pass-row-btn is-ghost"><i class="fa-solid fa-link-slash"></i> Unassign</button>
                                        </form>
                                        <a href="{{ route('passes.show', $p) }}" class="gov-pass-row-btn is-ghost"><i class="fa-solid fa-qrcode"></i> View QR</a>
                                        <form method="POST" action="{{ route('passes.revoke', $p) }}"
                                              onsubmit="return confirm('Revoke Pass #{{ $p->pass_number }}? {{ $p->visitor_name }} will be denied on their next scan.');">
                                            @csrf
                                            <button type="submit" class="gov-pass-row-btn is-ghost"><i class="fa-solid fa-ban"></i> Revoke</button>
                                        </form>
                                        <button type="button" class="gov-pass-row-btn is-ghost" data-pass-number="{{ $p->pass_number }}" onclick='openPassInfoModal(@json($infoPayload))'><i class="fa-solid fa-circle-info"></i> View Info</button>
                                    @else
                                        <a href="{{ route('passes.show', $p) }}" class="gov-pass-row-btn is-ghost"><i class="fa-solid fa-qrcode"></i> View QR</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 text-center py-8">No passes exist for this building yet.</p>
                    @endforelse
                    <p class="gov-pass-row-empty hidden text-sm text-slate-400 text-center py-8">No passes match your search.</p>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div id="passInfoModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="gov-info-modal-panel">
        <div class="gov-info-modal-header">
            <div>
                <span class="gov-eyebrow">Pass Information</span>
                <h3 id="infoModalTitle" class="gov-pass-modal-title"></h3>
            </div>
            <button type="button" onclick="closePassInfoModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full p-2.5 transition-colors leading-none">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="gov-info-modal-body">
            <div class="gov-info-photos">
                <div>
                    <span class="gov-meta-label">Visitor Photo</span>
                    <img id="infoPhoto" class="gov-info-photo" alt="Visitor photo">
                </div>
                <div>
                    <span class="gov-meta-label">ID Photo</span>
                    <img id="infoIdPhoto" class="gov-info-photo" alt="ID photo">
                </div>
            </div>
            <div class="gov-info-modal-grid" id="infoFieldsGrid"></div>
        </div>
    </div>
</div>

<div id="registerModal" class="reg-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="registration-title">
    <section class="reg-modal">
        <header class="reg-modal-header">
            <i class="fa-solid fa-desktop header-icon"></i>
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
                        <i class="fa-solid fa-camera step-icon"></i>
                        <div>
                            <p class="reg-step-label">Step 1: Camera Terminal</p>
                            <h2>Entrance Photo Capture</h2>
                        </div>
                    </div>

                        <div class="reg-camera-grid">
                        <div class="reg-camera-col">
                            <p class="reg-camera-help">Capture the photo of the visitor properly.</p>
                            <div class="reg-camera-preview" id="photoCaptureArea">
                                <span class="reg-focus-corner tl"></span>
                                <span class="reg-focus-corner tr"></span>
                                <span class="reg-focus-corner bl"></span>
                                <span class="reg-focus-corner br"></span>
                                <video id="photoVideo" autoplay playsinline class="hidden"></video>
                                <img id="photoPreview" class="hidden" alt="Captured photo">
                                <span id="photoPlaceholderText">Awaiting Camera Feed</span>
                            </div>
                            <div class="reg-camera-actions">
                                <button type="button" id="startCameraBtn" class="reg-button reg-button-blue" onclick="startCamera()">
                                    <span aria-hidden="true">&#9654;</span> Start Camera
                                </button>
                                <button type="button" id="captureBtn" class="reg-button reg-button-blue hidden" onclick="capturePhoto()">Capture</button>
                                <button type="button" id="retakeBtn" class="reg-button hidden" onclick="retakePhoto()">Retake</button>
                            </div>
                        </div>
                        <div class="reg-camera-col">
                            <p class="reg-camera-help">Capture front and back of the visitor's ID.</p>
                            <div class="reg-camera-preview" id="idPhotoCaptureArea">
                                <span class="reg-focus-corner tl"></span>
                                <span class="reg-focus-corner tr"></span>
                                <span class="reg-focus-corner bl"></span>
                                <span class="reg-focus-corner br"></span>
                                <video id="idPhotoVideo" autoplay playsinline class="hidden"></video>
                                <img id="idPhotoPreview" class="hidden" alt="Captured ID">
                                <span id="idPhotoPlaceholderText">Capture Front and Back of the ID</span>
                                <button type="button" id="idArrowLeft" class="hidden" onclick="goToIdSlide(0)" aria-label="View front" style="position:absolute; left:8px; top:50%; transform:translateY(-50%); width:32px; height:32px; border-radius:50%; background:rgba(15,23,42,0.55); color:#fff; border:0; font-size:14px; cursor:pointer; z-index:5;">&#9664;</button>
                                <button type="button" id="idArrowRight" class="hidden" onclick="goToIdSlide(1)" aria-label="View back" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); width:32px; height:32px; border-radius:50%; background:rgba(15,23,42,0.55); color:#fff; border:0; font-size:14px; cursor:pointer; z-index:5;">&#9654;</button>
                            </div>
                            <div class="reg-camera-actions">
                                <button type="button" id="idMainBtn" class="reg-button reg-button-blue" onclick="handleIdMainButton()">
                                    <span aria-hidden="true">&#9654;</span> Start Camera
                                </button>
                            </div>
                            <p class="reg-camera-help" id="idCaptureStatus" style="font-weight:700; color:#64748b;">Capture the visitor's ID — front, then back.</p>
                            <input type="hidden" name="id_photo_data" id="idPhotoDataInput">
                        </div>

                    </div>

                    <canvas id="photoCanvas" class="hidden"></canvas>
                    <input type="hidden" name="photo_data" id="photoDataInput">
                    <canvas id="idPhotoCanvas" class="hidden"></canvas>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/tesseract.js/5.1.1/tesseract.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsqr/1.4.0/jsQR.js"></script>
                </section>

                {{-- Step 2: Visitor Information --}}
                <section class="reg-step-card">
                    <div class="reg-step-heading">
                        <i class="fa-solid fa-file-lines step-icon"></i>
                        <div>
                            <p class="reg-step-label">Step 2: Information</p>
                            <h2>Visitor Information Form</h2>
                        </div>
                    </div>

                    <div class="reg-form-grid">
                        <div class="reg-field">
                            <label class="optional">First Name</label>
                            <input type="text" name="first_name">
                        </div>
                        <div class="reg-field">
                            <label class="optional">Middle Name</label>
                            <input type="text" name="middle_name">
                        </div>
                        <div class="reg-field">
                            <label class="optional">Last Name</label>
                            <input type="text" name="last_name">
                        </div>

                        <div class="reg-field">
                            <label class="optional">Gender / Sex</label>
                            <select name="gender">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="PNS">Prefer not to say</option>
                            </select>
                        </div>
                        <div class="reg-field">
                            <label class="optional">Contact No.</label>
                            <input type="text" name="contact_no" placeholder="+(63) ...">
                        </div>
                        <div class="reg-field">
                            <label class="optional">Email Address</label>
                            <input type="email" name="visitor_email" placeholder="For check-out / expiry reminders">
                        </div>

                        <div class="reg-field half">
                            <label class="optional">Government ID Type</label>
                            <select name="id_type">
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
                            <label class="optional">ID Number</label>
                            <input type="text" name="id_ref" placeholder="e.g. N01-23-456789">
                        </div>
                    </div>
                </section>

                {{-- Step 3: Destination + buildings + pass duration --}}
                <section class="reg-step-card reg-step-card-emphasis">
                    <div class="reg-step-heading">
                        <i class="fa-solid fa-signs-post step-icon"></i>
                        <div>
                            <p class="reg-step-label">Step 3: Reason and Other Details</p>
                            <h2>Destination</h2>
                        </div>
                    </div>
                    <div class="reg-form-grid">
                        <div class="reg-field full">
                            <label>Destination Building(s) <span class="reg-required">*</span> <span class="optional">(select 1 for a single-building pass, or 2+ for North Gate Access (Multi-Access Pass))</span></label>
                            <div class="reg-building-grid" id="regBuildingGrid">
                                @foreach ($buildings as $b)
                                   
                                <label class="reg-building-option">
                                    <input type="checkbox" name="building_ids[]" value="{{ $b->id }}" onchange="updateBuildingSelection()">
                                    <span class="reg-building-dot" style="background:{{ $b->color_hex }}"></span>
                                    <span class="reg-building-name">{{ $b->name }}</span>
                                </label>
                                @endforeach
                            </div>
                            <p class="reg-north-gate-hint" id="northGateHint">
                                2 or more buildings selected — this will be issued as a <strong>North Gate Access</strong> pass, valid at all selected buildings.
                            </p>
                        </div>

                        <div class="reg-field full">
                            <label>Reason for Visiting <span class="reg-required">*</span></label>
                            <select name="purpose_choice" id="purposeChoice" required onchange="document.getElementById('purposeOtherInput').classList.toggle('hidden', this.value !== 'Others'); document.getElementById('purposeOtherInput').required = (this.value === 'Others');">
                                <option value="">Select reason</option>
                                <option value="Official Business">Official Business</option>
                                <option value="Financial/Medical Assistance">Financial/Medical Assistance</option>
                                <option value="Visit">Visit</option>
                                <option value="Others">Others</option>
                            </select>
                            <input type="text" name="purpose_other" id="purposeOtherInput" class="hidden" style="margin-top:8px;" placeholder="Please specify">
                        </div>

                        <div class="reg-field full" id="congressmanField">
                            <label>Congressman(s) to Visit <span class="reg-required">*</span> <span class="optional">(only congressmen from the selected building(s) are listed)</span></label>
                            <div class="cong-picker" id="congPicker">
                                <div class="cong-chips" id="congChips"></div>
                                <input type="text" id="congSearch" autocomplete="off" disabled placeholder="Select a building first…">
                                <div class="cong-list" id="congList"></div>
                            </div>
                            <div id="congHiddenInputs"></div>
                            <label class="optional" style="margin-top:10px;">Other <span class="optional">(office or person not in the list — required if no congressman is chosen)</span></label>
                            <input type="text" name="office_other" id="officeOther" maxlength="255" required placeholder="e.g. HR Office, Secretariat">
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

    refreshCongPicker();
}

// ---- Congressman picker (filtered by the ticked building(s)) ----
const CONGRESSMEN = @json($roster);
const BUILDING_NAMES = @json($buildings->pluck('name', 'id'));
const selectedCong = new Map();

function checkedBuildingIds() {
    return Array.from(document.querySelectorAll('input[name="building_ids[]"]:checked')).map(c => Number(c.value));
}

function refreshCongPicker() {
    const ids = checkedBuildingIds();

    // A building was unticked → its congressmen drop out of the selection.
    for (const [id, c] of selectedCong) {
        if (!ids.includes(c.b)) selectedCong.delete(id);
    }

    const search = document.getElementById('congSearch');
    search.disabled = ids.length === 0;
    search.placeholder = ids.length ? 'Search name, district or room…' : 'Select a building first…';
    if (!ids.length) search.value = '';

    renderCongChips();
    renderCongList();
}

function renderCongChips() {
    const chips = document.getElementById('congChips');
    const hidden = document.getElementById('congHiddenInputs');
    chips.innerHTML = '';
    hidden.innerHTML = '';

    selectedCong.forEach(c => {
        const chip = document.createElement('span');
        chip.className = 'cong-chip';
        chip.textContent = c.name + (c.room ? ' · ' + c.room : '');

        const x = document.createElement('button');
        x.type = 'button';
        x.textContent = '×';
        x.setAttribute('aria-label', 'Remove ' + c.name);
        x.onclick = () => { selectedCong.delete(c.id); renderCongChips(); renderCongList(); };
        chip.appendChild(x);
        chips.appendChild(chip);

        const h = document.createElement('input');
        h.type = 'hidden';
        h.name = 'congressman_ids[]';
        h.value = c.id;
        hidden.appendChild(h);
    });

    // "Other" is only mandatory when no congressman is picked.
    document.getElementById('officeOther').required = selectedCong.size === 0;
}

function renderCongList() {
    const list = document.getElementById('congList');
    const ids = checkedBuildingIds();
    const q = document.getElementById('congSearch').value.trim().toLowerCase();
    list.innerHTML = '';
    if (!ids.length) return;

    const multi = ids.length > 1;
    const matches = CONGRESSMEN
        .filter(c => ids.includes(c.b) && !selectedCong.has(c.id) &&
            (!q || (c.name + ' ' + (c.detail || '') + ' ' + (c.room || '')).toLowerCase().includes(q)))
        .sort((a, b) => (multi ? String(BUILDING_NAMES[a.b]).localeCompare(String(BUILDING_NAMES[b.b])) : 0)
            || a.name.localeCompare(b.name));

    if (!matches.length) {
        const empty = document.createElement('div');
        empty.className = 'cong-empty';
        empty.textContent = 'No matching congressman.';
        list.appendChild(empty);
        return;
    }

    let lastB = null;
    matches.forEach(c => {
        if (multi && c.b !== lastB) {
            const g = document.createElement('div');
            g.className = 'cong-group';
            g.textContent = BUILDING_NAMES[c.b];
            list.appendChild(g);
            lastB = c.b;
        }
        const item = document.createElement('div');
        item.className = 'cong-item';
        const label = document.createElement('span');
        label.textContent = c.name + (c.detail ? ' — ' + c.detail : '');
        const meta = document.createElement('span');
        meta.className = 'meta';
        meta.textContent = c.room || '';
        item.append(label, meta);
        // mousedown (not click) so the search box doesn't blur before we register the pick
        item.addEventListener('mousedown', e => {
            e.preventDefault();
            selectedCong.set(c.id, c);
            document.getElementById('congSearch').value = '';
            renderCongChips();
            renderCongList();
            list.classList.remove('is-open');
        });
        list.appendChild(item);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('congSearch');
    const list = document.getElementById('congList');
    const open = () => { renderCongList(); list.classList.add('is-open'); };
    search.addEventListener('focus', open);
    search.addEventListener('input', open);
    search.addEventListener('keydown', e => {
        if (e.key === 'Enter') e.preventDefault();
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('#congPicker')) list.classList.remove('is-open');
    });
    refreshCongPicker();
});
// ---- End congressman picker ----

const journeySteps = [
    "{{ asset('images/carousel/step1.svg') }}",
    "{{ asset('images/carousel/step2.svg') }}",
    "{{ asset('images/carousel/step3.svg') }}"
];
let journeyIndex = 0;

function updateJourneyImage() {
    const img = document.getElementById('journeyImage');
    img.classList.add('is-fading');
    setTimeout(() => {
        img.src = journeySteps[journeyIndex];
        img.classList.remove('is-fading');
    }, 150);

    document.getElementById('journeyPrevBtn').disabled = journeyIndex === 0;
    document.getElementById('journeyNextBtn').disabled = journeyIndex === journeySteps.length - 1;
}

function nextJourneyStep() {
    if (journeyIndex < journeySteps.length - 1) {
        journeyIndex++;
        updateJourneyImage();
    }
}

function prevJourneyStep() {
    if (journeyIndex > 0) {
        journeyIndex--;
        updateJourneyImage();
    }
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
// ---- Photo capture (ID): one box, front then back, arrow to switch sides ----
let idPhotoStream = null;
let idSlide = 0; // 0 = front, 1 = back
let idPhotos = { front: null, back: null };
let idCameraActive = false;

function updateIdSlideUI() {
    const video = document.getElementById('idPhotoVideo');
    const preview = document.getElementById('idPhotoPreview');
    const placeholder = document.getElementById('idPhotoPlaceholderText');
    const mainBtn = document.getElementById('idMainBtn');
    const side = idSlide === 0 ? 'front' : 'back';
    const photo = idPhotos[side];

    video.classList.toggle('hidden', !idCameraActive);
    preview.classList.toggle('hidden', idCameraActive || !photo);
    placeholder.classList.toggle('hidden', idCameraActive || !!photo);
    if (photo && !idCameraActive) preview.src = photo;
    if (!idCameraActive && !photo) {
        placeholder.innerText = idSlide === 0 ? 'Capture Front and Back of the ID' : 'Now capture the back of the ID';
    }

    mainBtn.innerHTML = idCameraActive ? 'Capture' : (photo ? 'Retake' : '<span aria-hidden="true">&#9654;</span> Start Camera');
    document.getElementById('idArrowLeft').classList.toggle('hidden', idSlide !== 1);
    document.getElementById('idArrowRight').classList.toggle('hidden', !(idSlide === 0 && idPhotos.front));
}

function goToIdSlide(slide) {
    stopIdCameraStream();
    idCameraActive = false;
    idSlide = slide;
    updateIdSlideUI();
}

async function handleIdMainButton() {
    if (idCameraActive) { captureCurrentIdSlide(); return; }
    const side = idSlide === 0 ? 'front' : 'back';
    idPhotos[side] = null;
    try {
        idPhotoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        document.getElementById('idPhotoVideo').srcObject = idPhotoStream;
        idCameraActive = true;
        updateIdSlideUI();
    } catch (err) {
        alert('Could not access camera: ' + err.message);
    }
}

function captureCurrentIdSlide() {
    const video = document.getElementById('idPhotoVideo');
    const canvas = document.getElementById('idPhotoCanvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

    stopIdCameraStream();
    idCameraActive = false;

    if (idSlide === 0) {
        idPhotos.front = dataUrl;
        document.getElementById('idPhotoDataInput').value = dataUrl;
        updateIdSlideUI();
        runIdOcr(dataUrl);
        if (!idPhotos.back) setTimeout(() => goToIdSlide(1), 400);
    } else {
        idPhotos.back = dataUrl; // in-memory only — never uploaded or stored
        updateIdSlideUI();
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const qr = window.jsQR ? jsQR(imageData.data, imageData.width, imageData.height) : null;
        if (qr) applyIdBackQr(qr.data);
        else setIdCaptureStatus('No QR code detected on the back — Retake or leave it as is.', 'warn');
    }
}

function stopIdCameraStream() {
    if (idPhotoStream) { idPhotoStream.getTracks().forEach(t => t.stop()); idPhotoStream = null; }
}

function resetIdPhotoCapture() {
    stopIdCameraStream();
    idSlide = 0;
    idPhotos = { front: null, back: null };
    idCameraActive = false;
    document.getElementById('idPhotoDataInput').value = '';
    setIdCaptureStatus("Capture the visitor's ID — front, then back.", 'neutral');
    updateIdSlideUI();
}

function setIdCaptureStatus(message, tone) {
    const el = document.getElementById('idCaptureStatus');
    const colors = { neutral: '#64748b', busy: '#2563eb', success: '#1c9a5b', warn: '#b45309', error: 'var(--brand-red)' };
    el.innerText = message;
    el.style.color = colors[tone] || colors.neutral;
}

// ---- ID OCR auto-fill (National ID / PhilSys front, client-side via Tesseract.js) ----
async function runIdOcr(dataUrl) {
    setIdCaptureStatus('Reading ID… this can take a few seconds.', 'busy');
    try {
        const { data: { text } } = await Tesseract.recognize(dataUrl, 'eng');
        const filled = applyIdOcrText(text);
        setIdCaptureStatus(filled.length ? `Auto-filled: ${filled.join(', ')}. Please review.` : 'Could not read the ID clearly — please fill in manually.', filled.length ? 'success' : 'warn');
    } catch (err) {
        console.error('ID OCR failed:', err);
        setIdCaptureStatus('ID reading failed — please fill in manually.', 'error');
    }
}

function applyIdOcrText(rawText) {
    const lines = rawText.split('\n').map(l => l.trim()).filter(Boolean);
    const allLabelPatterns = [/Apelyido/i, /Last\s*Name/i, /Mga\s*Pangalan/i, /Given\s*Name/i, /Gitnang\s*Apelyido/i, /Middle\s*Name/i, /Petsa|Date\s*of\s*Birth/i, /Tirahan|Address/i, /Republika|Philippines|Identification/i];
    const looksLikeName = (line) => {
        const clean = line.replace(/[^A-Za-zÑñÁÉÍÓÚáéíóú' -]/g, '').trim();
        return clean.length >= 2 && !allLabelPatterns.some(p => p.test(clean));
    };
    const findValueAfter = (labelPatterns) => {
        for (let i = 0; i < lines.length; i++) {
            if (!labelPatterns.some(p => p.test(lines[i]))) continue;
            for (let j = i + 1; j <= i + 3 && j < lines.length; j++) {
                if (looksLikeName(lines[j])) return lines[j].replace(/[^A-Za-zÑñÁÉÍÓÚáéíóú' -]/g, '').trim();
            }
        }
        return null;
    };

    const lastName = findValueAfter([/Apelyido/i, /Last\s*Name/i]);
    const firstName = findValueAfter([/Mga\s*Pangalan/i, /Given\s*Name/i]);
    const middleName = findValueAfter([/Gitnang\s*Apelyido/i, /Middle\s*Name/i]);
    const idMatch = rawText.match(/\d{4}[\s-]\d{4}[\s-]\d{4}[\s-]\d{4}/);
    const idRef = idMatch ? idMatch[0].replace(/\s/g, '-') : null;

    const filled = [];
    setAutofillValue('last_name', lastName, 'Last Name', filled);
    setAutofillValue('first_name', firstName, 'First Name', filled);
    setAutofillValue('middle_name', middleName, 'Middle Name', filled);
    setAutofillValue('id_ref', idRef, 'ID Number', filled);

    if (lastName || firstName) {
        const idTypeSelect = document.querySelector('[name="id_type"]');
        if (idTypeSelect && !idTypeSelect.value) idTypeSelect.value = 'PhilSys (National ID)';
    }
    return filled;
}

function setAutofillValue(name, value, label, filledList) {
    if (!value) return;
    const input = document.querySelector(`[name="${name}"]`);
    if (!input) return;
    if (!input.value.trim() || input.dataset.autofilled === '1') {
        input.value = value;
        input.dataset.autofilled = '1';
        if (filledList) filledList.push(label);
    }
}
document.addEventListener('input', (e) => {
    if (e.target.matches('[name="first_name"],[name="middle_name"],[name="last_name"],[name="id_ref"]')) delete e.target.dataset.autofilled;
});
document.addEventListener('change', (e) => {
    if (e.target.matches('[name="gender"]')) delete e.target.dataset.autofilled;
});

// ---- ID back QR (PhilSys), decoded client-side via jsQR ----
function applyIdBackQr(rawText) {
    let json;
    try {
        json = JSON.parse(rawText);
    } catch (err) {
        setIdCaptureStatus('That QR did not contain readable ID data.', 'error');
        return;
    }
    const subj = json.subject || {};
    const filled = [];
    setAutofillValue('last_name', subj.lName, 'Last Name', filled);
    setAutofillValue('first_name', subj.fName, 'First Name', filled);
    setAutofillValue('middle_name', subj.mName, 'Middle Name', filled);
    setAutofillValue('id_ref', subj.PCN, 'ID Number', filled);

    const genderSelect = document.querySelector('[name="gender"]');
    const mapped = subj.sex === 'Male' ? 'Male' : subj.sex === 'Female' ? 'Female' : null;
    if (genderSelect && mapped && (!genderSelect.value || genderSelect.dataset.autofilled === '1')) {
        genderSelect.value = mapped;
        genderSelect.dataset.autofilled = '1';
        filled.push('Gender');
    }
    const idTypeSelect = document.querySelector('[name="id_type"]');
    if (idTypeSelect && !idTypeSelect.value) idTypeSelect.value = 'PhilSys (National ID)';

    setIdCaptureStatus(filled.length ? `QR auto-filled: ${filled.join(', ')}. Please review.` : 'QR read, but no matching fields found.', filled.length ? 'success' : 'warn');
}
// ---- End ID capture ----

function closeRegisterModal() {
    document.getElementById('registerModal').classList.add('hidden');
    document.getElementById('registerForm').reset();
    resetPhotoCapture();
    resetIdPhotoCapture();
    setPassClass('day');
    setPassClass('day');
    selectedCong.clear();
    updateBuildingSelection();
}
       let currentModalFilter = 'all';

        function openBuildingModal(buildingId, buildingName, colorName) {
        document.querySelectorAll('[id^="buildingPassGroup-"]').forEach(el => el.classList.add('hidden'));
        const group = document.getElementById('buildingPassGroup-' + buildingId);
        group.classList.remove('hidden');

        document.getElementById('buildingModalTitle').innerText = `${buildingName} - ${colorName} Pass`;

        const color = group.dataset.buildingColor || '#1e3a8a';
        document.getElementById('buildingModalSwatch').style.background = color;

        document.getElementById('buildingModalSearch').value = '';
        currentModalFilter = 'all';
        document.querySelectorAll('.modal-filter-pill').forEach(pill => {
            pill.classList.toggle('is-active', pill.dataset.filter === 'all');
        });
        applyModalFilters();

        document.getElementById('buildingPassesModal').classList.remove('hidden');
    }

    function closeBuildingModal() {
        document.getElementById('buildingPassesModal').classList.add('hidden');
    }

    function openPassInfoModal(info) {
        document.getElementById('infoModalTitle').innerText = info.visitor_name ? `Pass #${info.pass_number} — ${info.visitor_name}` : `Pass #${info.pass_number}`;

        const photo = document.getElementById('infoPhoto');
        photo.src = info.photo_url || '';
        photo.style.display = info.photo_url ? '' : 'none';

        const idPhoto = document.getElementById('infoIdPhoto');
        idPhoto.src = info.id_photo_url || '';
        idPhoto.style.display = info.id_photo_url ? '' : 'none';

        const rows = [
            ['Gender', info.gender],
            ['Contact No.', info.contact_no],
            ['Email', info.visitor_email],
            ['ID Type', info.id_type],
            ['ID Number', info.id_ref],
            ['Office to Visit', info.office_to_visit],
            ['Reason', info.purpose],
            ['Vehicle', info.vehicle],
            ['Registered By', info.registered_by],
            ['Pass Class', info.pass_class === 'long_term' ? 'Long-term' : 'Day'],
            ['Issued', info.issued_at],
            ['Expected Return', info.expected_return_date],
        ];

        document.getElementById('infoFieldsGrid').innerHTML = rows
            .filter(([, value]) => value)
            .map(([label, value]) => `<div><span class="gov-meta-label">${label}</span><div class="gov-meta-value">${value}</div></div>`)
            .join('');

        document.getElementById('passInfoModal').classList.remove('hidden');
    }

    function closePassInfoModal() {
        document.getElementById('passInfoModal').classList.add('hidden');
    }

    function filterModalPasses(status) {
        currentModalFilter = status;
        document.querySelectorAll('.modal-filter-pill').forEach(pill => {
            pill.classList.toggle('is-active', pill.dataset.filter === status);
        });
        applyModalFilters();
    }

    function applyModalFilters() {
        const visibleGroup = document.querySelector('[id^="buildingPassGroup-"]:not(.hidden)');
        if (!visibleGroup) return;

        const query = document.getElementById('buildingModalSearch').value.trim().toLowerCase();
        let anyVisible = false;

            visibleGroup.querySelectorAll('.gov-pass-card').forEach(row => {
            const matchesStatus = currentModalFilter === 'all'|| (currentModalFilter === 'inactive' ? ['expired', 'revoked'].includes(row.dataset.status) : row.dataset.status === currentModalFilter);
            const matchesSearch = !query || (row.dataset.search || '').includes(query);
            const show = matchesStatus && matchesSearch;
            row.style.display = show ? '' : 'none';
            if (show) anyVisible = true;
        });

        const emptyState = visibleGroup.querySelector('.gov-pass-row-empty');
        if (emptyState) {
            emptyState.classList.toggle('hidden', anyVisible || visibleGroup.querySelectorAll('.gov-pass-card').length === 0);
        }
    }

</script>
@endsection
