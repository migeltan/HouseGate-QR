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
        <span class="gov-location-badge h-11">
            <i class="fa-solid fa-building-circle-check"></i> {{ $buildings->first()->name ?? '—' }}
        </span>
    @else
        <div class="gov-location-wrap w-full">
            <select name="reg_building" onchange="this.form.submit()" class="gov-location-badge w-full h-11">
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
        <select name="reg_status" onchange="this.form.submit()" class="gov-location-badge w-full h-11">
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
        <div class="overflow-hidden rounded-xl border border-slate-400">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] border-collapse text-center text-xs">
                    <thead class="bg-slate-400 text-slate-800 uppercase tracking-wide">
                        <tr>
                            <th class="py-3 px-4 font-bold">Photos</th>
                            <th class="py-3 px-4 font-bold">Registered at</th>
                            <th class="py-3 px-4 font-bold">Visitor</th>
                            <th class="py-3 px-4 font-bold">ID type / ref</th>
                            <th class="py-3 px-4 font-bold">Pass class</th>
                            <th class="py-3 px-4 font-bold">Expected return</th>
                            <th class="py-3 px-4 font-bold">Registered by</th>
                            <th class="py-3 px-4 font-bold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($registrations as $r)
                            @php
                                $regStatusLabel = ! $r->unassigned_at
                                    ? 'Currently assigned'
                                    : ($r->unassign_reason === 'auto_expired' ? 'Auto-expired' : ($r->unassign_reason === 'renewed' ? 'Renewed' : 'Returned'));
                                $regPassClassLabel = $r->pass_class === 'long_term' ? 'Long-term' : 'Day';
                            @endphp
                            <tr class="cursor-pointer hover:bg-slate-50"
                                data-type="registration"
                                data-timestamp="{{ $r->registered_at->format('Y-m-d h:i:s A') }}"
                                data-visitor="{{ $r->visitor_name }}"
                                data-id-ref="{{ $r->id_type }} · {{ $r->id_ref }}"
                                data-pass-class="{{ $regPassClassLabel }}"
                                data-expected-return="{{ $r->expected_return_date?->format('Y-m-d') ?? '—' }}"
                                data-registered-by="{{ $r->registered_by ?? '—' }}"
                                data-status="{{ $regStatusLabel }}"
                                @if ($r->photo_path) data-photo="{{ asset('storage/' . $r->photo_path) }}" @endif
                                @if ($r->id_photo_path) data-id-photo="{{ asset('storage/' . $r->id_photo_path) }}" @endif
                                onclick="openRowModal(this)">
                                <td class="border-r border-slate-100 py-3 px-4">
                                    <div class="flex justify-center gap-1.5">
                                        @if ($r->photo_path)
                                            <img src="{{ asset('storage/' . $r->photo_path) }}" alt="Visitor photo" class="h-9 w-9 rounded-md object-cover ring-1 ring-slate-200">
                                        @endif
                                        @if ($r->id_photo_path)
                                            <img src="{{ asset('storage/' . $r->id_photo_path) }}" alt="ID photo" class="h-9 w-9 rounded-md object-cover ring-1 ring-slate-200">
                                        @endif
                                    </div>
                                </td>
                                <td class="border-r border-slate-100 py-3 px-4 font-mono text-slate-500 whitespace-nowrap">{{ $r->registered_at->format('Y-m-d h:i:s A') }}</td>
                                <td class="border-r border-slate-100 py-3 px-4 font-semibold text-slate-900 whitespace-nowrap">{{ $r->visitor_name }}</td>
                                <td class="border-r border-slate-100 py-3 px-4 text-slate-700 whitespace-nowrap">{{ $r->id_type }} &middot; {{ $r->id_ref }}</td>
                                <td class="border-r border-slate-100 py-3 px-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold bg-emerald-100 text-emerald-700">{{ $regPassClassLabel }}</span>
                                </td>
                                <td class="border-r border-slate-100 py-3 px-4 text-slate-700 whitespace-nowrap">{{ $r->expected_return_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="border-r border-slate-100 py-3 px-4 text-slate-700 whitespace-nowrap">{{ $r->registered_by ?? '—' }}</td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold {{ ! $r->unassigned_at ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $regStatusLabel }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-10 text-center text-slate-400">No pass registrations recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Footer: page count + export — no Delete/Purge here on purpose --}}
    <div class="flex flex-col gap-3 bg-white px-10 pb-5 sm:flex-row sm:items-center sm:justify-between">
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
                           class="grid h-7 w-7 place-items-center rounded-md text-sm font-medium {{ $page == $registrations->currentPage() ? 'bg-blue-600 text-white' : 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-100' }}">
                            {{ $page }}
                        </a>
                    @endforeach

                    <a href="{{ $registrations->nextPageUrl() ?? '#' }}"
                       class="grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ !$registrations->hasMorePages() ? 'pointer-events-none opacity-40' : '' }}"
                       aria-label="Next page">&rsaquo;</a>
                </nav>
            @endif

            <a href="{{ route('logs.registrations.export', request()->query()) }}"
                class="inline-flex items-center gap-2 rounded-[8px] bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 transition-colors">
                <i class="fa-solid fa-file-csv text-sm"></i> Export CSV
            </a>
        </div>
    </div>
</div>
{{-- ===================== END PASS REGISTRATION RECORDS PANE ===================== --}}
