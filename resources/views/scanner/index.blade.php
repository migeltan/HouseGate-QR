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
                    <span class="gov-card-title">Live Feed</span>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <label class="gov-qr-fallback-toggle" style="font-size:0.8rem; display:flex; align-items:center; gap:0.35rem;">
                    <input type="checkbox" id="qrFallbackMode"> QR fallback mode
                </label>
                <button onclick="toggleCamera()" id="toggleCamBtn" class="gov-btn-camera">
                    <i class="fa-solid fa-play"></i> Start Camera
                </button>
            </div>


        </div>

        <div class="gov-card-body flex-grow flex flex-col">
            <div class="gov-scanner-viewport">
                <div id="reader" class="w-full h-full"></div>
                <video id="securityCamVideo" autoplay playsinline muted class="hidden w-full h-full object-cover"></video>
                <canvas id="securityCamCanvas" class="hidden"></canvas>

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
                        <!-- Status -->
            <div id="statusHeader" class="gov-status-banner">
                <div>
                    <div class="gov-status-title-row">
                        <div id="statusText" class="gov-status-title"></div>
                        <span id="resDirectionBadge" class="gov-direction-badge hidden"></span>
                    </div>
                    <div id="statusSubtitle" class="gov-status-subtitle"></div>
                    <div id="statusEntryLine" class="gov-status-extra-line"></div>
                    <div id="advisoryText" class="gov-status-extra-line"></div>
                    <div id="statusVisiting" class="gov-status-extra-line"></div>
                    <div id="statusNoNameNotice" class="gov-status-extra-line hidden" style="color:#b45309; font-weight:700;">No name on file — verify identity via the ID photo.</div>
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

            <button type="button" id="viewIdPhotoBtn" class="hidden" style="margin-top:8px; font-size:12.5px; width:100%;" onclick="showIdPhotoPopup()">
                <i class="fa-solid fa-id-card"></i> View ID Photo
            </button>
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

<div id="resActivitySection" class="gov-activity-section hidden">
    <p class="gov-activity-heading">Recent Activity — This Pass</p>
    <div id="resActivityList" class="gov-activity-list"></div>
</div>

    {{--
                <div id="securityAdvisory" class="gov-advisory anim-fade-in-up anim-delay-2"><span id="advisoryText"></span></div>
            </div>
        </div>
    </div>

</div>
--}}


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

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
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
    if (journeyIndex < journeySteps.length - 1) { journeyIndex++; updateJourneyImage(); }
}

function prevJourneyStep() {
    if (journeyIndex > 0) { journeyIndex--; updateJourneyImage(); }
}

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
        e.preventDefault();
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
    const verificationPhoto = captureSecurityFrame();
    flashCaptureFeedback();

    try {
        const res = await fetch('{{ route('scanner.scan') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ token, scanned_building_id: buildingId, verification_photo: verificationPhoto })
        });

        if (!res.ok) {
            const text = await res.text();
            console.error('Scan request failed', res.status, text);
            alert('Scan failed (server error ' + res.status + '). Check console/logs.');
            return;
        }

        const data = await res.json();
        displayScanResultUI(data);
    } catch (err) {
        console.error('Scan processing error', err);
        alert('Scan failed to process — see console for details.');
    }
}

