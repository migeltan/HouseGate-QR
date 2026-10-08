<div class="overflow-hidden rounded-xl border border-slate-300">
    <div class="max-h-[440px] overflow-auto">
        <table class="w-full min-w-[820px] border-collapse text-left text-xs">
            <thead class="uppercase tracking-wide text-slate-600 [&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:bg-slate-100 [&_th]:shadow-[inset_0_-1px_0_#cbd5e1]">
                <tr>
                    <th class="py-3 px-4 font-bold">Member</th>
                    <th class="py-3 px-4 font-bold">Type</th>
                    <th class="py-3 px-4 font-bold">Building</th>
                    <th class="py-3 px-4 font-bold">Floor</th>
                    <th class="py-3 px-4 font-bold">Room</th>
                    @if ($isAdmin)
                        <th class="py-3 px-4 font-bold">Status</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($members as $m)
                    @php
                        $payload = [
                            'member_id' => $m->member_id,
                            'name' => $m->name,
                            'type' => $m->rep_type,
                            'detail' => $m->rep_detail,
                            'building' => $m->building->name ?? null,
                            'color' => $m->building->color_hex ?? '#94a3b8',
                            'floor' => $m->floor,
                            'room' => $m->room,
                            'photo' => $m->photo_url,
                            'building_id' => $m->building_id,
                            'active' => $m->is_active,
                            'urls' => $isAdmin ? [
                                'update' => route('congressmen.update', $m),
                                'deactivate' => route('congressmen.deactivate', $m),
                                'reactivate' => route('congressmen.reactivate', $m),
                            ] : null,
                        ];
                    @endphp
                    <tr data-member="{{ json_encode($payload) }}"
                        class="cursor-pointer transition-colors duration-150 hover:bg-blue-50/60 {{ $m->is_active ? '' : 'bg-slate-50/70' }}">
                        <td class="py-2.5 px-4">
                            <div class="flex items-center gap-3">
                                @if ($m->photo_url)
                                    <img src="{{ $m->photo_url }}" alt="" loading="lazy"
                                         class="h-14 w-11 shrink-0 rounded-md border border-slate-200 object-cover object-top">
                                @else
                                    <span class="grid h-14 w-11 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-100 text-slate-300"><i class="fa-solid fa-user"></i></span>
                                @endif
                                <div>
                                    <p class="font-semibold {{ $m->is_active ? 'text-slate-900' : 'text-slate-400' }}">{{ $m->name }}</p>
                                    @if ($m->rep_detail)
                                        <p class="text-[11px] text-slate-500">{{ $m->rep_detail }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap">
                            @php $partyList = str_contains($m->rep_type, 'Party'); @endphp
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $partyList ? 'bg-indigo-100 text-indigo-700' : 'bg-blue-100 text-blue-700' }}">{{ $partyList ? 'Party-list' : 'District' }}</span>
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">
                                <span class="h-2 w-2 rounded-full" style="background: {{ $m->building->color_hex ?? '#94a3b8' }}"></span>{{ $m->building->name ?? '—' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-slate-700 whitespace-nowrap">{{ $m->floor ?? '—' }}</td>
                        <td class="py-3 px-4 font-mono font-semibold text-slate-800 whitespace-nowrap">{{ $m->room ?? '—' }}</td>
                        @if ($isAdmin)
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $m->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $m->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="py-14 text-center">
                        <i class="fa-solid fa-address-book text-2xl text-slate-300"></i>
                        <p class="mt-2 text-sm font-semibold text-slate-600">No members found</p>
                        <p class="text-xs text-slate-400">Try adjusting your search or building filter.</p>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 flex items-center justify-between">
    <p class="text-sm text-slate-500">{{ $members->total() ? $members->total() . ' members · page ' . $members->currentPage() . ' of ' . $members->lastPage() : 'No results' }}</p>
    @if ($members->hasPages())
        <div class="flex gap-1">
            <a href="{{ $members->previousPageUrl() ?? '#' }}" class="js-dir-page grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ $members->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Previous page">&lsaquo;</a>
            <a href="{{ $members->nextPageUrl() ?? '#' }}" class="js-dir-page grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ ! $members->hasMorePages() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Next page">&rsaquo;</a>
        </div>
    @endif
</div>