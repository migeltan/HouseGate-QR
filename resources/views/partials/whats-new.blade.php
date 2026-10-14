{{-- "What's new" overlay. Included by layouts/app.blade.php on every sign-in until the user ticks
     "Don't show this again" for the current config('whatsnew.id'). Closing it otherwise only hides it for this
     session (POST whatsnew.dismiss sets a session flag). --}}
<div id="whatsNewOverlay" class="hg-overlay hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4"
     role="dialog" aria-modal="true" aria-labelledby="wnTitle"
     data-id="{{ $whatsNew['id'] }}" data-user="{{ auth()->id() }}" data-url="{{ route('whatsnew.dismiss') }}">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="gov-card-header flex items-start justify-between gap-3" style="padding: 1.25rem 1.5rem;">
            <div class="flex items-start gap-3">
                <i class="fa-solid fa-bullhorn gov-card-header-icon"></i>
                <div>
                    <span class="gov-eyebrow">{{ $whatsNew['eyebrow'] ?? "What's new" }}@if (! empty($whatsNew['version_label'])) &middot; {{ $whatsNew['version_label'] }}@endif</span>
                    <h3 class="gov-card-title" id="wnTitle">{{ $whatsNew['title'] }}</h3>
                </div>
            </div>
            <button type="button" data-wn-close class="mt-1 text-slate-400 hover:text-slate-700" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <ul class="wn-list">
            @foreach ($whatsNew['items'] as $item)
                <li class="wn-item">
                    <i class="fa-solid {{ $item['icon'] ?? 'fa-circle-check' }} wn-icon" aria-hidden="true"></i>
                    <div>
                        <strong>{{ $item['title'] }}</strong>
                        <p>{{ $item['text'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="wn-footer">
            <label class="wn-skip"><input type="checkbox" id="wnSkip"> Don't show this again</label>
            <button type="button" id="wnOk" data-wn-close class="gov-btn-camera">Got it</button>
        </div>
    </div>
</div>

<script>
(function () {
    const el = document.getElementById('whatsNewOverlay');
    if (!el) return;

    // "Don't show this again" is also remembered in this browser: if the request below fails (offline, or a
    // cached page from before it was saved), the popup still can't come back for this update.
    const key = 'hg.whatsnew.' + el.dataset.user;
    try { if (localStorage.getItem(key) === el.dataset.id) { el.remove(); return; } } catch (_) {}

    const skip = document.getElementById('wnSkip');
    const focusables = [...el.querySelectorAll('button, input')];
    let done = false, downOnBackdrop = false;

    function close() {
        if (done) return;
        done = true;
        const forever = !!(skip && skip.checked);
        el.classList.add('hidden');
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onKey);
        if (forever) { try { localStorage.setItem(key, el.dataset.id); } catch (_) {} }
        const token = document.querySelector('meta[name="csrf-token"]');
        fetch(el.dataset.url, {
            method: 'POST', credentials: 'same-origin', keepalive: true,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token ? token.content : '' },
            body: JSON.stringify({ forever }),
        }).catch(() => {});   // best effort; if it fails the popup simply shows again on the next page
    }
    function onKey(e) {
        if (e.key === 'Escape') { close(); return; }
        if (e.key !== 'Tab') return;
        // keep Tab inside the dialog
        const first = focusables[0], last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }

    el.querySelectorAll('[data-wn-close]').forEach(b => b.addEventListener('click', close));
    el.addEventListener('mousedown', e => { downOnBackdrop = e.target === el; });   // ignore drags that end on the backdrop
    el.addEventListener('click', e => { if (e.target === el && downOnBackdrop) close(); });
    document.addEventListener('keydown', onKey);

    el.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    document.getElementById('wnOk').focus();
})();
</script>