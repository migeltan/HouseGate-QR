<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\ScanLog;
use App\Models\VisitorPass;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ScannerController extends Controller
{
    // Slate/charcoal accent for Multiple Access badges, per spec.
    private const BADGE_MULTI_COLOR = '#475569';

    public function index()
    {
        $buildings = Building::where('code', '!=', 'NG')->orderBy('name')->get();
        $passes = VisitorPass::with('building')->orderBy('building_id')->orderBy('pass_number')->get();
        $recentLogs = ScanLog::with(['visitorPass', 'scannedBuilding'])->latest()->limit(50)->get();

        // Admin still picks freely; guard's building comes from their locked session value.
        $lockedBuildingId = request()->user()->isGuard() ? session('assigned_building_id') : null;
        $lockedBuilding = $lockedBuildingId ? Building::find($lockedBuildingId) : null;

        return view('scanner.index', compact('buildings', 'passes', 'recentLogs', 'lockedBuilding'));
    }

    private function storeVerificationPhoto(string $base64, ScanLog $log, ?VisitorPass $pass): void
{
        if (! preg_match('/^data:image\/(\w+);base64,/', $base64, $m)) {
            return;
        }
        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $raw = base64_decode(substr($base64, strpos($base64, ',') + 1));
        $path = 'verification-photos/' . $log->id . '_' . uniqid() . '.' . $ext;
        Storage::disk('public')->put($path, $raw);

        \App\Models\ScanVerificationPhoto::create([
            'scan_log_id' => $log->id,
            'visitor_pass_id' => $pass?->id,
            'photo_path' => $path,
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([ 'token' => 'required|string', 'verification_photo' => 'nullable|string',]);

        if ($user->isGuard()) {
            // Guard's building is locked server-side — ignore whatever the client sent.
            $scannedBuildingId = session('assigned_building_id');
            abort_unless($scannedBuildingId, 403, 'No building assigned to this session.');
        } else {
            $validated = $request->validate(['scanned_building_id' => 'required|exists:buildings,id']);
            $scannedBuildingId = $validated['scanned_building_id'];
        }

        $scannerBuilding = Building::findOrFail($scannedBuildingId);
        $pass = VisitorPass::with(['building', 'buildings'])->where('qr_token', $data['token'])->first();

        $direction = null;
        $result = 'INVALID';
        $reason = 'QR Token payload not recognized in central database.';
        $visitorName = 'Unknown / Unregistered';
        $passNumber = '----';
        $authorizedBuildingName = 'None';
        $colorHex = '#64748b';
        $photoUrl = null;
        $idPhotoUrl = null;

        $staleNotice = null;
        if ($pass && $pass->hasStaleOccupancy()) {
            $staleNotice = "Previous check-in at {$pass->currentBuilding?->name} was over "
                . VisitorPass::STALE_OCCUPANCY_HOURS . "h ago with no exit scan — treating this as a fresh entry.";
            $pass->update(['current_building_id' => null, 'checked_in_at' => null]);
        }

        if ($pass) {
            $visitorName = $pass->visitor_name ?: 'Unassigned Card';
            $passNumber = $pass->pass_number;
            $authorizedBuildingName = $pass->authorizedBuildingNames();
            $colorHex = $pass->is_multi_building ? self::BADGE_MULTI_COLOR : $pass->building->color_hex;
                        /** @var \Illuminate\Filesystem\FilesystemAdapter $publicDisk */
            $publicDisk = Storage::disk('public');
            $photoUrl = $pass->photo_path ? $publicDisk->url($pass->photo_path) : null;
            $idPhotoUrl = $pass->id_photo_path ? $publicDisk->url($pass->id_photo_path) : null;

            if ($pass->status === 'expired') {
                $result = 'EXPIRED';
                $reason = 'Visitor pass status marked as EXPIRED.';
            } elseif ($pass->status === 'revoked') {
                $result = 'REVOKED';
                $reason = 'Visitor pass is REVOKED by Security.';
            } elseif (! $pass->isAuthorizedFor($scannerBuilding->id)) {
                $result = 'UNAUTHORIZED';
                $reason = "BUILDING MISMATCH! Pass is authorized ONLY for [{$authorizedBuildingName}], but scanned at [{$scannerBuilding->name}].";
            } elseif ($pass->current_building_id && (int) $pass->current_building_id !== $scannerBuilding->id) {
                $result = 'BLOCKED';
                $currentName = $pass->currentBuilding?->name ?? 'another building';
                $reason = "Visitor must scan OUT of {$currentName} before entering {$scannerBuilding->name}.";
            } elseif ($pass->current_building_id === null) {
                $direction = 'in';
                $result = 'AUTHORIZED';
                $reason = "Access Granted - Entry logged at {$scannerBuilding->name}.";
                $pass->update(['current_building_id' => $scannerBuilding->id, 'checked_in_at' => now()]);
            } else {
                $direction = 'out';
                $result = 'AUTHORIZED';
                $reason = "Access Granted - Exit logged at {$scannerBuilding->name}.";
                $pass->update(['current_building_id' => null, 'last_egress_at' => now()]);
            }
        }

        $log = ScanLog::create([
            'visitor_pass_id' => $pass?->id,
            'qr_token_scanned' => $data['token'],
            'scanned_building_id' => $scannerBuilding->id,
            'visitor_name_snapshot' => $visitorName,
            'pass_number_snapshot' => $passNumber,
            'authorized_building_snapshot' => $authorizedBuildingName,
            'result' => $result,
            'reason' => $reason,
            'direction' => $direction,
            'scanned_by_user_id' => $user->id,
        ]);
        if (! empty($data['verification_photo'])) {
            $this->storeVerificationPhoto($data['verification_photo'], $log, $pass);
        }

        // Recent in/out history for this specific pass, so the guard can see
        // the visitor's movement pattern at a glance. Only AUTHORIZED scans
        // count as real entries/exits — denied/blocked attempts aren't
        // actual movements and would just clutter the timeline.
        $recentActivity = [];
        if ($pass) {
            $recentActivity = ScanLog::where('visitor_pass_id', $pass->id)
                ->where('result', 'AUTHORIZED')
                ->with('scannedBuilding')
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn ($entry) => [
                    'direction' => $entry->direction,
                    'building'  => $entry->scannedBuilding->name ?? $entry->authorized_building_snapshot,
                    'time'      => $entry->created_at->format('h:i A'),
                    'date'      => $entry->created_at->format('M j'),
                ])
                ->values();
        }

                // Who this visitor is here to see (from the pass's open registration).
        // Shows every congressman on the pass, plus any free-text "Other" office.
        $visiting = [];
        if ($pass && ($registration = $pass->openRegistration())) {
            $visiting = $registration->congressmen()
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => ['name' => $c->name, 'room' => $c->room])
                ->all();

            if ($registration->office_other) {
                $visiting[] = ['name' => $registration->office_other, 'room' => null];
            }
        }

        return response()->json([
            'result' => $result,
            'reason' => $reason,
            'visitor_name' => $visitorName,
            'pass_number' => $passNumber,
            'authorized_building' => $authorizedBuildingName,
            'scanned_building' => $scannerBuilding->name,
            'color_hex' => $colorHex,
            'photo_url' => $photoUrl,
            'id_photo_url' => $idPhotoUrl,
            'timestamp' => $log->created_at->format('h:i:s A'),
            'pass_class' => $pass?->pass_class,
            'days_remaining' => $pass?->daysRemaining(),
            'stale_notice' => $staleNotice,
            'direction' => $direction,
            'recent_activity' => $recentActivity,
            'visiting' => $visiting,
        ]);
    }
}