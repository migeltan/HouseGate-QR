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

    private const PAPERS = [
        'a4'     => ['label' => 'A4',     'w' => 210.0, 'h' => 297.0, 'size' => 'A4'],
        'letter' => ['label' => 'Letter', 'w' => 215.9, 'h' => 279.4, 'size' => 'letter'],
        'legal'  => ['label' => 'Legal',  'w' => 215.9, 'h' => 355.6, 'size' => 'legal'],
    ];
    private const MARGIN = 8; // mm, every side

    /** How many cells of $cw x $ch mm (with a $gap mm gutter) fit on the paper. Mirrored in the modal's JS. */
    private function grid(string $paper, float $cw, float $ch, float $gap): array
    {
        $p = self::PAPERS[$paper];
        $cols = max(1, (int) floor(($p['w'] - 2 * self::MARGIN + $gap) / ($cw + $gap)));
        $rows = max(1, (int) floor(($p['h'] - 2 * self::MARGIN + $gap) / ($ch + $gap)));

        return ['paper' => $p, 'cols' => $cols, 'rows' => $rows, 'per_page' => $cols * $rows];
    }

    /** Print / CSV selection: a range, a typed list ("1-10, 25, 40-45"), or start + count. */
    private function selection(Request $request): array
    {
        $d = $request->validate([
            'building_id'    => 'required|exists:buildings,id',
            'mode'           => 'required|in:range,list,count',
            'from'           => 'exclude_unless:mode,range|required|integer|min:1|max:9999',
            'to'             => 'exclude_unless:mode,range|required|integer|gte:from|max:9999',
            'list'           => 'exclude_unless:mode,list|required|string|max:500',
            'start'          => 'exclude_unless:mode,count|required|integer|min:1|max:9999',
            'count'          => 'exclude_unless:mode,count|required|integer|min:1|max:1000',
            'only_available' => 'nullable|boolean',
            'layout'         => 'nullable|in:card,qr',
            'paper'          => 'nullable|in:a4,letter,legal',
            'size'           => 'nullable|in:18,22,26',
        ]);

        $numbers = match ($d['mode']) {
            'range' => range((int) $d['from'], (int) $d['to']),
            'count' => range((int) $d['start'], min(9999, (int) $d['start'] + (int) $d['count'] - 1)),
            'list'  => $this->parseList($d['list']),
        };
        $numbers = array_values(array_unique($numbers));
        sort($numbers);
        abort_if(count($numbers) > 1000, 422, 'Too many passes (max 1000 at a time).');

        return [
            'building_id'    => $d['building_id'],
            'only_available' => (bool) ($d['only_available'] ?? false),
            'layout'         => $d['layout'] ?? 'card',
            'paper'          => $d['paper'] ?? 'a4',
            'size'           => (int) ($d['size'] ?? 22),
            'numbers'        => $numbers,
        ];
    }

    private function parseList(string $raw): array
    {
        $out = [];
        $normalised = preg_replace('/\s*-\s*/', '-', trim($raw));

        foreach (preg_split('/[\s,;]+/', $normalised, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            abort_unless(preg_match('/^(\d{1,4})(?:-(\d{1,4}))?$/', $part, $m), 422, "Can't read \"{$part}\".");
            $a = (int) $m[1];
            $b = isset($m[2]) ? (int) $m[2] : $a;
            abort_if($a < 1 || $b < $a, 422, "\"{$part}\" isn't a valid number or range.");
            foreach (range($a, $b) as $n) {
                $out[] = $n;
            }
            abort_if(count($out) > 5000, 422, 'Too many passes (max 1000 at a time).');
        }

        return $out;
    }

    private function passesFor(Building $building, array $d)
    {
        $padded = array_map(fn ($n) => str_pad((string) $n, 4, '0', STR_PAD_LEFT), $d['numbers']);

        return VisitorPass::where('building_id', $building->id)
            ->when($building->code === 'NG', fn ($q) => $q->where('is_multi_building', true))
            ->whereIn('pass_number', $padded)
            ->when($d['only_available'], fn ($q) => $q->where('status', 'available'))
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
        $d = $this->selection($request);
        $building = Building::findOrFail($d['building_id']);
        $passes = $this->passesFor($building, $d);

        if ($d['layout'] === 'qr') {
            $s = $d['size'];
            return view('passes.print-qr', [
                'building' => $building,
                'passes'   => $passes,
                'size'     => $s,
                'grid'     => $this->grid($d['paper'], $s + 4, $s + 9, 2),
            ]);
        }

        return view('passes.print-sheet', [
            'building' => $building,
            'passes'   => $passes,
            'grid'     => $this->grid($d['paper'], 60, 100, 4),
        ]);
    }

    public function csv(Request $request)
    {
        $d = $this->selection($request);
        $building = Building::findOrFail($d['building_id']);
        $passes = $this->passesFor($building, $d);

        return response()->streamDownload(function () use ($passes, $building) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['building', 'pass_number', 'qr_token']);
            foreach ($passes as $p) {
                fputcsv($out, [$building->name, $p->pass_number, $p->qr_token]);
            }
            fclose($out);
        }, "passes-{$building->code}-" . ($passes->first()?->pass_number ?? '0000') . '-' . ($passes->last()?->pass_number ?? '0000') . '.csv', ['Content-Type' => 'text/csv']);
    }
}