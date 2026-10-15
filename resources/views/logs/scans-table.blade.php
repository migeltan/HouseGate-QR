{{-- ============================= SCANS PANE ============================= --}}
    <form id="scansPane" class="records-pane" data-live-sig="{{ $liveSig ?? '' }}">


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
                <input type="text" id="logSearchInput" name="search" value="{{ request('search') }}"
                    placeholder="Search by name, contact person, pass #..." autocomplete="off"
                    class="w-full h-11 rounded-[8.1px] border border-slate-400 bg-slate-50 pl-9 pr-3 text-xs focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                    </div>
                </div>

                {{-- ========================= --}}
                {{-- BUILDING FILTER --}}
                {{-- ========================= --}}
                <div class="w-full sm:w-[25%] min-w-[160px] h-auto flex items-center">
                    @if (auth()->user()->isGuard())
<span class="gov-location-badge gov-location-badge--locked h-11" title="Your assigned building">
                            <i class="fa-solid fa-building-circle-check"></i> {{ $buildings->first()->name ?? '—' }}
                        </span>
                    @else
                        <div class="gov-location-wrap w-full">
                            <select name="building" onchange="applyRecordsFilter()" class="gov-location-badge w-full h-11">
                               <option value="ALL">Building</option>
                                @foreach ($buildings as $b)
                                    <option value="{{ $b->id }}" {{ (string) request('building') === (string) $b->id ? 'selected' : '' }}>
                                        {{ $b->name }}
                                    </option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down gov-location-chevron" aria-hidden="true"></i>
                        </div>
                    @endif
                </div>

                {{-- ========================= --}}
                {{-- RESULTS DROPDOWN OPTIONS --}}
                {{-- ========================= --}}
                <div class="w-full sm:w-[25%] min-w-[160px] h-auto flex items-center">
                <div class="gov-location-wrap w-full">
                   <select name="result" onchange="applyRecordsFilter()" class="gov-location-badge w-full h-11">
                        <option value="ALL">Results</option>
                        <option value="AUTHORIZED" {{ request('result') === 'AUTHORIZED' ? 'selected' : '' }}>Authorized</option>
                        <option value="UNAUTHORIZED" {{ request('result') === 'UNAUTHORIZED' ? 'selected' : '' }}>Unauthorized</option>
                        <option value="INVALID" {{ request('result') === 'INVALID' ? 'selected' : '' }}>Invalid</option>
                        <option value="UNASSIGNED" {{ request('result') === 'UNASSIGNED' ? 'selected' : '' }}>Unassigned</option>
                        <option value="EXPIRED" {{ request('result') === 'EXPIRED' ? 'selected' : '' }}>Expired</option>
                        <option value="REVOKED" {{ request('result') === 'REVOKED' ? 'selected' : '' }}>Revoked</option>
                        <option value="BLOCKED" {{ request('result') === 'BLOCKED' ? 'selected' : '' }}>Blocked</option>
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
                    <table class="w-full border-collapse text-left text-xs" style="min-width: 960px;">
