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
        <div class="gov-corner-accent" aria-hidden="true"></div>
    </div>
    <div class="gov-card-body gov-card-body-split">
        <div>
            <p>Register a visitor with their agenda and detail for access to a certain building. The personnel must:</p>
            <ol class="gov-steps">
                <li>Register the visitor</li>
                <li>Assign the specific building/s they need</li>
                <li>Unassign after return of visitor pass</li>
            </ol>
        </div>
        <button type="button" onclick="openRegisterModal()" class="gov-btn-camera flex-shrink-0">
            <i class="fa-solid fa-user-plus"></i> Register Visitor
        </button>
    </div>
</div>

<script>
// ---- Tab 1: Instructions ----
function openRegisterModal() {
    document.getElementById('registerModal').classList.remove('hidden');
}
// ---- End Tab 1 ----
</script>
