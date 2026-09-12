{{-- ============================= SCANS PANE ============================= --}}
    <div id="scansPane" class="records-pane">


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
                            placeholder="Search by name, pass #, etc..." autocomplete="off"
                            class="w-full rounded-[8.1px] border border-slate-400 bg-slate-50 py-3  .5 pl-9 pr-3 text-xs focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                    </div>
                </div>

                {{-- ========================= --}}
                {{-- BUILDING DROPDOWN OPTIONS --}}
                {{-- ========================= --}}
                <div class="w-full sm:w-[25%] min-w-[160px] h-auto flex">
                    <div class="relative w-full">
                        <select name="building" onchange="this.form.submit()"
                            class="peer relative w-full appearance-none rounded-lg bg-blue-600 py-2.5 pl-9 pr-4 text-sm sm:text-base font-semibold text-transparent shadow-sm outline-none transition-colors duration-150 hover:bg-blue-500 focus:bg-blue-700 focus:ring-2 focus:ring-blue-500/40 focus:ring-offset-1">
                            <option value="ALL" class="bg-white text-slate-900 font-medium text-left">Building</option>
                            @foreach ($buildings as $b)
                                <option value="{{ $b->id }}" class="bg-white text-slate-900 font-medium text-left" {{ (string) request('building') === (string) $b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>

                        {{-- Centered label overlay --}}
                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm sm:text-base font-semibold text-white">
                            {{ $buildings->firstWhere('id', request('building'))->name ?? 'Building' }}
                        </span>

                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 transition-transform duration-150 peer-focus:rotate-180">
                            <span class="block w-0 h-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[7px] border-t-white"></span>
                        </span>
                    </div>
                </div>

                {{-- ========================= --}}
                {{-- RESULTS DROPDOWN OPTIONS --}}
                {{-- ========================= --}}
                <div class="w-full sm:w-[25%] min-w-[160px] h-auto flex">
                    <div class="relative w-full">
                        <select name="result" onchange="this.form.submit()"
                            class="peer relative w-full appearance-none rounded-lg bg-blue-600 py-2.5 pl-9 pr-4 text-sm sm:text-base font-semibold text-transparent shadow-sm outline-none transition-colors duration-150 hover:bg-blue-500 focus:bg-blue-700 focus:ring-2 focus:ring-blue-500/40 focus:ring-offset-1">
                            <option value="ALL" class="bg-white text-slate-900 font-medium text-left">Results</option>
                            <option value="AUTHORIZED" class="bg-white text-slate-900 font-medium text-left" {{ request('result') === 'AUTHORIZED' ? 'selected' : '' }}>Authorized</option>
                            <option value="UNAUTHORIZED" class="bg-white text-slate-900 font-medium text-left" {{ request('result') === 'UNAUTHORIZED' ? 'selected' : '' }}>Unauthorized</option>
                            <option value="INVALID" class="bg-white text-slate-900 font-medium text-left" {{ request('result') === 'INVALID' ? 'selected' : '' }}>Invalid</option>
                        </select>

                        {{-- Centered label overlay --}}
                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm sm:text-base font-semibold text-white">
                            @php
                                $resultLabels = [
                                    'AUTHORIZED' => 'Authorized',
                                    'UNAUTHORIZED' => 'Unauthorized',
                                    'INVALID' => 'Invalid',
                                ];
                            @endphp
                            {{ $resultLabels[request('result')] ?? 'Results' }}
                        </span>

                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 transition-transform duration-150 peer-focus:rotate-180">
                            <span class="block w-0 h-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[7px] border-t-white"></span>
                        </span>
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
                    <table class="w-full min-w-[800.1px] border-collapse text-center text-xs">
                        <thead class="bg-slate-400 text-slate-800 uppercase tracking-wide">
                            <tr>
                                <th class="py-3 px-4 font-bold">Timestamp</th>
                                <th class="py-3 px-4 font-bold">Visitor</th>
                                <th class="py-3 px-4 font-bold">Pass #</th>
                                <th class="py-3 px-4 font-bold">Scanned at</th>
                                <th class="py-3 px-4 font-bold">Result</th>
                                <th class="py-3 px-4 font-bold">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($logs as $l)
                                <tr class="cursor-pointer hover:bg-slate-50"
                                    data-type="scan"
                                    data-timestamp="{{ $l->created_at->format('Y-m-d h:i:s A') }}"
                                    data-visitor="{{ $l->visitor_name_snapshot }}"
                                    data-pass-number="{{ $l->pass_number_snapshot }}"
                                    data-scanned-at="{{ $l->scannedBuilding->name ?? '' }}"
                                    data-result="{{ ucfirst(strtolower($l->result)) }}"
                                    data-reason="{{ $l->reason }}"
                                    onclick="openRowModal(this)">
                                    <td class="border-r border-slate-100 py-3 px-4 font-mono text-slate-500 whitespace-nowrap">{{ $l->created_at->format('Y-m-d h:i:s A') }}</td>
                                    <td class="border-r border-slate-100 py-3 px-4 font-semibold text-slate-900 whitespace-nowrap">{{ $l->visitor_name_snapshot }}</td>
                                    <td class="border-r border-slate-100 py-3 px-4 font-mono font-semibold text-slate-800 whitespace-nowrap">{{ $l->pass_number_snapshot }}</td>
                                    <td class="border-r border-slate-100 py-3 px-4 text-slate-700 whitespace-nowrap">{{ $l->scannedBuilding->name ?? '' }}</td>
                                    <td class="border-r border-slate-100 py-3 px-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold
                                            {{ $l->result === 'AUTHORIZED' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                            {{ ucfirst(strtolower($l->result)) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600 truncate max-w-xs" title="{{ $l->reason }}">{{ $l->reason }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-10 text-center text-slate-400">No scan events recorded yet.</td></tr>
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

            <a href="{{ route('logs.export', request()->query()) }}"
                class="inline-flex items-center gap-2 rounded-[7px] bg-emerald-700 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-800 transition-colors">
                    <img src="{{ asset('images/icons/Policy.svg') }}" alt="" class="h-3.5 w-3.5"> Export CSV
                </a>
                @if (auth()->user()->isAdmin())
                    <button type="button" onclick="openDeleteModal()"
                            class="inline-flex items-center gap-2 rounded-[7px] bg-red-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 transition-colors">
                        <img src="{{ asset('images/icons/Policy.svg') }}" alt="" class="h-3.5 w-3.5"> Delete Logs
                    </button>
                @endif
            </div>
        </div>
    </div>
    {{-- =========================== END SCANS PANE ============================ --}}
