<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\VisitorPass;
use App\Models\PassRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PassController extends Controller
{
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

        return view('passes.index', compact('buildings', 'passes', 'displayBuildings'));
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
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female,PNS',
            'contact_no' => 'required|string|max:50',
            'visitor_email' => 'nullable|email|max:255',
            'id_type' => 'required|string|max:255',
            'id_ref' => 'required|string|max:255',
            'office_to_visit' => 'required|string|max:255',
            'purpose' => 'required|string|max:255',
            'vehicle' => 'nullable|string|max:255',
            'registered_by' => 'nullable|string|max:255',
            'pass_class' => 'required|in:day,long_term',
            'expected_return_date' => 'required_if:pass_class,long_term|nullable|date|after:today',
            'building_ids' => 'required|array|min:1',
            'building_ids.*' => 'exists:buildings,id',
        ]);

        if (count($data['building_ids']) > 1) {
            abort_unless($request->user()->isAdmin(), 403, 'Only admins can issue a North Gate Access (multi-building) pass.');
        }

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

        $this->assignVisitorToPass($pass, $data, $request);

        return redirect()->route('passes.index')->with('success', "Pass assigned to {$pass->visitor_name}.");
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

        $this->assignVisitorToPass($pass, $data, $request);
        $pass->buildings()->sync($data['building_ids']);

        $buildingCount = count($data['building_ids']);
        return redirect()->route('passes.index')
            ->with('success', "North Gate Access pass #{$pass->pass_number} issued to {$pass->visitor_name} for {$buildingCount} buildings.");
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

        return redirect()->route('passes.index')
            ->with('success', "Updated authorized buildings for Pass #{$pass->pass_number}.");
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
            'purpose' => null, 'office_to_visit' => null, 'vehicle' => null,
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
        $fullName = trim(preg_replace('/\s+/', ' ',
            $data['first_name'] . ' ' . ($data['middle_name'] ?? '') . ' ' . $data['last_name']
        ));

        $pass->update([
            'visitor_name' => $fullName,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'gender' => $data['gender'],
            'contact_no' => $data['contact_no'],
            'id_ref' => $data['id_ref'],
            'id_type' => $data['id_type'],
            'purpose' => $data['purpose'],
            'office_to_visit' => $data['office_to_visit'],
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

        PassRegistration::create([
            'visitor_pass_id' => $pass->id,
            'visitor_name' => $fullName,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'gender' => $data['gender'],
            'contact_no' => $data['contact_no'],
            'id_type' => $data['id_type'],
            'id_ref' => $data['id_ref'],
            'photo_path' => $pass->photo_path,
            'id_photo_path' => $idPhotoPath,
            'purpose' => $data['purpose'],
            'office_to_visit' => $data['office_to_visit'],
            'vehicle' => $data['vehicle'] ?? null,
            'visitor_email' => $data['visitor_email'] ?? null,
            'registered_by' => $data['registered_by'] ?? null,
            'pass_class' => $data['pass_class'],
            'expected_return_date' => $data['pass_class'] === 'long_term' ? $data['expected_return_date'] : null,
            'registered_at' => now(),
        ]);
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