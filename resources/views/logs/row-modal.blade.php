{{-- ============================= UNIVERSAL ROW DETAILS MODAL ============================= --}}



<div id="rowDetailsModalOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4"
     onclick="if(event.target === this) closeRowModal()">



    <div class="w-full max-w-[900.5px] max-h-[1500.5px] rounded-2xl bg-white shadow-2xl  overflow-y-auto">

        <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-6 py-4">
            <h3 id="rowModalTitle" class="flex items-center gap-2 text-base font-bold text-slate-900">
                <i class="fa-solid fa-circle-info text-blue-500"></i> Record Details
            </h3>
            <button type="button" onclick="closeRowModal()" class="text-slate-400 hover:text-slate-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        {{-- Photos (registrations only) --}}
        <div id="rowModalPhotos" class="hidden items-center justify-center gap-6 px-6 pt-5">
            <div class="text-center">
                <img id="rowModalPhoto" src="" alt="Visitor photo" class="hidden h-20 w-20 rounded-lg object-cover ring-1 ring-slate-200 mx-auto">
                <span class="mt-1 block text-[11px] font-medium text-slate-400">Visitor</span>
            </div>
            <div class="text-center">
                <img id="rowModalIdPhoto" src="" alt="ID photo" class="hidden h-20 w-20 rounded-lg object-cover ring-1 ring-slate-200 mx-auto">
                <span class="mt-1 block text-[11px] font-medium text-slate-400">ID</span>
            </div>
        </div>

        <dl class="space-y-3 px-6 py-5 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="font-medium text-slate-500">Timestamp</dt>
                <dd id="rowModalTimestamp" class="font-mono text-slate-800"></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="font-medium text-slate-500">Visitor</dt>
                <dd id="rowModalVisitor" class="font-semibold text-slate-900"></dd>
            </div>

            {{-- Scan-only fields --}}
            <div id="rowModalScanFields" class="hidden space-y-3">
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">Pass #</dt>
                    <dd id="rowModalPassNumber" class="font-mono font-semibold text-slate-800"></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">Scanned at</dt>
                    <dd id="rowModalScannedAt" class="text-slate-700"></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">Result</dt>
                    <dd id="rowModalResult" class="text-slate-700"></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">Reason</dt>
                    <dd id="rowModalReason" class="text-right text-slate-700"></dd>
                </div>
            </div>

            {{-- Registration-only fields --}}
            <div id="rowModalRegFields" class="hidden space-y-3">
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">ID type / ref</dt>
                    <dd id="rowModalIdRef" class="text-slate-700"></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">Pass class</dt>
                    <dd id="rowModalPassClass" class="text-slate-700"></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">Expected return</dt>
                    <dd id="rowModalExpectedReturn" class="text-slate-700"></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">Registered by</dt>
                    <dd id="rowModalRegisteredBy" class="text-slate-700"></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">Status</dt>
                    <dd id="rowModalStatus" class="text-slate-700"></dd>
                </div>
            </div>
        </dl>

        <div class="flex justify-end border-t border-slate-200 px-6 py-4">
            <button type="button" onclick="closeRowModal()"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    function openRowModal(row) {
        const type = row.dataset.type;
        const overlay = document.getElementById('rowDetailsModalOverlay');
        const title = document.getElementById('rowModalTitle');

        document.getElementById('rowModalScanFields').classList.add('hidden');
        document.getElementById('rowModalRegFields').classList.add('hidden');
        document.getElementById('rowModalPhotos').classList.add('hidden');
        document.getElementById('rowModalPhotos').classList.remove('flex');

        document.getElementById('rowModalTimestamp').textContent = row.dataset.timestamp || '—';
        document.getElementById('rowModalVisitor').textContent = row.dataset.visitor || '—';

        if (type === 'scan') {
            title.innerHTML = '<i class="fa-solid fa-circle-info text-blue-500"></i> Scan Details';
            document.getElementById('rowModalScanFields').classList.remove('hidden');
            document.getElementById('rowModalPassNumber').textContent = row.dataset.passNumber || '—';
            document.getElementById('rowModalScannedAt').textContent = row.dataset.scannedAt || '—';
            document.getElementById('rowModalResult').textContent = row.dataset.result || '—';
            document.getElementById('rowModalReason').textContent = row.dataset.reason || '—';
        } else if (type === 'registration') {
            title.innerHTML = '<i class="fa-solid fa-circle-info text-blue-500"></i> Registration Details';
            document.getElementById('rowModalRegFields').classList.remove('hidden');
            document.getElementById('rowModalIdRef').textContent = row.dataset.idRef || '—';
            document.getElementById('rowModalPassClass').textContent = row.dataset.passClass || '—';
            document.getElementById('rowModalExpectedReturn').textContent = row.dataset.expectedReturn || '—';
            document.getElementById('rowModalRegisteredBy').textContent = row.dataset.registeredBy || '—';
            document.getElementById('rowModalStatus').textContent = row.dataset.status || '—';

            const photo = row.dataset.photo;
            const idPhoto = row.dataset.idPhoto;
            if (photo || idPhoto) {
                const photosBlock = document.getElementById('rowModalPhotos');
                photosBlock.classList.remove('hidden');
                photosBlock.classList.add('flex');

                const photoImg = document.getElementById('rowModalPhoto');
                const idPhotoImg = document.getElementById('rowModalIdPhoto');

                if (photo) {
                    photoImg.src = photo;
                    photoImg.classList.remove('hidden');
                } else {
                    photoImg.classList.add('hidden');
                }

                if (idPhoto) {
                    idPhotoImg.src = idPhoto;
                    idPhotoImg.classList.remove('hidden');
                } else {
                    idPhotoImg.classList.add('hidden');
                }
            }
        }

        overlay.classList.remove('hidden');
    }

    function closeRowModal() {
        document.getElementById('rowDetailsModalOverlay').classList.add('hidden');
    }
</script>
{{-- ========================= END UNIVERSAL ROW DETAILS MODAL ========================= --}}
