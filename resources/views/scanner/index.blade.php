{{-- resources/views/scanner/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Scanner Terminal')

@section('content')

<div class="gov-card">
    <div class="gov-card-header">
        <div class="gov-card-header-left">
            <i class="fa-solid fa-desktop gov-card-header-icon"></i>
            <div>
                <span class="gov-eyebrow">Instructions</span>
                <span class="gov-card-title">Visitor Access Scanner</span>
            </div>
        </div>
        <div class="gov-corner-accent" aria-hidden="true"></div>
    </div>
    <div class="gov-card-body">
        <p>Scan a visitor's pass to validate authorized building access through this terminal. The personnel must do the following:</p>
        <ol class="gov-steps">
            <li>Start the camera scanner</li>
            <li>Choose the corresponding station assigned</li>
            <li>Let the visitors scan their QR-Based Visitor Pass</li>
        </ol>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6" id="scannerAuthGrid">

    {{-- Live Scanner --}}
    <div class="gov-card flex flex-col" id="liveScannerCard">
    <div class="gov-card-header">
        <div class="gov-card-header-left">
            <i class="fa-solid fa-camera gov-card-header-icon"></i>

                <div>
                    <span class="gov-eyebrow">Camera</span>
                    <span class="gov-card-title">Live Scanner</span>
                </div>
            </div>

            <button onclick="toggleCamera()" id="toggleCamBtn" class="gov-btn-camera">
                <i class="fa-solid fa-play"></i> Start Camera
            </button>

        </div>

        <div class="gov-card-body flex-grow flex flex-col">
            <div class="gov-scanner-viewport">
                <div id="reader" class="w-full h-full"></div>

                <div id="scanTargetOverlay" class="gov-scan-overlay hidden">
                    <span class="gov-scan-corner gov-scan-corner-tl"></span>
                    <span class="gov-scan-corner gov-scan-corner-tr"></span>
                    <span class="gov-scan-corner gov-scan-corner-bl"></span>
                    <span class="gov-scan-corner gov-scan-corner-br"></span>
                </div>

                <div id="camPlaceholder" class="gov-scan-placeholder">
                    <p>Awaiting Camera Feed</p>
                </div>


            </div>
        </div>
    </div>

    {{-- Authorization (location badge lives in the header now) --}}
    <div id="resultCard" class="gov-card flex flex-col">
        <div class="gov-card-header">
            <div class="gov-card-header-left">
                <i class="fa-solid fa-folder-open gov-card-header-icon"></i>
                <div>
                    <span class="gov-eyebrow">Status and Location</span>
                    <span class="gov-card-title">Authorization</span>
                </div>
            </div>

                        <div class="flex items-center gap-2">
                @if (auth()->user()->isGuard())
                    <span class="gov-location-badge">
                        <i class="fa-solid fa-building-circle-check"></i> {{ $lockedBuilding->name }} Entrance
                    </span>
                    <input type="hidden" id="scannerBuildingId" value="{{ $lockedBuilding->id }}">
                @else
                    <div class="gov-location-wrap">
                        <select id="scannerBuildingId" class="gov-location-badge">
                            @foreach ($buildings as $b)
                                <option value="{{ $b->id }}">{{ $b->name }} Entrance</option>
                            @endforeach
                        </select>
                        <i class="fa-solid fa-chevron-down gov-location-chevron" aria-hidden="true"></i>
                    </div>
                @endif
                <button type="button" class="gov-btn-icon" onclick="toggleFullscreen('scannerAuthGrid')" aria-label="Full screen">
                    <i class="fa-solid fa-expand"></i>
                </button>
            </div>


        </div>

        <div class="gov-card-body flex-grow flex flex-col">
            <div id="resultIdle" class="gov-idle-panel">
                <p>Awaiting Pass Scan</p>
            </div>

                    <div id="resultActive"
            class="flex-grow flex flex-col"
            style="display: none;">

            <!-- Status -->
            <div id="statusHeader" class="gov-status-banner">
                <div>
                    <div id="statusText" class="gov-status-title"></div>
                    <div id="statusSubtitle" class="gov-status-subtitle"></div>
                </div>

                <div id="scanTimestamp" class="gov-status-timestamp"></div>
            </div>
           {{--<div id="resultActive" class="flex-grow flex flex-col gap-4" style="display: none;">
                <div id="statusHeader" class="gov-status-banner anim-fade-in-up">
                    <div>
                        <div id="statusText" class="gov-status-title"></div>
                        <div id="statusSubtitle" class="gov-status-subtitle"></div>
                    </div>
                    <div id="scanTimestamp" class="gov-status-timestamp"></div>
                </div>

                {{-- STALE WARNING
                <div id="staleNotice" class="gov-advisory is-pending anim-fade-in-up hidden">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span id="staleNoticeText"></span>
                </div>
                

                <div class="flex gap-4 items-stretch w-full anim-fade-in-up">
                    <div class="flex-shrink-0">
                        <img id="resPhoto" class="rounded-lg border w-40 h-40 object-cover hidden" alt="Visitor photo">
                        <div id="resPhotoPlaceholder" class="rounded-lg border w-40 h-40 flex items-center justify-center text-slate-300">
                            <i class="fa-solid fa-user text-3xl"></i>
                        </div>
                    </div>

                    <div class="gov-meta-grid flex-1 anim-delay-1">
                        <div><span class="gov-meta-label">Visitor</span><div id="resVisitorName" class="gov-meta-value"></div></div>
                        <div><span class="gov-meta-label">Pass #</span><div id="resPassNum" class="gov-meta-value font-mono"></div></div>
                        <div><span class="gov-meta-label">Authorized Bldg.</span><div id="resPassBldg" class="gov-meta-value"></div></div>
                        <div><span class="gov-meta-label">Scanned At</span><div id="resScanLoc" class="gov-meta-value"></div></div>
                        <div id="resPassClassRow" class="hidden">
                            <span class="gov-meta-label">Pass class</span>
                            <div id="resPassClass" class="gov-meta-value">
                                <span class="badge-neutral" id="resPassClassBadge"></span>
                            </div>
                        </div>
                    </div>
                </div>

                --}}
                 <!-- Visitor Information -->
    <div class="result-details">

        <div class="result-photo">
            <img id="resPhoto"
                 class="hidden"
                 alt="Visitor photo">

            <div id="resPhotoPlaceholder"
                 class="result-photo-placeholder">
                <i class="fa-solid fa-user"></i>
            </div>
        </div>

        <div class="gov-meta-grid">

            <div>
                <span class="gov-meta-label">Visitor</span>
                <div id="resVisitorName" class="gov-meta-value"></div>
            </div>

            <div>
                <span class="gov-meta-label">Pass #</span>
                <div id="resPassNum" class="gov-meta-value font-mono"></div>
            </div>

            <div>
                <span class="gov-meta-label">Authorized Bldg.</span>
                <div id="resPassBldg" class="gov-meta-value"></div>
            </div>

            <div>
                <span class="gov-meta-label">Scanned At</span>
                <div id="resScanLoc" class="gov-meta-value"></div>
            </div>

            <div id="resPassClassRow" class="hidden">
                <span class="gov-meta-label">Pass class</span>

                <div id="resPassClass" class="gov-meta-value">
                    <span class="badge-neutral"
                          id="resPassClassBadge"></span>
                </div>
            </div>

        </div>

    </div>

    {{--
                <div id="securityAdvisory" class="gov-advisory anim-fade-in-up anim-delay-2"><span id="advisoryText"></span></div>
            </div>
        </div>
    </div>

</div>
--}}

  <!-- Confirmation -->
    <div id="securityAdvisory" class="gov-advisory">
        <i class="fa-solid fa-circle-check"></i>
        <span id="advisoryText"></span>
    </div>

</div>
        </div>
    </div>
</div>

{{-- User Journey --}}

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
        <div class="relative">
            <img src="{{ asset('images/user-journey-diagram.svg') }}"
                 alt="User journey: Visitor Registration, Pass Assignment, QR Code from Pass, Visitor Presents Pass"
                 class="w-full h-auto">
            <button type="button" class="gov-journey-nav" aria-label="Next step">
                <i class="fa-solid fa-caret-right"></i>
            </button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let html5QrcodeScanner = null;
let isCameraActive = false;
let lastScannedToken = null;
let scanCooldownActive = false;

// --- Hardware scanner (Honeywell HF680, USB HID keyboard-wedge mode) ---
let hwBuffer = '';
let hwLastKeyTime = 0;
const HW_SCAN_MAX_GAP_MS = 50;
const HW_SCAN_MIN_LENGTH = 4;

document.addEventListener('keydown', (e) => {
    const now = Date.now();

    if (e.key === 'Enter') {
        if (hwBuffer.length >= HW_SCAN_MIN_LENGTH && (now - hwLastKeyTime) <= HW_SCAN_MAX_GAP_MS) {
            const token = hwBuffer;
            hwBuffer = '';
            if (!scanCooldownActive || token !== lastScannedToken) {
                lastScannedToken = token;
                scanCooldownActive = true;
                processScanToken(token);
                setTimeout(() => { scanCooldownActive = false; lastScannedToken = null; }, 3000);
            }
        } else {
            hwBuffer = '';
        }
        return;
    }

    if (e.key.length === 1) {
        if (now - hwLastKeyTime > HW_SCAN_MAX_GAP_MS) {
            hwBuffer = '';
        }
        hwBuffer += e.key;
        hwLastKeyTime = now;
    }
});

function playAudioFeedback(authorized) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        if (authorized) {
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime);
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1);
        } else {
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(180, ctx.currentTime);
            osc.frequency.setValueAtTime(130, ctx.currentTime + 0.15);
        }
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.4);
    } catch(e) {}
}

