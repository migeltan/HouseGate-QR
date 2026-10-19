{{-- TAB 1 - INSTRUCTIONS --}}
@php($awayData = \App\Services\AwayCards::forCurrentUser())
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
            <button type="button" onclick="openAwayModal()" class="gov-btn-glass-outline flex-shrink-0">
                <i class="fa-solid fa-right-left"></i> Away cards
                <span id="awayBadge" class="away-badge {{ $awayData['overdue'] > 0 ? '' : 'is-calm' }}" @if ($awayData['count'] < 1) style="display:none" @endif>{{ $awayData['count'] }}</span>
            </button>
            @if (auth()->user()->isAdmin())
                <button type="button" onclick="openInventoryModal()" class="gov-btn-glass-outline flex-shrink-0">
                    <i class="fa-solid fa-boxes-stacked"></i> Manage Inventory
                </button>
            @endif
            {{-- Guards at the Others station only scan for now: Gate 6 issuing is admin-only (see register()) --}}
            @unless (auth()->user()->isGuard() && $buildings->contains('code', \App\Models\Building::OTHERS_CODE))
                <button type="button" onclick="openRegisterModal()" class="gov-btn-camera flex-shrink-0">
                    <i class="fa-solid fa-user-plus"></i> Register Visitor
                </button>
            @endunless
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

@include('passes.away-modal', ['awayData' => $awayData])

<script>
// ---- Tab 1: Instructions ----
function openAwayModal() { document.getElementById('awayModal')?.classList.remove('hidden'); window.hgLive?.refresh(); }
function closeAwayModal() { document.getElementById('awayModal')?.classList.add('hidden'); }

// ---- Away cards: styled confirm + in-place submit (no page reload) ----
function setAwayBadge(count, overdue) {
    const badge = document.getElementById('awayBadge');
    if (!badge) return;
    badge.textContent = count;
    badge.style.display = count > 0 ? '' : 'none';
    badge.classList.toggle('is-calm', !(overdue > 0));
}

document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-away')) return;
    e.preventDefault();
    if (form.dataset.busy) return;

    if (form.hasAttribute('data-away-confirm')) {
        const ok = await confirmDialog({
            title: 'Hand the ID back?',
            subject: form.dataset.awayVisitor,
            message: 'Confirm the visitor is taking back their ' + form.dataset.awayIdLabel + ' now.',
            confirmLabel: 'Yes, handed back',
            tone: 'warning',
        });
        if (!ok) return;
    }

    form.dataset.busy = '1';
    const btn = form.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving';

    try {
        const res = await fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
            credentials: 'same-origin',
        });
        let data = null;
        try { data = await res.json(); } catch (_) {}

        if (data && data.html) {   // fresh list also on "already done" (409)
            document.getElementById('awayList')?.replaceWith(
                Object.assign(document.createElement('div'), { innerHTML: data.html }).firstElementChild
            );
            setAwayBadge(data.count, data.overdue);
            if (data.sig) window.hgLive?.setSig('away', data.sig);
        }
        if (res.ok && data && data.ok) {
            showToast(data.message, 'success');
        } else {
            showToast(data?.message || (res.status === 419 ? 'Your session expired. Reload the page and try again.'
                : res.status === 403 ? 'You are not allowed to do that.'
                : 'Something went wrong. Please try again.'), 'error');
        }
    } catch (err) {
        showToast('Network error. Nothing was changed.', 'error');
    } finally {
        delete form.dataset.busy;
        if (form.isConnected) { btn.disabled = false; btn.innerHTML = original; }
    }
});

// ---- Away cards: live updates (see window.hgLive in layouts/app.blade.php) ----
// Registered on DOMContentLoaded because the layout script that defines hgLive runs after this one.
document.addEventListener('DOMContentLoaded', () => {
    if (!window.hgLive) return;
    window.hgLive.register('away', {
        sig: @json(\App\Services\AwayCards::signature($awayData)),
        // never swap the list while a confirm dialog is open or a button is mid-request
        busy: () => !!document.querySelector('#awayList form[data-busy]') || !!document.querySelector('.gov-confirm'),
        apply(d) {
            const list = document.getElementById('awayList');
            if (list) list.replaceWith(Object.assign(document.createElement('div'), { innerHTML: d.html }).firstElementChild);
            setAwayBadge(d.count, d.overdue);
        },
    });
});
if (new URLSearchParams(location.search).get('away')) { document.addEventListener('DOMContentLoaded', openAwayModal); }
function openRegisterModal() {
    const overlay = document.getElementById('registerModal');
    overlay.classList.remove('hidden');
    // Always start at the top: the scroller is .reg-modal and it keeps its old scrollTop between opens.
    const scroller = overlay.querySelector('.reg-modal');
    if (scroller) scroller.scrollTop = 0;
    requestAnimationFrame(() => { if (scroller) scroller.scrollTop = 0; });
}
function openInventoryModal() { document.getElementById('inventoryModal')?.classList.remove('hidden'); }
function closeInventoryModal() { document.getElementById('inventoryModal')?.classList.add('hidden'); }
// ---- End Tab 1 ----
</script>

