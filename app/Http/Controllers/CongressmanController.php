<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\Building;
use App\Models\Congressman;
use App\Services\PhotoResizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CongressmanController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();

        $members = Congressman::query()
            ->with('building:id,name,color_hex')
            ->when($request->filled('q'), function ($q) use ($request) {
                $s = '%' . $request->query('q') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $s)
                    ->orWhere('rep_detail', 'like', $s)
                    ->orWhere('room', 'like', $s)
                    ->orWhere('member_id', 'like', $s));
            })
            ->when($request->filled('building'), fn ($q) => $q->where('building_id', $request->query('building')))
            // Guards only ever see active members; admins can filter by status.
            ->when(! $isAdmin, fn ($q) => $q->active())
            ->when($isAdmin && in_array($request->query('status'), ['active', 'inactive'], true),
                fn ($q) => $q->where('is_active', $request->query('status') === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Filters and pagination swap just the table, no page reload.
        if ($request->ajax()) {
            return view('congressmen.table', compact('members', 'isAdmin'));
        }

        $buildings = Building::selectable()->orderBy('name')->get();

        return view('congressmen.index', compact('members', 'buildings', 'isAdmin'));
    }

    // ------------------------------------------------------------------
    // Admin-only edits (routes sit inside the `admin` middleware group)
    // ------------------------------------------------------------------

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $jpeg = $this->readPhoto($request);

        $member = new Congressman($data);
        $member->member_id = $this->newMemberId();
        $member->is_active = true;
        $member->save();

        if ($jpeg) {
            $this->savePhoto($member, $jpeg);
        }

        AdminLog::record('congressman.created', $member->name, $this->location($member));

        return response()->json(['message' => 'Member added.']);
    }

    public function update(Request $request, Congressman $congressman)
    {
        $data = $this->validated($request, $congressman);
        $jpeg = $this->readPhoto($request);

        $labels = [
            'name' => 'name', 'rep_type' => 'type', 'rep_detail' => 'district / party-list',
            'building_id' => 'building', 'floor' => 'floor', 'room' => 'room',
        ];
        $buildings = Building::pluck('name', 'id');

        $congressman->fill($data);
        $changes = collect($congressman->getDirty())->map(function ($new, $field) use ($congressman, $labels, $buildings) {
            $old = $congressman->getOriginal($field);
            if ($field === 'building_id') {
                $old = $buildings[$old] ?? $old;
                $new = $buildings[$new] ?? $new;
            }

            return ($labels[$field] ?? $field) . ': ' . ($old ?? '—') . ' → ' . ($new ?? '—');
        })->values();

        $congressman->save();

        if ($jpeg) {
            $this->savePhoto($congressman, $jpeg);
            $changes->push('photo replaced');
        }

        if ($changes->isNotEmpty()) {
            AdminLog::record('congressman.updated', $congressman->name, $changes->implode('; '));
        }

        return response()->json(['message' => $changes->isNotEmpty() ? 'Member updated.' : 'No changes to save.']);
    }

    public function deactivate(Congressman $congressman)
    {
        $congressman->update(['is_active' => false]);
        AdminLog::record('congressman.deactivated', $congressman->name, $this->location($congressman));

        return response()->json(['message' => 'Member deactivated. They no longer appear in the Registry list.']);
    }

    public function reactivate(Congressman $congressman)
    {
        $congressman->update(['is_active' => true]);
        AdminLog::record('congressman.reactivated', $congressman->name, $this->location($congressman));

        return response()->json(['message' => 'Member reactivated.']);
    }

    private function validated(Request $request, ?Congressman $ignore = null): array
    {
        $data = $request->validate([
            // Unique because the photo importer matches files by name.
            'name' => ['required', 'string', 'max:255', Rule::unique('congressmen', 'name')->ignore($ignore?->id)],
            'rep_type' => ['required', Rule::in(['District Representative', 'Party-list Representative'])],
            'rep_detail' => ['nullable', 'string', 'max:255'],
            'building_id' => ['required', Rule::exists('buildings', 'id')->where(fn ($q) => $q->where('code', '!=', 'NG'))],
            'floor' => ['nullable', 'integer', 'min:0', 'max:30'],
            'room' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        unset($data['photo']);

        return $data;
    }

    /** Uploaded photo re-encoded as a small JPEG, or null when none was sent. */
    private function readPhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        $jpeg = PhotoResizer::toJpeg(file_get_contents($request->file('photo')->getRealPath()));
        if (! $jpeg) {
            throw ValidationException::withMessages(['photo' => 'That file could not be read as an image.']);
        }

        return $jpeg;
    }

    private function savePhoto(Congressman $member, string $jpeg): void
    {
        $path = "congressmen/{$member->member_id}.jpg";
        Storage::disk('public')->put($path, $jpeg);

        $member->photo_path = $path;
        $member->updated_at = now();   // bumps the ?v= cache-buster even when the path is unchanged
        $member->save();
    }

    /** Admin-added members get ADD-0001, ADD-0002, ...; roster IDs (K106, ...) are never reused. */
    private function newMemberId(): string
    {
        $n = Congressman::where('member_id', 'like', 'ADD-%')->count() + 1;
        while (Congressman::where('member_id', 'ADD-' . str_pad($n, 4, '0', STR_PAD_LEFT))->exists()) {
            $n++;
        }

        return 'ADD-' . str_pad($n, 4, '0', STR_PAD_LEFT);
    }

    private function location(Congressman $m): string
    {
        $m->loadMissing('building');

        return collect([
            $m->building->name ?? null,
            $m->floor !== null ? 'Floor ' . $m->floor : null,
            $m->room,
        ])->filter()->implode(' · ');
    }
}