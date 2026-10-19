{{-- ======================= PASS REGISTRATION RECORDS PANE ======================= --}}
<form id="registrationsPane" method="GET" class="records-pane hidden">

{{-- ======================================================================== --}}
{{-- =====>>> FILTERS ===== --}}
{{-- ======================================================================== --}}
    <div class="flex flex-col gap-3  border-b border-slate-300 mb-[15.5px] px-10 py-6 sm:flex-row sm:items-center sm:justify-end">

        <div class="flex w-full h-auto [&>div]:px-2">

            {{-- ============ --}}
            {{-- INPUT FIELDS --}}
            {{-- ============ --}}
            <div class="w-[50%] h-auto flex ">
    <div class="relative w-full">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
       <input type="text" id="regSearchInput" name="reg_search" value="{{ request('reg_search') }}"
           placeholder="Search by visitor name or ID #..." autocomplete="off"
              class="w-full h-11 rounded-[8.1px] border border-slate-400 bg-slate-50 pl-9 pr-3 text-xs focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
    </div>
</div>

<div class="w-full sm:w-[25%] min-w-[160px] h-auto flex items-center">
    @if (auth()->user()->isGuard())
        <span class="gov-location-badge gov-location-badge--locked h-11" title="Your assigned building">
            <i class="fa-solid fa-building-circle-check"></i> {{ $buildings->first()->name ?? '—' }}
        </span>
    @else
        <div class="gov-location-wrap w-full">
            <select name="reg_building" onchange="applyRecordsFilter()"class="gov-location-badge w-full h-11">
                <option value="ALL">Building</option>
                @foreach ($buildings as $b)
                    <option value="{{ $b->id }}" {{ (string) request('reg_building') === (string) $b->id ? 'selected' : '' }}>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>
            <i class="fa-solid fa-chevron-down gov-location-chevron" aria-hidden="true"></i>
        </div>
    @endif
</div>
{{-- ========================= --}}
{{-- STATUS DROPDOWN OPTIONS --}}
{{-- ========================= --}}
<div class="w-full sm:w-[25%] min-w-[160px] h-auto flex items-center">
    <div class="gov-location-wrap w-full">
        <select name="reg_status" onchange="applyRecordsFilter()" class="gov-location-badge w-full h-11">
            <option value="ALL">Status</option>
            <option value="open" {{ request('reg_status') === 'open' ? 'selected' : '' }}>Currently assigned</option>
            <option value="closed" {{ request('reg_status') === 'closed' ? 'selected' : '' }}>Returned / expired</option>
        </select>
        <i class="fa-solid fa-chevron-down gov-location-chevron" aria-hidden="true"></i>
    </div>
</div>

        </div>

    </div>

