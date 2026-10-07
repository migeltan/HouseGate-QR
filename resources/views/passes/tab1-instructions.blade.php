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
                <button type="button" onclick="openInventoryModal()" class="gov-btn-outline">
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

        <main class="reg-modal-body">
            {{-- 1. Generate --}}
            <form method="POST" action="{{ route('passes.inventory.generate') }}" class="reg-step-card">
                @csrf
                <p class="reg-step-label">1 · Generate passes</p>
                <div class="reg-field full">
                    <label>Building</label>
                    <select name="building_id" required>
                        @foreach ($displayBuildings as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}{{ $b->code === 'NG' ? ' (multi-building pool)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="reg-pair-row">
                    <div class="reg-field"><label>From #</label><input type="number" name="from" value="1" min="1" max="9999" required></div>
                    <div class="reg-field"><label>To #</label><input type="number" name="to" value="250" min="1" max="9999" required></div>
                </div>
                <p class="reg-camera-help">Numbers that already exist are skipped. Existing QR tokens never change.</p>
                <button type="submit" class="reg-button reg-button-green">Generate</button>
            </form>

            {{-- 2. Print / export --}}
            <form method="GET" class="reg-step-card">
                <p class="reg-step-label">2 · Print or export</p>
                <div class="reg-field full">
                    <label>Building</label>
                    <select name="building_id" required>
                        @foreach ($displayBuildings as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}{{ $b->code === 'NG' ? ' (multi-building pool)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="reg-pair-row">
                    <div class="reg-field"><label>From #</label><input type="number" name="from" value="1" min="1" max="9999" required></div>
                    <div class="reg-field"><label>To #</label><input type="number" name="to" value="250" min="1" max="9999" required></div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" formaction="{{ route('passes.inventory.print') }}" formtarget="_blank" class="reg-button reg-button-blue">Open print sheet</button>
                    <button type="submit" formaction="{{ route('passes.inventory.csv') }}" class="reg-button">Download CSV</button>
                </div>
            </form>
        </main>
    </section>
</div>
@endif
</script>