@if (auth()->user()->isAdmin())
<div id="inventoryModal" class="reg-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="inventory-title">
    <section class="reg-modal inv-modal" id="inventoryDialog">
        <header class="reg-modal-header">
            <i class="fa-solid fa-boxes-stacked header-icon"></i>
            <div>
                <p class="reg-eyebrow">Inventory</p>
                <h1 id="inventory-title">Manage Cards</h1>
            </div>
            <button type="button" class="reg-close-button" aria-label="Close" onclick="closeInventoryModal()">&times;</button>
        </header>

        <main class="reg-modal-body inv-body">
            {{-- Shared building picker + live stats --}}
            <div class="inv-building">
                <label for="invBuilding">Building</label>
                <select id="invBuilding">
                    @foreach ($displayBuildings as $b)
                        <option value="{{ $b->id }}" data-template="{{ $b->template_image ? asset($b->template_image) : '' }}" data-qr="{{ $b->qr_color_hex ?: ($b->color_hex ?: '#000000') }}">{{ $b->name }}{{ $b->code === 'NG' ? ' (multi-building pool)' : '' }}</option>
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
                <button type="button" class="inv-tab" data-inv-tab="stock" onclick="invTab('stock')"><i class="fa-solid fa-boxes-packing"></i> Desk cards</button>
            </div>

            {{-- Generate --}}
            <form method="POST" action="{{ route('passes.inventory.generate') }}" data-inv-panel="generate" class="inv-panel">
                @csrf
                <input type="hidden" name="building_id" id="invGenBuilding">
                <div class="inv-split">
                    <div class="inv-split-main inv-aside">
                        <span class="inv-aside-title">Pass numbers to create</span>
                        <div class="inv-pair">
                            <div class="inv-field"><label for="invGenFrom">From #</label><input id="invGenFrom" type="number" name="from" value="1" min="1" max="9999" required></div>
                            <div class="inv-field"><label for="invGenTo">To #</label><input id="invGenTo" type="number" name="to" value="250" min="1" max="9999" required></div>
                        </div>
                        <div class="inv-actions">
                            <button type="submit" id="invGenBtn" class="gov-btn-camera"><i class="fa-solid fa-plus"></i> Generate passes</button>
                        </div>
                    </div>
                    <aside class="inv-aside">
                        <span class="inv-aside-title">What will happen</span>
                        <p class="inv-summary" id="invGenSummary"></p>
                        <p class="inv-help">Existing numbers are skipped and existing QR tokens never change. Generating doesn't print anything.</p>
                    </aside>
                </div>
            </form>

            {{-- Print / export --}}
            <form method="GET" action="{{ route('passes.inventory.print') }}" target="_blank" id="invPrintForm" data-inv-panel="print" class="inv-panel hidden">
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
                        <label class="inv-opt-label" for="invPaperSelect">Paper</label>
                        <select id="invPaperSelect" class="cd-select" onchange="invOpt('paper', this.value)">
                            <option value="a4">A4 (210 × 297 mm)</option>
                            <option value="letter">Letter (8.5 × 11 in)</option>
                            <option value="legal">Legal (8.5 × 14 in)</option>
                        </select>
                    </div>
                </div>

                {{-- Card design: every input is named t[...] so the GET form sends it to the print sheet as-is --}}
                <?php
                    $cdCard = [['card_w', 'Width (mm)', 30, 200, 60], ['card_h', 'Height (mm)', 30, 290, 100], ['radius', 'Corner (mm)', 0, 10, 2], ['gap', 'Gap (mm)', 0, 20, 4]];
                    $cdQr   = [['qr_size', 'Size (% of width)', 15, 90, 50.67], ['qr_x', 'Left (%)', 0, 100, 50], ['qr_y', 'Top (%)', 0, 100, 60.2], ['qr_pad', 'Padding (mm)', 0, 5, 1.2]];
                    $cdNum  = [['num_size', 'Size (% of width)', 4, 30, 13.33], ['num_x', 'Left (%)', 0, 100, 50], ['num_y', 'Top (%)', 0, 100, 84.7]];
                ?>
                <div class="cd-box" data-inv-cardonly>
                    <div class="cd-head">
                        <span class="inv-opt-label">Card design</span>
                        <span class="cd-hint" id="cdPrevSize"></span>
                    </div>
                    <div class="cd-tpl">
                        <select id="cdTemplate" class="cd-select" onchange="cdPick()">
                            <option value="">Default (60 × 100 mm)</option>
                        </select>
                        <button type="button" class="cd-btn" onclick="cdSave()">Save as new</button>
                        <button type="button" class="cd-btn" id="cdUpdate" onclick="cdUpdate()" disabled>Update</button>
                        <button type="button" class="cd-btn is-danger" id="cdDelete" onclick="cdDelete()" disabled>Delete</button>
                    </div>
                    <p class="cd-msg" id="cdMsg"></p>
                    <div class="cd-grid">
                        <div class="cd-preview-wrap">
                            <div class="cd-prev" id="cdPrev">
                                <div class="cd-prev-qr" id="cdPrevQr"><div></div></div>
                                <div class="cd-prev-num" id="cdPrevNum">0001</div>
                            </div>
                        </div>
                        <div class="qd-form qd-cols-4">
                            <h4 class="qd-cap">Card</h4>
                            @foreach ($cdCard as [$k, $label, $min, $max, $val])
                                <div class="inv-field">
                                    <label for="cd_{{ $k }}">{{ $label }}</label>
                                    <input id="cd_{{ $k }}" class="cd-in" data-cd="{{ $k }}" name="t[{{ $k }}]" type="number" step="any" min="{{ $min }}" max="{{ $max }}" value="{{ $val }}">
                                </div>
                            @endforeach
                            <h4 class="qd-cap">Page &amp; background</h4>
                            <div class="inv-field">
                                <label for="cd_margin">Page margin (mm)</label>
                                <input id="cd_margin" class="cd-in" data-cd="margin" name="t[margin]" type="number" step="any" min="0" max="30" value="8">
                            </div>
                            <div class="inv-field qd-span-3">
                                <label for="cd_bg_fit">Background fit</label>
                                <select id="cd_bg_fit" class="cd-select cd-in" data-cd="bg_fit" name="t[bg_fit]">
                                    <option value="cover">Cover (crop to fill)</option>
                                    <option value="contain">Contain (whole image)</option>
                                    <option value="fill">Stretch to fit</option>
                                </select>
                            </div>
                            <h4 class="qd-cap">QR code</h4>
                            @foreach ($cdQr as [$k, $label, $min, $max, $val])
                                <div class="inv-field">
                                    <label for="cd_{{ $k }}">{{ $label }}</label>
                                    <input id="cd_{{ $k }}" class="cd-in" data-cd="{{ $k }}" name="t[{{ $k }}]" type="number" step="any" min="{{ $min }}" max="{{ $max }}" value="{{ $val }}">
                                </div>
                            @endforeach
                            <h4 class="qd-cap">Pass number</h4>
                            @foreach ($cdNum as [$k, $label, $min, $max, $val])
                                <div class="inv-field">
                                    <label for="cd_{{ $k }}">{{ $label }}</label>
                                    <input id="cd_{{ $k }}" class="cd-in" data-cd="{{ $k }}" name="t[{{ $k }}]" type="number" step="any" min="{{ $min }}" max="{{ $max }}" value="{{ $val }}">
                                </div>
                            @endforeach
                            <div class="inv-field">
                                <label for="cd_num_color">Colour</label>
                                <input id="cd_num_color" class="cd-in cd-color" data-cd="num_color" name="t[num_color]" type="color" value="#ffffff">
                            </div>
                            <div class="qd-checks">
                                <label class="inv-check"><input type="hidden" name="t[num_show]" value="0"><input class="cd-in" data-cd="num_show" type="checkbox" name="t[num_show]" value="1" checked> Show pass number</label>
                                <label class="inv-check"><input type="hidden" name="t[landscape]" value="0"><input class="cd-in" data-cd="landscape" type="checkbox" name="t[landscape]" value="1"> Landscape paper</label>
                                <label class="inv-check"><input type="hidden" name="t[guides]" value="0"><input class="cd-in" data-cd="guides" type="checkbox" name="t[guides]" value="1"> Cut guides</label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- QR sheet layout: every input is named q[...] so the GET form sends it to the print sheet as-is --}}
                <?php
                    $qrRows = [
                        'Size & grid' => [['size', 'QR size (mm)', 6, 80, 15, 'any'], ['cols', 'Columns', 1, 30, 6, 1], ['rows', 'Rows', 1, 40, 9, 1]],
                        'Spacing' => [['margin', 'Page margin (mm)', 0, 30, 8, 'any'], ['gap', 'Gap (mm)', 0, 20, 2, 'any'], ['pad', 'Cell padding (mm)', 0, 10, 2, 'any']],
                        'Position & label' => [['offset_x', 'Shift right (mm)', -50, 50, 0, 'any'], ['offset_y', 'Shift down (mm)', -50, 50, 0, 'any'], ['label_size', 'Label size (pt)', 4, 14, 7, 'any']],
                    ];
                ?>
                <div class="cd-box hidden" data-inv-qronly>
                    <div class="cd-head">
                        <span class="inv-opt-label">Sheet layout</span>
                        <span class="cd-hint" id="qrHint"></span>
                    </div>
                    <div class="cd-tpl">
                        <select id="qrTemplate" class="cd-select" onchange="qrPresetPick()">
                            <option value="">Default (auto layout)</option>
                            <optgroup label="Built-in">
                                <option value="b:fill6x9">Fill page · 6 × 9</option>
                                <option value="b:fill4x6">Fill page · 4 × 6 (bigger QRs)</option>
                            </optgroup>
                            <optgroup label="Saved" id="qrSavedGroup" hidden></optgroup>
                        </select>
                        <button type="button" class="cd-btn" onclick="qrPresetSave()">Save as new</button>
                        <button type="button" class="cd-btn" id="qrTplUpdate" onclick="qrPresetUpdate()" disabled>Update</button>
                        <button type="button" class="cd-btn is-danger" id="qrTplDelete" onclick="qrPresetDelete()" disabled>Delete</button>
                    </div>
                    <p class="cd-msg is-error" id="qrMsg"></p>
                    <div class="cd-grid">
                        <div class="cd-preview-wrap">
                            <div class="qd-side">
                                <div class="qd-page" id="qrPage"><div class="qd-grid" id="qrGrid"></div></div>
                                <p class="qd-tip">Drag the page to shift the grid</p>
                                <button type="button" class="cd-btn" onclick="qrCentre()">Centre grid</button>
                            </div>
                        </div>
                        <div class="qd-form qd-cols-3">
                            <h4 class="qd-cap">Arrangement</h4>
                            <div class="inv-field qd-span-2">
                                <label for="qd_mode">How to place the QRs</label>
                                <select id="qd_mode" class="cd-select qd-in" data-qd="mode" name="q[mode]">
                                    <option value="fixed">Auto: I set the QR size</option>
                                    <option value="fill">Grid: I set columns × rows</option>
                                </select>
                            </div>
                            <div class="inv-field">
                                <label for="qd_color">QR colour</label>
                                <select id="qd_color" class="cd-select qd-in" data-qd="color" name="q[color]">
                                    <option value="building">Building colour</option>
                                    <option value="black">Black</option>
                                </select>
                            </div>
                            @foreach ($qrRows as $group => $fields)
                                <h4 class="qd-cap">{{ $group }}</h4>
                                @foreach ($fields as [$k, $label, $min, $max, $val, $step])
                                    <div class="inv-field">
                                        <label for="qd_{{ $k }}" id="qd_label_{{ $k }}">{{ $label }}</label>
                                        <input id="qd_{{ $k }}" class="qd-in" data-qd="{{ $k }}" name="q[{{ $k }}]" type="number" step="{{ $step }}" min="{{ $min }}" max="{{ $max }}" value="{{ $val }}">
                                    </div>
                                @endforeach
                            @endforeach
                            <div class="qd-checks">
                                <label class="inv-check"><input type="hidden" name="q[spread]" value="0"><input class="qd-in" data-qd="spread" type="checkbox" name="q[spread]" value="1" checked> Spread evenly across the page</label>
                                <label class="inv-check"><input type="hidden" name="q[label]" value="0"><input class="qd-in" data-qd="label" type="checkbox" name="q[label]" value="1" checked> Show label under each QR</label>
                                <label class="inv-check"><input type="hidden" name="q[landscape]" value="0"><input class="qd-in" data-qd="landscape" type="checkbox" name="q[landscape]" value="1"> Landscape paper</label>
                                <label class="inv-check"><input type="hidden" name="q[guides]" value="0"><input class="qd-in" data-qd="guides" type="checkbox" name="q[guides]" value="1" checked> Cut guides</label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            {{-- Desk cards: which blank cards this desk physically holds --}}
            <form method="POST" action="{{ route('passes.inventory.stock') }}" data-inv-panel="stock" class="inv-panel hidden" onsubmit="return invStockConfirm()">
                @csrf
                <input type="hidden" name="building_id" id="invStockBuilding">
                <input type="hidden" name="mode" id="invStockModeInput" value="desk">

                <div class="inv-split">
                    <div class="inv-split-main">
                        <p class="inv-intro">Guards can only hand out cards that are at <b>their own desk</b>. Use this to tell the system which card numbers this desk really has.</p>

                        <div class="inv-stats">
                            <span class="inv-stat is-available"><b id="invStockDesk">0</b> at this desk</span>
                            <span class="inv-stat"><b id="invStockVault">0</b> in storage</span>
                            <span class="inv-stat is-inactive"><b id="invStockAway">0</b> with another building</span>
                        </div>
                        <p class="inv-warnbox hidden" id="invStockWarn"><i class="fa-solid fa-triangle-exclamation"></i> <span></span></p>

                        <div class="inv-choice" role="radiogroup" aria-label="What do you want to do?">
                            <button type="button" class="inv-choice-btn is-active" data-inv-stockmode="desk" onclick="invStockMode('desk')" role="radio" aria-checked="true">
                                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                                <span><b>Give cards to this desk</b><small>Guards here can start handing them out.</small></span>
                            </button>
                            <button type="button" class="inv-choice-btn" data-inv-stockmode="vault" onclick="invStockMode('vault')" role="radio" aria-checked="false">
                                <i class="fa-solid fa-box-archive"></i>
                                <span><b>Take cards back to storage</b><small>Guards here can no longer hand them out.</small></span>
                            </button>
                        </div>
                    </div>
                    <aside class="inv-aside">
                        <span class="inv-aside-title">Card numbers</span>
                        <div class="inv-pair">
                            <div class="inv-field"><label for="invStockFrom">First card number</label><input id="invStockFrom" type="number" name="from" value="1" min="1" max="9999" required></div>
                            <div class="inv-field"><label for="invStockTo">Last card number</label><input id="invStockTo" type="number" name="to" value="50" min="1" max="9999" required></div>
                        </div>
                        <p class="inv-count" id="invStockCount"></p>
                        <p class="inv-summary" id="invStockSummary"></p>
                        <div class="inv-actions">
                            <button type="submit" id="invStockBtn" class="gov-btn-camera"><i class="fa-solid fa-boxes-packing"></i> <span id="invStockBtnLabel">Update cards</span></button>
                        </div>
                    </aside>
                </div>
            </form>
        </main>

        <footer class="reg-modal-footer inv-footer hidden" id="invPrintFooter">
            <p class="inv-summary" id="invSummary"></p>
            <div class="inv-actions">
                <button type="submit" form="invPrintForm" data-inv-print class="gov-btn-camera"><i class="fa-solid fa-print"></i> Open print sheet</button>
                <button type="submit" form="invPrintForm" data-inv-print formaction="{{ route('passes.inventory.csv') }}" formtarget="_self" class="gov-btn-glass-outline"><i class="fa-solid fa-file-csv"></i> Download CSV</button>
            </div>
        </footer>
    </section>