{{-- ======================================================================== --}}
{{-- =====>>> TABLE  ===== --}}
{{-- ======================================================================== --}}
    <div class="px-10 pb-5">
        <div class="overflow-hidden rounded-xl border border-slate-300">
            <div class="max-h-[440px] overflow-auto">
                <table class="w-full border-collapse text-left text-xs" style="min-width: 1080px;">
                    <thead class="uppercase tracking-wide text-slate-600 [&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:bg-slate-100 [&_th]:shadow-[inset_0_-1px_0_#cbd5e1]">
                        <tr>
                            <th class="py-3 px-4 font-bold text-center">Photos</th>
                            <th class="py-3 px-4 font-bold">Registered at</th>
                            <th class="py-3 px-4 font-bold">Visitor</th>
                            <th class="py-3 px-4 font-bold text-center">Buildings</th>
                            <th class="py-3 px-4 font-bold">Congressman / Office</th>
                            <th class="py-3 px-4 font-bold">ID type / ref</th>
                            <th class="py-3 px-4 font-bold text-center">Expected return</th>
                            <th class="py-3 px-4 font-bold text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($registrations as $r)
                            @php
                                $regStatusLabel = ! $r->unassigned_at
                                    ? 'Currently assigned'
                                    : match ($r->unassign_reason) {
                                        'auto_expired' => 'Auto-expired',
                                        'renewed' => 'Renewed',
                                        'transferred' => 'Transferred',
                                        default => 'Returned',
                                    };
                                $regPassClassLabel = $r->pass_class === 'long_term' ? 'Long-term' : 'Day';
                                $trail = $trails[$r->id] ?? null;
                                // Files are deleted when a card is returned; only show photos that still exist.
                                $regDisk = \Illuminate\Support\Facades\Storage::disk('public');
                                $regPhotoOk = $r->photo_path && $regDisk->exists($r->photo_path);
                                $regIdPhotoOk = $r->id_photo_path && $regDisk->exists($r->id_photo_path);
                                $regPhotoGone = ($r->photo_path && ! $regPhotoOk) || ($r->id_photo_path && ! $regIdPhotoOk);
                                // Day passes store no return date: they expire at the daily cutoff after issue.
                                if ($r->expected_return_date) {
                                    $regReturnLabel = $r->expected_return_date->format('M j, Y');
                                } elseif ($r->pass_class === 'long_term') {
                                    $regReturnLabel = '—';
                                } else {
                                    $regCutoff = $r->registered_at->copy()->setTimeFromTimeString(\App\Models\VisitorPass::DAY_PASS_CUTOFF);
                                    if ($regCutoff->lte($r->registered_at)) { $regCutoff->addDay(); }
                                    $regReturnLabel = $regCutoff->format('M j, g:i A');
                                }
                            @endphp
                            <tr class="cursor-pointer transition-colors duration-150 hover:bg-blue-50/60"
                                data-type="registration"
                                data-timestamp="{{ $r->registered_at->format('Y-m-d h:i:s A') }}"
                                data-visitor="{{ $r->visitor_name }}"
                                data-buildings="{{ $r->buildings_snapshot ?? '—' }}"
                                data-office="{{ $r->office_to_visit ?? '—' }}"
                                data-contact-person="{{ $r->contact_person ?? '—' }}"
                                data-id-ref="{{ $r->id_type }} · {{ $r->id_ref }}"
                                data-pass-class="{{ $regPassClassLabel }}"
                                data-expected-return="{{ $regReturnLabel }}"
                                data-registered-by="{{ $r->registered_by ?? '—' }}"
                                data-status="{{ $regStatusLabel }}"
                                @if ($trail) data-trail="{{ json_encode($trail) }}" @endif
                                @if ($regPhotoOk) data-photo="{{ asset('storage/' . $r->photo_path) }}" @endif
                                @if ($regIdPhotoOk) data-id-photo="{{ asset('storage/' . $r->id_photo_path) }}" @endif
                                onclick="openRowModal(this)">
                                {{-- Photos --}}
                                <td class="py-3 px-4">
                                    <div class="flex items-center justify-center gap-1.5">
                                        @if ($regPhotoOk)
                                            <img src="{{ asset('storage/' . $r->photo_path) }}" alt="Visitor photo" class="h-9 w-9 rounded-md object-cover ring-1 ring-slate-200">
                                        @endif
                                        @if ($regIdPhotoOk)
                                            <img src="{{ asset('storage/' . $r->id_photo_path) }}" alt="ID photo" class="h-9 w-9 rounded-md object-cover ring-1 ring-slate-200">
                                        @endif
                                        @if (! $regPhotoOk && ! $regIdPhotoOk)
                                            @if ($regPhotoGone)
                                                <span class="text-[11px] text-slate-400" title="Photos are deleted when the card is returned">Removed</span>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                                {{-- Registered at + who registered --}}
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="reg-cell-stack">
                                        <span class="font-mono text-slate-700">{{ $r->registered_at->format('Y-m-d h:i:s A') }}</span>
                                        <span class="reg-sub">by {{ $r->registered_by ?? '—' }}</span>
                                    </div>
                                </td>
                                {{-- Visitor + pass class --}}
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="reg-cell-stack">
                                        <span class="font-semibold text-slate-900">{{ $r->visitor_name }}</span>
                                        <span class="reg-pill {{ $r->pass_class === 'long_term' ? 'reg-pill-long' : 'reg-pill-day' }}">{{ $regPassClassLabel }}</span>
                                    </div>
                                </td>
                                {{-- Buildings --}}
                                <td class="py-3 px-4 text-center text-slate-700">
                                    {{ $r->buildings_snapshot ?? '—' }}
                                    @if ($trail && $trail['from'])
                                        <div class="reg-sub">← from {{ $trail['from'] }}</div>
                                    @endif
                                </td>
                                {{-- Office + contact person --}}
                                <td class="py-3 px-4 text-slate-700" style="max-width: 300px;">
                                    <div class="reg-cell-stack">
                                        <span class="reg-clamp" title="{{ $r->office_to_visit }}">{{ $r->office_to_visit ?? '—' }}</span>
                                        @if ($r->contact_person)
                                            <span class="reg-sub"><i class="fa-solid fa-user"></i> {{ $r->contact_person }}</span>
                                        @endif
                                    </div>
                                </td>
                                {{-- ID type + reference --}}
                                <td class="py-3 px-4 whitespace-nowrap text-slate-700">
                                    <div class="reg-cell-stack">
                                        <span>{{ $r->id_type }}</span>
                                        <span class="reg-sub font-mono">{{ $r->id_ref }}</span>
                                    </div>
                                </td>
                                {{-- Expected return --}}
                                <td class="py-3 px-4 text-center whitespace-nowrap text-slate-700">{{ $regReturnLabel }}</td>
                                {{-- Status --}}
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold {{ ! $r->unassigned_at ? 'bg-emerald-100 text-emerald-700' : ($r->unassign_reason === 'transferred' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600') }}">
                                        {{ $regStatusLabel }}
                                    </span>
                                    @if ($trail && $trail['to'])
                                        <div class="reg-sub">→ {{ $trail['to'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-14 text-center">
                                <i class="fa-solid fa-id-card text-2xl text-slate-300"></i>
                                <p class="mt-2 text-sm font-semibold text-slate-600">No registrations found</p>
                                <p class="text-xs text-slate-400">Try adjusting your search or filters.</p>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Footer: page count + export — no Delete/Purge here on purpose --}}
<div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/60 px-10 py-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500">
            @if ($registrations->total() > 0)
                Showing page {{ $registrations->currentPage() }} out of {{ $registrations->lastPage() }}
            @else
                No results
            @endif
        </p>

        <div class="flex items-center gap-3">
            @if ($registrations->hasPages())
                <nav class="flex items-center gap-1" aria-label="Registration pagination">
                    <a href="{{ $registrations->previousPageUrl() ?? '#' }}"
                       class="grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ $registrations->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}"
                       aria-label="Previous page">&lsaquo;</a>

                    @foreach ($registrations->getUrlRange(1, $registrations->lastPage()) as $page => $url)
                        <a href="{{ $url }}"
                           class="grid h-7 w-7 place-items-center rounded-md text-sm font-medium {{ $page == $registrations->currentPage() ? 'bg-[#235aa6] text-white' : 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-100' }}">
                            {{ $page }}
                        </a>
                    @endforeach

                    <a href="{{ $registrations->nextPageUrl() ?? '#' }}"
                       class="grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ !$registrations->hasMorePages() ? 'pointer-events-none opacity-40' : '' }}"
                       aria-label="Next page">&rsaquo;</a>
                </nav>
            @endif

            <a href="{{ route('logs.registrations.export', request()->query()) }}" class="gov-btn-glass-outline">
                <i class="fa-solid fa-file-csv"></i> Export CSV
            </a>
        </div>
    </div>
</form>
{{-- ===================== END PASS REGISTRATION RECORDS PANE ===================== --}}
