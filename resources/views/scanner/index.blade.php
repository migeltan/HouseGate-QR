{{-- resources/views/scanner/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Scanner Terminal')

@section('content')
<div class="hero-govt">
    <div class="sunburst-red" aria-hidden="true"></div>

    <div class="hero-frame">
        <div class="hero-frame-rattan" aria-hidden="true"></div>

        <div class="hero-inner-panel flex items-center gap-5">
            <div class="hero-logo-badge hidden sm:flex">
                <img src="{{ asset('images/lsb-icon.png') }}" alt="LSB emblem">
            </div>
            <div>
                <p class="eyebrow">Perimeter Security Group &middot; Scanner Terminal</p>
                <h1>Visitor Access Scanner</h1>
                <p class="lead">Scan a visitor's pass to validate authorized building access through this terminal.</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    {{-- Yellow: live feed, tall left column --}}
    <div class="lg:col-span-6 card-govt card-vivid p-5 shadow-sm space-y-4 self-start" style="--ribbon-color: var(--brand-gold)">
        <div class="flex items-center justify-between gap-3">
            <h2 class="card-header-title">
                <i class="fa-solid fa-camera"></i> Live scanner feed
            </h2>
            <button onclick="toggleCamera()" id="toggleCamBtn" class="text-xs btn-govt-primary btn-interactive px-3 py-1.5 rounded-lg">
                <i class="fa-solid fa-power-off"></i> Start webcam
            </button>
        </div>

        <div class="scanner-viewport-inset">
            <div class="relative scanner-feed overflow-hidden aspect-square flex items-center justify-center">
                <div id="reader" class="w-full h-full"></div>

                {{-- Scanner target overlay — corner framing graphic + laser sweep, shown only while camera is active --}}
                <div id="scanTargetOverlay" class="scan-target-overlay hidden">
                    <span class="scan-corner scan-corner-tl"></span>
                    <span class="scan-corner scan-corner-tr"></span>
                    <span class="scan-corner scan-corner-bl"></span>
                    <span class="scan-corner scan-corner-br"></span>
                    <span class="scan-laser"></span>
                </div>

                <div id="camPlaceholder" class="scanner-placeholder absolute inset-0 flex flex-col items-center justify-center p-6 text-center">
                    <i class="fa-solid fa-camera text-5xl mb-3"></i>
                    <p class="text-xs">Awaiting camera feed.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Right column: Red (station location) stacked over Blue (result) --}}
    <div class="lg:col-span-6 flex flex-col gap-6">

        <div class="card-govt card-vivid p-5 shadow-sm" style="--ribbon-color: var(--brand-red)">
            <label class="card-header-title mb-2">
                <i class="fa-solid fa-building-circle-check"></i> Personnel Station Location
            </label>

            @if (auth()->user()->isGuard())
                <div class="scanner-select w-full rounded-2xl px-4 py-3 font-bold bg-slate-100 text-slate-700">
                    {{ $lockedBuilding->name }} Entrance (locked to your session)
                </div>
                <input type="hidden" id="scannerBuildingId" value="{{ $lockedBuilding->id }}">
            @else
                <select id="scannerBuildingId" class="scanner-select w-full rounded-2xl px-4 py-3 font-bold">
                    @foreach ($buildings as $b)
                        <option value="{{ $b->id }}">{{ $b->name }} Entrance</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div id="resultCard" class="card-govt card-vivid p-5 shadow-sm flex-grow flex flex-col" style="--ribbon-color: var(--brand-blue)">
            <h2 class="card-header-title mb-4"><i class="fa-solid fa-shield-halved"></i> Authorization Status</h2>

            <div id="resultIdle" class="idle-panel flex-grow flex flex-col items-center justify-center text-center">
                <i class="fa-solid fa-id-card idle-panel-icon"></i>
                <h3 class="idle-panel-title">Awaiting Pass Scan</h3>
            </div>

            <div id="resultActive" class="hidden flex-grow flex flex-col gap-4">
                <div id="statusHeader" class="status-banner-card anim-fade-in-up">
                    <div>
                        <div id="statusText" class="status-title"></div>
                        <div id="statusSubtitle" class="status-subtitle"></div>
                    </div>
                    <div id="scanTimestamp" class="status-timestamp"></div>
                </div>

                {{-- Stale-occupancy notice — only shown when the backend flags
                     a forgotten scan-out being treated as a fresh entry. --}}
                <div id="staleNotice" class="advisory-card is-pending anim-fade-in-up hidden">
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

                    <div class="meta-grid-card flex-1 w-full grid grid-cols-2 gap-4 content-center anim-delay-1">
                        <div><span class="meta-label">Visitor</span><div id="resVisitorName" class="meta-value"></div></div>
                        <div><span class="meta-label">Pass #</span><div id="resPassNum" class="meta-value font-mono"></div></div>
                        <div><span class="meta-label">Authorized Bldg.</span><div id="resPassBldg" class="meta-value"></div></div>
                        <div><span class="meta-label">Scanned At</span><div id="resScanLoc" class="meta-value"></div></div>
                        <div id="resPassClassRow" class="hidden">
                            <span class="meta-label">Pass class</span>
                            <div id="resPassClass" class="meta-value">
                                <span class="badge-neutral" id="resPassClassBadge"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="securityAdvisory" class="advisory-card anim-fade-in-up anim-delay-2"><span id="advisoryText"></span></div>
            </div>
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
// The HF680 isn't a camera — it types the scanned token as fast keystrokes
// followed by Enter, into whatever has focus. We don't rely on focus at
// all: we watch every keystroke on the page, and treat a burst of
// characters arriving faster than a human could type, terminated by
// Enter, as a hardware scan. This works no matter what element (or
// nothing) currently has focus.
let hwBuffer = '';
let hwLastKeyTime = 0;
const HW_SCAN_MAX_GAP_MS = 50;   // keystrokes this close together = scanner, not typing
const HW_SCAN_MIN_LENGTH = 4;    // ignore stray Enter presses / accidental short bursts

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

    // Single printable character, arriving fast enough to be the scanner.
    if (e.key.length === 1) {
        if (now - hwLastKeyTime > HW_SCAN_MAX_GAP_MS) {
            hwBuffer = ''; // gap too long — this is a human typing, start over
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
    document.getElementById('resultIdle').classList.add('hidden');
    const activeEl = document.getElementById('resultActive');
    activeEl.classList.remove('hidden');

    // Re-trigger the fade-in-up entrance animation on every new scan by
    // forcing a reflow between removing and re-adding the animation class.
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

    // Pass class chip — only shown for long-term passes with days remaining.
    const passClassRow = document.getElementById('resPassClassRow');
    if (data.pass_class === 'long_term' && data.days_remaining !== null && data.days_remaining !== undefined) {
        document.getElementById('resPassClassBadge').innerText = `Long-term · ${data.days_remaining}d left`;
        passClassRow.classList.remove('hidden');
    } else {
        passClassRow.classList.add('hidden');
    }

    // Stale-occupancy notice — backend sends this only when it just reset
    // a forgotten scan-out and treated this scan as a fresh entry.
    const staleNotice = document.getElementById('staleNotice');
    const staleNoticeText = document.getElementById('staleNoticeText');
    if (data.stale_notice) {
        staleNoticeText.innerText = data.stale_notice;
        staleNotice.classList.remove('hidden');
    } else {
        staleNotice.classList.add('hidden');
    }

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
        header.className = 'status-banner-card is-authorized anim-fade-in-up';
        document.getElementById('statusText').innerText = 'Access authorized';
        document.getElementById('statusSubtitle').innerText = 'Visitor authorized for this building';
        advisoryBox.className = 'advisory-card is-authorized anim-fade-in-up anim-delay-2';
        advisory.innerText = `Confirmed: ${data.visitor_name} holds a valid pass for ${data.scanned_building}.`;
    } else {
        header.className = 'status-banner-card is-denied anim-fade-in-up';
        document.getElementById('statusText').innerText = 'Denied: ' + data.result.charAt(0) + data.result.slice(1).toLowerCase();
        document.getElementById('statusSubtitle').innerText = 'Security alert';
        advisoryBox.className = 'advisory-card is-denied anim-fade-in-up anim-delay-2';
        advisory.innerText = data.reason;
    }
    playAudioFeedback(data.result === 'AUTHORIZED');
}

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
            btn.innerHTML = '<i class="fa-solid fa-stop"></i> Stop camera';
            btn.className = "text-xs btn-govt-outline btn-interactive px-3 py-1.5 rounded-lg";
            btn.style.borderColor = 'var(--status-denied)';
            btn.style.color = 'var(--status-denied)';
            placeholder.classList.add('hidden');
            targetOverlay.classList.remove('hidden');
        }).catch(err => alert("Camera error: " + err));
    } else {
        html5QrcodeScanner.stop().then(() => {
            html5QrcodeScanner.clear();
            isCameraActive = false;
            btn.innerHTML = '<i class="fa-solid fa-power-off"></i> Start webcam';
            btn.className = "text-xs btn-govt-primary btn-interactive px-3 py-1.5 rounded-lg";
            btn.style.borderColor = '';
            btn.style.color = '';
            placeholder.classList.remove('hidden');
            targetOverlay.classList.add('hidden');
        });
    }
}
</script>
@endsection