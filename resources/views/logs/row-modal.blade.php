{{-- ============================= UNIVERSAL ROW DETAILS MODAL ============================= --}}

<div id="rowDetailsModalOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4"
     onclick="if(event.target === this) closeRowModal()">

    <div class="gov-info-modal-panel is-record">
        <div class="gov-info-modal-header">
            <div>
                <span class="gov-eyebrow" id="rowModalEyebrow">Record Details</span>
                <h3 id="rowModalTitle" class="gov-pass-modal-title"></h3>
            </div>
            <button type="button" onclick="closeRowModal()"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full p-2.5 transition-colors leading-none flex-shrink-0">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="gov-info-modal-body">
            <div id="rowModalPhotos" class="gov-info-photos hidden">
                <div>
                    <span class="gov-meta-label">Visitor Photo</span>
                    <img id="rowModalPhoto" class="gov-info-photo hidden" alt="Visitor photo">
                </div>
                <div id="rowModalIdPhotoWrap">
                    <span class="gov-meta-label">ID Photo</span>
                    <img id="rowModalIdPhoto" class="gov-info-photo hidden" alt="ID photo">
                </div>
            </div>

            <div class="gov-info-modal-grid" id="rowModalFieldsGrid"></div>
        </div>
    </div>
</div>

<script>
    function recBadgeClass(v) {
        const k = String(v).toLowerCase();
        if (['authorized', 'currently assigned'].includes(k)) return 'is-ok';
        if (['unassigned', 'transferred', 'renewed'].includes(k)) return 'is-info';
        if (['expired', 'blocked', 'auto-expired'].includes(k)) return 'is-warn';
        if (['invalid', 'returned'].includes(k)) return 'is-neutral';
        return 'is-bad';
    }

    function openRowModal(row) {
        const type = row.dataset.type;
        const overlay = document.getElementById('rowDetailsModalOverlay');
        const title = document.getElementById('rowModalTitle');
        const eyebrow = document.getElementById('rowModalEyebrow');
        const photosBlock = document.getElementById('rowModalPhotos');
        const photoImg = document.getElementById('rowModalPhoto');
        const idPhotoImg = document.getElementById('rowModalIdPhoto');
        const idPhotoWrap = document.getElementById('rowModalIdPhotoWrap');
        const fieldsGrid = document.getElementById('rowModalFieldsGrid');

        photosBlock.classList.add('hidden');
        photosBlock.classList.remove('is-single');
        photoImg.classList.add('hidden');
        idPhotoImg.classList.add('hidden');
        idPhotoWrap.classList.remove('hidden');

        let sections = [];

        if (type === 'scan') {
            eyebrow.textContent = 'Scan Details';
            title.textContent = row.dataset.visitor || 'Unassigned Card';

            sections = [
                ['Outcome', [
                    ['Result', row.dataset.result, 'badge'],
                    ['Timestamp', row.dataset.timestamp],
                    ['Reason', row.dataset.reason, 'wide'],
                ]],
                ['Visitor & Pass', [
                    ['Visitor', row.dataset.visitor],
                    ['Contact Person', row.dataset.contactPerson],
                    ['Pass #', row.dataset.passNumber],
                    ['Scanned At', row.dataset.scannedAt],
                ]],
            ];

            const photo = row.dataset.photo;
            if (photo) {
                photosBlock.classList.remove('hidden');
                photosBlock.classList.add('is-single');
                idPhotoWrap.classList.add('hidden');
                photoImg.src = photo;
                photoImg.classList.remove('hidden');
            }
        } else if (type === 'registration') {
            eyebrow.textContent = 'Registration Details';
            title.textContent = row.dataset.visitor || '—';

            sections = [
                ['Visitor', [
                    ['Visitor', row.dataset.visitor],
                    ['ID type / ref', (row.dataset.idRef || '').replace(/\s*·\s*$/, '')],
                    ['Contact Person', row.dataset.contactPerson],
                ]],
                ['Visit', [
                    ['Buildings to Visit', row.dataset.buildings, 'wide'],
                    ['Congressman / Office', row.dataset.office, 'wide'],
                ]],
                ['Pass', [
                    ['Status', row.dataset.status, 'badge'],
                    ['Pass class', row.dataset.passClass, 'tag'],
                    ['Expected return', row.dataset.expectedReturn],
                    ['Registered at', row.dataset.timestamp],
                    ['Registered by', row.dataset.registeredBy],
                ]],
            ];

            const photo = row.dataset.photo;
            const idPhoto = row.dataset.idPhoto;
            if (photo || idPhoto) {
                photosBlock.classList.remove('hidden');
                if (photo) {
                    photoImg.src = photo;
                    photoImg.classList.remove('hidden');
                }
                if (idPhoto) {
                    idPhotoImg.src = idPhoto;
                    idPhotoImg.classList.remove('hidden');
                } else {
                    idPhotoWrap.classList.add('hidden');
                }
            }
        }

        // textContent, never innerHTML: names and contact persons are typed by guards.
        fieldsGrid.replaceChildren(...sections.map(([heading, fields]) => {
            const sec = document.createElement('div'); sec.className = 'gov-rec-section';
            const h = document.createElement('div'); h.className = 'gov-rec-title'; h.textContent = heading;
            const grid = document.createElement('div'); grid.className = 'gov-rec-grid';
            fields.filter(([, value]) => value).forEach(([label, value, flag]) => {
                const wrap = document.createElement('div');
                if (flag === 'wide') wrap.className = 'is-wide';
                const l = document.createElement('span'); l.className = 'gov-meta-label'; l.textContent = label;
                let v;
                if (flag === 'badge' || flag === 'tag') {
                    v = document.createElement('span');
                    v.className = 'gov-rec-badge ' + (flag === 'tag' ? 'is-info' : recBadgeClass(value));
                } else {
                    v = document.createElement('div');
                    v.className = 'gov-meta-value';
                }
                v.textContent = value;
                wrap.append(l, v);
                grid.appendChild(wrap);
            });
            sec.append(h, grid);
            return sec;
        }));

        overlay.classList.remove('hidden');
    }

    function closeRowModal() {
        document.getElementById('rowDetailsModalOverlay').classList.add('hidden');
    }
</script>
{{-- ========================= END UNIVERSAL ROW DETAILS MODAL ========================= --}}