{{-- TAB 1 - INSTRUCTIONS --}}
{{-- Script for this tab is at the bottom of this file. --}}
<div class="gov-card">
    <div class="gov-card-header">
        <div class="gov-card-header-left">
            <i class="fa-solid fa-clipboard-list gov-card-header-icon"></i>
            <div>
                <span class="gov-eyebrow">Instructions</span>
                <span class="gov-card-title">Visitor Pass Registry</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @if (auth()->user()->isAdmin())
                <button type="button" onclick="openInventoryModal()" class="gov-btn-glass-outline flex-shrink-0">
                    <i class="fa-solid fa-boxes-stacked"></i> Manage Inventory
                </button>
            @endif
            <button type="button" onclick="openRegisterModal()" class="gov-btn-camera flex-shrink-0">
                <i class="fa-solid fa-user-plus"></i> Register Visitor
            </button>
        </div>
    </div>
    <div class="gov-card-body">
        <p class="gov-instr-lead">Register a visitor with their agenda and detail for access to a certain building. The personnel must:</p>
        <ol class="gov-instr-steps">
            <li><span class="gov-instr-num">1</span><span>Register the visitor</span></li>
            <li><span class="gov-instr-num">2</span><span>Assign the specific building/s they need</span></li>
            <li><span class="gov-instr-num">3</span><span>Unassign after return of visitor pass</span></li>
        </ol>
    </div>
</div>

<script>
// ---- Tab 1: Instructions ----
function openRegisterModal() {
    document.getElementById('registerModal').classList.remove('hidden');
}
function openInventoryModal() { document.getElementById('inventoryModal')?.classList.remove('hidden'); }
function closeInventoryModal() { document.getElementById('inventoryModal')?.classList.add('hidden'); }
// ---- End Tab 1 ----
</script>