async function processScanToken(token) {
    if (!token) return;
    const buildingId = document.getElementById('scannerBuildingId').value;

    const res = await fetch('{{ route('scanner.scan') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ token: token, scanned_building_id: buildingId })
    });
    const data = await res.json();
    displayScanResultUI(data);
}

function displayScanResultUI(data) {
    const idleEl = document.getElementById('resultIdle');
    const activeEl = document.getElementById('resultActive');

    //idleEl.style.display = 'none';
    //activeEl.style.display = 'flex';

    // Hide waiting state
    idleEl.style.display = 'none';

    // Show scan result
    activeEl.style.display = 'flex';

    activeEl.querySelectorAll('.anim-fade-in-up').forEach(el => {
        el.classList.remove('anim-fade-in-up');
        void el.offsetWidth;
        el.classList.add('anim-fade-in-up');
    });

    /*
    document.getElementById('scanTimestamp').innerText = data.timestamp;
    document.getElementById('resVisitorName').innerText = data.visitor_name;
    document.getElementById('resPassNum').innerText = data.pass_number;
    document.getElementById('resPassBldg').innerText = data.authorized_building;
    document.getElementById('resScanLoc').innerText = data.scanned_building;
    */
       document.getElementById('scanTimestamp').innerText = data.timestamp;
    document.getElementById('resVisitorName').innerText = data.visitor_name;
    document.getElementById('resPassNum').innerText = data.pass_number;
    document.getElementById('resPassBldg').innerText = data.authorized_building;
    document.getElementById('resScanLoc').innerText = data.scanned_building;


    const passClassRow = document.getElementById('resPassClassRow');
    if (data.pass_class === 'long_term' && data.days_remaining !== null && data.days_remaining !== undefined) {
        document.getElementById('resPassClassBadge').innerText = `Long-term · ${data.days_remaining}d left`;
        passClassRow.classList.remove('hidden');
    } else {
        passClassRow.classList.add('hidden');
    }


    /*STALE WARNING
    const staleNotice = document.getElementById('staleNotice');
    const staleNoticeText = document.getElementById('staleNoticeText');
    if (data.stale_notice) {
        staleNoticeText.innerText = data.stale_notice;
        staleNotice.classList.remove('hidden');
    } else {
        staleNotice.classList.add('hidden');
    }*/

    const photoImg = document.getElementById('resPhoto');
    const photoPlaceholder = document.getElementById('resPhotoPlaceholder');
    if (data.photo_url) {
        photoImg.src = data.photo_url;
        photoImg.classList.remove('hidden');
        photoPlaceholder.classList.add('hidden');
    } else {
        photoImg.classList.add('hidden');
        photoPlaceholder.classList.remove('hidden');
    }

    const header = document.getElementById('statusHeader');
    const advisory = document.getElementById('advisoryText');
    const advisoryBox = document.getElementById('securityAdvisory');

    if (data.result === 'AUTHORIZED') {
        header.className = 'gov-status-banner is-authorized anim-fade-in-up';
        document.getElementById('statusText').innerText = 'Access authorized';
        document.getElementById('statusSubtitle').innerText = 'Visitor authorized for this building';
        advisoryBox.className = 'gov-advisory is-authorized anim-fade-in-up anim-delay-2';
        advisory.innerText = `Confirmed: ${data.visitor_name} holds a valid pass for ${data.scanned_building}.`;
    } else {
        header.className = 'gov-status-banner is-denied anim-fade-in-up';
        document.getElementById('statusText').innerText = 'Denied: ' + data.result.charAt(0) + data.result.slice(1).toLowerCase();
        document.getElementById('statusSubtitle').innerText = 'Security alert';
        advisoryBox.className = 'gov-advisory is-denied anim-fade-in-up anim-delay-2';
        advisory.innerText = data.reason;
    }
    playAudioFeedback(data.result === 'AUTHORIZED');
}

