<?php

namespace App\Http\Controllers;

use App\Models\Building; // ASSUMPTION: adjust to your actual Building model namespace/path
use App\Models\ScanLog;
use App\Models\PassRegistration;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $buildings = $user->isGuard()
            ? Building::where('id', session('assigned_building_id'))->get()
            : Building::where('code', '!=', 'NG')->orderBy('name')->get();

        $logs = $this->applyFilters($this->scopedLogsQuery($request), $request)
            ->latest()->paginate(25)->withQueryString();

        $registrations = $this->applyRegistrationFilters($this->scopedRegistrationsQuery($request), $request)
            ->latest('registered_at')->paginate(25, ['*'], 'reg_page')->withQueryString();

        return view('logs.index', compact('logs', 'buildings', 'registrations'));
    }

    public function export(Request $request): StreamedResponse
    {
        $logs = $this->applyFilters($this->scopedLogsQuery($request), $request)
            ->latest()
            ->get();

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Timestamp', 'Visitor Name', 'Contact Person', 'Pass Number','Authorized Building', 'Scanned Building', 'Result', 'Reason']);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $this->csvSafe($log->visitor_name_snapshot),
                    $this->csvSafe($log->contact_person_snapshot),
                    $log->pass_number_snapshot,
                    $log->authorized_building_snapshot,
                    $log->scannedBuilding->name ?? '',
                    $log->result,
                    $log->reason,
                ]);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, 'scan_logs_' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * CSV export for the Pass Registration Records sub-tab — separate file
     * from export() above so the two audit trails never mix in one download.
     */
    public function exportRegistrations(Request $request): StreamedResponse
    {
        $registrations = $this->applyRegistrationFilters($this->scopedRegistrationsQuery($request), $request)
            ->latest('registered_at')
            ->get();

        $callback = function () use ($registrations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Registered At', 'Visitor Name', 'Buildings to Visit', 'Congressman / Office', 'Contact Person', 'ID Type', 'ID Ref', 'Pass Class', 'Expected Return', 'Registered By', 'Unassigned At', 'Reason']);
            foreach ($registrations as $r) {
                fputcsv($handle, [
                    $r->registered_at->format('Y-m-d H:i:s'),
                    $this->csvSafe($r->visitor_name),
                    $this->csvSafe($r->buildings_snapshot),
                    $this->csvSafe($r->office_to_visit),
                    $this->csvSafe($r->contact_person),
                    $r->id_type,
                    $r->id_ref,
                    $r->pass_class,
                    $r->expected_return_date?->format('Y-m-d'),
                    $r->registered_by,
                    $r->unassigned_at?->format('Y-m-d H:i:s'),
                    $r->unassign_reason,
                ]);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, 'pass_registrations_' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

        /**
     * Stops spreadsheet formula injection: a cell starting with = + - @ (typed by a
     * guard or copied from an ID) would otherwise be executed when the CSV is opened.
     */
    private function csvSafe(?string $value): ?string
    {
        return ($value !== null && preg_match('/^[=+\-@\t\r]/', $value)) ? "'" . $value : $value;
    }

    /**
     * Option A — delete logs
    public function purgeRange(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $count = ScanLog::whereBetween('created_at', [
            $validated['start_date'] . ' 00:00:00',
            $validated['end_date'] . ' 23:59:59',
        ])->delete();

        return redirect()->route('logs.index')
            ->with('success', "Purged {$count} log(s) between {$validated['start_date']} and {$validated['end_date']}.");
    }

    /**
     * Option B — delete every log. Requires the literal string "PURGE" typed by the user.
     * Uses delete() rather than truncate() to respect FK constraints on scanned_building_id.
     * Reachable only by admin — locked out via the 'admin' route middleware.
     */
    public function purgeAll(Request $request)
    {
        $request->validate([
            'confirm' => 'required|string',
        ]);

        if (strtoupper(trim($request->confirm)) !== 'PURGE') {
            return redirect()->route('logs.index')
                ->with('error', 'Confirmation keyword did not match. No logs were deleted.');
        }

        $count = ScanLog::query()->count();
        ScanLog::query()->delete();

        return redirect()->route('logs.index')
            ->with('success', "All {$count} log(s) were permanently deleted.");
    }

    /**
     * Base scan-log query, scoped to the guard's locked building — used by
     * index() AND export() so the CSV can never show more than the screen
     * does. Admin gets everything, unscoped.
     */
    private function scopedLogsQuery(Request $request): Builder
    {
        $query = ScanLog::with(['scannedBuilding', 'verificationPhoto']);

        if ($request->user()->isGuard()) {
            $query->where('scanned_building_id', session('assigned_building_id'));
        }

        return $query;
    }

    /**
     * Same idea, for pass_registrations — used by index() AND
     * exportRegistrations().
     */
    private function scopedRegistrationsQuery(Request $request): Builder
    {
        $query = PassRegistration::with('visitorPass.building');

        if ($request->user()->isGuard()) {
            $query->whereHas('visitorPass', fn ($q) => $q->where('building_id', session('assigned_building_id')));
        }

        return $query;
    }

    /**
     * Shared search/result/building filtering used by both index() and export(),
     * so the CSV always matches what's on screen.
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('visitor_name_snapshot', 'like', "%{$s}%")
                  ->orWhere('pass_number_snapshot', 'like', "%{$s}%")
                  ->orWhere('authorized_building_snapshot', 'like', "%{$s}%");
            });
        }

        if ($request->filled('result') && $request->result !== 'ALL') {
            $query->where('result', $request->result);
        }

        if ($request->filled('building') && $request->building !== 'ALL') {
            $query->where('scanned_building_id', $request->building);
        }

        return $query;
    }

    /**
     * Same idea as applyFilters(), but for the separate Pass Registration
     * Records sub-tab — deliberately no purge/delete method for this one,
     * since pass_registrations is meant to be append-only.
     */
    private function applyRegistrationFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('reg_search')) {
            $s = $request->reg_search;
            $query->where(function ($q) use ($s) {
                $q->where('visitor_name', 'like', "%{$s}%")
                ->orWhere('id_ref', 'like', "%{$s}%")
                ->orWhere('office_to_visit', 'like', "%{$s}%")
                ->orWhere('contact_person', 'like', "%{$s}%")
                ->orWhere('buildings_snapshot', 'like', "%{$s}%");
            });
        }

        if ($request->filled('reg_status') && $request->reg_status !== 'ALL') {
            $request->reg_status === 'open'
                ? $query->whereNull('unassigned_at')
                : $query->whereNotNull('unassigned_at');
        }

        if ($request->filled('reg_building') && $request->reg_building !== 'ALL') {
            $query->whereHas('visitorPass', fn ($q) => $q->where('building_id', $request->reg_building));
        }

        return $query;
    }
}