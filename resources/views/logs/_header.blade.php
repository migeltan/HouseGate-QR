{{-- Hero — reuses the exact segmented-border treatment from scanner/index.blade.php and logs/index.blade.php --}}
<div class="main-header overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm mb-6">

    {{-- header band: icon + eyebrow + title, with corner ribbon --}}
    <div class="relative overflow-hidden bg-slate-50 border-b border-slate-200 px-6 py-5 sm:px-8 sm:py-6 shadow-md">
        {{-- decorative gold/navy corner ribbon — fixed square, NOT full header height --}}
        <div class="absolute top-0 right-0 w-20 h-20 sm:w-28 sm:h-28" aria-hidden="true">
            <div class="absolute inset-0 bg-amber-400" style="clip-path: polygon(0% 0%, 100% 0%, 100% 100%);"></div>
            <div class="absolute inset-0 bg-blue-800" style="clip-path: polygon(10% 0%, 100% 0%, 100% 90%);"></div>
        </div>

        <div class="relative flex items-center gap-4">
            <svg viewBox="0 0 24 24" class="h-9 w-9 shrink-0 text-slate-900" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2h9l4 4v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Z" />
                <path d="M15 2v4h4" />
                <circle cx="10.5" cy="10.2" r="2.4" />
                <path d="M10.5 7.8v2.4h2.4" />
                <path d="M7.5 15.6h7" />
                <path d="M7.5 18h7" />
            </svg>
            <div class="min-w-0">
                <p class="font-serif text-sm sm:text-base font-semibold text-blue-700">
                    Instructions
                </p>
                <h1 class="mt-0.5 text-lg sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Visitor Pass Registry
                </h1>
            </div>
        </div>
    </div>

    {{-- body: description + numbered steps + register button --}}
    <div class="px-6 py-5 sm:px-8 sm:py-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <p class="text-sm sm:text-[15px] leading-relaxed text-slate-700">
                Register a visitor with their agenda and detail for access to a certain building. The personnel must:
            </p>
            <ol class="mt-2 list-decimal space-y-1 pl-9 text-sm sm:text-[15px] leading-relaxed text-slate-700">
                <li>Register the visitor</li>
                <li>Assign the specific building/s they need</li>
                <li>Unassign after return of visitor pass</li>
            </ol>
        </div>
        <button onclick="document.getElementById('registerModal').classList.remove('hidden')" class="gov-btn-camera flex-shrink-0">
            <i class="fa-solid fa-user-plus"></i> Register Visitor
        </button>
    </div>
</div>