</div>
@endif

@if (auth()->user()->isAdmin())
<script>
// ---- Inventory modal ----
// $invData comes from PassController@index: grouped stats + number ranges (no pass rows).
const INV = @json($invData);
const expandRanges = r => { const s = new Set(); r.forEach(([a, b]) => { for (let n = a; n <= b; n++) s.add(n); }); return s; };
Object.values(INV).forEach(e => { e.all = expandRanges(e.all); e.avail = expandRanges(e.avail); e.stock = expandRanges(e.stock); e.vault = expandRanges(e.vault); });
const INV_MIN_STOCK = {{ \App\Models\VisitorPass::MIN_DESK_STOCK }};
const $i = id => document.getElementById(id);
const passWord = n => (n === 1 ? 'pass' : 'passes');

function invTab(tab) {
    // The dialog fits each tab's content; remember its height so the change can be eased instead of snapping
    const dialog = document.getElementById('inventoryDialog');
    const from = dialog ? dialog.offsetHeight : 0;
    if (dialog) {
        clearTimeout(dialog._fit);
        dialog.style.transition = 'none';
        dialog.style.height = '';
    }

    document.querySelectorAll('[data-inv-tab]').forEach(b => b.classList.toggle('is-active', b.dataset.invTab === tab));
    document.querySelectorAll('[data-inv-panel]').forEach(p => p.classList.toggle('hidden', p.dataset.invPanel !== tab));
    // The print actions live in the dialog footer, so it only shows on this tab
    document.getElementById('invPrintFooter')?.classList.toggle('hidden', tab !== 'print');

    if (dialog && from) {
        const to = dialog.offsetHeight;
        if (to !== from) {
            dialog.style.height = from + 'px';
            void dialog.offsetHeight;   // commit the starting height before animating
            dialog.style.transition = 'height 0.22s ease';
            dialog.style.height = to + 'px';
            dialog._fit = setTimeout(() => { dialog.style.transition = ''; dialog.style.height = ''; }, 260);
        } else {
            dialog.style.transition = '';
        }
    }
}
function invStockMode(mode) {
    $i('invStockModeInput').value = mode;
    document.querySelectorAll('[data-inv-stockmode]').forEach(b => {
        const on = b.dataset.invStockmode === mode;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    invRefresh();
}
// Taking cards out of a desk's stock is the risky direction, so ask first.
function invStockConfirm() {
    if ($i('invStockModeInput').value === 'desk') return true;
    return confirm($i('invStockBtnLabel').textContent + '?\n\nGuards at this desk will no longer be able to hand these cards out.');
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
    const paper = $i('invPaper').value;
    if ($i('invLayout').value === 'qr') return qrLayoutJs(paper, qrRead(), +$i('invSize').value).perPage;
    let [w, h] = PAPER[paper];
    const t = cdRead();
    if (t.landscape) [w, h] = [h, w];
    const cw = Math.max(1, t.card_w), ch = Math.max(1, t.card_h);
    const cols = Math.max(1, Math.floor((w - 2 * t.margin + t.gap) / (cw + t.gap)));
    const rows = Math.max(1, Math.floor((h - 2 * t.margin + t.gap) / (ch + t.gap)));
    return cols * rows;
}
function invOpt(kind, value) {
    $i({ layout: 'invLayout', paper: 'invPaper', size: 'invSize' }[kind]).value = value;
    const key = 'inv' + kind[0].toUpperCase() + kind.slice(1);
    document.querySelectorAll(`[data-inv-${kind}]`).forEach(b => b.classList.toggle('is-active', b.dataset[key] === value));
    document.querySelectorAll('[data-inv-qronly]').forEach(el => el.classList.toggle('hidden', $i('invLayout').value !== 'qr'));
    document.querySelectorAll('[data-inv-cardonly]').forEach(el => el.classList.toggle('hidden', $i('invLayout').value !== 'card'));
    if (kind === 'size') qrEl('size').value = value;
    qrUi();
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
    const inv = INV[id];
    $i('invGenBuilding').value = id;
    $i('invPrintBuilding').value = id;
    $i('invStockBuilding').value = id;

    // Stats
    $i('invStatTotal').textContent = inv.total;
    $i('invStatAvail').textContent = inv.available;
    $i('invStatActive').textContent = inv.active;
    $i('invStatInactive').textContent = inv.inactive;

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
        for (let n = ga; n <= gb; n++) if (!inv.all.has(n)) fresh++;
        const total = gb - ga + 1;
        gSum.textContent = fresh
            ? `${fresh} new ${passWord(fresh)} will be created · ${total - fresh} already exist`
            : 'Nothing to create. Every number in that range already exists.';
        if (!fresh) gSum.classList.add('is-warn');
        gBtn.disabled = fresh === 0;
    }
    // Desk cards
    const cardWord = n => (n === 1 ? 'card' : 'cards');
    const pad4 = n => String(n).padStart(4, '0');
    $i('invStockDesk').textContent = inv.stock.size;
    $i('invStockVault').textContent = inv.vault.size;
    $i('invStockAway').textContent = inv.away;
    const sWarn = $i('invStockWarn');
    sWarn.classList.toggle('hidden', inv.stock.size >= INV_MIN_STOCK);
    sWarn.querySelector('span').textContent = `Only ${inv.stock.size} ${inv.stock.size === 1 ? 'card is' : 'cards are'} at this desk. Keep at least ${INV_MIN_STOCK} so guards don't run out.`;

    const stA = +$i('invStockFrom').value, stB = +$i('invStockTo').value;
    const give = $i('invStockModeInput').value === 'desk';
    const stCount = $i('invStockCount'), stSum = $i('invStockSummary');
    const stBtn = $i('invStockBtn'), stLabel = $i('invStockBtnLabel');
    stSum.classList.remove('is-error', 'is-warn');
    stLabel.textContent = give ? 'Give cards to this desk' : 'Take cards back to storage';
    if (!stA || !stB || stA < 1 || stB < stA || stB > 9999) {
        stCount.textContent = '';
        stSum.textContent = 'Type the first and last card number, for example 200 to 250.';
        stSum.classList.add('is-error'); stBtn.disabled = true;
    } else if (stB - stA >= 1000) {
        stCount.textContent = '';
        stSum.textContent = 'That is too many at once. Please do 1000 cards or fewer.';
        stSum.classList.add('is-error'); stBtn.disabled = true;
    } else {
        const c = { here: 0, storage: 0, away: 0, busy: 0, none: 0 };
        for (let n = stA; n <= stB; n++) {
            if (!inv.all.has(n)) c.none++;
            else if (inv.stock.has(n)) c.here++;
            else if (inv.vault.has(n)) c.storage++;
            else if (inv.avail.has(n)) c.away++;
            else c.busy++;
        }
        const total = stB - stA + 1;
        stCount.textContent = `${total} ${cardWord(total)} selected: #${pad4(stA)} to #${pad4(stB)}`;
        const move = give ? c.storage : c.here;
        const after = give ? inv.stock.size + move : inv.stock.size - move;
        const notes = [];
        if (give && c.here) notes.push(`${c.here} already at this desk`);
        if (!give && c.storage) notes.push(`${c.storage} already in storage`);
        if (c.busy) notes.push(`${c.busy} with a visitor right now`);
        if (c.away) notes.push(`${c.away} with another building`);
        if (c.none) notes.push(`${c.none} not created yet`);
        const skipped = notes.length ? ` (Not changed: ${notes.join(', ')}.)` : '';
        if (move) {
            stSum.textContent = `${move} ${cardWord(move)} will ${give ? 'go to this desk' : 'go back to storage'}. This desk will have ${after}.${skipped}`;
            stLabel.textContent = give ? `Give ${move} ${cardWord(move)} to this desk` : `Take ${move} ${cardWord(move)} back to storage`;
            if (after < INV_MIN_STOCK) stSum.classList.add('is-warn');
        } else {
            stSum.textContent = `Nothing to change.${skipped}`;
            stSum.classList.add('is-warn');
        }
        stBtn.disabled = move === 0;
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
        if (!inv.all.has(n)) missing++;
        else if (onlyAvail && !inv.avail.has(n)) skipped++;
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

// ---- Card designer (Print / Export > Card design) ----
// Mirrors PassInventoryController::CARD_DEFAULTS. Inputs are named t[...], so the print form submits them as-is.
const CD_NUM = ['card_w', 'card_h', 'gap', 'margin', 'radius', 'qr_size', 'qr_x', 'qr_y', 'qr_pad', 'num_size', 'num_x', 'num_y'];
const CD_TXT = ['bg_fit', 'num_color'];
const CD_BOOL = ['landscape', 'guides', 'num_show'];
const CD_DEFAULTS = {
    card_w: 60, card_h: 100, gap: 4, margin: 8, radius: 2, bg_fit: 'cover', landscape: false, guides: false,
    qr_size: 50.67, qr_x: 50, qr_y: 60.2, qr_pad: 1.2, num_show: true, num_size: 13.33, num_x: 50, num_y: 84.7, num_color: '#ffffff',
};
const CD_URL = @json(route('passes.inventory.templates'));
let cdTemplates = [];
const cdEl = k => document.querySelector(`[data-cd="${k}"]`);

function cdRead() {
    const t = {};
    CD_NUM.forEach(k => { t[k] = parseFloat(cdEl(k).value) || 0; });
    CD_TXT.forEach(k => { t[k] = cdEl(k).value; });
    CD_BOOL.forEach(k => { t[k] = cdEl(k).checked; });
    return t;
}
function cdWrite(s) {
    [...CD_NUM, ...CD_TXT].forEach(k => { cdEl(k).value = s[k]; });
    CD_BOOL.forEach(k => { cdEl(k).checked = !!s[k]; });
    cdPreview();
    invRefresh();
}
function cdPreview() {
    const t = cdRead(), opt = $i('invBuilding').selectedOptions[0];
    const cw = Math.max(1, t.card_w), ch = Math.max(1, t.card_h);
    const k = Math.min(240 / cw, 360 / ch);   // preview pixels per mm
    Object.assign($i('cdPrev').style, {
        width: cw * k + 'px', height: ch * k + 'px', borderRadius: t.radius * k + 'px',
        backgroundImage: opt && opt.dataset.template ? `url("${opt.dataset.template}")` : 'none',
        backgroundSize: { cover: 'cover', contain: 'contain', fill: '100% 100%' }[t.bg_fit] || 'cover',
    });
    const qs = cw * t.qr_size / 100 * k;
    Object.assign($i('cdPrevQr').style, {
        left: t.qr_x + '%', top: t.qr_y + '%', width: qs + 'px', height: qs + 'px',
        padding: t.qr_pad * k + 'px', borderRadius: cw * 0.0267 * k + 'px',
    });
    const num = $i('cdPrevNum');
    num.style.display = t.num_show ? '' : 'none';
    Object.assign(num.style, { left: t.num_x + '%', top: t.num_y + '%', color: t.num_color, fontSize: cw * t.num_size / 100 * k + 'px' });
    $i('cdPrevSize').textContent = `${t.card_w} × ${t.card_h} mm`;
}

function cdMsg(text, bad) {
    const m = $i('cdMsg');
    m.textContent = text || '';
    m.classList.toggle('is-error', !!bad);
}
function cdFill(selectId) {
    const sel = $i('cdTemplate');
    sel.length = 1;
    cdTemplates.forEach(t => sel.add(new Option(t.name, t.id)));
    sel.value = cdTemplates.some(t => String(t.id) === String(selectId)) ? String(selectId) : '';
    $i('cdUpdate').disabled = $i('cdDelete').disabled = !sel.value;
}
async function cdLoad(selectId) {
    try {
        const res = await fetch(CD_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        cdTemplates = res.ok ? (await res.json()).filter(t => t.kind === 'card') : [];
    } catch (_) {
        cdTemplates = [];
    }
    cdFill(selectId);
}
function cdPick() {
    const id = $i('cdTemplate').value;
    const tpl = cdTemplates.find(t => String(t.id) === id);
    $i('cdUpdate').disabled = $i('cdDelete').disabled = !id;
    cdMsg('');
    cdWrite(tpl ? tpl.settings : CD_DEFAULTS);
}
async function cdSend(url, extra) {
    const body = new URLSearchParams(extra);
    Object.entries(cdRead()).forEach(([k, v]) => body.set(`t[${k}]`, v === true ? 1 : v === false ? 0 : v));
    const res = await fetch(url, {
        method: 'POST', credentials: 'same-origin', body,
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Could not save the template.'));
    return data;
}
async function cdSave() {
    const name = (prompt('Template name:') || '').trim();
    if (!name) return;
    try {
        const d = await cdSend(CD_URL, { name });
        await cdLoad(d.template.id);
        cdMsg(`Saved "${d.template.name}".`);
    } catch (e) { cdMsg(e.message, true); }
}
async function cdUpdate() {
    const id = $i('cdTemplate').value;
    if (!id) return;
    try {
        const d = await cdSend(`${CD_URL}/${id}`, { _method: 'PUT' });
        await cdLoad(d.template.id);
        cdMsg(`Updated "${d.template.name}".`);
    } catch (e) { cdMsg(e.message, true); }
}
async function cdDelete() {
    const sel = $i('cdTemplate');
    if (!sel.value) return;
    const ok = await confirmDialog({
        title: 'Delete this template?', subject: sel.selectedOptions[0].textContent,
        message: 'Only the saved layout is removed. Printed cards are not affected.', confirmLabel: 'Delete', tone: 'danger',
    });
    if (!ok) return;
    try {
        await cdSend(`${CD_URL}/${sel.value}`, { _method: 'DELETE' });
        await cdLoad('');
        cdWrite(CD_DEFAULTS);
        cdMsg('Template deleted.');
    } catch (e) { cdMsg(e.message, true); }
}

document.querySelectorAll('.cd-in').forEach(el => ['input', 'change'].forEach(ev => el.addEventListener(ev, () => { cdPreview(); invRefresh(); })));
document.querySelectorAll('.cd-in[type="number"]').forEach(el => el.addEventListener('change', () => {
    let v = parseFloat(el.value);
    if (isNaN(v)) v = CD_DEFAULTS[el.dataset.cd];
    el.value = Math.min(+el.max, Math.max(+el.min, v));
    cdPreview();
    invRefresh();
}));
$i('invBuilding').addEventListener('change', cdPreview);
cdPreview();
cdLoad('');
// ---- End card designer ----

// ---- QR sheet designer (Print / Export > QR only) ----
// Mirrors PassInventoryController::QR_DEFAULTS / qrLayout(). Inputs are named q[...], so the print form submits them as-is.
const QD_NUM = ['size', 'cols', 'rows', 'margin', 'gap', 'pad', 'label_size', 'offset_x', 'offset_y'];
const QD_BOOL = ['label', 'landscape', 'guides', 'spread'];
const QD_DEFAULTS = { mode: 'fixed', size: 15, cols: 6, rows: 9, margin: 8, gap: 2, pad: 2, label: true, label_size: 7, landscape: false, guides: true, spread: true, color: 'building', offset_x: 0, offset_y: 0 };

function qrEl(k) { return document.querySelector(`[data-qd="${k}"]`); }
function qrRead() {
    const q = { mode: qrEl('mode').value, color: qrEl('color').value };
    QD_NUM.forEach(key => { q[key] = parseFloat(qrEl(key).value) || 0; });
    QD_BOOL.forEach(key => { q[key] = qrEl(key).checked; });
    return q;
}
function qrLayoutJs(paper, q, sizeFallback) {
    let [w, h] = PAPER[paper];
    if (q.landscape) [w, h] = [h, w];
    const lb = q.label ? 5 + (q.label_size - 7) * 0.3528 * 1.2 : 0;
    let cols, rows, cw, ch, size, gx = q.gap, gy = q.gap;
    if (q.mode === 'fill') {
        cols = Math.max(1, Math.trunc(q.cols));
        rows = Math.max(1, Math.trunc(q.rows));
        cw = (w - 2 * q.margin - (cols - 1) * q.gap) / cols;
        ch = (h - 2 * q.margin - (rows - 1) * q.gap) / rows;
        size = Math.min(cw - 2 * q.pad, ch - 2 * q.pad - lb);
    } else {
        size = q.size > 0 ? q.size : sizeFallback;
        cw = size + 2 * q.pad;
        ch = size + 2 * q.pad + lb;
        cols = Math.max(1, Math.floor((w - 2 * q.margin + q.gap) / (cw + q.gap)));
        rows = Math.max(1, Math.floor((h - 2 * q.margin + q.gap) / (ch + q.gap)));
        // Spread the leftover space evenly so the QRs reach the margins (rows keep 0.1 mm spare so the last row never spills onto a new page)
        if (q.spread) {
            if (cols > 1) gx = (w - 2 * q.margin - cols * cw) / (cols - 1);
            if (rows > 1) gy = Math.max(q.gap, (h - 2 * q.margin - rows * ch) / (rows - 1) - 0.1);
        }
    }
    size = Math.max(4, Math.floor(size * 10) / 10);
    return { w, h, cols, rows, cw, ch, size, gapX: gx, gapY: gy, perPage: cols * rows };
}
function qrPreview() {
    const q = qrRead(), fill = q.mode === 'fill';
    const L = qrLayoutJs($i('invPaper').value, q, +$i('invSize').value);
    const k = Math.min(280 / L.w, 420 / L.h);   // preview pixels per mm
    const opt = $i('invBuilding').selectedOptions[0];
    const color = q.color === 'black' ? '#000000' : ((opt && opt.dataset.qr) || '#000000');
    const page = $i('qrPage'), g = $i('qrGrid');
    page.style.width = L.w * k + 'px';
    page.style.height = L.h * k + 'px';
    page.dataset.k = k;
    g.style.cssText = `left:${q.offset_x * k}px;top:${q.offset_y * k}px;padding-top:${q.margin * k}px;` +
        `grid-template-columns:repeat(${L.cols},${L.cw * k}px);gap:${L.gapY * k}px ${L.gapX * k}px;` +
        `--cw:${L.cw * k}px;--ch:${L.ch * k}px;--qs:${L.size * k}px;--qc:${color};` +
        `--cj:${fill ? 'center' : 'flex-start'};--cpt:${fill ? 0 : q.pad * k}px;--lm:${1.2 * k}px;` +
        `--lw:${L.size * k * 0.6}px;--lh:${Math.max(1, q.label_size * 0.3528 * k * 0.8)}px`;
    g.classList.toggle('qd-hide-guides', !q.guides);
    const cell = '<div class="qd-cell"><i class="qd-q"></i>' + (q.label ? '<b class="qd-l"></b>' : '') + '</div>';
    g.innerHTML = cell.repeat(Math.min(L.perPage, 600));
    $i('qrHint').textContent = `${L.cols} × ${L.rows} per page · QR ${L.size} mm`;
    $i('qrMsg').textContent = L.size < 12 ? 'Small QR: test-scan a printed sample before printing a full batch.' : '';
    $i('qrMsg').classList.add('is-error');
}
// Enable only the inputs that matter for the chosen arrangement, then redraw
function qrUi() {
    const fill = qrEl('mode').value === 'fill';
    qrEl('size').disabled = fill;
    qrEl('spread').disabled = fill;
    qrEl('cols').disabled = qrEl('rows').disabled = !fill;
    qrEl('label_size').disabled = !qrEl('label').checked;
    $i('qd_label_size').textContent = fill ? 'QR size (auto)' : 'QR size (mm)';
    // In grid mode the QR size is worked out from columns × rows, so show that instead of a stale number
    if (fill) qrEl('size').value = qrLayoutJs($i('invPaper').value, qrRead(), 0).size;
    qrPreview();
}
function qrSync() { qrUi(); invRefresh(); }
function qrCentre() {
    qrEl('offset_x').value = 0;
    qrEl('offset_y').value = 0;
    qrPreview();
}

// Presets: built-in layouts plus saved ones (same /passes/inventory/templates endpoints, kind = 'qr')
const QD_BUILTIN = {
    fill6x9: { ...QD_DEFAULTS, mode: 'fill', cols: 6, rows: 9 },
    fill4x6: { ...QD_DEFAULTS, mode: 'fill', cols: 4, rows: 6 },
};
let qrTemplates = [];

function qrWrite(s) {
    qrEl('mode').value = s.mode;
    qrEl('color').value = s.color;
    QD_NUM.forEach(k => { qrEl(k).value = s[k]; });
    QD_BOOL.forEach(k => { qrEl(k).checked = !!s[k]; });
    qrSync();
}
function qrPresetMsg(text, bad) {
    const m = $i('qrMsg');
    m.textContent = text || '';
    m.classList.toggle('is-error', !!bad);
}
function qrPresetButtons() {
    const saved = /^\d+$/.test($i('qrTemplate').value);
    $i('qrTplUpdate').disabled = $i('qrTplDelete').disabled = !saved;
}
function qrPresetFill(selectId) {
    const sel = $i('qrTemplate'), group = $i('qrSavedGroup');
    group.replaceChildren(...qrTemplates.map(t => new Option(t.name, t.id)));
    group.hidden = !qrTemplates.length;
    const wanted = String(selectId);
    sel.value = qrTemplates.some(t => String(t.id) === wanted) ? wanted : '';
    qrPresetButtons();
}
async function qrPresetLoad(selectId) {
    try {
        const res = await fetch(CD_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        qrTemplates = res.ok ? (await res.json()).filter(t => t.kind === 'qr') : [];
    } catch (_) {
        qrTemplates = [];
    }
    qrPresetFill(selectId);
}
function qrPresetPick() {
    const v = $i('qrTemplate').value;
    const tpl = qrTemplates.find(t => String(t.id) === v);
    qrPresetButtons();
    qrWrite(tpl ? tpl.settings : (QD_BUILTIN[v.replace('b:', '')] || QD_DEFAULTS));
    qrPresetMsg('');
}
async function qrPresetSend(url, extra) {
    const body = new URLSearchParams({ kind: 'qr', ...extra });
    Object.entries(qrRead()).forEach(([k, v]) => body.set(`q[${k}]`, v === true ? 1 : v === false ? 0 : v));
    const res = await fetch(url, {
        method: 'POST', credentials: 'same-origin', body,
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Could not save the preset.'));
    return data;
}
async function qrPresetSave() {
    const name = (prompt('Preset name:') || '').trim();
    if (!name) return;
    try {
        const d = await qrPresetSend(CD_URL, { name });
        await qrPresetLoad(d.template.id);
        qrPresetMsg(`Saved "${d.template.name}".`);
    } catch (e) { qrPresetMsg(e.message, true); }
}
async function qrPresetUpdate() {
    const id = $i('qrTemplate').value;
    if (!/^\d+$/.test(id)) return;
    try {
        const d = await qrPresetSend(`${CD_URL}/${id}`, { _method: 'PUT' });
        await qrPresetLoad(d.template.id);
        qrPresetMsg(`Updated "${d.template.name}".`);
    } catch (e) { qrPresetMsg(e.message, true); }
}
async function qrPresetDelete() {
    const sel = $i('qrTemplate');
    if (!/^\d+$/.test(sel.value)) return;
    const ok = await confirmDialog({
        title: 'Delete this preset?', subject: sel.selectedOptions[0].textContent,
        message: 'Only the saved layout is removed. Printed sheets are not affected.', confirmLabel: 'Delete', tone: 'danger',
    });
    if (!ok) return;
    try {
        await qrPresetSend(`${CD_URL}/${sel.value}`, { _method: 'DELETE' });
        await qrPresetLoad('');
        qrWrite(QD_DEFAULTS);
        qrPresetMsg('Preset deleted.');
    } catch (e) { qrPresetMsg(e.message, true); }
}

document.querySelectorAll('.qd-in').forEach(el => ['input', 'change'].forEach(ev => el.addEventListener(ev, qrSync)));
document.querySelectorAll('.qd-in[type="number"]').forEach(el => el.addEventListener('change', () => {
    let v = parseFloat(el.value);
    if (isNaN(v)) v = QD_DEFAULTS[el.dataset.qd];
    v = Math.min(+el.max, Math.max(+el.min, v));
    if (el.step === '1') v = Math.round(v);
    el.value = v;
    qrSync();
}));
$i('invBuilding').addEventListener('change', qrPreview);

// Drag the page preview to shift the whole grid (0.5 mm steps, +/- 50 mm)
(function () {
    const page = $i('qrPage');
    let st = null;
    page.addEventListener('pointerdown', e => {
        st = { x: e.clientX, y: e.clientY, ox: parseFloat(qrEl('offset_x').value) || 0, oy: parseFloat(qrEl('offset_y').value) || 0, k: +page.dataset.k || 1 };
        page.setPointerCapture(e.pointerId);
    });
    page.addEventListener('pointermove', e => {
        if (!st) return;
        const cl = v => Math.max(-50, Math.min(50, Math.round(v * 2) / 2));
        const nx = cl(st.ox + (e.clientX - st.x) / st.k), ny = cl(st.oy + (e.clientY - st.y) / st.k);
        qrEl('offset_x').value = nx;
        qrEl('offset_y').value = ny;
        $i('qrGrid').style.left = nx * st.k + 'px';
        $i('qrGrid').style.top = ny * st.k + 'px';
    });
    ['pointerup', 'pointercancel'].forEach(ev => page.addEventListener(ev, () => { st = null; }));
})();
qrUi();
qrPresetLoad('');
// ---- End QR sheet designer ----

['invBuilding', 'invGenFrom', 'invGenTo', 'invFrom', 'invTo', 'invStart', 'invCount', 'invList', 'invOnlyAvail', 'invStockFrom', 'invStockTo']
    .forEach(id => ['input', 'change'].forEach(ev => $i(id).addEventListener(ev, invRefresh)));
invRefresh();
// ---- End Inventory modal ----
</script>
@endif
