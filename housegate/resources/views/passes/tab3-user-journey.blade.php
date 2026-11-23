{{-- User Journey — markup + the script that drives the step carousel.
     Self-contained: the script sits below the markup it targets and uses
     addEventListener, so it needs nothing from index.blade.php. --}}
<div class="gov-card">
    <div class="gov-card-header">
        <div class="gov-card-header-left">
            <i class="fa-solid fa-people-arrows gov-card-header-icon"></i>
            <div>
                <span class="gov-eyebrow">User Journey</span>
                <span class="gov-card-title">Diagram of User Journey of the System</span>
            </div>
        </div>
        <div class="gov-corner-accent" aria-hidden="true"></div>
    </div>
    <div class="gov-card-body">
        <div class="relative max-w-2xl mx-auto">
            <img id="journeyImage" src="{{ asset('images/carousel/step1.svg') }}" alt="User journey step"
                class="w-full h-auto">
            <button type="button" id="journeyPrevBtn" class="gov-journey-nav is-prev" aria-label="Previous step"
                disabled>
                <i class="fa-solid fa-caret-left"></i>
            </button>
            <button type="button" id="journeyNextBtn" class="gov-journey-nav" aria-label="Next step">
                <i class="fa-solid fa-caret-right"></i>
            </button>
        </div>
    </div>
</div>

<script>
    (function() {
        const steps = [
            "{{ asset('images/carousel/step1.svg') }}",
            "{{ asset('images/carousel/step2.svg') }}",
            "{{ asset('images/carousel/step3.svg') }}"
        ];
        let index = 0;

        const img = document.getElementById('journeyImage');
        const prevBtn = document.getElementById('journeyPrevBtn');
        const nextBtn = document.getElementById('journeyNextBtn');

        function render() {
            img.classList.add('is-fading');
            setTimeout(() => {
                img.src = steps[index];
                img.classList.remove('is-fading');
            }, 150);

            prevBtn.disabled = index === 0;
            nextBtn.disabled = index === steps.length - 1;
        }

        prevBtn.addEventListener('click', () => {
            if (index > 0) {
                index--;
                render();
            }
        });

        nextBtn.addEventListener('click', () => {
            if (index < steps.length - 1) {
                index++;
                render();
            }
        });
    })();
</script>