<thead class="uppercase tracking-wide text-slate-600 [&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:bg-slate-100 [&_th]:shadow-[inset_0_-1px_0_#cbd5e1]">
                            <tr>
                            <th class="py-3 px-4 font-bold text-center">Photo</th>
                            <th class="py-3 px-4 font-bold">Timestamp</th>
                            <th class="py-3 px-4 font-bold">Visitor</th>
                            <th class="py-3 px-4 font-bold">Pass # / Scanned at</th>
                            <th class="py-3 px-4 font-bold text-center">Result</th>
                            <th class="py-3 px-4 font-bold">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($logs as $l)
                            @php
                                // Photo files can be deleted later; only show the ones that still exist.
                                $scanPhotoPath = $l->verificationPhoto?->photo_path;
                                $scanPhotoOk = $scanPhotoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($scanPhotoPath);
                                $scanPhotoGone = $scanPhotoPath && ! $scanPhotoOk;
                            @endphp
                            <tr class="cursor-pointer transition-colors duration-150 hover:bg-blue-50/60" data-log-id="{{ $l->id }}"
                                    data-type="scan"
                                    data-timestamp="{{ $l->created_at->format('Y-m-d h:i:s A') }}"
                                    data-visitor="{{ $l->visitor_name_snapshot }}"
                                    data-contact-person="{{ $l->contact_person_snapshot ?? '—' }}"
                                    data-pass-number="{{ $l->pass_number_snapshot }}"
                                    data-scanned-at="{{ $l->scannedBuilding->name ?? '' }}"
                                    data-result="{{ ucfirst(strtolower($l->result)) }}"
                                    data-reason="{{ $l->reason }}"
                                    data-photo="{{ $scanPhotoOk ? asset('storage/' . $scanPhotoPath) : '' }}"
                                    onclick="openRowModal(this)">
                                    {{-- Photo --}}
                                    <td class="py-3 px-4">
                                        <div class="flex items-center justify-center">
                                            @if ($scanPhotoOk)
                                                <img src="{{ asset('storage/' . $scanPhotoPath) }}" alt="Verification photo" loading="lazy" class="h-9 w-9 rounded-md object-cover ring-1 ring-slate-200">
                                            @elseif ($scanPhotoGone)
                                                <span class="text-[11px] text-slate-400" title="Photo no longer on disk">Removed</span>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    {{-- Timestamp + direction --}}
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="reg-cell-stack">
                                            <span class="font-mono text-slate-700">{{ $l->created_at->format('Y-m-d h:i:s A') }}</span>
                                            @if ($l->direction === 'in')
                                                <span class="reg-sub"><i class="fa-solid fa-right-to-bracket"></i> Entry</span>
                                            @elseif ($l->direction === 'out')
                                                <span class="reg-sub"><i class="fa-solid fa-right-from-bracket"></i> Exit</span>
                                            @endif
                                        </div>
                                    </td>
                                    {{-- Visitor + contact person --}}
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="reg-cell-stack">
                                            <span class="font-semibold text-slate-900">{{ $l->visitor_name_snapshot ?: '—' }}</span>
                                            @if ($l->contact_person_snapshot)
                                                <span class="reg-sub"><i class="fa-solid fa-user"></i> {{ $l->contact_person_snapshot }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    {{-- Pass # + building scanned at --}}
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="reg-cell-stack">
                                            <span class="font-mono font-semibold text-slate-800">{{ $l->pass_number_snapshot ?: '—' }}</span>
                                            @if ($l->scannedBuilding)
                                                <span class="reg-sub">{{ $l->scannedBuilding->name }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    {{-- Result --}}
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold
                                            {{ match($l->result) {
                                                'AUTHORIZED' => 'bg-emerald-100 text-emerald-700',
                                                'UNASSIGNED' => 'bg-indigo-100 text-indigo-700',
                                                'EXPIRED', 'BLOCKED' => 'bg-amber-100 text-amber-700',
                                                'INVALID' => 'bg-slate-100 text-slate-600',
                                                default => 'bg-red-100 text-red-700',
                                            } }}">
                                            {{ ucfirst(strtolower($l->result)) }}
                                        </span>
                                    </td>
                                    {{-- Reason --}}
                                    <td class="py-3 px-4 text-slate-600" style="max-width: 320px;">
                                        <span class="reg-clamp" title="{{ $l->reason }}">{{ $l->reason }}</span>
                                    </td>
                                </tr>
                            @empty
<tr><td colspan="6" class="py-14 text-center">
    <i class="fa-solid fa-clipboard-list text-2xl text-slate-300"></i>
    <p class="mt-2 text-sm font-semibold text-slate-600">No scan events found</p>
    <p class="text-xs text-slate-400">Try adjusting your search or filters.</p>
</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        {{-- Footer: page count + export / purge --}}
        <div class="flex flex-col gap-3 bg-white px-10 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500">
                @if ($logs->total() > 0)
                    Showing page {{ $logs->currentPage() }} out of {{ $logs->lastPage() }}
                @else
                    No results
                @endif
            </p>

            <div class="flex items-center gap-3">
                @if ($logs->hasPages())
                    <nav class="flex items-center gap-1" aria-label="Log pagination">
                        <a href="{{ $logs->previousPageUrl() ?? '#' }}"
                        class="grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ $logs->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}"
                        aria-label="Previous page">&lsaquo;</a>

                        @foreach ($logs->getUrlRange(1, $logs->lastPage()) as $page => $url)
                            <a href="{{ $url }}"
                            class="grid h-7 w-7 place-items-center rounded-md text-sm font-medium {{ $page == $logs->currentPage() ? 'bg-blue-600 text-white' : 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-100' }}">
                                {{ $page }}
                            </a>
                        @endforeach

                        <a href="{{ $logs->nextPageUrl() ?? '#' }}"
                        class="grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ !$logs->hasMorePages() ? 'pointer-events-none opacity-40' : '' }}"
                        aria-label="Next page">&rsaquo;</a>
                    </nav>
                @endif

                <a href="{{ route('logs.export', request()->query()) }}" class="gov-btn-glass-outline">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </a>
                @if (auth()->user()->isAdmin())
                    <span class="mx-1 hidden h-6 w-px bg-slate-300 sm:block" aria-hidden="true"></span>
                    <button type="button" onclick="openDeleteModal()"
                            class="inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-4 py-[0.45rem] text-[0.8rem] font-semibold text-red-700 transition-colors hover:border-red-600 hover:bg-red-600 hover:text-white">
                        <i class="fa-solid fa-trash-can"></i> Delete Logs
                    </button>
                @endif
            </div>
        </div>
    </form>
    {{-- =========================== END SCANS PANE ============================ --}}