@if (auth()->user()->isAdmin())
<div id="inventoryModal" class="reg-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="inventory-title">
    <section class="reg-modal" style="max-width: 34rem;">
        <header class="reg-modal-header">
            <i class="fa-solid fa-boxes-stacked header-icon"></i>
            <div>
                <p class="reg-eyebrow">Inventory</p>
                <h1 id="inventory-title">Generate &amp; Print Passes</h1>
            </div>
            <button type="button" class="reg-close-button" aria-label="Close" onclick="closeInventoryModal()">&times;</button>
        </header>

        <main class="reg-modal-body inv-body">
            {{-- Shared building picker + live stats --}}
            <div class="inv-building">
                <label for="invBuilding">Building</label>
                <select id="invBuilding">
                    @foreach ($displayBuildings as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}{{ $b->code === 'NG' ? ' (multi-building pool)' : '' }}</option>
                    @endforeach
                </select>
                <div class="inv-stats">
                    <span class="inv-stat"><b id="invStatTotal">0</b> total</span>
                    <span class="inv-stat is-available"><b id="invStatAvail">0</b> available</span>
                    <span class="inv-stat is-active"><b id="invStatActive">0</b> active</span>
                    <span class="inv-stat is-inactive"><b id="invStatInactive">0</b> inactive</span>
                </div>
            </div>

            <div class="inv-tabs" role="tablist">
                <button type="button" class="inv-tab is-active" data-inv-tab="generate" onclick="invTab('generate')"><i class="fa-solid fa-plus"></i> Generate</button>
                <button type="button" class="inv-tab" data-inv-tab="print" onclick="invTab('print')"><i class="fa-solid fa-print"></i> Print / Export</button>
            </div>

            {{-- Generate --}}
            <form method="POST" action="{{ route('passes.inventory.generate') }}" data-inv-panel="generate" class="inv-panel">
                @csrf
                <input type="hidden" name="building_id" id="invGenBuilding">
                <div class="inv-pair">
                    <div class="inv-field"><label for="invGenFrom">From #</label><input id="invGenFrom" type="number" name="from" value="1" min="1" max="9999" required></div>
                    <div class="inv-field"><label for="invGenTo">To #</label><input id="invGenTo" type="number" name="to" value="250" min="1" max="9999" required></div>
                </div>
                <p class="inv-summary" id="invGenSummary"></p>
                <p class="inv-help">Existing numbers are skipped and existing QR tokens never change. Generating doesn't print anything.</p>
                <div class="inv-actions">
                    <button type="submit" id="invGenBtn" class="gov-btn-camera"><i class="fa-solid fa-plus"></i> Generate passes</button>
                </div>
            </form>

            {{-- Print / export --}}
            <form method="GET" action="{{ route('passes.inventory.print') }}" target="_blank" data-inv-panel="print" class="inv-panel hidden">
                <input type="hidden" name="building_id" id="invPrintBuilding">
                <input type="hidden" name="mode" id="invModeInput" value="range">

                <div class="inv-seg" role="tablist" aria-label="Which passes">
                    <button type="button" class="inv-seg-btn is-active" data-inv-mode="range" onclick="invMode('range')">Range</button>
                    <button type="button" class="inv-seg-btn" data-inv-mode="list" onclick="invMode('list')">Specific numbers</button>
                    <button type="button" class="inv-seg-btn" data-inv-mode="count" onclick="invMode('count')">Start + count</button>
                </div>

                <div data-inv-fields="range" class="inv-pair">
                    <div class="inv-field"><label for="invFrom">From #</label><input id="invFrom" type="number" name="from" value="1" min="1" max="9999"></div>
                    <div class="inv-field"><label for="invTo">To #</label><input id="invTo" type="number" name="to" value="250" min="1" max="9999"></div>
                </div>
                <div data-inv-fields="list" class="inv-field hidden">
                    <label for="invList">Pass numbers</label>
                    <input id="invList" type="text" name="list" placeholder="1-10, 25, 40-45" autocomplete="off">
                </div>
                <div data-inv-fields="count" class="inv-pair hidden">
                    <div class="inv-field"><label for="invStart">Start at #</label><input id="invStart" type="number" name="start" value="1" min="1" max="9999"></div>
                    <div class="inv-field"><label for="invCount">How many</label><input id="invCount" type="number" name="count" value="12" min="1" max="1000"></div>
                </div>

                <label class="inv-check"><input type="checkbox" id="invOnlyAvail" name="only_available" value="1"> Only available (unassigned) passes</label>

                <input type="hidden" name="layout" id="invLayout" value="card">
                <input type="hidden" name="paper" id="invPaper" value="a4">
                <input type="hidden" name="size" id="invSize" value="22">
                <div class="inv-opts">
                    <div class="inv-opt">
                        <span class="inv-opt-label">Print as</span>
                        <div class="inv-seg">
                            <button type="button" class="inv-seg-btn is-active" data-inv-layout="card" onclick="invOpt('layout','card')">Full pass card</button>
                            <button type="button" class="inv-seg-btn" data-inv-layout="qr" onclick="invOpt('layout','qr')">QR only (for PVC IDs)</button>
                        </div>
                    </div>
                    <div class="inv-opt">
                        <span class="inv-opt-label">Paper</span>
                        <div class="inv-seg">
                            <button type="button" class="inv-seg-btn is-active" data-inv-paper="a4" onclick="invOpt('paper','a4')">A4</button>
                            <button type="button" class="inv-seg-btn" data-inv-paper="letter" onclick="invOpt('paper','letter')">Letter</button>
                            <button type="button" class="inv-seg-btn" data-inv-paper="legal" onclick="invOpt('paper','legal')">Legal</button>
                        </div>
                    </div>
                    <div class="inv-opt hidden" data-inv-qronly>
                        <span class="inv-opt-label">QR size</span>
                        <div class="inv-seg">
                            <button type="button" class="inv-seg-btn" data-inv-size="18" onclick="invOpt('size','18')">18 mm</button>
                            <button type="button" class="inv-seg-btn is-active" data-inv-size="22" onclick="invOpt('size','22')">22 mm</button>
                            <button type="button" class="inv-seg-btn" data-inv-size="26" onclick="invOpt('size','26')">26 mm</button>
                        </div>
                    </div>
                </div>

                <p class="inv-summary" id="invSummary"></p>

                <div class="inv-actions">
                    <button type="submit" data-inv-print class="gov-btn-camera"><i class="fa-solid fa-print"></i> Open print sheet</button>
                    <button type="submit" data-inv-print formaction="{{ route('passes.inventory.csv') }}" formtarget="_self" class="gov-btn-glass-outline"><i class="fa-solid fa-file-csv"></i> Download CSV</button>
                </div>
            </form>
        </main>
    </section>
