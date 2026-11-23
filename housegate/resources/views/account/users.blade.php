@php
    $me = auth()->id();
    $iconBtn = 'grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-slate-600 transition-colors';
@endphp
<div class="overflow-hidden rounded-xl border border-slate-300">
    <div class="max-h-[440px] overflow-auto">
        <table class="w-full min-w-[860px] border-collapse text-left text-xs">
            <thead class="uppercase tracking-wide text-slate-600 [&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:bg-slate-100 [&_th]:shadow-[inset_0_-1px_0_#cbd5e1]">
                <tr>
                    <th class="py-3 px-4 font-bold">Name</th>
                    <th class="py-3 px-4 font-bold">HREP ID</th>
                    <th class="py-3 px-4 font-bold">Email</th>
                    <th class="py-3 px-4 font-bold">Role</th>
                    <th class="py-3 px-4 font-bold">Status</th>
                    <th class="py-3 px-4 text-right font-bold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $u)
                    @php
                        $payload = [
                            'id' => $u->id,
                            'name' => $u->name,
                            'email' => $u->email,
                            'hrep_id' => $u->hrep_id,
                            'role' => $u->role,
                            'self' => $u->id === $me,
                            'urls' => [
                                'update' => route('account.users.update', $u),
                                'password' => route('account.users.password', $u),
                                'deactivate' => route('account.users.deactivate', $u),
                                'reactivate' => route('account.users.reactivate', $u),
                            ],
                        ];
                    @endphp
                    <tr data-user="{{ json_encode($payload) }}" class="transition-colors duration-150 hover:bg-blue-50/60 {{ $u->is_active ? '' : 'bg-slate-50/70' }}">
                        <td class="py-3 px-4 font-semibold whitespace-nowrap {{ $u->is_active ? 'text-slate-900' : 'text-slate-400' }}">
                            {{ $u->name }}
                            @if ($u->id === $me)
                                <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">You</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 font-mono text-slate-600 whitespace-nowrap">{{ $u->hrep_id ?? '—' }}</td>
                        <td class="py-3 px-4 text-slate-600 max-w-[240px] truncate" title="{{ $u->email }}">{{ $u->email }}</td>
                        <td class="py-3 px-4 whitespace-nowrap">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase {{ $u->role === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700' }}">{{ $u->role }}</span>
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $u->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $u->is_active ? 'Active' : 'Deactivated' }}</span>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex justify-end gap-1.5">
                                <button type="button" data-action="edit" class="{{ $iconBtn }} hover:bg-slate-100" title="Edit" aria-label="Edit {{ $u->name }}"><i class="fa-solid fa-pen text-[11px]"></i></button>
                                <button type="button" data-action="password" class="{{ $iconBtn }} hover:bg-slate-100" title="Reset password" aria-label="Reset password for {{ $u->name }}"><i class="fa-solid fa-key text-[11px]"></i></button>
                                @if ($u->id !== $me)
                                    @if ($u->is_active)
                                        <button type="button" data-action="deactivate" class="{{ $iconBtn }} hover:border-red-300 hover:bg-red-50 hover:text-red-600" title="Deactivate" aria-label="Deactivate {{ $u->name }}"><i class="fa-solid fa-user-slash text-[11px]"></i></button>
                                    @else
                                        <button type="button" data-action="reactivate" class="{{ $iconBtn }} hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-600" title="Reactivate" aria-label="Reactivate {{ $u->name }}"><i class="fa-solid fa-user-check text-[11px]"></i></button>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-14 text-center">
                        <i class="fa-solid fa-users text-2xl text-slate-300"></i>
                        <p class="mt-2 text-sm font-semibold text-slate-600">No accounts found</p>
                        <p class="text-xs text-slate-400">Try adjusting your search or filters.</p>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 flex items-center justify-between">
    <p class="text-sm text-slate-500">{{ $users->total() ? $users->total() . ' accounts · page ' . $users->currentPage() . ' of ' . $users->lastPage() : 'No results' }}</p>
    @if ($users->hasPages())
        <div class="flex gap-1">
            <a href="{{ $users->previousPageUrl() ?? '#' }}" class="js-user-page grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ $users->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Previous page">&lsaquo;</a>
            <a href="{{ $users->nextPageUrl() ?? '#' }}" class="js-user-page grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 {{ ! $users->hasMorePages() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Next page">&rsaquo;</a>
        </div>
    @endif
</div>