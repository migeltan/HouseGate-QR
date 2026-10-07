<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Print passes — {{ $building->name }}</title>
<style>
    @page { size: A4; margin: 8mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, sans-serif; background: #eef2f7; }
    .toolbar { position: sticky; top: 0; z-index: 5; display: flex; gap: 14px; align-items: center;
               padding: 10px 16px; background: #fff; border-bottom: 1px solid #d9dce1; font-size: 14px; }
    .toolbar button { padding: 8px 16px; border: 0; border-radius: 8px; background: #235aa6; color: #fff; font-weight: 600; cursor: pointer; }
    .page { display: grid; grid-template-columns: repeat(3, 60mm); gap: 4mm; justify-content: center;
            padding: 8mm 0; break-after: page; }
    .page:last-child { break-after: auto; }
    .slot { width: 60mm; height: 100mm; overflow: hidden; border-radius: 2mm; break-inside: avoid; }
    .card { position: relative; width: 300px; height: 500px; background-size: cover; background-position: center;
            transform: scale(0.7559); transform-origin: top left; }
    .qr { position: absolute; left: 50%; top: 45%; transform: translateX(-50%); background: #fff; padding: 6px; border-radius: 8px; }
    .num { position: absolute; left: 50%; top: 80%; transform: translateX(-50%); color: #fff;
           font: 900 2.5rem ui-monospace, monospace; text-shadow: 0 1px 3px rgba(0, 0, 0, .4); }
    @media print {
        .toolbar { display: none; }
        body { background: #fff; }
        .page { padding: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">Print</button>
    <span><strong>{{ $building->name }}</strong> · {{ $passes->count() }} passes · {{ ceil($passes->count() / 6) }} page(s) · set scale to 100% and turn off headers/footers</span>
</div>

@forelse ($passes->chunk(6) as $chunk)
    <section class="page">
        @foreach ($chunk as $p)
            <div class="slot">
                <div class="card" style="background-image:url('{{ asset($building->template_image) }}')">
                    <div class="qr" data-token="{{ $p->qr_token }}"></div>
                    <div class="num">{{ $p->pass_number }}</div>
                </div>
            </div>
        @endforeach
    </section>
@empty
    <p style="padding:2rem;text-align:center;">No passes in that range. Generate them first.</p>
@endforelse

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.querySelectorAll('.qr').forEach(el => new QRCode(el, {
    text: el.dataset.token, width: 140, height: 140,
    colorDark: "{{ $building->qr_color_hex }}", colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.M
}));
</script>
</body>
</html>