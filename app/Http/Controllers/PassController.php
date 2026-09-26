<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Congressman;
use App\Models\VisitorPass;
use App\Models\PassRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PassController extends Controller
{
    // ID types whose numbers are not reliably unique across people (employee
    // numbers etc.). An exact match on these is only a "possible" match.
    private const WEAK_ID_TYPES = ['company id', 'other'];
    public function index(Request $request)
    {
        $user = $request->user();

               // Buildings selectable as a registration destination — North Gate
        // is excluded here on purpose; it's not a real destination, it's
        // the bookkeeping home for multi-building passes.
        $buildingsQuery = Building::where('code', '!=', 'NG')->orderBy('name');
        if ($user->isGuard()) {
            $buildingsQuery->where('id', session('assigned_building_id'));
        }
        $buildings = $buildingsQuery->get();

        // Buildings shown as their own clickable tile on the Passes grid —
        // same guard scoping as $buildings, but North Gate IS included so
        // its own dedicated pass pool gets a visible card.
        $displayBuildingsQuery = Building::orderBy('name');
        if ($user->isGuard()) {
            $displayBuildingsQuery->where('id', session('assigned_building_id'));
        }
        $displayBuildings = $displayBuildingsQuery->get();

        $passesQuery = VisitorPass::with(['building', 'buildings']);
        if ($user->isGuard()) {
            $passesQuery->where('building_id', session('assigned_building_id'))->where('is_multi_building', false);
        }
        $passes = $passesQuery->orderBy('building_id')->orderBy('pass_number')->get();

        // Congressman roster for the registration modal. $buildings is already
        // guard-scoped and excludes North Gate, so guards only get their own building.
        $roster = Congressman::inBuildings($buildings->pluck('id')->all())
            ->orderBy('name')
            ->get(['id', 'name', 'rep_detail', 'room', 'building_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'detail' => $c->rep_detail,
                'room' => $c->room,
                'b' => $c->building_id,
            ])
            ->values();

        return view('passes.index', compact('buildings', 'passes', 'displayBuildings', 'roster'));
    }

    public function register(Request $request)
    {
        // Guard can never submit a different building or trigger multi —
        // server overrides whatever the client sent, same pattern as the
        // Scanner's building lock.
        if ($request->user()->isGuard()) {
            $request->merge(['building_ids' => [session('assigned_building_id')]]);
        }

        $data = $request->validate([
                        'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:Male,Female,PNS',
            'contact_no' => 'nullable|string|max:50',
            'visitor_email' => 'nullable|email|max:255',
            'id_type' => 'nullable|string|max:255',
            'id_ref' => 'nullable|string|max:255',
            'congressman_ids' => 'nullable|array',
            'congressman_ids.*' => 'integer|exists:congressmen,id',
            'office_other' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'purpose_choice' => 'required|in:Official Business,Financial/Medical Assistance,Visit,Others',
            'purpose_other' => 'required_if:purpose_choice,Others|nullable|string|max:255',
            'vehicle' => 'nullable|string|max:255',
            'registered_by' => 'required|string|max:255',
            'pass_class' => 'required|in:day,long_term',
            'expected_return_date' => 'required_if:pass_class,long_term|nullable|date|after:today',
            'building_ids' => 'required|array|min:1',
            'building_ids.*' => 'exists:buildings,id',
        ]);

        // Reason is chosen from a fixed list; free text only applies when "Others" is picked.
                // Workflow 1: refuse a second pass for someone who already holds an active one.
        // This is the real enforcement; the Step 2 warning is only a convenience.
        $data['transfer_from_pass_id'] = null;
        if ($dup = $this->findActiveDuplicate(
            $data['id_type'] ?? null, $data['id_ref'] ?? null,
            $data['first_name'] ?? null, $data['last_name'] ?? null, $data['contact_no'] ?? null
        )) {
            $p = $dup['pass'];
            $token = trim((string) $request->input('transfer_qr_token'));

            if ($token !== '') {
                // Workflow 2: the guard is holding the old card. Its QR token must belong to
                // the exact pass this person matched, otherwise nothing gets closed.
                $old = VisitorPass::where('qr_token', $token)->first();
                if (! $old || (int) $old->id !== (int) $p['id']) {
                    return back()->withErrors(
                        "That card is not the active pass for this visitor (found #{$p['pass_number']}, {$p['buildings']}). Scan the old card again."
                    )->withInput();
                }
                $data['transfer_from_pass_id'] = $old->id;
            } elseif ($dup['level'] === 'exact') {
                return back()->withErrors(
                    "This ID already has an active pass (#{$p['pass_number']}, {$p['buildings']}). A new pass cannot be issued."
                )->withInput();
            } elseif (! $request->boolean('confirm_different_person')) {
                return back()->withErrors(
                    "Possible match: {$p['holder']} already has active pass #{$p['pass_number']} ({$p['buildings']}). Confirm this is a different person to continue."
                )->withInput();
            }
        }

        $data['purpose'] = $data['purpose_choice'] === 'Others'
            ? trim($data['purpose_other'])
            : $data['purpose_choice'];

        if (count($data['building_ids']) > 1) {
            abort_unless($request->user()->isAdmin(), 403, 'Only admins can issue a North Gate Access (multi-building) pass.');
        }

                // Building lock: every chosen congressman must sit in one of the submitted
        // buildings (for guards, building_ids was already forced to their own).
        $congressmanIds = array_values(array_unique($data['congressman_ids'] ?? []));
        $other = trim($data['office_other'] ?? '');

        $congressmen = Congressman::inBuildings($data['building_ids'])
            ->whereIn('id', $congressmanIds)
            ->get();

        if ($congressmen->count() !== count($congressmanIds)) {
            return back()->withErrors('One or more selected congressmen are not in the selected building(s).')->withInput();
        }

        if ($congressmen->isEmpty() && $other === '') {
            return back()->withErrors('Choose at least one congressman, or fill in "Other".')->withInput();
        }

        $data['congressman_ids'] = $congressmen->pluck('id')->all();
        $data['office_other'] = $other !== '' ? $other : null;
        $data['office_to_visit'] = $this->officeSummary($congressmen, $data['office_other']);

        if ($data['pass_class'] === 'long_term' && ! empty($data['expected_return_date'])) {
            $cap = VisitorPass::addWorkingDays(now(), VisitorPass::MAX_LONG_TERM_WORKING_DAYS);
            if (\Carbon\Carbon::parse($data['expected_return_date'])->gt($cap)) {
                return back()->withErrors(
                    'Expected return date exceeds the 30-working-day cap for long-term passes ('
                    . $cap->format('M j, Y') . ' latest).'
                )->withInput();
            }
        }

        return count($data['building_ids']) === 1
            ? $this->registerSingleBuilding($data, $request)
            : $this->registerMultiBuilding($data, $request);
    }

        /**
     * Live duplicate check for the Step 2 form. System-wide on purpose:
     * deliberately NOT scoped to the guard's building, and returns only the
     * fields the warning needs.
     */
    public function checkDuplicate(Request $request)
    {
        $match = $this->findActiveDuplicate(
            (string) $request->query('id_type'), (string) $request->query('id_ref'),
            (string) $request->query('first_name'), (string) $request->query('last_name'),
            (string) $request->query('contact_no')
        );

        // Lets the Step 2 panel confirm a scanned old card belongs to the matched
        // pass before the guard submits — register() re-checks this either way.
        $transferReady = false;
        if ($match) {
            $token = trim((string) $request->query('transfer_qr_token'));
            if ($token !== '') {
                $old = VisitorPass::where('qr_token', $token)->where('status', 'active')->first();
                $transferReady = $old && (int) $old->id === (int) $match['pass']['id'];
            }
        }

        return response()->json(['match' => $match, 'transfer_ready' => $transferReady]);
    }

    // Transfer Mode (Step 2 toggle): resolves an active pass directly by its
    // qr_token so Step 2 can auto-fill from it, instead of the fuzzy
    // name/ID match used above by checkDuplicate().
    public function lookupTransferSource(Request $request)
    {
        $token = trim((string) $request->query('token'));
        if ($token === '') {
            return response()->json(['message' => 'No card token provided.'], 422);
        }

        $pass = VisitorPass::where('qr_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$pass) {
            return response()->json(['message' => 'That card does not match an active pass.'], 404);
        }

        $building = $pass->is_multi_building
            ? ($pass->buildings->count() ? $pass->buildings->pluck('name')->join(', ') : 'North Gate Access')
            : optional($pass->building)->name;

        return response()->json([
            'pass' => [
                'id'            => $pass->id,
                'pass_number'   => $pass->pass_number,
                'holder'        => trim(collect([$pass->first_name, $pass->middle_name, $pass->last_name])->filter()->join(' ')),
                'building'      => $building,
                'first_name'    => $pass->first_name,
                'middle_name'   => $pass->middle_name,
                'last_name'     => $pass->last_name,
                'gender'        => $pass->gender,
                'contact_no'    => $pass->contact_no,
                'visitor_email' => $pass->visitor_email,
                'id_type'       => $pass->id_type,
                'id_ref'        => $pass->id_ref,
            ],
        ]);
    }
    /**
     * Returns null, or ['level' => 'exact'|'possible', 'more' => int, 'pass' => [...]].
     *  - exact:    same (id_type, id_ref) on an active pass, government ID types only (hard stop)
     *  - possible: everything else that matches (soft block, guard must confirm):
     *      · same (id_type, id_ref) with a weak ID type (Company ID / Other)
     *      · same first + last name        (runs regardless of whether an ID was given)
     *      · same last name + contact no.  (covers a missing first name)
     * 'more' = how many additional active passes also matched.
     */
    private function findActiveDuplicate(?string $idType, ?string $idRef, ?string $firstName, ?string $lastName, ?string $contactNo): ?array
    {
        $norm = fn (?string $v) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $v));
        $name = fn (?string $v) => mb_strtolower(preg_replace('/[^\p{L}]/u', '', (string) $v));
        $phone = fn (?string $v) => substr(preg_replace('/\D/', '', (string) $v), -10);

        $idType = strtolower(trim((string) $idType));
        $idKey = $norm($idRef);
        $first = $name($firstName);
        $last = $name($lastName);
        $phoneKey = $phone($contactNo);

        $hasId = $idType !== '' && $idKey !== '';
        $hasName = $first !== '' && $last !== '';
        $hasContact = $last !== '' && strlen($phoneKey) >= 7;
        if (! $hasId && ! $hasName && ! $hasContact) {
            return null;
        }

        // "Active" = status active AND not past its own expiry. The day/long-term
        // date guards cover a nightly sweep that hasn't run yet, so a stale pass
        // can never block a returning visitor. System-wide: no building filter.
        $today = now()->startOfDay();
        $candidates = VisitorPass::with(['building', 'buildings', 'currentBuilding'])
            ->where('status', 'active')
            ->where(function ($q) use ($today) {
                $q->where(fn ($d) => $d->where('pass_class', 'day')->where('issued_at', '>=', $today))
                  ->orWhere(fn ($l) => $l->where('pass_class', 'long_term')->whereDate('expected_return_date', '>=', $today));
            })
            ->get();

        $matches = [];
        foreach ($candidates as $pass) {
            $level = null;
            if ($hasId && $norm($pass->id_ref) === $idKey && strtolower(trim((string) $pass->id_type)) === $idType) {
                $level = in_array($idType, self::WEAK_ID_TYPES, true) ? 'possible' : 'exact';
            } elseif (
                ($hasName && $name($pass->first_name) === $first && $name($pass->last_name) === $last)
                || ($hasContact && $name($pass->last_name) === $last && $phone($pass->contact_no) === $phoneKey)
            ) {
                $level = 'possible';
            }
            if ($level) {
                $matches[] = [$level, $pass];
            }
        }

        if (! $matches) {
            return null;
        }

        // An exact match always outranks a possible one when choosing which pass to show.
        usort($matches, fn ($a, $b) => ($a[0] === 'exact' ? 0 : 1) <=> ($b[0] === 'exact' ? 0 : 1));
        [$level, $pass] = $matches[0];

        return [
            'level' => $level,
            'more' => count($matches) - 1,
            'pass' => [
                'id' => $pass->id,
                'pass_number' => $pass->pass_number,
                'buildings' => $pass->authorizedBuildingNames(),
                'holder' => $pass->visitor_name,
                'purpose' => $pass->purpose,
                'pass_class' => $pass->pass_class,
                'issued_at' => $pass->issued_at?->format('M j, g:i A'),
                'checked_in_at' => $pass->currentBuilding?->name,
            ],
        ];
    }

    private function registerSingleBuilding(array $data, Request $request)
    {
        $buildingId = $data['building_ids'][0];

        $pass = VisitorPass::where('building_id', $buildingId)
            ->where('is_multi_building', false)
            ->whereNull('visitor_name')
            ->orderBy('pass_number')
            ->first();

        if (! $pass) {
            return back()->withErrors('No available passes left for that building. Please choose another building.')->withInput();
        }

        DB::transaction(fn () => $this->assignVisitorToPass($pass, $data, $request));

                return redirect()->route('passes.index')
            ->with('success', "Pass #{$pass->pass_number} assigned to {$pass->visitor_name} ({$pass->building->name}).")
            ->with('success_pass_number', $pass->pass_number);
    }

    /**
     * North Gate Access passes share one dedicated pool, homed under the
     * North Gate building row purely for pass_number bookkeeping — the
     * buildings pivot below is the real source of authorization truth.
     * Once a pass_number/qr_token is minted it's permanent (printed onto
     * physical PVC cards), so reassignment reuses the first unassigned
     * multi pass if one exists, and only mints a new one (meaning a new
     * physical card) when none are free.
     */
    private function registerMultiBuilding(array $data, Request $request)
    {
        $northGateId = Building::where('code', 'NG')->value('id');

        $pass = VisitorPass::where('is_multi_building', true)
            ->whereNull('visitor_name')
            ->first();

        if (! $pass) {
            $nextNumber = (int) (VisitorPass::query()
                ->where('building_id', $northGateId)
                ->where('is_multi_building', true)
                ->selectRaw('MAX(CAST(pass_number AS UNSIGNED)) as max_num')
                ->first()
                ?->max_num ?? 0) + 1;

            $passNumber = str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

            $pass = VisitorPass::create([
                'building_id' => $northGateId,
                'pass_number' => $passNumber,
                'qr_token' => "PASS-MULTI-{$passNumber}",
                'is_multi_building' => true,
            ]);
        }

           DB::transaction(function () use ($pass, $data, $request) {
               $this->assignVisitorToPass($pass, $data, $request);
               $pass->buildings()->sync($data['building_ids']);
           });

        $buildingCount = count($data['building_ids']);
        return redirect()->route('passes.index')
            ->with('success', "North Gate Access pass #{$pass->pass_number} issued to {$pass->visitor_name} for {$buildingCount} buildings.")
            ->with('success_pass_number', $pass->pass_number);
    }

    public function updateBuildings(Request $request, VisitorPass $pass)
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($pass->is_multi_building, 404);

        $data = $request->validate([
            'building_ids' => 'required|array|min:2',
            'building_ids.*' => 'exists:buildings,id',
        ]);

        if ($pass->current_building_id && ! in_array($pass->current_building_id, $data['building_ids'])) {
            return back()->withErrors(
                "Cannot remove {$pass->currentBuilding->name} — visitor is currently checked in there. Scan them out first."
            );
        }

        $pass->buildings()->sync($data['building_ids']);
        $pass->openRegistration()?->update([
            'buildings_snapshot' => Building::whereIn('id', $data['building_ids'])->orderBy('name')->pluck('name')->join(', '),
        ]);

        return redirect()->route('passes.index')
            ->with('success', "Updated authorized buildings for Pass #{$pass->pass_number}.");

        
        // Buildings removed → drop those buildings' congressmen from the open registration
        // and rebuild the summary so it no longer names people the pass doesn't cover.
        if ($registration = $pass->openRegistration()) {
            $stale = $registration->congressmen()
                ->whereNotIn('congressmen.building_id', $data['building_ids'])
                ->pluck('congressmen.id');

            if ($stale->isNotEmpty()) {
                $registration->congressmen()->detach($stale->all());

                $summary = $this->officeSummary($registration->congressmen()->get(), $registration->office_other);
                $registration->update(['office_to_visit' => $summary]);
                $pass->update(['office_to_visit' => $summary]);
            }
        }
    }

    public function show(Request $request, VisitorPass $pass)
    {
        $this->authorizeGuardScope($request, $pass);
        $pass->load(['building', 'buildings']);
        return view('passes.show', compact('pass'));
    }

    public function unassign(Request $request, VisitorPass $pass)
    {
        $this->authorizeGuardScope($request, $pass);

        if ($pass->photo_path) {
            Storage::disk('public')->delete($pass->photo_path);
        }
        if ($pass->id_photo_path) {
            Storage::disk('public')->delete($pass->id_photo_path);
        }

        $pass->openRegistration()?->update([
            'unassigned_at' => now(),
            'unassign_reason' => 'returned',
        ]);

        $pass->update([
            'visitor_name' => null, 'first_name' => null, 'middle_name' => null, 'last_name' => null,
            'gender' => null, 'contact_no' => null, 'id_ref' => null, 'id_type' => null,
                        'purpose' => null, 'office_to_visit' => null, 'contact_person' => null, 'vehicle' => null,
            'status' => 'available', 'issued_at' => null,
            'photo_path' => null, 'id_photo_path' => null, 'pass_class' => 'day',
            'expected_return_date' => null, 'visitor_email' => null, 'registered_by' => null,
            'current_building_id' => null, 'checked_in_at' => null, 'last_egress_at' => null,
        ]);

        if ($pass->is_multi_building) {
            $pass->buildings()->detach();
        }

        return redirect()->route('passes.index')
            ->with('success', "Pass #{$pass->pass_number} unassigned and returned to available stock.");
    }


        /**
     * Immediately deny a pass without closing out the visit the way
     * unassign() does — visitor data, dates, and building assignment stay
     * intact so the record remains visible/auditable; only status changes
     * and any current building presence is cleared. Unassign remains the
     * action that actually returns the pass to available stock.
     */
    public function revoke(Request $request, VisitorPass $pass)
    {
        $this->authorizeGuardScope($request, $pass);

        $pass->update([
            'status' => 'revoked',
            'current_building_id' => null,
            'checked_in_at' => null,
        ]);

        return redirect()->route('passes.index')
            ->with('success', "Pass #{$pass->pass_number} revoked.");
    }
    
    /**
     * Workflow 2: the old card was handed back at the destination. Same reset as
     * unassign(), except the registration is closed as 'transferred' (the new
     * registration already points back at it). The card returns straight to
     * available stock in its home building.
     */
    private function closeForTransfer(VisitorPass $old, PassRegistration $oldRegistration): void
    {
        $photoPaths = array_filter([$old->photo_path, $old->id_photo_path]);

        $oldRegistration->update([
            'unassigned_at' => now(),
            'unassign_reason' => 'transferred',
        ]);

        $old->update([
            'visitor_name' => null, 'first_name' => null, 'middle_name' => null, 'last_name' => null,
            'gender' => null, 'contact_no' => null, 'id_ref' => null, 'id_type' => null,
            'purpose' => null, 'office_to_visit' => null, 'contact_person' => null, 'vehicle' => null,
            'status' => 'available', 'issued_at' => null,
            'photo_path' => null, 'id_photo_path' => null, 'pass_class' => 'day',
            'expected_return_date' => null, 'visitor_email' => null, 'registered_by' => null,
            'current_building_id' => null, 'checked_in_at' => null, 'last_egress_at' => null,
        ]);

        if ($old->is_multi_building) {
            $old->buildings()->detach();
        }

        foreach ($photoPaths as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * A guard may only unassign/view passes homed in their own building.
     * North Gate Access (multi-building) passes are never guard-actionable,
     * even if the guard's building happens to be one of the authorized ones —
     * only admins manage multi passes.
     */
    private function authorizeGuardScope(Request $request, VisitorPass $pass): void
    {
        if (! $request->user()->isGuard()) {
            return;
        }
        abort_if($pass->is_multi_building, 403, 'Multi-building passes are admin-managed only.');
        abort_unless((int) $pass->building_id === (int) session('assigned_building_id'), 403);
    }

       private function assignVisitorToPass(VisitorPass $pass, array $data, Request $request): void
    {
        $idPhotoPath = $this->storeIdPhoto($request) ?? $pass->id_photo_path;

        $oldPass = null;
        $oldRegistration = null;
        if (! empty($data['transfer_from_pass_id'])) {
            // Re-check under a row lock: another guard may have closed it since the form loaded.
            $oldPass = VisitorPass::whereKey($data['transfer_from_pass_id'])->lockForUpdate()->first();
            $oldRegistration = ($oldPass && $oldPass->status === 'active') ? $oldPass->openRegistration() : null;
            if (! $oldRegistration) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'transfer' => 'That pass is no longer active, so it cannot be transferred. Refresh and try again.',
                ]);
            }
        }
        $fullName = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
        ]))));
        // Everything but building/reason/congressman is optional now, so a name can
        // genuinely be blank. Store a real fallback string, never null — registerSingleBuilding()
        // finds free passes via whereNull('visitor_name'), so null here would put an
        // already-issued pass back in that pool.
        if ($fullName === '') {
            $fullName = 'Unnamed Visitor';
        }

        $pass->update([
            'visitor_name' => $fullName,
            'first_name' => $data['first_name'] ?? null,
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'gender' => $data['gender'] ?? null,
            'contact_no' => $data['contact_no'] ?? null,
            'id_ref' => $data['id_ref'] ?? null,
            'id_type' => $data['id_type'] ?? null,
            'purpose' => $data['purpose'],
            'office_to_visit' => $data['office_to_visit'],
            'contact_person' => $data['contact_person'] ?? null,
            'vehicle' => $data['vehicle'] ?? null,
            'status' => 'active',
            'issued_at' => now(),
            'photo_path' => $this->storeVisitorPhoto($request) ?? $pass->photo_path,
            'id_photo_path' => $idPhotoPath,
            'pass_class' => $data['pass_class'],
            'expected_return_date' => $data['pass_class'] === 'long_term' ? $data['expected_return_date'] : null,
            'visitor_email' => $data['visitor_email'] ?? null,
            'registered_by' => $data['registered_by'] ?? null,
        ]);

        $registration = PassRegistration::create([
            'visitor_pass_id' => $pass->id,
            'visitor_name' => $fullName,
            'first_name' => $data['first_name'] ?? null,
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'gender' => $data['gender'] ?? null,
            'contact_no' => $data['contact_no'] ?? null,
            'id_type' => $data['id_type'] ?? null,
            'id_ref' => $data['id_ref'] ?? null,
            'photo_path' => $pass->photo_path,
            'id_photo_path' => $idPhotoPath,
            'purpose' => $data['purpose'],
            'office_to_visit' => $data['office_to_visit'],
            'vehicle' => $data['vehicle'] ?? null,
            'visitor_email' => $data['visitor_email'] ?? null,
            'registered_by' => $data['registered_by'] ?? null,
            'pass_class' => $data['pass_class'],
            'expected_return_date' => $data['pass_class'] === 'long_term' ? $data['expected_return_date'] : null,
            'office_other' => $data['office_other'] ?? null,
                        'contact_person' => $data['contact_person'] ?? null,
            // Frozen at registration: multi-pass building links are wiped on unassign.
            'buildings_snapshot' => Building::whereIn('id', $data['building_ids'])->orderBy('name')->pluck('name')->join(', '),
            'registered_at' => now(),
            'transferred_from_registration_id' => $oldRegistration?->id,
        ]);

        $registration->congressmen()->sync($data['congressman_ids'] ?? []);

        // Close out the old card now that the new one is issued — otherwise
        // it stays active and the visitor ends up holding two passes at once.
        if ($oldPass && $oldRegistration) {
            $this->closeForTransfer($oldPass, $oldRegistration);
        }
}
    /**
     * One-line "who they're visiting" text kept in office_to_visit so the info
     * modal and pre-existing passes keep working. The pivot holds the real data.
     */
    private function officeSummary(\Illuminate\Support\Collection $congressmen, ?string $other): string
    {
        $parts = $congressmen->sortBy('name')
            ->map(fn ($c) => $c->name . ($c->room ? " ({$c->room})" : ''))
            ->values()
            ->all();

        if ($other) {
            $parts[] = "Other: {$other}";
        }

        return Str::limit(implode('; ', $parts), 254, '…'); // column is 255 chars
    }

    private function storeVisitorPhoto(Request $request): ?string
    {
        if (! $request->filled('photo_data')) {
            return null;
        }
        $imageData = base64_decode(substr($request->input('photo_data'), strpos($request->input('photo_data'), ',') + 1));
        $filename = 'visitor-photos/' . uniqid('visitor_') . '.jpg';
        Storage::disk('public')->put($filename, $imageData);
        return $filename;
    }

    private function storeIdPhoto(Request $request): ?string
    {
        if (! $request->filled('id_photo_data')) {
            return null;
        }
        $imageData = base64_decode(substr($request->input('id_photo_data'), strpos($request->input('id_photo_data'), ',') + 1));
        $filename = 'id-photos/' . uniqid('id_') . '.jpg';
        Storage::disk('public')->put($filename, $imageData);
        return $filename;
    }
}