// =====================================================================
// UNIVERSAL BUTTON LIFECYCLE ENGINE (`window.runButtonLifecycle`)
// Smoothly animates any button: Idle -> Press/Ripple -> Working -> Done -> Restored
// =====================================================================
window.runButtonLifecycle = function(btn, options = {}, callback) {
    if (!btn || btn.dataset.lifecycleBusy === 'true') {
        if (typeof callback === 'function') callback();
        return;
    }
    btn.dataset.lifecycleBusy = 'true';

    const duration = options.duration || 320;
    const workingText = options.workingText || 'Applying...';
    const doneText = options.doneText || 'Done';
    const originalHtml = btn.innerHTML;

    // Add bottom sweep bar
    const sweepBar = document.createElement('span');
    sweepBar.className = 'btn-lifecycle-bar';
    btn.appendChild(sweepBar);

    // Stage 1: Working Spinner State
    btn.classList.add('btn-state-working');
    btn.innerHTML = `
        <svg class="w-3.5 h-3.5 animate-spin text-sky-300 shrink-0" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span class="whitespace-nowrap">${workingText}</span>
    `;
    btn.appendChild(sweepBar);

    setTimeout(() => {
        // Execute actual action
        if (typeof callback === 'function') callback();

        // Stage 2: Verified Checkmark Burst State
        btn.classList.remove('btn-state-working');
        btn.classList.add('btn-state-done');
        btn.innerHTML = `
            <svg class="w-3.5 h-3.5 text-emerald-300 shrink-0 scale-110 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span class="whitespace-nowrap text-emerald-200 font-semibold">${doneText}</span>
        `;

        // Stage 3: Smoothly settle back to live updated state
        setTimeout(() => {
            btn.classList.remove('btn-state-done');
            if (document.body.contains(btn)) {
                btn.innerHTML = originalHtml;
                if (btn.id === 'header-create-btn') {
                    updateDynamicHeader(activePageId);
                }
            }
            delete btn.dataset.lifecycleBusy;
        }, 480);
    }, duration);
};

window.addEventListener('DOMContentLoaded', () => {
    // =====================================================================
    // SEMANTIC ICON LIFECYCLE DISPATCHER (`window.triggerIconLifecycle`)
    // Animates icons inside any clicked button, row, or pill through a
    // tailored click-burst lifecycle based on the icon's semantic role.
    // =====================================================================
    window.triggerIconLifecycle = function(containerEl) {
        if (!containerEl) return;
        const icons = containerEl.querySelectorAll('svg:not(.animate-spin), i.fa-solid, i.fa-regular');
        icons.forEach(icon => {
            icon.classList.remove('icon-lifecycle-pop', 'icon-lifecycle-rewind', 'icon-lifecycle-launch');
            void icon.offsetWidth; // Force reflow so repeated clicks replay smoothly

            const cls = (icon.getAttribute('class') || '') + ' ' + (containerEl.getAttribute('onclick') || '') + ' ' + (containerEl.getAttribute('title') || '');
            if (/rotate-left|Reset|reset/i.test(cls)) {
                icon.classList.add('icon-lifecycle-rewind');
            } else if (/fa-play|fa-paper-plane|fa-bolt|export|Export|arrow-up-right/i.test(cls)) {
                icon.classList.add('icon-lifecycle-launch');
            } else {
                icon.classList.add('icon-lifecycle-pop');
            }

            setTimeout(() => {
                icon.classList.remove('icon-lifecycle-pop', 'icon-lifecycle-rewind', 'icon-lifecycle-launch');
            }, 540);
        });
    };

    // Global Interactive Button Ripple & Icon Lifecycle
    document.addEventListener('click', (e) => {
        const iconHost = e.target.closest('button, .axis-kpi-card, .axis-meta-pill, tr[onclick]');
        if (iconHost) {
            window.triggerIconLifecycle(iconHost);
        }

        const btnEl = e.target.closest('button');
        if (btnEl && !btnEl.disabled) {
            const rect = btnEl.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'btn-lifecycle-ripple';
            ripple.style.left = `${e.clientX - rect.left}px`;
            ripple.style.top = `${e.clientY - rect.top}px`;
            btnEl.appendChild(ripple);
            setTimeout(() => ripple.remove(), 540);
        }

    });

});
