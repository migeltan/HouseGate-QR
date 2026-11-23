@php
    // $t = card template settings (mm / % of card). Defaults in PassInventoryController::CARD_DEFAULTS
    // reproduce the original fixed 60 x 100 mm layout.
    $cw     = (float) $t['card_w'];
    $ch     = (float) $t['card_h'];
    $qrMm   = round($cw * $t['qr_size'] / 100, 2);
    $numMm  = round($cw * $t['num_size'] / 100, 2);
    $qrRad  = round($cw * 0.0267, 2);
    $fit    = ['cover' => 'cover', 'contain' => 'contain', 'fill' => '100% 100%'][$t['bg_fit']] ?? 'cover';
    $pages  = (int) ceil($passes->count() / $grid['per_page']);
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Print passes — {{ $building->name }}</title>
<style>
    @page { size: {{ $grid['paper']['page_size'] }}; margin: {{ $grid['margin'] }}mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, sans-serif; background: #eef2f7; }
    .toolbar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: 14px; align-items: center;
               padding: 10px 16px; background: #fff; border-bottom: 1px solid #d9dce1; font-size: 14px; }
    .toolbar button { padding: 8px 16px; border: 0; border-radius: 8px; background: #235aa6; color: #fff; font-weight: 600; cursor: pointer; }
    .toolbar label { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
    .page { display: grid; grid-template-columns: repeat({{ $grid['cols'] }}, {{ $cw }}mm); gap: {{ $t['gap'] }}mm;
            justify-content: center; align-content: start; padding: {{ $grid['margin'] }}mm 0; break-after: page; }
    .page:last-child { break-after: auto; }
    .slot { position: relative; width: {{ $cw }}mm; height: {{ $ch }}mm; overflow: hidden;
            border-radius: {{ $t['radius'] }}mm; break-inside: avoid; }
    .guides .slot { outline: 0.15mm solid #c5ccd6; }
    .card { position: absolute; inset: 0; background-size: {{ $fit }}; background-position: center; background-repeat: no-repeat; }
    .qr { position: absolute; left: {{ $t['qr_x'] }}%; top: {{ $t['qr_y'] }}%; transform: translate(-50%, -50%);
          width: {{ $qrMm }}mm; height: {{ $qrMm }}mm; padding: {{ $t['qr_pad'] }}mm; background: #fff; border-radius: {{ $qrRad }}mm; }
    .qr canvas { display: none; }
    .qr img { display: block; width: 100% !important; height: 100% !important; }
    .num { position: absolute; left: {{ $t['num_x'] }}%; top: {{ $t['num_y'] }}%; transform: translate(-50%, -50%);
           color: {{ $t['num_color'] }}; font: 900 {{ $numMm }}mm/1 ui-monospace, monospace; white-space: nowrap;
           text-shadow: 0 0.2mm 0.6mm rgba(0, 0, 0, .4); }
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
<body class="{{ $t['guides'] ? 'guides' : '' }}">
<div class="toolbar">
<button id="printBtn" onclick="window.print()" disabled>Preparing…</button>
    <label><input type="checkbox" id="guides" {{ $t['guides'] ? 'checked' : '' }}> Cut guides</label>
    <span><strong>{{ $building->name }}</strong> · {{ $passes->count() }} passes · {{ $pages }} page(s) · {{ $grid['paper']['label'] }}{{ $t['landscape'] ? ' landscape' : '' }} · {{ $cw }} × {{ $ch }} mm · set scale to 100% and turn off headers/footers</span>
</div>

@forelse ($passes->chunk($grid['per_page']) as $chunk)
    <section class="page">
        @foreach ($chunk as $p)
            <div class="slot">
                <div class="card" style="background-image:url('{{ asset($building->template_image) }}')">
<div class="qr sk" data-token="{{ $p->qr_token }}"></div>
                    @if ($t['num_show'])
                        <div class="num">{{ $p->pass_number }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </section>
@empty
    <p style="padding:2rem;text-align:center;">No passes in that range. Generate them first.</p>
@endforelse

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const pending = [...document.querySelectorAll('.qr')];
const printBtn = document.getElementById('printBtn');
(function drawBatch() {
    if (typeof QRCode === 'undefined') { printBtn.textContent = 'QR library failed to load (needs internet)'; return; }
    pending.splice(0, 12).forEach(el => {
        new QRCode(el, {
            text: el.dataset.token, width: 256, height: 256,
            colorDark: "{{ $building->qr_color_hex }}", colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
        el.classList.remove('sk');
    });
    if (pending.length) { requestAnimationFrame(drawBatch); return; }
    printBtn.disabled = false; printBtn.textContent = 'Print';
})();
document.getElementById('guides').addEventListener('change', e =>
    document.body.classList.toggle('guides', e.target.checked));
</script>
</body>
</html>