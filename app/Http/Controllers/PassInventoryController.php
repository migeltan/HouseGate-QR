<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\Building;
use App\Models\PrintTemplate;
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
    private function grid(string $paper, float $cw, float $ch, float $gap, float $margin = self::MARGIN, bool $landscape = false): array
    {
        $p = self::PAPERS[$paper];
        $p['page_size'] = $p['size'] . ($landscape ? ' landscape' : '');
        if ($landscape) {
            [$p['w'], $p['h']] = [$p['h'], $p['w']];
        }
        $cols = max(1, (int) floor(($p['w'] - 2 * $margin + $gap) / ($cw + $gap)));
        $rows = max(1, (int) floor(($p['h'] - 2 * $margin + $gap) / ($ch + $gap)));

        return ['paper' => $p, 'cols' => $cols, 'rows' => $rows, 'per_page' => $cols * $rows, 'margin' => $margin];
    }

    /**
     * Full-pass-card template. mm for sizes, % of the card for positions (centre of each element).
     * Defaults reproduce the original fixed 60 x 100 mm layout; the print URL may override any key
     * with t[key]=value (e.g. t[card_w]=80&t[qr_size]=60).
     */
    private const CARD_DEFAULTS = [
        'card_w' => 60.0, 'card_h' => 100.0, 'gap' => 4.0, 'margin' => 8.0, 'radius' => 2.0,
        'landscape' => false, 'guides' => false, 'bg_fit' => 'cover',
        'qr_size' => 50.67, 'qr_x' => 50.0, 'qr_y' => 60.2, 'qr_pad' => 1.2,
        'num_show' => true, 'num_size' => 13.33, 'num_x' => 50.0, 'num_y' => 84.7, 'num_color' => '#ffffff',
    ];

    private function cardSettings(Request $request): array
    {
        $request->validate([
            't'            => 'nullable|array',
            't.card_w'     => 'nullable|numeric|min:30|max:200',
            't.card_h'     => 'nullable|numeric|min:30|max:290',
            't.gap'        => 'nullable|numeric|min:0|max:20',
            't.margin'     => 'nullable|numeric|min:0|max:30',
            't.radius'     => 'nullable|numeric|min:0|max:10',
            't.landscape'  => 'nullable|boolean',
            't.guides'     => 'nullable|boolean',
            't.bg_fit'     => 'nullable|in:cover,contain,fill',
            't.qr_size'    => 'nullable|numeric|min:15|max:90',
            't.qr_x'       => 'nullable|numeric|min:0|max:100',
            't.qr_y'       => 'nullable|numeric|min:0|max:100',
            't.qr_pad'     => 'nullable|numeric|min:0|max:5',
            't.num_show'   => 'nullable|boolean',
            't.num_size'   => 'nullable|numeric|min:4|max:30',
            't.num_x'      => 'nullable|numeric|min:0|max:100',
            't.num_y'      => 'nullable|numeric|min:0|max:100',
            't.num_color'  => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'template_id'  => 'nullable|integer|exists:print_templates,id',
        ]);

        // Saved template first, explicit t[...] overrides on top.
        $input = array_merge(
            $request->filled('template_id') ? (array) PrintTemplate::findOrFail($request->input('template_id'))->settings : [],
            (array) $request->input('t', [])
        );

        $out = self::CARD_DEFAULTS;
        foreach ($input as $key => $value) {
            if (! array_key_exists($key, $out) || $value === null || $value === '') {
                continue;
            }
            $out[$key] = match (true) {
                is_bool($out[$key])  => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                is_float($out[$key]) => (float) $value,
                default              => (string) $value,
            };
        }

        return $out;
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

    /**
     * Which blank cards a desk physically holds. 'desk' puts vault cards (held by no desk) at this
     * building's desk; 'vault' takes blank cards back out of its stock. Cards with a visitor and
     * cards held away at another desk are never touched.
     */
    public function stock(Request $request)
    {
        $data = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'from'        => 'required|integer|min:1|max:9999',
            'to'          => 'required|integer|gte:from|max:9999',
            'mode'        => 'required|in:desk,vault',
        ]);
        abort_if($data['to'] - $data['from'] >= 1000, 422, 'Range too large (max 1000 at a time).');

        $building = Building::findOrFail($data['building_id']);
        $padded = array_map(
            fn ($n) => str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            range((int) $data['from'], (int) $data['to'])
        );

        $blank = fn () => VisitorPass::where('building_id', $building->id)
            ->when($building->code === 'NG', fn ($q) => $q->where('is_multi_building', true))
            ->whereIn('pass_number', $padded)
            ->whereNull('visitor_name');

        $moved = $data['mode'] === 'desk'
            ? $blank()->whereNull('holder_building_id')->update(['holder_building_id' => $building->id])
            : $blank()->whereColumn('holder_building_id', 'building_id')->update(['holder_building_id' => null]);

        $stock = VisitorPass::where('building_id', $building->id)
            ->when($building->code === 'NG', fn ($q) => $q->where('is_multi_building', true))
            ->inStock()
            ->count();

        $verb = $data['mode'] === 'desk' ? 'given to this desk' : 'taken back to storage';
        $range = "#{$padded[0]}–#" . end($padded);
        AdminLog::record('pass.stock_updated', $building->name, "{$moved} card(s) {$verb} ({$range}). Desk stock now {$stock}.");

        $message = "{$building->name}: {$moved} " . ($moved === 1 ? 'card' : 'cards') . " {$verb}. This desk now has {$stock} " . ($stock === 1 ? 'card' : 'cards') . '.';
        if ($stock < VisitorPass::MIN_DESK_STOCK) {
            $message .= ' Keep at least ' . VisitorPass::MIN_DESK_STOCK . " so guards don't run out.";
        }

        return redirect()->route('passes.index')->with('success', $message);
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

        $t = $this->cardSettings($request);

        return view('passes.print-sheet', [
            'building' => $building,
            'passes'   => $passes,
            't'        => $t,
            'grid'     => $this->grid($d['paper'], $t['card_w'], $t['card_h'], $t['gap'], $t['margin'], $t['landscape']),
        ]);
    }

    private function templateRow(PrintTemplate $t): array
    {
        return [
            'id'       => $t->id,
            'name'     => $t->name,
            'settings' => array_merge(self::CARD_DEFAULTS, (array) $t->settings),
        ];
    }

    /** Saved card templates (admin only). */
    public function templates()
    {
        return response()->json(PrintTemplate::orderBy('name')->get()->map(fn ($t) => $this->templateRow($t))->values());
    }

    /** The editor sends the full settings as t[...], exactly like the print URL. */
    public function storeTemplate(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:60|unique:print_templates,name']);
        $template = PrintTemplate::create([
            'name'       => $data['name'],
            'settings'   => $this->cardSettings($request),
            'created_by' => $request->user()->id,
        ]);
        AdminLog::record('print_template.created', $template->name);

        return response()->json(['ok' => true, 'template' => $this->templateRow($template)], 201);
    }

    /** Rename and/or overwrite settings. Send the FULL t[...] set: a partial one resets the rest to defaults. */
    public function updateTemplate(Request $request, PrintTemplate $template)
    {
        $data = $request->validate(['name' => 'sometimes|required|string|max:60|unique:print_templates,name,' . $template->id]);
        if (isset($data['name'])) {
            $template->name = $data['name'];
        }
        if ($request->has('t')) {
            $template->settings = $this->cardSettings($request);
        }
        $template->save();
        AdminLog::record('print_template.updated', $template->name);

        return response()->json(['ok' => true, 'template' => $this->templateRow($template)]);
    }

    public function destroyTemplate(PrintTemplate $template)
    {
        AdminLog::record('print_template.deleted', $template->name);
        $template->delete();

        return response()->json(['ok' => true]);
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