</div>
@endif

@if (auth()->user()->isAdmin())
@php
    $invData = $displayBuildings->mapWithKeys(function ($b) use ($passes) {
        $own = $passes->where('building_id', $b->id)->where('is_multi_building', $b->code === 'NG');
        return [$b->id => ['passes' => $own->map(fn ($p) => [(int) $p->pass_number, $p->status])->values()]];
    });
@endphp
<script>
// ---- Inventory modal ----
const INV = @json($invData);
const $i = id => document.getElementById(id);
const passWord = n => (n === 1 ? 'pass' : 'passes');

function invTab(tab) {
    document.querySelectorAll('[data-inv-tab]').forEach(b => b.classList.toggle('is-active', b.dataset.invTab === tab));
    document.querySelectorAll('[data-inv-panel]').forEach(p => p.classList.toggle('hidden', p.dataset.invPanel !== tab));
}
function invMode(mode) {
    $i('invModeInput').value = mode;
    document.querySelectorAll('[data-inv-mode]').forEach(b => b.classList.toggle('is-active', b.dataset.invMode === mode));
    document.querySelectorAll('[data-inv-fields]').forEach(f => f.classList.toggle('hidden', f.dataset.invFields !== mode));
    invRefresh();
}

// Mirrors PassInventoryController::grid() so the modal can show pages per sheet
const PAPER = { a4: [210, 297], letter: [215.9, 279.4], legal: [215.9, 355.6] };
function invPerPage() {
    const [w, h] = PAPER[$i('invPaper').value];
    const s = +$i('invSize').value;
    const [cw, ch, gap] = $i('invLayout').value === 'qr' ? [s + 4, s + 9, 2] : [60, 100, 4];
    const cols = Math.max(1, Math.floor((w - 16 + gap) / (cw + gap)));
    const rows = Math.max(1, Math.floor((h - 16 + gap) / (ch + gap)));
    return cols * rows;
}
function invOpt(kind, value) {
    $i({ layout: 'invLayout', paper: 'invPaper', size: 'invSize' }[kind]).value = value;
    const key = 'inv' + kind[0].toUpperCase() + kind.slice(1);
    document.querySelectorAll(`[data-inv-${kind}]`).forEach(b => b.classList.toggle('is-active', b.dataset[key] === value));
    document.querySelectorAll('[data-inv-qronly]').forEach(el => el.classList.toggle('hidden', $i('invLayout').value !== 'qr'));
    invRefresh();
}

// Same rules as PassInventoryController::selection()
function invParse() {
    const mode = $i('invModeInput').value;
    if (mode === 'range') {
        const a = +$i('invFrom').value, b = +$i('invTo').value;
        if (!a || !b || a < 1 || b < a || b > 9999) return { error: 'Enter a valid range.' };
        if (b - a >= 1000) return { error: 'Max 1000 at a time.' };
        return { nums: Array.from({ length: b - a + 1 }, (_, k) => a + k) };
    }
    if (mode === 'count') {
        const s = +$i('invStart').value, n = +$i('invCount').value;
        if (!s || s < 1 || !n || n < 1 || n > 1000) return { error: 'Enter a start number and a count (1–1000).' };
        const last = Math.min(9999, s + n - 1);
        return { nums: Array.from({ length: last - s + 1 }, (_, k) => s + k) };
    }
    const raw = $i('invList').value.replace(/\s*-\s*/g, '-').split(/[\s,;]+/).filter(Boolean);
    if (!raw.length) return { error: 'Type numbers or ranges, e.g. 1-10, 25, 40-45.' };
    const nums = new Set();
    for (const part of raw) {
        const m = part.match(/^(\d{1,4})(?:-(\d{1,4}))?$/);
        if (!m) return { error: `Can't read "${part}".` };
        const a = +m[1], b = m[2] ? +m[2] : a;
        if (a < 1 || b < a) return { error: `"${part}" isn't a valid number or range.` };
        for (let n = a; n <= b; n++) nums.add(n);
        if (nums.size > 1000) return { error: 'Max 1000 at a time.' };
    }
    return { nums: [...nums].sort((x, y) => x - y) };
}

