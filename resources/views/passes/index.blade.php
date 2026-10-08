@extends('layouts.app')
@section('title', 'Visitor Pass Management')


@section('content')

    {{-- TAB 1 - INSTRUCTIONS --}}
    @include('passes.tab1-instructions')

    {{-- TAB 2 - BUILDINGS (includes the building passes modal + pass info modal) --}}
    @include('passes.tab2-buildings')

    {{-- Register Visitor modal — each step lives in resources/views/passes/ --}}
    <div id="registerModal" class="reg-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="registration-title">
        <section class="reg-modal">
            <header class="reg-modal-header">
                <i class="fa-solid fa-desktop header-icon"></i>
                <div>
<p class="reg-eyebrow" id="registerEyebrow">Registration</p>
                    <h1 id="registration-title">Register Visitor and Issue Pass</h1>
                </div>
                <button type="button" class="reg-close-button" aria-label="Close"
                    onclick="closeRegisterModal()">&times;</button>
            </header>

            {{-- Transfer banner: visible on every step while a transfer is in progress --}}
            <div id="transferBanner" class="reg-transfer-banner hidden" role="status">
                <span class="reg-transfer-banner-icon"><i class="fa-solid fa-right-left"></i></span>
                <div class="reg-transfer-banner-text">
                    <strong id="transferBannerTitle">Transfer in progress</strong>
                    <span id="transferBannerDetail">Scan the visitor’s old card to begin.</span>
                </div>
                <button type="button" class="reg-transfer-banner-cancel" onclick="cancelTransfer()">Cancel transfer</button>
            </div>

            <form method="POST" action="{{ route('passes.register') }}" id="registerForm">
                @csrf

                <main class="reg-modal-body">
                    @include('passes.step1-camera')
                    @include('passes.step2-information')
                    @include('passes.step3-destination')
                </main>

                <footer class="reg-modal-footer">
                    <button type="button" class="reg-button" onclick="closeRegisterModal()">Cancel</button>
                    <span id="registerHint" class="reg-footer-hint" aria-live="polite"></span>
                    <button type="submit" id="registerSubmitBtn" class="reg-button reg-button-green">
                        <i class="fa-solid fa-spinner fa-spin hidden" id="registerSubmitSpinner"></i>
                        <span id="registerSubmitLabel">Admit and Auto-assign</span>
                    </button>
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

            hint.classList.toggle('is-active', checked >= 2);

            refreshCongPicker(); // also re-evaluates the submit button
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
                x.onclick = () => removeCong(c.id);
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
            if (document.getElementById('congList').classList.contains('is-open')) positionCongList();
            refreshSubmitState();
        }

        let congShown = [];   // the matches currently listed, in order
        let congActive = -1;  // keyboard-highlighted row

        function pickCong(c) {
            selectedCong.set(c.id, c);
            const search = document.getElementById('congSearch');
            search.value = '';
            renderCongChips();
            renderCongList();   // list stays open so several can be picked in a row
            positionCongList();
            search.focus();
        }

        function removeCong(id) {
            selectedCong.delete(id);
            renderCongChips();
            renderCongList();
        }

        function closeCongList() {
            document.getElementById('congList').classList.remove('is-open');
        }

        function setCongActive(i) {
            const items = document.querySelectorAll('#congList .cong-item');
            if (!items.length) { congActive = -1; return; }
            congActive = (i + items.length) % items.length;
            items.forEach((el, n) => el.classList.toggle('is-active', n === congActive));
            items[congActive].scrollIntoView({ block: 'nearest' });
        }

        // Opens upward when there isn't room below, and sizes itself to the space it has,
        // so it never runs off the bottom of the window.
        function positionCongList() {
            const list = document.getElementById('congList');
            const box = document.getElementById('congBox');
            const bounds = box.closest('.reg-modal-body').getBoundingClientRect();
            const rect = box.getBoundingClientRect();
            const below = bounds.bottom - rect.bottom - 12;
            const above = rect.top - bounds.top - 12;
            const openUp = below < 200 && above > below;
            list.classList.toggle('is-up', openUp);
            list.style.maxHeight = Math.max(120, Math.min(300, openUp ? above : below)) + 'px';
        }

        function appendCongFooter(list) {
            const foot = document.createElement('div');
            foot.className = 'cong-footer';
            const count = document.createElement('span');
            count.textContent = selectedCong.size ? `${selectedCong.size} selected` : 'Pick one or more';
            const done = document.createElement('button');
            done.type = 'button';
            done.textContent = 'Done';
            // mousedown (not click) so the search box doesn't blur first
            done.addEventListener('mousedown', e => { e.preventDefault(); closeCongList(); });
            foot.append(count, done);
            list.appendChild(foot);
        }

        function renderCongList() {
            const list = document.getElementById('congList');
            const ids = checkedBuildingIds();
            const q = document.getElementById('congSearch').value.trim().toLowerCase();
            list.innerHTML = '';
            congShown = [];
            congActive = -1;
            if (!ids.length) return;

            const multi = ids.length > 1;
            const matches = CONGRESSMEN
                .filter(c => ids.includes(c.b) && !selectedCong.has(c.id) &&
                    (!q || (c.name + ' ' + (c.detail || '') + ' ' + (c.room || '')).toLowerCase().includes(q)))
                .sort((a, b) => (multi ? String(BUILDING_NAMES[a.b]).localeCompare(String(BUILDING_NAMES[b.b])) : 0) ||
                    a.name.localeCompare(b.name));

            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'cong-empty';
                empty.textContent = selectedCong.size ? 'No more matches.' : 'No matching congressman.';
                list.appendChild(empty);
                appendCongFooter(list);
                return;
            }

            congShown = matches;
            let lastB = null;
            matches.forEach((c, idx) => {
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
                    pickCong(matches[idx]);
                });
                list.appendChild(item);
            });
            appendCongFooter(list);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const search = document.getElementById('congSearch');
            const list = document.getElementById('congList');
            const open = () => {
                renderCongList();
                positionCongList();
                list.classList.add('is-open');
            };
            search.addEventListener('focus', open);
            search.addEventListener('input', open);
            search.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (list.classList.contains('is-open')) {
                        const c = congShown[congActive >= 0 ? congActive : 0];
                        if (c) pickCong(c);
                    }
                } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!list.classList.contains('is-open')) open();
                    setCongActive(congActive + (e.key === 'ArrowDown' ? 1 : -1));
                } else if (e.key === 'Escape') {
                    closeCongList();
                } else if (e.key === 'Backspace' && !search.value && selectedCong.size) {
                    removeCong(Array.from(selectedCong.keys()).pop());
                }
            });
            // Clicking anywhere in the box (not on a chip's ×) focuses the search.
            document.getElementById('congBox').addEventListener('click', e => {
                if (!e.target.closest('.cong-chip button')) search.focus();
            });
            document.addEventListener('click', e => {
                if (!e.target.closest('#congPicker')) closeCongList();
            });
            const reposition = () => { if (list.classList.contains('is-open')) positionCongList(); };
            window.addEventListener('resize', reposition);
            document.querySelector('#registerModal .reg-modal-body').addEventListener('scroll', reposition);
            refreshCongPicker();
        });
        // ---- End congressman picker ----

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
                    cap.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
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
                photoStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user'
                    }
                });
                video.srcObject = photoStream;
                video.classList.remove('hidden');
                document.getElementById('photoPlaceholderText').classList.add('hidden');
                document.getElementById('startCameraBtn').classList.add('hidden');
                document.getElementById('captureBtn').classList.remove('hidden');
            } catch (err) {
                showToast(err.message, 'error', 'Camera Unavailable');
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
                photoStream.getTracks().forEach(t => t.stop());
                photoStream = null;
            }
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
        let idPhotos = {
            front: null,
            back: null
        };
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
                placeholder.innerText = idSlide === 0 ? 'Capture Front and Back of the ID' :
                    'Now capture the back of the ID';
            }

            mainBtn.innerHTML = idCameraActive ? 'Capture' : (photo ? 'Retake' :
                '<span aria-hidden="true">&#9654;</span> Start Camera');
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
            if (idCameraActive) {
                captureCurrentIdSlide();
                return;
            }
            const side = idSlide === 0 ? 'front' : 'back';
            idPhotos[side] = null;
            try {
                idPhotoStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    }
                });
                if (idSlide === 0 && window.Tesseract) getOcrWorker().catch(() => {}); // load OCR while the guard lines up the card
                document.getElementById('idPhotoVideo').srcObject = idPhotoStream;
                idCameraActive = true;
                updateIdSlideUI();
            } catch (err) {
                alert('Could not access camera: ' + err.message);
            }
        }

        async function captureCurrentIdSlide() {
            if (captureCurrentIdSlide.busy) return; // ignore double-taps during the burst
            captureCurrentIdSlide.busy = true;
            const video = document.getElementById('idPhotoVideo');
            const canvas = document.getElementById('idPhotoCanvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');

            // Burst: grab 4 frames ~120ms apart and keep the sharpest (hand shake / autofocus hunting).
            let bestFrame = null, bestScore = -1;
            for (let i = 0; i < 4; i++) {
                ctx.drawImage(video, 0, 0);
                const score = measureSharpness(canvas);
                if (score > bestScore) {
                    bestScore = score;
                    bestFrame = ctx.getImageData(0, 0, canvas.width, canvas.height);
                }
                if (i < 3) await new Promise(r => setTimeout(r, 120));
            }
            ctx.putImageData(bestFrame, 0, 0);
            captureCurrentIdSlide.busy = false;
            const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

            stopIdCameraStream();
            idCameraActive = false;

            if (idSlide === 0) {
                idPhotos.front = dataUrl;
                document.getElementById('idPhotoDataInput').value = dataUrl;
                updateIdSlideUI();
                runIdOcr(canvas);
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
            if (idPhotoStream) {
                idPhotoStream.getTracks().forEach(t => t.stop());
                idPhotoStream = null;
            }
        }

        function resetIdPhotoCapture() {
            stopIdCameraStream();
            idSlide = 0;
            idPhotos = {
                front: null,
                back: null
            };
            idCameraActive = false;
            document.getElementById('idPhotoDataInput').value = '';
            setIdCaptureStatus("Capture the visitor's ID — front, then back.", 'neutral');
            clearIdChips();
            updateIdSlideUI();
        }

        function setIdCaptureStatus(message, tone) {
            const el = document.getElementById('idCaptureStatus');
            const colors = {
                neutral: '#64748b',
                busy: '#2563eb',
                success: '#1c9a5b',
                warn: '#b45309',
                error: 'var(--brand-red)'
            };
            el.innerText = message;
            el.style.color = colors[tone] || colors.neutral;
        }

        // ---- ID OCR auto-fill (any ID type; client-side via Tesseract.js, images never leave the device) ----
        const ID_BLUR_THRESHOLD = 40; // Laplacian variance on a 640px copy; raise it if blurry shots slip through

        const ID_FIELD_LABELS = {
            last: [/Apelyido/i, /Last\s*Nam/i, /Sur\s*name/i, /Family\s*Nam/i],
            first: [/Pangalan/i, /G\w{2,4}n\s*Nam/i, /First\s*Nam/i],
            middle: [/Gitnang/i, /Middle\s*Nam/i],
        };
        const ID_LABEL_RX = [
            ...Object.values(ID_FIELD_LABELS).flat(),
            /\bPetsa\b|Date\s*of\s*Birth|\bBirth/i, /Tirahan|\bAddress\b/i,
            /Republika|Philippines|Identification|National\s*ID|\bLicense\b|\bPassport\b/i,
            /\b(Sex|Kasarian|Nationality|Signature|Expiry|Expiration|Valid|Blood|Height|Weight|Agency|Conditions|Restrictions)\b/i,
        ];
        const ID_ADDRESS_RX = /\d|\b(city|brgy|barangay|st|street|ave|avenue|metro|manila|province|blk|lot)\b/i;

        const isIdLabel = (s) => ID_LABEL_RX.some(p => p.test(s));
        const idClean = (s) => s.replace(/[^A-Za-zÑñÁÉÍÓÚáéíóú.,' -]/g, '').replace(/\s+/g, ' ').trim();
        const idNameLike = (s) => (s.match(/[A-Za-zÑñÁÉÍÓÚáéíóú]/g) || []).length >= 2 && !isIdLabel(s);
        const splitCombined = (s) => { // "DELA CRUZ, JUAN SANTOS"
            const [l, ...rest] = s.split(',');
            const r = rest.join(' ').trim();
            return (l.trim().length >= 2 && r.length >= 2) ? { last: l.trim(), given: r } : null;
        };

        const isCapsLine = (s) => /^[A-ZÑ][A-ZÑ.' -]+$/.test(s);
        const PHILSYS_NOISE_RX = /PHL|REPUBLIK|PILIPINAS|PAMBANSANG|PAGKAKA|PHILIPPINE|IDENTIF|CARD/;

        // PhilSys front: card number, then LAST / FIRST / MIDDLE in large caps, then the birth date.
        // Position-based, so it still works when the tiny italic labels are misread.
        function philsysLayoutNames(lines) {
            let start = lines.findIndex(l => /\d{4}[\s-]\d{4}[\s-]\d{4}[\s-]\d{4}/.test(l));
            if (start < 0) {
                start = lines.reduce((acc, l, i) => (/PAMBANSANG|PAGKAKA|PILIPINAS/i.test(l) ? i : acc), -1);
            }
            if (start < 0) return null;
            const names = [];
            for (const raw of lines.slice(start + 1)) {
                if (/\d/.test(raw)) { if (names.length) break; else continue; } // the date line ends the name block
                const c = idClean(raw).replace(/\s+[a-zñ]{1,2}$/, '');
                if (!isCapsLine(c) || c.replace(/[^A-ZÑ]/g, '').length < 2 || PHILSYS_NOISE_RX.test(c)) continue;
                names.push(c);
                if (names.length === 3) break;
            }
            return names.length >= 2 ? [names[0], names[1], names[2] || null] : null;
        }

        // One shared OCR worker: the language data downloads once, later captures start instantly.
        let ocrWorkerPromise = null;

        function getOcrWorker() {
            if (!ocrWorkerPromise) {
                ocrWorkerPromise = Tesseract.createWorker('eng').then(async (w) => {
                    await w.setParameters({ tessedit_pageseg_mode: '11', preserve_interword_spaces: '1' }); // sparse text suits cards
                    return w;
                }).catch((e) => { ocrWorkerPromise = null; throw e; });
            }
            return ocrWorkerPromise;
        }

        // Grayscale + contrast stretch + upscale small frames. Makes camera shots far easier for Tesseract.
        function preprocessIdCanvas(src) {
            const scale = Math.min(2, Math.max(1, 1800 / src.width));
            const c = document.createElement('canvas');
            c.width = Math.round(src.width * scale);
            c.height = Math.round(src.height * scale);
            const ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(src, 0, 0, c.width, c.height);
            const img = ctx.getImageData(0, 0, c.width, c.height);
            const d = img.data, n = d.length / 4;
            const gray = new Uint8ClampedArray(n);
            const hist = new Uint32Array(256);
            for (let i = 0; i < n; i++) {
                const g = (d[i * 4] * 0.299 + d[i * 4 + 1] * 0.587 + d[i * 4 + 2] * 0.114) | 0;
                gray[i] = g;
                hist[g]++;
            }
            let lo = 0, hi = 255, acc = 0;
            for (let v = 0; v < 256; v++) { acc += hist[v]; if (acc >= n * 0.02) { lo = v; break; } }
            acc = 0;
            for (let v = 255; v >= 0; v--) { acc += hist[v]; if (acc >= n * 0.02) { hi = v; break; } }
            const range = Math.max(40, hi - lo);
            for (let i = 0; i < n; i++) {
                const v = Math.max(0, Math.min(255, ((gray[i] - lo) * 255 / range) | 0));
                d[i * 4] = d[i * 4 + 1] = d[i * 4 + 2] = v;
            }
            ctx.putImageData(img, 0, 0);
            return c;
        }

        // Variance of the Laplacian on a 640px copy: low = blurry.
        function measureSharpness(src) {
            const w = 640, h = Math.max(3, Math.round(src.height * w / src.width));
            const c = document.createElement('canvas');
            c.width = w;
            c.height = h;
            const ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(src, 0, 0, w, h);
            const d = ctx.getImageData(0, 0, w, h).data;
            const g = new Float32Array(w * h);
            for (let i = 0; i < w * h; i++) g[i] = d[i * 4] * 0.299 + d[i * 4 + 1] * 0.587 + d[i * 4 + 2] * 0.114;
            let sum = 0, sumSq = 0, cnt = 0;
            for (let y = 1; y < h - 1; y++) {
                for (let x = 1; x < w - 1; x++) {
                    const i = y * w + x;
                    const l = 4 * g[i] - g[i - 1] - g[i + 1] - g[i - w] - g[i + w];
                    sum += l; sumSq += l * l; cnt++;
                }
            }
            const mean = sum / cnt;
            return sumSq / cnt - mean * mean;
        }

        async function runIdOcr(srcCanvas) {
            clearIdChips();
            setIdCaptureStatus('Reading ID… this can take a few seconds.', 'busy');
            try {
                // Do the canvas work first: the shared capture canvas is reused for the back side.
                const prepared = preprocessIdCanvas(srcCanvas);
                const blurry = measureSharpness(srcCanvas) < ID_BLUR_THRESHOLD;

                const worker = await getOcrWorker();
                const { data } = await worker.recognize(prepared);
                const lines = (data.lines || []).map(l => ({ text: l.text.trim(), conf: l.confidence })).filter(l => l.text);
                const text = lines.length ? lines.map(l => l.text).join('\n') : (data.text || '');

                const filled = applyIdOcrText(text);
                const chips = renderIdChips(lines.length ? lines : text.split('\n').map(t => ({ text: t.trim() })));
                if (filled.includes('Last Name') && filled.includes('First Name')) clearIdChips(); // no manual picking needed
                const note = blurry ? ' The image looks blurry. Retake for a better read.' : '';

                if (filled.length) {
                    setIdCaptureStatus(`Auto-filled: ${filled.join(', ')}. Please review.${note}`, 'success');
                } else if (chips) {
                    setIdCaptureStatus(`Could not pick out the name. Tap a line below, or type it.${note}`, 'warn');
                } else {
                    setIdCaptureStatus(`Could not read the ID clearly. Please fill in manually.${note}`, 'warn');
                }
            } catch (err) {
                console.error('ID OCR failed:', err);
                setIdCaptureStatus('ID reading failed. Please fill in manually.', 'error');
            }
        }

        function applyIdOcrText(rawText) {
            const lines = rawText.split('\n').map(l => l.trim()).filter(Boolean);

            // Value next to a label: after a ':' on the same line, else the next ALL-CAPS line (values print in
            // caps; labels are small mixed-case text that OCR garbles), else any name-like line within 3 lines.
            const findValue = (patterns, exclude = []) => {
                for (let i = 0; i < lines.length; i++) {
                    const hit = patterns.find(p => p.test(lines[i]));
                    if (!hit || exclude.some(p => p.test(lines[i]))) continue;
                    if (lines[i].includes(':')) {
                        const same = idClean(lines[i].split(':').slice(1).join(':'));
                        if (idNameLike(same)) return same;
                    }
                    const next = lines.slice(i + 1, i + 4).map(idClean).filter(idNameLike);
                    const v = next.find(isCapsLine) || next[0];
                    if (v) return v;
                }
                return null;
            };

            // PhilSys by layout first; every other ID falls back to labels.
            let [last, first, middle] = philsysLayoutNames(lines) || [
                findValue(ID_FIELD_LABELS.last, ID_FIELD_LABELS.middle),
                findValue(ID_FIELD_LABELS.first),
                findValue(ID_FIELD_LABELS.middle),
            ];

            // "LAST, FIRST MIDDLE" on a single line (licenses, many other IDs).
            let parts = null;
            const combined = [last, first, middle].find(v => v && v.includes(','));
            if (combined) {
                parts = splitCombined(combined);
                last = first = middle = null;
            }
            if (!parts && !last && !first) {
                for (const line of lines) {
                    const c = idClean(line);
                    if (!c.includes(',') || ID_ADDRESS_RX.test(line) || isIdLabel(c)) continue;
                    parts = splitCombined(c);
                    if (parts) break;
                }
            }
            if (parts) {
                last = parts.last;
                first = parts.given; // the guard can move a middle name with the tap chips
            }

            // Passport machine-readable zone: P<PHLDELA<CRUZ<<JUAN<SANTOS<<<<  (best effort, OCR-B is hard for Tesseract)
            let isPassport = false;
            if (!last && !first) {
                for (const raw of lines) {
                    const m = raw.toUpperCase().replace(/\s/g, '').replace(/[«‹]/g, '<')
                        .match(/^P[<A-Z][A-Z]{3}([A-Z]+(?:<[A-Z]+)*)<<([A-Z]+(?:<[A-Z]+)*)/);
                    if (m) {
                        last = m[1].replace(/</g, ' ');
                        first = m[2].replace(/</g, ' ');
                        isPassport = true;
                        break;
                    }
                }
            }

            [last, first, middle] = [last, first, middle].map(v => v ? v.replace(/,/g, ' ').replace(/\s+/g, ' ').trim() : null);

            const idMatch = rawText.match(/\d{4}[\s-]\d{4}[\s-]\d{4}[\s-]\d{4}/);
            const idRef = idMatch ? idMatch[0].replace(/\s/g, '-') : null;

            const filled = [];
            setAutofillValue('last_name', last, 'Last Name', filled);
            setAutofillValue('first_name', first, 'First Name', filled);
            setAutofillValue('middle_name', middle, 'Middle Name', filled);
            setAutofillValue('id_ref', idRef, 'ID Number', filled);

            // Only guess the ID type when it is unmistakable; otherwise leave it for the guard.
            const idTypeSelect = document.querySelector('[name="id_type"]');
            if (idTypeSelect && !idTypeSelect.value) {
                if (isPassport) idTypeSelect.value = 'Passport';
                else if (idMatch || /Mga\s*Pangalan/i.test(rawText)) idTypeSelect.value = 'PhilSys (National ID)';
            }
            return filled;
        }

        // ---- Tap-a-line-to-assign chips: works on any ID, no layout knowledge needed ----
        let selectedIdChip = null;

        function clearIdChips() {
            selectedIdChip = null;
            document.getElementById('idChipList')?.replaceChildren();
            document.getElementById('idChipAssign')?.classList.add('hidden');
            document.getElementById('idOcrChips')?.classList.add('hidden');
        }

        function renderIdChips(lines) {
            const seen = new Set();
            const texts = [];
            lines.forEach(l => {
                if (l.conf !== undefined && l.conf < 30) return;
                idClean(l.text).split(',').map(s => s.trim()).forEach(t => {
                    const key = t.toUpperCase();
                    if (t.length < 2 || t.length > 40 || !idNameLike(t) || seen.has(key)) return;
                    seen.add(key);
                    texts.push(t);
                });
            });

            const list = document.getElementById('idChipList');
            list.replaceChildren(...texts.slice(0, 14).map(t => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'id-chip';
                b.textContent = t; // textContent: OCR output is untrusted
                return b;
            }));
            document.getElementById('idChipAssign').classList.add('hidden');
            document.getElementById('idOcrChips').classList.toggle('hidden', texts.length === 0);
            return texts.length > 0;
        }

        document.addEventListener('click', (e) => {
            const chip = e.target.closest('.id-chip');
            if (chip) {
                const wasSelected = chip.classList.contains('is-selected');
                document.querySelectorAll('.id-chip.is-selected').forEach(c => c.classList.remove('is-selected'));
                selectedIdChip = wasSelected ? null : chip.textContent;
                chip.classList.toggle('is-selected', !wasSelected);
                document.getElementById('idChipAssign').classList.toggle('hidden', !selectedIdChip);
                return;
            }
            const assign = e.target.closest('#idChipAssign [data-assign]');
            if (assign && selectedIdChip) {
                const input = document.querySelector(`[name="${assign.dataset.assign}"]`);
                if (!input) return;
                input.value = selectedIdChip; // an explicit tap is a deliberate choice: overrides earlier auto-fill
                delete input.dataset.autofilled;
                scheduleDuplicateCheck();
                setIdCaptureStatus(`${assign.textContent} set to "${selectedIdChip}". Please review.`, 'success');
                document.querySelectorAll('.id-chip.is-selected').forEach(c => c.classList.remove('is-selected'));
                selectedIdChip = null;
                document.getElementById('idChipAssign').classList.add('hidden');
            }
        });

        function setAutofillValue(name, value, label, filledList) {
            if (!value) return;
            const input = document.querySelector(`[name="${name}"]`);
            if (!input) return;
            if (!input.value.trim() || input.dataset.autofilled === '1') {
                input.value = value;
                input.dataset.autofilled = '1';
                if (filledList) filledList.push(label);
                scheduleDuplicateCheck(); // programmatic fills don't fire 'input' events
            }
        }
        document.addEventListener('input', (e) => {
            if (e.target.matches('[name="first_name"],[name="middle_name"],[name="last_name"],[name="id_ref"]'))
                delete e.target.dataset.autofilled;
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

            setIdCaptureStatus(filled.length ? `QR auto-filled: ${filled.join(', ')}. Please review.` :
                'QR read, but no matching fields found.', filled.length ? 'success' : 'warn');
        }
        // ---- End ID capture ----

        // ---- Duplicate active-pass check (Workflow 1) ----
        const DUP_URL = @json(route('passes.check-duplicate'));
        let dupTimer = null;
        let dupSeq = 0;
        let dupState = null; // null | 'exact' | 'possible'

        function scheduleDuplicateCheck() {
            clearTimeout(dupTimer);
            dupTimer = setTimeout(runDuplicateCheck, 500);
        }

        async function runDuplicateCheck() {
            const form = document.getElementById('registerForm');
            const v = (n) => (form.elements[n]?.value || '').trim();
            if (!(v('id_type') && v('id_ref')) && !(v('first_name') && v('last_name')) && !(v('last_name') && v(
                    'contact_no'))) {
                dupSeq++;
                renderDuplicate(null);
                return;
            }
            const params = new URLSearchParams({
                id_type: v('id_type'),
                id_ref: v('id_ref'),
                first_name: v('first_name'),
                last_name: v('last_name'),
                contact_no: v('contact_no'),
            });
            const transferToken = document.getElementById('transferQrTokenInput').value.trim();
            if (transferToken) params.set('transfer_qr_token', transferToken);
            const seq = ++dupSeq;
            const skTimer = setTimeout(() => { if (seq === dupSeq) toggleDupSkeleton(true); }, 150);
            try {
                const res = await fetch(`${DUP_URL}?${params}`, {
                    headers: {
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });
                if (!res.ok) return;
                const data = await res.json();
                if (seq === dupSeq) renderDuplicate(data.match, data.transfer_ready);
            } catch (e) {
                /* the server re-checks on submit */
            } finally {
                clearTimeout(skTimer);
                if (seq === dupSeq) toggleDupSkeleton(false);
            }
        }

        function toggleDupSkeleton(on) {
            document.getElementById('dupSkeleton').classList.toggle('hidden', !on);
        }

        function dupBlocked() {
            if (transferModeActive) return !document.getElementById('transferQrTokenInput').value;
            if (dupTransferReady) return false; // a verified transfer resolves either level
            return dupState === 'exact' ||
                (dupState === 'possible' && !document.getElementById('dupConfirmCheck').checked);
        }

        // Every required piece of Step 3, mirrored from the server rules in register().
        function formReady() {
            const f = document.getElementById('registerForm');
            const v = (n) => (f.elements[n]?.value || '').trim();
            if (checkedBuildingIds().length === 0) return false;
            if (!v('purpose_choice') || (v('purpose_choice') === 'Others' && !v('purpose_other'))) return false;
            if (selectedCong.size === 0 && !v('office_other')) return false;
            if (f.elements['pass_class'].value === 'long_term' && !v('expected_return_date')) return false;
            if (!v('registered_by')) return false;
            return true;
        }

        // Which required pieces are still missing (mirrors formReady) — shown beside the Admit button.
        function missingFields() {
            const f = document.getElementById('registerForm');
            const v = (n) => (f.elements[n]?.value || '').trim();
            const out = [];
            if (checkedBuildingIds().length === 0) out.push('building');
            if (selectedCong.size === 0 && !v('office_other')) out.push('congressman or “Other”');
            if (!v('purpose_choice') || (v('purpose_choice') === 'Others' && !v('purpose_other'))) out.push('reason');
            if (f.elements['pass_class'].value === 'long_term' && !v('expected_return_date')) out.push('return date');
            if (!v('registered_by')) out.push('registered by');
            return out;
        }

        // Single owner of the Admit button's state: required fields AND duplicate warning.
        function refreshSubmitState() {
            const btn = document.getElementById('registerSubmitBtn');
            const blocked = dupBlocked();
            btn.disabled = blocked || !formReady();
            btn.style.opacity = btn.disabled ? '0.5' : '1';
            btn.classList.toggle('is-dup-blocked', blocked);

            const hint = document.getElementById('registerHint');
            if (hint) {
                const missing = btn.disabled && !blocked ? missingFields() : [];
                const err = hint.dataset.error || '';   // set by submitRegisterInPlace(); cleared when the user edits
                hint.classList.toggle('is-error', !!err);
                hint.textContent = err || (blocked
                    ? (transferModeActive ? 'Scan the visitor’s old card above to continue.' : 'Resolve the duplicate-pass warning above to continue.')
                    : (missing.length ? 'Still needed: ' + missing.join(', ') : ''));
            }
        }

        // Show the hint as soon as the modal opens (the form is empty at that point).
        new MutationObserver(() => {
            if (!document.getElementById('registerModal').classList.contains('hidden')) refreshSubmitState();
        }).observe(document.getElementById('registerModal'), { attributes: true, attributeFilter: ['class'] });

        function updateDupSubmitState() {
            refreshSubmitState();
        }

        function renderDuplicate(match, transferReady) {
            toggleDupSkeleton(false);
            dupState = match ? match.level : null;
            dupTransferReady = !!transferReady;
            document.getElementById('dupConfirmCheck').checked = false; // any new result must be re-confirmed
            document.getElementById('dupWarning').classList.toggle('hidden', !match);
            document.getElementById('dupTransferBlock').classList.toggle('hidden', !match);
            if (!match) {
                document.getElementById('transferQrTokenInput').value = '';
                transferHwInput.value = '';
                stopTransferCamera();
                document.getElementById('transferCameraToggle').checked = false;
                document.getElementById('dupTransferScanArea').classList.add('hidden');
                updateDupSubmitState();
                return;
            }

            const exact = match.level === 'exact';
            const p = match.pass;
            const box = document.getElementById('dupBox');
            box.classList.toggle('is-exact', exact);
            box.classList.toggle('is-possible', !exact);
            box.classList.toggle('is-transfer-ready', dupTransferReady);

            document.getElementById('dupTitle').textContent = exact ?
                'This person already has an active pass' :
                'Possible match: this person may already have an active pass';

            const dl = document.getElementById('dupDetails');
            dl.replaceChildren();
            [
                ['Pass No.', '#' + p.pass_number],
                ['Building(s)', p.buildings],
                ['Holder', p.holder],
                ['Purpose', p.purpose],
                ['Pass type', p.pass_class === 'long_term' ? 'Long-term' : 'Day'],
                ['Issued', p.issued_at],
                ['Currently inside', p.checked_in_at],
            ].forEach(([label, value]) => {
                if (!value) return;
                const dt = document.createElement('dt');
                dt.textContent = label;
                const dd = document.createElement('dd');
                dd.textContent = value; // textContent: holder name comes from OCR
                dl.append(dt, dd);
            });

            const more = match.more > 0 ? ` (+${match.more} more active pass${match.more > 1 ? 'es' : ''} matched)` : '';
            document.getElementById('dupNote').textContent = (dupTransferReady ?
                    'Ready to transfer — submitting will close the old card and issue a new one here.' :
                    (exact ?
                        'The same ID cannot be issued a second pass. Cancel this registration, or scan the old card below to transfer it.' :
                        'Confirm this is a different person, or scan the old card below to transfer the existing pass.')) +
                more;
            document.getElementById('dupCancelBtn').classList.toggle('hidden', !exact || dupTransferReady);
            document.getElementById('dupConfirmWrap').classList.toggle('hidden', exact || dupTransferReady);

            const hasTransferToken = !!document.getElementById('transferQrTokenInput').value;
            document.getElementById('transferQrSuccess').classList.toggle('hidden', !dupTransferReady);
            document.getElementById('transferClearBtn').classList.toggle('hidden', !hasTransferToken);
            if (dupTransferReady) {
                setTransferStatus('', 'neutral');
            } else if (hasTransferToken) {
                setTransferStatus('That card does not match the record shown above. Try again, or use Clear.', 'error');
            }
            if (match && !dupTransferReady) focusTransferHwInput();

            updateDupSubmitState();
        }

        document.addEventListener('input', (e) => {
            if (e.target.closest('#registerForm') && e.target.matches(
                    '[name="id_ref"],[name="first_name"],[name="last_name"],[name="contact_no"]'))
                scheduleDuplicateCheck();
        });
        document.addEventListener('change', (e) => {
            if (!e.target.closest('#registerForm')) return;
            if (e.target.matches('[name="id_type"]')) scheduleDuplicateCheck();
            refreshSubmitState(); // any change (building, reason, dates, confirm box) can flip readiness
        });
        document.getElementById('registerForm').addEventListener('submit', (e) => {
            e.preventDefault();
            if (dupBlocked()) {
                document.getElementById('dupWarning').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return;
            }
            submitRegisterInPlace(e.currentTarget);
        });
        // Editing anything dismisses a previous server error (runs before the document-level handlers).
        ['input', 'change'].forEach(ev => document.getElementById('registerForm').addEventListener(ev, () => {
            delete document.getElementById('registerHint').dataset.error;
        }));

        // Register / Transfer in the background: errors stay inside this window with every field and
        // photo intact; success updates rows + counts live and closes the window. If this script
        // never loads, the form still posts normally (server redirects as before).
        async function submitRegisterInPlace(form) {
            if (form.dataset.busy) return;
            form.dataset.busy = '1';
            delete document.getElementById('registerHint').dataset.error;
            lockRegisterSubmit();

            let errorMsg = '';
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });
                let data = null;
                try { data = await res.json(); } catch (_) {}

                if (res.ok && data && data.ok) {
                    (data.updates || []).forEach(applyPassPayload);
                    const num = data.pass_number;
                    closeRegisterModal();
                    showToast(data.message, 'success', null, {
                        label: 'View Pass',
                        onClick: () => document.querySelector(`[data-pass-number="${num}"][onclick^="openPassInfoModal"]`)?.click(),
                    });
                } else {
                    if (data && data.duplicate) {   // server caught a duplicate the live check hadn't shown
                        renderDuplicate(data.duplicate, false);
                        document.getElementById('dupWarning').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    errorMsg = (data && data.errors && Object.values(data.errors).flat()[0])
                        || (data && data.message && res.status !== 419 && res.status !== 500 ? data.message : '')
                        || (res.status === 419 ? 'Your session expired. Sign in again in a new tab, then reload this page.'
                            : res.status === 403 ? 'You are not allowed to do that.'
                            : 'Something went wrong on the server. Your entries are still here. Try again.');
                }
            } catch (e) {
                errorMsg = 'Network error. Your entries are still here. Try again.';
            } finally {
                delete form.dataset.busy;
                unlockRegisterSubmit();
                if (errorMsg) {
                    document.getElementById('registerHint').dataset.error = errorMsg;
                    refreshSubmitState();
                }
            }
        }

        // Visible "saving" state: blocks double-submits and tells the guard when it's slow.
        let registerSlowTimer = null;
        function lockRegisterSubmit() {
            const btn = document.getElementById('registerSubmitBtn');
            // Next tick: disabling inside the submit event can cancel the submission in some browsers.
            setTimeout(() => {
                btn.disabled = true;
                btn.style.opacity = '0.9';
                document.getElementById('registerSubmitSpinner').classList.remove('hidden');
                document.getElementById('registerSubmitLabel').textContent = transferModeActive ? 'Transferring…' : 'Issuing pass…';
                document.getElementById('registerHint').textContent = 'Saving — please keep this window open.';
                document.getElementById('registerModal').classList.add('is-submitting');
            }, 0);
            registerSlowTimer = setTimeout(() => {
                document.getElementById('registerHint').textContent =
                    'Still working… this is taking longer than usual. Please wait and don’t click again.';
            }, 8000);
        }

        function unlockRegisterSubmit() {
            clearTimeout(registerSlowTimer);
            document.getElementById('registerSubmitSpinner').classList.add('hidden');
            document.getElementById('registerModal').classList.remove('is-submitting');
            syncTransferUi();
            refreshSubmitState();
        }
        // Coming back via the browser's Back button restores the page frozen mid-submit.
        window.addEventListener('pageshow', (e) => { if (e.persisted) unlockRegisterSubmit(); });
        // ---- End duplicate check ----
        // ---- Transfer: scan the visitor's OLD physical card (Workflow 2) ----
        // Primary path: a Honeywell 2D scanner is a keyboard wedge — it types the
        // qr_token into whatever input has focus. Camera/jsQR is an opt-in fallback.
        let transferStream = null;
        let transferScanTimer = null;
        let transferHwDebounce = null;
        let dupTransferReady = false;

        const transferHwInput = document.getElementById('transferHwInput');
        transferHwInput.addEventListener('input', () => {
            clearTimeout(transferHwDebounce);
            transferHwDebounce = setTimeout(() => {
                const token = transferHwInput.value.trim();
                if (token) verifyTransferToken(token);
            }, 250);
        });
        transferHwInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(transferHwDebounce);
                const token = transferHwInput.value.trim();
                if (token) verifyTransferToken(token);
            }
        });

        function focusTransferHwInput() {
            if (!document.getElementById('transferCameraToggle').checked) {
                setTimeout(() => transferHwInput.focus(), 50);
            }
        }

        function toggleTransferCameraFallback(enabled) {
            document.getElementById('dupTransferScanArea').classList.toggle('hidden', !enabled);
            if (enabled) {
                // Only one camera stream tends to work at a time on mobile devices.
                stopCameraStream();
                stopIdCameraStream();
                navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'environment'
                        }
                    })
                    .then(stream => {
                        transferStream = stream;
                        const video = document.getElementById('transferQrVideo');
                        video.srcObject = stream;
                        video.classList.remove('hidden');
                        document.getElementById('transferQrPlaceholder').classList.remove('hidden');
                        setTransferStatus('Point the camera at the QR on the old card.', 'busy');
                        transferScanTimer = setInterval(scanTransferFrame, 400);
                    })
                    .catch(err => {
                        document.getElementById('transferCameraToggle').checked = false;
                        document.getElementById('dupTransferScanArea').classList.add('hidden');
                        showToast(err.message, 'error', 'Camera Unavailable');
                    });
            } else {
                stopTransferCamera();
                focusTransferHwInput();
            }
        }

        function stopTransferCamera() {
            clearInterval(transferScanTimer);
            transferScanTimer = null;
            if (transferStream) {
                transferStream.getTracks().forEach(t => t.stop());
                transferStream = null;
            }
            document.getElementById('transferQrVideo').classList.add('hidden');
        }

        // QR decode helper shared by both camera-scan paths. A 1080p frame is far more pixels than
        // jsQR needs, so cap the long edge and reuse one canvas instead of allocating one every tick.
        const qrScanCanvas = document.createElement('canvas');
        const qrScanCtx = qrScanCanvas.getContext('2d', { willReadFrequently: true });
        function decodeQrFrame(video) {
            const scale = Math.min(1, 800 / Math.max(video.videoWidth, video.videoHeight));
            const w = Math.round(video.videoWidth * scale);
            const h = Math.round(video.videoHeight * scale);
            if (qrScanCanvas.width !== w) qrScanCanvas.width = w;
            if (qrScanCanvas.height !== h) qrScanCanvas.height = h;
            qrScanCtx.drawImage(video, 0, 0, w, h);
            const img = qrScanCtx.getImageData(0, 0, w, h);
            const qr = window.jsQR ? jsQR(img.data, w, h, { inversionAttempts: 'dontInvert' }) : null;
            return qr && qr.data ? qr.data.trim() : null;
        }

        let transferScanBusy = false;
        function scanTransferFrame() {
            const video = document.getElementById('transferQrVideo');
            if (transferScanBusy || !video.videoWidth) return;
            transferScanBusy = true;
            try {
                const token = decodeQrFrame(video);
                if (token) verifyTransferToken(token);
            } finally {
                transferScanBusy = false;
            }
        }

        async function verifyTransferToken(token) {
            if (!token) return;
            if (document.getElementById('transferCameraToggle').checked) stopTransferCamera();
            document.getElementById('transferQrTokenInput').value = token;
            transferHwInput.value = '';
            setTransferStatus('Checking card…', 'busy');
            await runDuplicateCheck(); // re-runs the whole check, now including the scanned token
        }

        function clearTransferScan() {
            stopTransferCamera();
            document.getElementById('transferCameraToggle').checked = false;
            document.getElementById('dupTransferScanArea').classList.add('hidden');
            document.getElementById('transferQrTokenInput').value = '';
            transferHwInput.value = '';
            dupTransferReady = false;
            document.getElementById('transferQrSuccess').classList.add('hidden');
            document.getElementById('transferClearBtn').classList.add('hidden');
            setTransferStatus('', 'neutral');
            refreshSubmitState();
            focusTransferHwInput();
        }

        function setTransferStatus(message, tone) {
            const el = document.getElementById('transferScanStatus');
            const colors = {
                neutral: '#64748b',
                busy: '#2563eb',
                success: '#1c9a5b',
                error: 'var(--brand-red)'
            };
            el.textContent = message;
            el.style.color = colors[tone] || colors.neutral;
            el.classList.toggle('is-busy', tone === 'busy');
        }
        // ---- End transfer scan ----

        // ---- Transfer Mode (Step 2 toggle): scan-first, autofills + locks visitor identity fields ----
        const TRANSFER_LOOKUP_URL = @json(route('passes.lookup-transfer-source'));
        let transferModeStream = null;
        let transferModeScanTimer = null;
        let transferModeHwDebounce = null;
        let transferModeActive = false;
                let transferSource = null;          // the verified old pass, or null
        let transferModeScanBusy = false;
        const transferModeLockedFields = ['first_name', 'middle_name', 'last_name', 'gender', 'contact_no', 'visitor_email',
            'id_type', 'id_ref'
        ];

        function toggleTransferMode(enabled) {
            transferModeActive = enabled;
            document.getElementById('transferModeBlock').style.display = enabled ? '' : 'none';
            document.getElementById('manualIdentityFields').classList.toggle('is-hidden-transfer', enabled);

            if (enabled) {
                // Transfer Mode replaces the typed duplicate check for this registration.
                clearTimeout(dupTimer);
                dupSeq++;
                renderDuplicate(null);
                focusTransferModeHwInput();
            } else {
                clearTransferModeScan();
            }
            syncTransferUi();
            refreshSubmitState();
        }

        function cancelTransfer() {
            document.getElementById('transferModeToggle').checked = false;
            toggleTransferMode(false);
        }

        const transferModeHwInput = document.getElementById('transferModeHwInput');
        transferModeHwInput.addEventListener('input', () => {
            clearTimeout(transferModeHwDebounce);
            transferModeHwDebounce = setTimeout(() => {
                const token = transferModeHwInput.value.trim();
                if (token) lookupTransferSource(token);
            }, 250);
        });
        transferModeHwInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(transferModeHwDebounce);
                const token = transferModeHwInput.value.trim();
                if (token) lookupTransferSource(token);
            }
        });

        function focusTransferModeHwInput() {
            if (!document.getElementById('transferModeCameraToggle').checked) {
                setTimeout(() => transferModeHwInput.focus(), 50);
            }
        }

        function toggleTransferModeCameraFallback(enabled) {
            document.getElementById('transferModeScanArea').classList.toggle('hidden', !enabled);
            if (enabled) {
                stopCameraStream();
                stopIdCameraStream();
                navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'environment'
                        }
                    })
                    .then(stream => {
                        transferModeStream = stream;
                        const video = document.getElementById('transferModeVideo');
                        video.srcObject = stream;
                        video.classList.remove('hidden');
                        document.getElementById('transferModePlaceholder').classList.remove('hidden');
                        setTransferModeStatus('Point the camera at the QR on the old card.', 'busy');
                        transferModeScanTimer = setInterval(scanTransferModeFrame, 400);
                    })
                    .catch(err => {
                        document.getElementById('transferModeCameraToggle').checked = false;
                        document.getElementById('transferModeScanArea').classList.add('hidden');
                        showToast(err.message, 'error', 'Camera Unavailable');
                    });
            } else {
                stopTransferModeCamera();
                focusTransferModeHwInput();
            }
        }

        function stopTransferModeCamera() {
            clearInterval(transferModeScanTimer);
            transferModeScanTimer = null;
            if (transferModeStream) {
                transferModeStream.getTracks().forEach(t => t.stop());
                transferModeStream = null;
            }
            document.getElementById('transferModeVideo').classList.add('hidden');
        }

        function scanTransferModeFrame() {
            const video = document.getElementById('transferModeVideo');
            if (transferModeScanBusy || !video.videoWidth) return;
            transferModeScanBusy = true;
            try {
                const token = decodeQrFrame(video);
                if (token) lookupTransferSource(token);
            } finally {
                transferModeScanBusy = false;
            }
        }
        async function lookupTransferSource(token) {
            if (!token) return;
            if (document.getElementById('transferModeCameraToggle').checked) stopTransferModeCamera();
            transferModeHwInput.value = '';
            setTransferModeStatus('Checking card…', 'busy');
            try {
                const res = await fetch(`${TRANSFER_LOOKUP_URL}?token=${encodeURIComponent(token)}`, {
                    headers: {
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (!res.ok || !data.pass) {
                    setTransferModeStatus(data.message || "That card doesn't match an active pass.", 'error');
                    return;
                }
                applyTransferModeMatch(data.pass, token);
            } catch (e) {
                setTransferModeStatus('Could not verify that card. Try again.', 'error');
            }
        }

        function applyTransferModeMatch(pass, token) {
            document.getElementById('transferQrTokenInput').value = token;
            transferSource = pass;

            const form = document.getElementById('registerForm');
            const setField = (name, value) => {
                const el = form.elements[name];
                if (!el) return;
                el.value = value || '';
                el.readOnly = true;
                el.classList.add('is-locked');
                if (el.tagName === 'SELECT') el.style.pointerEvents = 'none';
            };
            transferModeLockedFields.forEach(name => setField(name, pass[name]));

            document.getElementById('transferModeSuccess').classList.remove('hidden');
            document.getElementById('transferModeClearBtn').classList.remove('hidden');
            fillTransferSummary(pass);
            document.getElementById('transferModeSummary').classList.remove('hidden');
            document.getElementById('transferDestinationNote').classList.remove('hidden');
            document.getElementById('transferDestinationNoteText').textContent =
                `Pass #${pass.pass_number} · ${pass.holder}`;
            setTransferModeStatus('', 'neutral');
            syncTransferUi();
            refreshSubmitState();
        }

        function clearTransferModeScan() {
            stopTransferModeCamera();
            transferSource = null;
            document.getElementById('transferModeCameraToggle').checked = false;
            document.getElementById('transferModeScanArea').classList.add('hidden');
            document.getElementById('transferQrTokenInput').value = '';
            transferModeHwInput.value = '';
            document.getElementById('transferModeSuccess').classList.add('hidden');
            document.getElementById('transferModeClearBtn').classList.add('hidden');
            document.getElementById('transferModeSummary').classList.add('hidden');
            document.getElementById('transferDestinationNote').classList.add('hidden');
            setTransferModeStatus('', 'neutral');

            const form = document.getElementById('registerForm');
            transferModeLockedFields.forEach(name => {
                const el = form.elements[name];
                if (!el) return;
                el.readOnly = false;
                el.classList.remove('is-locked');
                el.style.pointerEvents = '';
            });

            syncTransferUi();
            refreshSubmitState();
            focusTransferModeHwInput();
        }

        function setTransferModeStatus(message, tone) {
            const el = document.getElementById('transferModeStatus');
            const colors = {
                neutral: '#64748b',
                busy: '#2563eb',
                success: '#1c9a5b',
                error: 'var(--brand-red)'
            };
            el.textContent = message;
            el.style.color = colors[tone] || colors.neutral;
            el.classList.toggle('is-busy', tone === 'busy');
        }

        // One place that keeps every transfer cue consistent: banner, title, button label, "Current" tags.
        function syncTransferUi() {
            const on = transferModeActive;
            const verified = on && !!transferSource;

            const banner = document.getElementById('transferBanner');
            banner.classList.toggle('hidden', !on);
            banner.classList.toggle('is-verified', verified);
            document.getElementById('transferModeBlock').classList.toggle('is-verified', verified);

            document.getElementById('registerEyebrow').textContent = on ? 'Transfer' : 'Registration';
            document.getElementById('registration-title').textContent = on ? 'Transfer Visitor Pass' : 'Register Visitor and Issue Pass';
            document.getElementById('registerSubmitLabel').textContent = on ? 'Transfer and re-issue pass' : 'Admit and Auto-assign';

            document.getElementById('transferBannerTitle').textContent = verified
                ? `Transferring Pass #${transferSource.pass_number} · ${transferSource.holder || 'Unnamed Visitor'}`
                : 'Transfer in progress';
            document.getElementById('transferBannerDetail').textContent = verified
                ? `From ${transferSource.building}. Choose where they’re headed — the old card returns to stock when you confirm.`
                : 'Scan the visitor’s old card to begin.';

            // Tag the building(s) the old card is currently authorised for.
            const current = verified ? (transferSource.building_ids || []).map(Number) : [];
            document.querySelectorAll('#registerModal .reg-building-option').forEach(opt => {
                const id = Number(opt.querySelector('input')?.value);
                const isCurrent = current.includes(id);
                opt.classList.toggle('is-current', isCurrent);
                const name = opt.querySelector('.reg-building-name');
                let tag = name.querySelector('.reg-current-tag');
                if (isCurrent && !tag) {
                    tag = document.createElement('em');
                    tag.className = 'reg-current-tag';
                    tag.textContent = 'Current';
                    name.appendChild(tag);
                } else if (!isCurrent && tag) {
                    tag.remove();
                }
            });
        }

        function fillTransferSummary(pass) {
            document.getElementById('tmPassNo').textContent = pass.pass_number;
            document.getElementById('tmHolder').textContent = pass.holder || 'Unnamed Visitor';
            document.getElementById('tmFrom').textContent = `Currently at ${pass.building || '—'}`;

            const rows = [
                ['Gender', pass.gender],
                ['Contact No.', pass.contact_no],
                ['Email', pass.visitor_email],
                ['ID', [pass.id_type, pass.id_ref].filter(Boolean).join(' · ')],
            ].filter(([, value]) => value);

            const dl = document.getElementById('tmDetails');
            dl.replaceChildren();
            rows.forEach(([label, value]) => {
                const wrap = document.createElement('div');
                const dt = document.createElement('dt');
                dt.textContent = label;
                const dd = document.createElement('dd');
                dd.textContent = value;
                wrap.append(dt, dd);
                dl.append(wrap);
            });
            dl.classList.toggle('hidden', rows.length === 0);
        }

        // ---- End Transfer Mode ----

        function closeRegisterModal() {
            document.getElementById('registerModal').classList.add('hidden');
            document.getElementById('registerForm').reset();
            clearTimeout(dupTimer);
            dupSeq++;
            renderDuplicate(null);
            document.getElementById('transferModeToggle').checked = false;
            toggleTransferMode(false);
            resetPhotoCapture();
            resetIdPhotoCapture();
            setPassClass('day');
            selectedCong.clear();
            updateBuildingSelection();
        }
    </script>
@endsection
