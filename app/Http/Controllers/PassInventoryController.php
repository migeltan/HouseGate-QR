<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\VisitorPass;
use App\Services\PassGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PassInventoryController extends Controller
{
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'from'        => 'required|integer|min:1|max:9999',
            'to'          => 'required|integer|gte:from|max:9999',
        ]);
        abort_if($data['to'] - $data['from'] >= 1000, 422, 'Range too large (max 1000 at a time).');

        return $data;
    }

    private function passesFor(Building $building, array $d)
    {
        $pad = fn ($n) => str_pad((string) $n, 4, '0', STR_PAD_LEFT);

        return VisitorPass::where('building_id', $building->id)
            ->when($building->code === 'NG', fn ($q) => $q->where('is_multi_building', true))
            ->whereBetween('pass_number', [$pad($d['from']), $pad($d['to'])])
            ->orderBy('pass_number')
            ->get();
    }

    public function generate(Request $request, PassGenerator $generator)
    {
        $d = $this->validated($request);
        $building = Building::findOrFail($d['building_id']);
        $r = $generator->generate($building, (int) $d['from'], (int) $d['to']);

        Log::info('Passes generated', [
            'by' => $request->user()->id, 'building' => $building->code,
            'from' => $d['from'], 'to' => $d['to'], 'created' => $r['created'],
        ]);

        return redirect()->route('passes.index')
            ->with('success', "{$building->name}: {$r['created']} created, {$r['skipped']} already existed.");
    }

    public function print(Request $request)
    {
        $d = $this->validated($request);
        $building = Building::findOrFail($d['building_id']);

        return view('passes.print-sheet', [
            'building' => $building,
            'passes'   => $this->passesFor($building, $d),
        ]);
    }

    public function csv(Request $request)
    {
        $d = $this->validated($request);
        $building = Building::findOrFail($d['building_id']);
        $passes = $this->passesFor($building, $d);

        return response()->streamDownload(function () use ($passes, $building) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['building', 'pass_number', 'qr_token']);
            foreach ($passes as $p) {
                fputcsv($out, [$building->name, $p->pass_number, $p->qr_token]);
            }
            fclose($out);
        }, "passes-{$building->code}-{$d['from']}-{$d['to']}.csv", ['Content-Type' => 'text/csv']);
    }
}