function flashCaptureFeedback() {
    if (!securityCamStream) return;
    const video = document.getElementById('securityCamVideo');
    video.classList.add('gov-capture-flash');
    setTimeout(() => video.classList.remove('gov-capture-flash'), 200);
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

    window.__lastIdPhotoUrl = data.id_photo_url || null;
    document.getElementById('viewIdPhotoBtn').classList.toggle('hidden', !window.__lastIdPhotoUrl);
    document.getElementById('statusNoNameNotice').classList.toggle('hidden', data.visitor_name !== 'Unnamed Visitor');

    const header = document.getElementById('statusHeader');
    const advisory = document.getElementById('advisoryText');
    const entryLine = document.getElementById('statusEntryLine');

    const statusClassMap = {
        AUTHORIZED: 'is-authorized',
        UNAUTHORIZED: 'is-denied',
        EXPIRED: 'is-expired',
        REVOKED: 'is-revoked',
        BLOCKED: 'is-blocked',
        INVALID: 'is-invalid',
    };
    const statusClass = statusClassMap[data.result] || 'is-invalid';
    header.className = `gov-status-banner ${statusClass} anim-fade-in-up`;

    const label = data.result.charAt(0) + data.result.slice(1).toLowerCase();
    document.getElementById('statusText').innerHTML = `Access <span class="gov-status-title-accent">${label}</span>`;
    document.getElementById('statusSubtitle').innerText = data.reason;
    entryLine.innerText = data.result === 'AUTHORIZED'
        ? (data.direction === 'out' ? 'Exited' : 'Entered') + ' at ' + data.timestamp
        : '';
    advisory.innerText = data.result === 'AUTHORIZED'
        ? `Confirmed: ${data.visitor_name} holds a valid pass for ${data.scanned_building}.`
        : data.reason;

    // Congressman(s) / offices the visitor is going to, one per line.
    const visiting = data.visiting || [];
    document.getElementById('statusVisiting').innerText = visiting.length
        ? 'Visiting:\n' + visiting.map(v => v.room ? `${v.name} — ${v.room}` : v.name).join('\n')
        : '';
        
    const directionBadge = document.getElementById('resDirectionBadge');
    if (data.direction === 'in') {
        directionBadge.textContent = 'IN';
        directionBadge.className = 'gov-direction-badge is-in';
    } else if (data.direction === 'out') {
        directionBadge.textContent = 'OUT';
        directionBadge.className = 'gov-direction-badge is-out';
    } else {
        directionBadge.className = 'gov-direction-badge hidden';
    }

    const activitySection = document.getElementById('resActivitySection');
    const activityList = document.getElementById('resActivityList');
    activityList.innerHTML = '';
    if (data.recent_activity && data.recent_activity.length) {
        data.recent_activity.forEach(entry => {
            const label = entry.direction === 'in' ? 'Entered' : entry.direction === 'out' ? 'Exited' : 'Scanned';
            const row = document.createElement('div');
            row.className = 'gov-activity-row';
            row.innerHTML = `
                <span class="gov-activity-dir is-${entry.direction || 'neutral'}">${label}</span>
                <span class="gov-activity-building">${entry.building}</span>
                <span class="gov-activity-time">${entry.time} &middot; ${entry.date}</span>
            `;
            activityList.appendChild(row);
        });
        activitySection.classList.remove('hidden');
    } else {
        activitySection.classList.add('hidden');
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

let securityCamStream = null;

function toggleCamera() {
    const btn = document.getElementById('toggleCamBtn');
    const placeholder = document.getElementById('camPlaceholder');
    const targetOverlay = document.getElementById('scanTargetOverlay');
    const qrMode = document.getElementById('qrFallbackMode').checked;

    if (!isCameraActive) {
        if (qrMode) {
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
                    setTimeout(() => { scanCooldownActive = false; lastScannedToken = null; }, 3000);
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
            navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
                .then(stream => {
                    securityCamStream = stream;
                    const video = document.getElementById('securityCamVideo');
                    video.srcObject = stream;
                    video.classList.remove('hidden');
                    isCameraActive = true;
                    btn.innerHTML = '<i class="fa-solid fa-stop"></i> Stop Camera';
                    btn.className = 'gov-btn-camera is-recording';
                    placeholder.classList.add('hidden');
                }).catch(err => alert("Camera error: " + err));
        }
    } else {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => html5QrcodeScanner.clear());
            html5QrcodeScanner = null;
        }
        if (securityCamStream) {
            securityCamStream.getTracks().forEach(t => t.stop());
            securityCamStream = null;
            document.getElementById('securityCamVideo').classList.add('hidden');
        }
        isCameraActive = false;
        btn.innerHTML = '<i class="fa-solid fa-play"></i> Start Camera';
        btn.className = 'gov-btn-camera';
        placeholder.classList.remove('hidden');
        targetOverlay.classList.add('hidden');
    }
}

function captureSecurityFrame() {
    if (!securityCamStream) return null;
    const video = document.getElementById('securityCamVideo');
    const canvas = document.getElementById('securityCamCanvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    return canvas.toDataURL('image/jpeg', 0.85);
}

function showIdPhotoPopup() {
    if (!window.__lastIdPhotoUrl) return;
    const overlay = document.createElement('div');
    overlay.className = 'gov-toast-container';
    overlay.innerHTML = `
        <div class="gov-toast" style="max-width:520px; padding:1rem;">
            <img src="${window.__lastIdPhotoUrl}" alt="ID photo" style="width:100%; border-radius:0.6rem;">
            <button type="button" class="gov-toast-action" style="margin-top:1rem;">Close</button>
        </div>
    `;
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
    overlay.querySelector('.gov-toast-action').addEventListener('click', () => overlay.remove());
    document.body.appendChild(overlay);
}

</script>
@endsection{{-- resources/views/scanner/index.blade.php --}}

