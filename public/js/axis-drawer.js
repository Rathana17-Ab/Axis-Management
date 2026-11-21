// =====================================================================
// AXIS UNIVERSAL DRAWER CONTROLLER (`window.AxisDrawer`)
// Behaviour for every <x-axis.drawer> (resources/views/components/axis/drawer.blade.php):
// open/close transitions, Escape/overlay close, focus trap & restore,
// background scroll lock, inline validation (client + Laravel 422),
// busy state with duplicate-submit protection, and a promise-based confirm.
// =====================================================================
window.AxisDrawer = (function () {
    const TRANSITION_MS = 280;
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const stack = [];

    function resolve(target) {
        if (!target) return null;
        if (typeof target === 'string') return document.getElementById(target);
        return target.closest ? target.closest('[data-axis-drawer]') : null;
    }

    function topDrawer() {
        return stack[stack.length - 1] || null;
    }

    function isOpen(target) {
        const d = resolve(target);
        return !!d && stack.includes(d);
    }

    function syncScrollLock() {
        document.documentElement.classList.toggle('axis-drawer-open', stack.length > 0);
    }

    /** Update header text / mode / primary label without re-rendering the drawer. */
    function configure(target, options = {}) {
        const d = resolve(target);
        if (!d) return;
        const set = (selector, value) => {
            if (value === undefined) return;
            const el = d.querySelector(selector);
            if (!el) return;
            el.textContent = value ?? '';
            el.classList.toggle('hidden', value === null || value === '');
        };
        set('[data-drawer-title]', options.title);
        set('[data-drawer-description]', options.description);
        set('[data-drawer-breadcrumb]', options.breadcrumb);
        if (options.submitLabel !== undefined) {
            const label = d.querySelector('[data-drawer-submit-label]');
            if (label) label.textContent = options.submitLabel;
            d.dataset.submitLabel = options.submitLabel;
        }
        if (options.busyLabel !== undefined) {
            const btn = d.querySelector('[data-drawer-submit]');
            if (btn) btn.dataset.busyLabel = options.busyLabel;
        }
        if (options.icon !== undefined) {
            const icon = d.querySelector('[data-drawer-icon]');
            if (icon) icon.className = 'fa-solid ' + options.icon;
        }
        if (options.mode !== undefined) {
            d.dataset.mode = options.mode;
            const btn = d.querySelector('[data-drawer-submit]');
            if (btn) {
                btn.classList.toggle('axis-btn-danger', options.mode === 'delete');
                btn.classList.toggle('btn-primary-glass', options.mode !== 'delete');
            }
        }
    }

    function focusFirst(d) {
        const preferred = d.querySelector('[data-drawer-autofocus]')
            || d.querySelector('.axis-drawer__body input:not([type="hidden"]), .axis-drawer__body select, .axis-drawer__body textarea')
            || d.querySelector('[data-drawer-submit]')
            || d.querySelector('.axis-drawer__close');
        if (preferred) preferred.focus({ preventScroll: true });
    }

    function open(target, options = {}) {
        const d = resolve(target);
        if (!d) return;
        configure(d, options);
        clearErrors(d);
        setBusy(d, false);
        if (options.reset) {
            const form = d.querySelector('form') || (d.querySelector('.axis-drawer__panel').tagName === 'FORM' ? d.querySelector('.axis-drawer__panel') : null);
            if (form) form.reset();
        }

        if (!stack.includes(d)) {
            d._returnFocus = document.activeElement;
            stack.push(d);
            clearTimeout(d._hideTimer);
            d.hidden = false;
            void d.offsetWidth; // let the browser paint the closed state so the slide-in animates
            d.classList.add('is-open');
            if (window.SoundFX && options.sound !== false) SoundFX.modalOpen();
        }
        syncScrollLock();
        setTimeout(() => focusFirst(d), 60);
    }

    function close(target, options = {}) {
        const d = resolve(target);
        if (!d || !stack.includes(d)) return;

        stack.splice(stack.indexOf(d), 1);
        d.classList.remove('is-open');
        d._hideTimer = setTimeout(() => { if (!stack.includes(d)) d.hidden = true; }, TRANSITION_MS);
        syncScrollLock();

        if (window.SoundFX && options.sound !== false) SoundFX.modalClose();
        if (d.contains(document.activeElement)) document.activeElement.blur();
        if (d._returnFocus && d._returnFocus !== document.body && document.contains(d._returnFocus)) {
            d._returnFocus.focus({ preventScroll: true });
        }
        if (d._confirmResolve) {
            const resolveConfirm = d._confirmResolve;
            d._confirmResolve = null;
            resolveConfirm(false);
        }
        const onClose = d.dataset.onClose;
        if (onClose) {
            try { new Function(onClose).call(d); } catch (e) { console.error('[AxisDrawer] onClose', e); }
        }
        d.dispatchEvent(new CustomEvent('axis-drawer:closed', { bubbles: true }));
    }

    function setBusy(target, busy) {
        const d = resolve(target);
        if (!d) return;
        d.dataset.busy = busy ? 'true' : 'false';
        d.setAttribute('aria-busy', busy ? 'true' : 'false');
        const btn = d.querySelector('[data-drawer-submit]');
        if (!btn) return;
        const label = btn.querySelector('[data-drawer-submit-label]');
        const spinner = btn.querySelector('[data-drawer-spinner]');
        if (busy) {
            btn.disabled = true;
            if (label) {
                btn.dataset.idleLabel = label.textContent;
                label.textContent = btn.dataset.busyLabel || 'Working…';
            }
            if (spinner) spinner.classList.remove('hidden');
        } else {
            btn.disabled = false;
            if (label && btn.dataset.idleLabel) label.textContent = btn.dataset.idleLabel;
            if (spinner) spinner.classList.add('hidden');
        }
        d.querySelectorAll('[data-drawer-close]').forEach(el => {
            if (el.tagName === 'BUTTON') el.disabled = busy;
        });
    }

    function showAlert(target, message) {
        const d = resolve(target);
        const box = d && d.querySelector('[data-drawer-alert]');
        if (!box) return;
        box.textContent = message || '';
        box.classList.toggle('hidden', !message);
    }

    function clearErrors(target) {
        const d = resolve(target);
        if (!d) return;
        d.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; el.classList.add('hidden'); });
        d.querySelectorAll('[aria-invalid="true"]').forEach(el => el.removeAttribute('aria-invalid'));
        showAlert(d, '');
    }

    function clearFieldError(fieldEl) {
        const field = fieldEl.closest('[data-field]');
        if (!field) return;
        const err = field.querySelector('[data-error-for]');
        if (err) { err.textContent = ''; err.classList.add('hidden'); }
        field.querySelectorAll('[aria-invalid="true"]').forEach(el => el.removeAttribute('aria-invalid'));
    }

    /**
     * Show errors under their fields. `errors` is Laravel's shape: { field: [message, …] }.
     * Keys with no matching field are listed in the alert at the top of the body.
     */
    function showErrors(target, errors) {
        const d = resolve(target);
        if (!d || !errors) return;
        const unmatched = [];
        let firstInvalid = null;
        Object.entries(errors).forEach(([key, messages]) => {
            const message = Array.isArray(messages) ? messages[0] : String(messages);
            const errEl = d.querySelector(`[data-error-for="${CSS.escape(key)}"]`);
            if (!errEl) { unmatched.push(message); return; }
            errEl.textContent = message;
            errEl.classList.remove('hidden');
            const field = errEl.closest('[data-field]');
            const control = field && field.querySelector('input, select, textarea');
            if (control) {
                control.setAttribute('aria-invalid', 'true');
                if (!firstInvalid) firstInvalid = control;
            }
        });
        if (unmatched.length) showAlert(d, unmatched.join(' '));
        if (firstInvalid) firstInvalid.focus({ preventScroll: false });
    }

    /** Browser constraint validation (required, type="email", min/max…) rendered as inline errors. */
    function validate(target) {
        const d = resolve(target);
        if (!d) return true;
        const errors = {};
        d.querySelectorAll('.axis-drawer__body input, .axis-drawer__body select, .axis-drawer__body textarea').forEach(el => {
            if (!el.willValidate || el.checkValidity()) return;
            const key = el.closest('[data-field]')?.dataset.field;
            if (!key || errors[key]) return;
            errors[key] = [el.validity.valueMissing ? 'This field is required.' : el.validationMessage];
        });
        if (Object.keys(errors).length) {
            showErrors(d, errors);
            return false;
        }
        return true;
    }

    /**
     * Run a save action with the drawer's busy state. Rejects duplicate submits, validates first,
     * and maps a Laravel 422 response (err.status / err.body.errors from AxisAPI) onto the fields.
     *
     * @returns {Promise<boolean>} true when the action succeeded
     */
    async function submit(target, action, options = {}) {
        const d = resolve(target);
        if (!d || d.dataset.busy === 'true') return false;
        clearErrors(d);
        if (options.validate !== false && !validate(d)) {
            if (window.SoundFX) SoundFX.sodAlert();
            return false;
        }
        setBusy(d, true);
        try {
            await action();
            return true;
        } catch (err) {
            if (err && err.status === 422 && err.body && err.body.errors) {
                showErrors(d, err.body.errors);
            } else {
                showAlert(d, (err && err.message) ? `Couldn't save: ${err.message}` : "Couldn't save. Please try again.");
                console.error('[AxisDrawer]', err);
            }
            if (window.SoundFX) SoundFX.sodAlert();
            return false;
        } finally {
            setBusy(d, false);
        }
    }

    /**
     * Shared confirmation drawer (#axis-confirm-drawer). Resolves true when confirmed.
     */
    function confirm(options = {}) {
        const d = document.getElementById('axis-confirm-drawer');
        if (!d) return Promise.resolve(window.confirm(options.description || options.title || 'Are you sure?'));
        if (d._confirmResolve) d._confirmResolve(false);
        const mode = options.mode || 'delete';
        configure(d, {
            title: options.title || 'Are you sure?',
            description: options.description || null,
            breadcrumb: options.breadcrumb ?? null,
            submitLabel: options.confirmLabel || (mode === 'delete' ? 'Delete' : 'Confirm'),
            busyLabel: options.busyLabel || 'Working…',
            icon: options.icon || (mode === 'delete' ? 'fa-trash-can' : 'fa-circle-question'),
            mode,
        });
        const body = d.querySelector('[data-confirm-body]');
        if (body) {
            body.textContent = options.message || '';
            body.closest('[data-confirm-message]')?.classList.toggle('hidden', !options.message);
        }
        return new Promise(resolvePromise => {
            d._confirmResolve = resolvePromise;
            // Destructive confirmations start on Cancel, so Enter never deletes by accident
            d.querySelector('.axis-drawer__footer [data-drawer-close]')?.toggleAttribute('data-drawer-autofocus', mode === 'delete');
            open(d, { sound: false });
            if (window.SoundFX) (mode === 'delete' ? SoundFX.sodAlert() : SoundFX.modalOpen());
        });
    }

    /** Primary button of the confirm drawer. */
    function acceptConfirm() {
        const d = document.getElementById('axis-confirm-drawer');
        if (!d || !d._confirmResolve) return;
        const resolveConfirm = d._confirmResolve;
        d._confirmResolve = null;
        close(d, { sound: false });
        resolveConfirm(true);
    }

    // ---------------------------------------------------------------
    // Global wiring
    // ---------------------------------------------------------------
    document.addEventListener('click', (e) => {
        const closer = e.target.closest('[data-drawer-close]');
        // Users can't dismiss a drawer mid-save; code paths (e.g. a successful submit) close it directly
        if (closer && resolve(closer)?.dataset.busy !== 'true') close(closer);
    });

    document.addEventListener('input', (e) => {
        if (e.target.closest('[data-axis-drawer]')) clearFieldError(e.target);
    });
    document.addEventListener('change', (e) => {
        if (e.target.closest('[data-axis-drawer]')) clearFieldError(e.target);
    });

    document.addEventListener('keydown', (e) => {
        const d = topDrawer();
        if (!d) return;
        // Popovers opened from a drawer (e.g. the date picker) handle their own Escape / Tab
        if (e.target instanceof Element && e.target.closest('[data-own-keys]')) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (d.dataset.busy !== 'true') close(d);
            return;
        }
        if (e.key === 'Tab') {
            const panel = d.querySelector('.axis-drawer__panel');
            const items = [...panel.querySelectorAll(FOCUSABLE)].filter(el => el.offsetParent !== null);
            if (items.length === 0) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (!panel.contains(document.activeElement)) {
                e.preventDefault();
                first.focus();
            } else if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }, true);

    return { open, close, configure, isOpen, setBusy, submit, validate, showErrors, clearErrors, showAlert, confirm, acceptConfirm };
})();