function toggleFullscreen(elId) {
    const el = document.getElementById(elId);
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        el.requestFullscreen().catch(err => alert('Fullscreen error: ' + err.message));
    }
}

document.addEventListener('fullscreenchange', () => {
    const isFs = !!document.fullscreenElement;
    document.querySelectorAll('.gov-btn-icon i').forEach(icon => {
        icon.classList.toggle('fa-expand', !isFs);
        icon.classList.toggle('fa-compress', isFs);
    });
});

function toggleCamera() {
    const btn = document.getElementById('toggleCamBtn');
    const placeholder = document.getElementById('camPlaceholder');
    const targetOverlay = document.getElementById('scanTargetOverlay');
    if (!isCameraActive) {
        html5QrcodeScanner = new Html5Qrcode("reader");
        html5QrcodeScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 220, height: 220 } },
            (decodedText) => {
                if (scanCooldownActive) return;
                if (decodedText === lastScannedToken) return;
                lastScannedToken = decodedText;
                scanCooldownActive = true;
                processScanToken(decodedText);
                setTimeout(() => {
                    scanCooldownActive = false;
                    lastScannedToken = null;
                }, 3000);
            },
            () => {}
        ).then(() => {
            isCameraActive = true;
            btn.innerHTML = '<i class="fa-solid fa-stop"></i> Stop Camera';
            btn.className = 'gov-btn-camera is-recording';
            placeholder.classList.add('hidden');
            targetOverlay.classList.remove('hidden');
        }).catch(err => alert("Camera error: " + err));
    } else {
        html5QrcodeScanner.stop().then(() => {
            html5QrcodeScanner.clear();
            isCameraActive = false;
            btn.innerHTML = '<i class="fa-solid fa-play"></i> Start Camera';
            btn.className = 'gov-btn-camera';
            placeholder.classList.remove('hidden');
            targetOverlay.classList.add('hidden');
        });
    }
}
</script>
@endsection{{-- resources/views/scanner/index.blade.php --}}