function invRefresh() {
    const id = $i('invBuilding').value;
    const have = new Map(INV[id].passes); // number -> status
    $i('invGenBuilding').value = id;
    $i('invPrintBuilding').value = id;

    // Stats
    let avail = 0, active = 0, inactive = 0;
    have.forEach(s => { s === 'available' ? avail++ : s === 'active' ? active++ : inactive++; });
    $i('invStatTotal').textContent = have.size;
    $i('invStatAvail').textContent = avail;
    $i('invStatActive').textContent = active;
    $i('invStatInactive').textContent = inactive;

    // Generate summary
    const ga = +$i('invGenFrom').value, gb = +$i('invGenTo').value;
    const gSum = $i('invGenSummary'), gBtn = $i('invGenBtn');
    gSum.classList.remove('is-error', 'is-warn');
    if (!ga || !gb || ga < 1 || gb < ga || gb > 9999) {
        gSum.textContent = 'Enter a valid range.'; gSum.classList.add('is-error'); gBtn.disabled = true;
    } else if (gb - ga >= 1000) {
        gSum.textContent = 'Max 1000 at a time.'; gSum.classList.add('is-error'); gBtn.disabled = true;
    } else {
        let fresh = 0;
        for (let n = ga; n <= gb; n++) if (!have.has(n)) fresh++;
        const total = gb - ga + 1;
        gSum.textContent = fresh
            ? `${fresh} new ${passWord(fresh)} will be created · ${total - fresh} already exist`
            : 'Nothing to create. Every number in that range already exists.';
        if (!fresh) gSum.classList.add('is-warn');
        gBtn.disabled = fresh === 0;
    }

    // Print / export summary
    const sel = invParse();
    const sum = $i('invSummary'), btns = document.querySelectorAll('[data-inv-print]');
    sum.classList.remove('is-error', 'is-warn');
    if (sel.error) {
        sum.textContent = sel.error; sum.classList.add('is-error'); btns.forEach(b => b.disabled = true);
        return;
    }
    const onlyAvail = $i('invOnlyAvail').checked;
    let found = 0, missing = 0, skipped = 0;
    sel.nums.forEach(n => {
        if (!have.has(n)) missing++;
        else if (onlyAvail && have.get(n) !== 'available') skipped++;
        else found++;
    });
    const per = invPerPage();
    const pages = Math.ceil(found / per);
    const parts = [`${found} ${passWord(found)} selected`];
    if (found) parts.push(`${pages} page${pages === 1 ? '' : 's'} (${per} per page)`);
    if (missing) parts.push(`${missing} not generated yet`);
    if (skipped) parts.push(`${skipped} skipped (not available)`);
    sum.textContent = parts.join(' · ');
    if (missing || !found) sum.classList.add('is-warn');
    btns.forEach(b => b.disabled = found === 0);
}

['invBuilding', 'invGenFrom', 'invGenTo', 'invFrom', 'invTo', 'invStart', 'invCount', 'invList', 'invOnlyAvail']
    .forEach(id => ['input', 'change'].forEach(ev => $i(id).addEventListener(ev, invRefresh)));
invRefresh();
// ---- End Inventory modal ----
</script>
@endif
