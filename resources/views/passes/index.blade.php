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
                    <p class="reg-eyebrow">Registration</p>
                    <h1 id="registration-title">Register Visitor and Issue Pass</h1>
                </div>
                <button type="button" class="reg-close-button" aria-label="Close"
                    onclick="closeRegisterModal()">&times;</button>
            </header>

            <form method="POST" action="{{ route('passes.register') }}" id="registerForm">
                @csrf

                <main class="reg-modal-body">
                    @include('passes.step1-camera')
                    @include('passes.step2-information')
                    @include('passes.step3-destination')
                </main>

                <footer class="reg-modal-footer">
                    <button type="button" class="reg-button" onclick="closeRegisterModal()">Cancel</button>
                    <button type="submit" id="registerSubmitBtn" class="reg-button reg-button-green">Admit and
                        Auto-assign</button>
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
                x.onclick = () => {
                    selectedCong.delete(c.id);
                    renderCongChips();
                    renderCongList();
                };
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
            refreshSubmitState();
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
                .sort((a, b) => (multi ? String(BUILDING_NAMES[a.b]).localeCompare(String(BUILDING_NAMES[b.b])) : 0) ||
                    a.name.localeCompare(b.name));

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
            const open = () => {
                renderCongList();
                list.classList.add('is-open');
            };
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
                        facingMode: 'environment'
                    }
                });
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

        // ---- ID OCR auto-fill (National ID / PhilSys front, client-side via Tesseract.js) ----
        async function runIdOcr(dataUrl) {
            setIdCaptureStatus('Reading ID… this can take a few seconds.', 'busy');
            try {
                const {
                    data: {
                        text
                    }
                } = await Tesseract.recognize(dataUrl, 'eng');
                const filled = applyIdOcrText(text);
                setIdCaptureStatus(filled.length ? `Auto-filled: ${filled.join(', ')}. Please review.` :
                    'Could not read the ID clearly — please fill in manually.', filled.length ? 'success' : 'warn');
            } catch (err) {
                console.error('ID OCR failed:', err);
                setIdCaptureStatus('ID reading failed — please fill in manually.', 'error');
            }
        }

        function applyIdOcrText(rawText) {
            const lines = rawText.split('\n').map(l => l.trim()).filter(Boolean);
            const allLabelPatterns = [/Apelyido/i, /Last\s*Name/i, /Mga\s*Pangalan/i, /Given\s*Name/i,
                /Gitnang\s*Apelyido/i, /Middle\s*Name/i, /Petsa|Date\s*of\s*Birth/i, /Tirahan|Address/i,
                /Republika|Philippines|Identification/i
            ];
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

        // Single owner of the Admit button's state: required fields AND duplicate warning.
        function refreshSubmitState() {
            const btn = document.getElementById('registerSubmitBtn');
            const blocked = dupBlocked();
            btn.disabled = blocked || !formReady();
            btn.style.opacity = btn.disabled ? '0.5' : '1';
            btn.classList.toggle('is-dup-blocked', blocked);
        }

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
        document.getElementById('registerForm').addEventListener('input', refreshSubmitState);
        document.getElementById('registerForm').addEventListener('submit', (e) => {
            if (dupBlocked()) {
                e.preventDefault();
                document.getElementById('dupWarning').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
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

        function scanTransferFrame() {
            const video = document.getElementById('transferQrVideo');
            if (!video.videoWidth) return;
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const qr = window.jsQR ? jsQR(imageData.data, imageData.width, imageData.height) : null;
            if (qr && qr.data) verifyTransferToken(qr.data.trim());
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
            refreshSubmitState();
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
            if (!video.videoWidth) return;
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const qr = window.jsQR ? jsQR(imageData.data, imageData.width, imageData.height) : null;
            if (qr && qr.data) lookupTransferSource(qr.data.trim());
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
            document.getElementById('transferModeSummary').classList.remove('hidden');
            document.getElementById('transferModeSummaryText').textContent =
                `Pass #${pass.pass_number} · ${pass.holder} · currently at ${pass.building}`;
            document.getElementById('transferDestinationNote').classList.remove('hidden');
            document.getElementById('transferDestinationNoteText').textContent =
                `Pass #${pass.pass_number} · ${pass.holder}`;
            setTransferModeStatus('', 'neutral');
            refreshSubmitState();
        }

        function clearTransferModeScan() {
            stopTransferModeCamera();
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
