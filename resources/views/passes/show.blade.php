@extends('layouts.app')
@section('title', 'Pass Preview')

@section('content')
@php
    $isMulti = $pass->is_multi_building;

    // Multi-building passes are homed at North Gate's own building_id
    // (see PassController::registerMultiBuilding()), so $pass->building
    // already IS the North Gate row — the old "ma.png" special-case
    // predates that and is no longer needed. North Gate's own
    // template_image/qr_color_hex columns drive the artwork now, same
    // as every other building.
    $templateImage = asset($pass->building->template_image);
    $qrColor = $pass->building->qr_color_hex;
@endphp

<div class="flex flex-col items-center gap-4">
   <p class="gov-eyebrow">House of Representatives &middot; {{ $isMulti ? 'North Gate Access' : 'Visitor pass' }}</p>
    <div class="flex justify-center">
        <div class="space-y-4">
            <div id="printablePassArea" class="pass-preview-card relative w-[300px] h-[500px] rounded-xl overflow-hidden"
                 style="background-image:url('{{ $templateImage }}'); background-size:cover; background-position:center;">

                <!-- QR overlay - adjust top/left % if misaligned with your template's dashed box -->
                <div class="absolute left-1/2 -translate-x-1/2 bg-white p-1.5 rounded-lg" style="top:45%;">
                    <div id="qrCodeContainer"></div>
                </div>

                <!-- Pass number overlay -->
                <div class="absolute left-1/2 -translate-x-1/2 text-white font-black font-mono drop-shadow-md" style="top:80%; font-size:2.5rem;">
                    {{ $pass->pass_number }}
                </div>
            </div>

            <div class="flex gap-2">
                <button onclick="window.print()" class="flex-1 gov-btn-camera" style="justify-content:center;"><i class="fa-solid fa-print"></i> Print</button>
                <a href="{{ route('passes.index') }}" class="flex-1 gov-btn-outline" style="justify-content:center;">Back</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById("qrCodeContainer"), {
    text: "{{ $pass->qr_token }}",
    width: 140, height: 140,
    colorDark: "{{ $qrColor }}",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});
</script>

<style>
@media print {
    body * { visibility: hidden; }
    #printablePassArea, #printablePassArea * { visibility: visible; }
    #printablePassArea { position: fixed; left: 0; top: 0; }
}
</style>
@endsection