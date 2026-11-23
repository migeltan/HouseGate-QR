<div class="overflow-hidden rounded-xl border border-slate-300">
    <div class="max-h-[440px] overflow-auto">
        <table class="w-full min-w-[800px] border-collapse text-left text-xs">
            <thead class="uppercase tracking-wide text-slate-600 [&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:bg-slate-100 [&_th]:shadow-[inset_0_-1px_0_#cbd5e1]">
                <tr>
                    <th class="py-3 px-4 font-bold">When</th>
                    <th class="py-3 px-4 font-bold">By</th>
                    <th class="py-3 px-4 font-bold">Action</th>
                    <th class="py-3 px-4 font-bold">Subject</th>
                    <th class="py-3 px-4 font-bold">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $l)
                    @php
                        $badge = match (\Illuminate\Support\Str::before($l->action, '.')) {
                            'pass' => 'bg-blue-100 text-blue-700',
                            'log' => 'bg-red-100 text-red-700',
                            'congressman' => 'bg-indigo-100 text-indigo-700',
                            default => 'bg-slate-100 text-slate-700',
                        };
                    @endphp
                    <tr class="transition-colors duration-150 hover:bg-blue-50/60">
                        <td class="py-3 px-4 font-mono text-slate-500 whitespace-nowrap">{{ $l->created_at->format('Y-m-d h:i:s A') }}</td>
                        <td class="py-3 px-4 font-semibold text-slate-900 whitespace-nowrap">{{ $l->actor_name }}</td>
                        <td class="py-3 px-4 whitespace-nowrap"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge }}">{{ ucfirst(str_replace(['.', '_'], ' ', $l->action)) }}</span></td>
                        <td class="py-3 px-4 text-slate-700 max-w-[220px] truncate" title="{{ $l->subject }}">{{ $l->subject ?? '—' }}</td>
                        <td class="py-3 px-4 text-slate-600 max-w-[320px] truncate" title="{{ $l->details }}">{{ $l->details ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-14 text-center">
                        <i class="fa-solid fa-list-check text-2xl text-slate-300"></i>
                        <p class="mt-2 text-sm font-semibold text-slate-600">No log entries found</p>
                        <p class="text-xs text-slate-400">Try adjusting your search or filter.</p>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 flex items-center justify-between">
    <p class="text-sm text-slate-500">{{ $logs->total() ? $logs->total() . ' entries · page ' . $logs->currentPage() . ' of ' . $logs->lastPage() : 'No results' }}</p>
    @if ($logs->hasPages())
        <div class="flex gap-1">
            <a href="{{ $logs->previousPageUrl() ?? '#' }}" class="js-log-page grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ $logs->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Previous page">&lsaquo;</a>
            <a href="{{ $logs->nextPageUrl() ?? '#' }}" class="js-log-page grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ ! $logs->hasMorePages() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Next page">&rsaquo;</a>
        </div>
    @endif
</div>