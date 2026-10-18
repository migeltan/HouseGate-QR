@php
    // $q = QR sheet settings, $grid = computed layout (see PassInventoryController::qrLayout)
    $pages   = (int) ceil($passes->count() / $grid['per_page']);
    $qrColor = $building->qr_color_hex ?: ($building->color_hex ?: '#000000');
    $mono    = $q['color'] === 'black';
    $fill    = $q['mode'] === 'fill';
    $cw      = $grid['cw'];
    $ch      = $grid['ch'];
    $size    = $grid['size'];
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>QR labels — {{ $building->name }}</title>
<style>
    @page { size: {{ $grid['paper']['page_size'] }}; margin: {{ $grid['margin'] }}mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, sans-serif; background: #eef2f7; }
    .toolbar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: 14px; align-items: center;
               padding: 10px 16px; background: #fff; border-bottom: 1px solid #d9dce1; font-size: 14px; }
    .toolbar button { padding: 8px 16px; border: 0; border-radius: 8px; background: #235aa6; color: #fff; font-weight: 600; cursor: pointer; }
    .toolbar label { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
    .page { position: relative; left: {{ $q['offset_x'] }}mm; top: {{ $q['offset_y'] }}mm;
            display: grid; grid-template-columns: repeat({{ $grid['cols'] }}, {{ $cw }}mm); gap: {{ $q['gap'] }}mm;
            justify-content: center; align-content: start; padding: {{ $grid['margin'] }}mm 0; break-after: page; }
    .page:last-child { break-after: auto; }
    .cell { width: {{ $cw }}mm; height: {{ $ch }}mm; padding-top: {{ $fill ? 0 : $q['pad'] }}mm; display: flex; flex-direction: column;
            align-items: center; justify-content: {{ $fill ? 'center' : 'flex-start' }}; background: #fff; outline: 0.15mm solid #c5ccd6; }
    .no-guides .cell { outline: none; }
    .qr { width: {{ $size }}mm; height: {{ $size }}mm; flex: none; }
    .qr canvas { display: none; }
    .qr img { display: block; width: 100% !important; height: 100% !important; }
    .label { margin-top: 1.2mm; font: 700 {{ $q['label_size'] }}pt/1 ui-monospace, Consolas, monospace; letter-spacing: .02em; color: var(--qr, #000); white-space: nowrap; }
    body.mono .label { color: #000; }
    @keyframes sk { from { background-position: 200% 0; } to { background-position: -200% 0; } }
    .sk { background: linear-gradient(90deg, #e6ecf4 25%, #f5f8fc 50%, #e6ecf4 75%); background-size: 200% 100%; animation: sk 1.4s ease-in-out infinite; }
    .toolbar button:disabled { opacity: .6; cursor: wait; }
    @media print {
        .toolbar { display: none; }
        body { background: #fff; }
        .page { padding: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body class="{{ $mono ? 'mono' : '' }} {{ $q['guides'] ? '' : 'no-guides' }}" style="--qr: {{ $qrColor }}">
<div class="toolbar">
<button id="printBtn" onclick="window.print()" disabled>Preparing…</button>
    <label><input type="checkbox" id="guides" {{ $q['guides'] ? 'checked' : '' }}> Cut guides</label>
    <label><input type="checkbox" id="colorQr" {{ $mono ? '' : 'checked' }}> Building colour</label>
    <span><strong>{{ $building->name }}</strong> · {{ $passes->count() }} labels · {{ $pages }} page(s) · {{ $grid['paper']['label'] }}{{ $q['landscape'] ? ' landscape' : '' }} · {{ $grid['cols'] }} × {{ $grid['rows'] }} per page · {{ $size }} mm QR · set scale to 100% and turn off headers/footers</span>
</div>

@forelse ($passes->chunk($grid['per_page']) as $chunk)
    <section class="page">
        @foreach ($chunk as $p)
            <div class="cell">
<div class="qr sk" data-token="{{ $p->qr_token }}"></div>
                @if ($q['label'])
                    <div class="label">{{ $building->code }} · {{ $p->pass_number }}</div>
                @endif
            </div>
        @endforeach
    </section>
@empty
    <p style="padding:2rem;text-align:center;">No passes match that selection. Generate them first.</p>
@endforelse

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const QR_COLOR = @json($qrColor);
const printBtn = document.getElementById('printBtn');
let pending = [];
function drawBatch() {
    if (typeof QRCode === 'undefined') { printBtn.textContent = 'QR library failed to load (needs internet)'; return; }
    pending.splice(0, 12).forEach(el => {
        new QRCode(el, {
            text: el.dataset.token, width: 256, height: 256,
            colorDark: document.getElementById('colorQr').checked ? QR_COLOR : '#000000', colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
        el.classList.remove('sk');
    });
    if (pending.length) { requestAnimationFrame(drawBatch); return; }
    printBtn.disabled = false; printBtn.textContent = 'Print';
}
function drawAll() {
    printBtn.disabled = true; printBtn.textContent = 'Preparing…';
    pending = [...document.querySelectorAll('.qr')];
    pending.forEach(el => { el.innerHTML = ''; el.classList.add('sk'); });
    drawBatch();
}
drawAll();
document.getElementById('colorQr').addEventListener('change', e => {
    document.body.classList.toggle('mono', !e.target.checked);
    drawAll();
});
document.getElementById('guides').addEventListener('change', e =>
    document.body.classList.toggle('no-guides', !e.target.checked));
</script>
</body>
</html>