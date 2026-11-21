// =====================================================================
// STUDIO-GRADE ACOUSTIC UI SOUND DESIGN SYSTEM (`window.SoundFX`)
// 16 Distinct Action Profiles — Multi-Oscillator + Noise + Stereo Pan
// =====================================================================
window.SoundFX = (function() {
    let ctx = null;
    let noiseBuffer = null;
    let pinkNoiseBuffer = null;
    let muted = false;
    let masterVolume = 0.6;
    let clockTickEnabled = false;
    let preset = 'crystal'; // 'crystal' | 'mechanical' | 'minimal'
    const SETTINGS_KEY = 'axis.sound';
    const VOLUME_LEVELS = [0.3, 0.6, 1];
    let lastHoverTime = 0;
    let lastKeyTime = 0;
    let lastScrubTime = 0;
    let eqTimeout = null;
    let tickCounter = 0;

    const PRESET_MOD = {
        crystal:    { pitchMult: 1.0,  gainMult: 1.0,  filterMult: 1.15, label: 'Crystal' },
        mechanical: { pitchMult: 0.86, gainMult: 1.12, filterMult: 0.85, label: 'Clicky' },
        minimal:    { pitchMult: 1.12, gainMult: 0.55, filterMult: 0.75, label: 'Soft' }
    };

    function getContext() {
        if (muted) return null;
        // Browsers refuse audio before the first click/key press; skip until then instead of warning
        if (navigator.userActivation && !navigator.userActivation.hasBeenActive) return null;
        if (!ctx) {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                ctx = new AudioCtx();
                buildNoiseBuffers(ctx);
            }
        }
        if (ctx && ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }
        return ctx;
    }

    // Pre-compute white & pink noise buffers for realistic mechanical/air transients
    function buildNoiseBuffers(c) {
        const sr = c.sampleRate;
        noiseBuffer = c.createBuffer(1, sr * 0.25, sr);
        const white = noiseBuffer.getChannelData(0);
        for (let i = 0; i < white.length; i++) {
            white[i] = Math.random() * 2 - 1;
        }

        pinkNoiseBuffer = c.createBuffer(1, sr * 0.35, sr);
        const pink = pinkNoiseBuffer.getChannelData(0);
        let b0 = 0, b1 = 0, b2 = 0, b3 = 0, b4 = 0, b5 = 0, b6 = 0;
        for (let i = 0; i < pink.length; i++) {
            const w = Math.random() * 2 - 1;
            b0 = 0.99886 * b0 + w * 0.0555179;
            b1 = 0.99332 * b1 + w * 0.0750759;
            b2 = 0.96900 * b2 + w * 0.1538520;
            b3 = 0.86650 * b3 + w * 0.3104856;
            b4 = 0.55000 * b4 + w * 0.5329522;
            b5 = -0.7616 * b5 - w * 0.0168980;
            pink[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + w * 0.5362) * 0.04;
            b6 = w * 0.115926;
        }
    }

    function createStereoOut(c, panValue = 0) {
        if (c.createStereoPanner) {
            const panner = c.createStereoPanner();
            panner.pan.setValueAtTime(Math.max(-0.85, Math.min(0.85, panValue || 0)), c.currentTime);
            panner.connect(c.destination);
            return panner;
        }
        return c.destination;
    }

    function pulseEqualizer(label) {
        const eq = document.getElementById('sound-eq');
        if (eq && !muted) {
            eq.classList.add('sfx-playing');
            const bars = eq.querySelectorAll('.sfx-eq-bar');
            bars.forEach((b) => {
                const h = Math.floor(35 + Math.random() * 65);
                b.style.transform = `scaleY(${h / 100})`;
            });
            if (eqTimeout) clearTimeout(eqTimeout);
            eqTimeout = setTimeout(() => {
                eq.classList.remove('sfx-playing');
                bars.forEach(b => b.style.removeProperty('transform'));
            }, 230);
        }
        if (label) {
            const el = document.getElementById('lab-last-sound-name');
            if (el) el.textContent = label;
        }
    }

    function playNoiseBurst(c, dest, duration, filterType, freq, q, peakGain) {
        if (!noiseBuffer) buildNoiseBuffers(c);
        const now = c.currentTime;
        const src = c.createBufferSource();
        src.buffer = noiseBuffer;
        const flt = c.createBiquadFilter();
        flt.type = filterType;
        flt.frequency.setValueAtTime(freq, now);
        flt.Q.setValueAtTime(q, now);
        const g = c.createGain();
        g.gain.setValueAtTime(peakGain, now);
        g.gain.exponentialRampToValueAtTime(0.0005, now + duration);
        src.connect(flt);
        flt.connect(g);
        g.connect(dest);
        src.start(now);
        src.stop(now + duration + 0.005);
    }

    // 1. hoverTick(pan): Ultra-subtle 8ms air/velvet micro-brush
    function hoverTick(pan = -0.25) {
        const perfNow = performance.now();
        if (perfNow - lastHoverTime < 60) return;
        lastHoverTime = perfNow;

        const c = getContext();
        if (!c) return;
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, pan);
        const now = c.currentTime;

        playNoiseBurst(c, dest, 0.008, 'bandpass', 3200 * mod.filterMult, 4.5, 0.032 * masterVolume * mod.gainMult);

        const osc = c.createOscillator();
        const g = c.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1800 * mod.pitchMult, now);
        osc.frequency.exponentialRampToValueAtTime(1150 * mod.pitchMult, now + 0.008);
        g.gain.setValueAtTime(0.035 * masterVolume * mod.gainMult, now);
        g.gain.exponentialRampToValueAtTime(0.0005, now + 0.008);
        osc.connect(g);
        g.connect(dest);
        osc.start(now);
        osc.stop(now + 0.009);
    }

    /** Short, quiet key-tick for text entry. Throttled so fast typing stays comfortable. */
    function keyTick() {
        const perfNow = performance.now();
        if (perfNow - lastKeyTime < 35) return;
        lastKeyTime = perfNow;

        const c = getContext();
        if (!c) return;
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0.05);
        const now = c.currentTime;

        playNoiseBurst(c, dest, 0.006, 'highpass', 2400 * mod.filterMult, 1.4, 0.045 * masterVolume * mod.gainMult);

        const osc = c.createOscillator();
        const g = c.createGain();
        osc.type = preset === 'mechanical' ? 'square' : 'sine';
        osc.frequency.setValueAtTime(1250 * mod.pitchMult, now);
        osc.frequency.exponentialRampToValueAtTime(760 * mod.pitchMult, now + 0.012);
        g.gain.setValueAtTime(0.05 * masterVolume * mod.gainMult, now);
        g.gain.exponentialRampToValueAtTime(0.0005, now + 0.013);
        osc.connect(g);
        g.connect(dest);
        osc.start(now);
        osc.stop(now + 0.014);
    }

    // 2. buttonTap(pan): Crisp macOS/Linear-style tactile switch tap
    function buttonTap(pan = 0) {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Click');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, pan);
        const now = c.currentTime;

        // 1.5ms transient click
        playNoiseBurst(c, dest, 0.004, 'highpass', 1800, 1.2, 0.09 * masterVolume * mod.gainMult);

        // Pitch-drop body over 22ms
        const osc = c.createOscillator();
        const g = c.createGain();
        osc.type = preset === 'mechanical' ? 'square' : 'triangle';
        osc.frequency.setValueAtTime(950 * mod.pitchMult, now);
        osc.frequency.exponentialRampToValueAtTime(320 * mod.pitchMult, now + 0.022);

        g.gain.setValueAtTime(0.16 * masterVolume * mod.gainMult, now);
        g.gain.exponentialRampToValueAtTime(0.001, now + 0.023);

        osc.connect(g);
        g.connect(dest);
        osc.start(now);
        osc.stop(now + 0.025);
    }

    // 3. navSwitch(index): Pitched wooden/glass marimba note per sidebar tab
    function navSwitch(index = 0) {
        const c = getContext();
        if (!c) return;
        const scale = [392.00, 440.00, 493.88, 523.25, 587.33, 659.25, 783.99]; // G4..G5
        const baseFreq = scale[Math.abs(index) % scale.length];
        pulseEqualizer('Change page');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, -0.35);
        const now = c.currentTime;

        const osc = c.createOscillator();
        const sub = c.createOscillator();
        const flt = c.createBiquadFilter();
        const g = c.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(baseFreq * mod.pitchMult, now);
        osc.frequency.exponentialRampToValueAtTime(baseFreq * 1.02 * mod.pitchMult, now + 0.075);

        sub.type = 'triangle';
        sub.frequency.setValueAtTime(baseFreq * 2 * mod.pitchMult, now);

        flt.type = 'lowpass';
        flt.frequency.setValueAtTime(2400 * mod.filterMult, now);
        flt.frequency.exponentialRampToValueAtTime(680, now + 0.08);

        g.gain.setValueAtTime(0.001, now);
        g.gain.linearRampToValueAtTime(0.18 * masterVolume * mod.gainMult, now + 0.006);
        g.gain.exponentialRampToValueAtTime(0.001, now + 0.085);

        osc.connect(flt);
        sub.connect(flt);
        flt.connect(g);
        g.connect(dest);
        osc.start(now);
        sub.start(now);
        osc.stop(now + 0.09);
        sub.stop(now + 0.09);
    }

    // 4. dropdownSelect(): Two-step mechanical ratchet/detent click (600Hz -> 880Hz)
    function dropdownSelect() {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Pick an option');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0.1);
        const now = c.currentTime;

        [600, 880].forEach((freq, i) => {
            const t = now + i * 0.016;
            const osc = c.createOscillator();
            const g = c.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(freq * mod.pitchMult, t);
            osc.frequency.exponentialRampToValueAtTime(freq * 0.65 * mod.pitchMult, t + 0.014);
            g.gain.setValueAtTime(0.13 * masterVolume * mod.gainMult, t);
            g.gain.exponentialRampToValueAtTime(0.001, t + 0.015);
            osc.connect(g);
            g.connect(dest);
            osc.start(t);
            osc.stop(t + 0.016);
        });
    }

    // 5. toggleSwitch(isOn): Upward snap (520->1040Hz) or downward snap (880->440Hz)
    function toggleSwitch(isOn = true) {
        const c = getContext();
        if (!c) return;
        pulseEqualizer(`Toggle ${isOn ? 'on' : 'off'}`);
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0);
        const now = c.currentTime;

        playNoiseBurst(c, dest, 0.005, 'bandpass', 2400, 2.0, 0.07 * masterVolume * mod.gainMult);

        const osc = c.createOscillator();
        const g = c.createGain();
        osc.type = 'sine';
        const startF = isOn ? 520 : 880;
        const endF = isOn ? 1040 : 440;
        osc.frequency.setValueAtTime(startF * mod.pitchMult, now);
        osc.frequency.exponentialRampToValueAtTime(endF * mod.pitchMult, now + 0.042);

        g.gain.setValueAtTime(0.16 * masterVolume * mod.gainMult, now);
        g.gain.exponentialRampToValueAtTime(0.001, now + 0.045);

        osc.connect(g);
        g.connect(dest);
        osc.start(now);
        osc.stop(now + 0.048);
    }

    // 6. sliderScrub(percent): Pitch-mapped crystalline harmonic grain (330Hz at 0% -> 990Hz at 100%)
    function sliderScrub(percent = 50) {
        const perfNow = performance.now();
        if (perfNow - lastScrubTime < 45) return;
        lastScrubTime = perfNow;

        const c = getContext();
        if (!c) return;
        const pct = Math.max(0, Math.min(100, parseFloat(percent) || 0));
        const freq = 330 + (pct / 100) * 660;
        pulseEqualizer(`Slider ${Math.round(pct)}%`);
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, (pct / 50 - 1) * 0.35);
        const now = c.currentTime;

        const osc = c.createOscillator();
        const g = c.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq * mod.pitchMult, now);
        g.gain.setValueAtTime(0.11 * masterVolume * mod.gainMult, now);
        g.gain.exponentialRampToValueAtTime(0.001, now + 0.032);
        osc.connect(g);
        g.connect(dest);
        osc.start(now);
        osc.stop(now + 0.035);
    }

    // 7. modalOpen(): Ethereal frosted-glass ascending fifth chord (F4 -> C5 -> G5)
    function modalOpen() {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Open window');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0);
        const now = c.currentTime;

        [349.23, 523.25, 783.99].forEach((freq, idx) => {
            const t = now + idx * 0.038;
            const osc = c.createOscillator();
            const g = c.createGain();
            osc.type = idx === 2 ? 'triangle' : 'sine';
            osc.frequency.setValueAtTime(freq * mod.pitchMult, t);

            g.gain.setValueAtTime(0.001, t);
            g.gain.linearRampToValueAtTime(0.12 * masterVolume * mod.gainMult, t + 0.015);
            g.gain.exponentialRampToValueAtTime(0.0008, t + 0.19);

            osc.connect(g);
            g.connect(dest);
            osc.start(t);
            osc.stop(t + 0.2);
        });
    }

    // 8. modalClose(): Gentle descending muted glass release (660Hz -> 330Hz)
    function modalClose() {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Close window');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0);
        const now = c.currentTime;

        [659.25, 440.00, 329.63].forEach((freq, idx) => {
            const t = now + idx * 0.03;
            const osc = c.createOscillator();
            const flt = c.createBiquadFilter();
            const g = c.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(freq * mod.pitchMult, t);
            flt.type = 'lowpass';
            flt.frequency.setValueAtTime(1100 * mod.filterMult, t);

            g.gain.setValueAtTime(0.11 * masterVolume * mod.gainMult, t);
            g.gain.exponentialRampToValueAtTime(0.0008, t + 0.12);

            osc.connect(flt);
            flt.connect(g);
            g.connect(dest);
            osc.start(t);
            osc.stop(t + 0.13);
        });
    }

    // 9. timerStart(): Precision chronometer latch + ascending dual-chime (880Hz A5 -> 1318.5Hz E6)
    function timerStart() {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Start timer');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0.2);
        const now = c.currentTime;

        playNoiseBurst(c, dest, 0.006, 'bandpass', 3400, 3.0, 0.12 * masterVolume * mod.gainMult);

        [880.0, 1318.51].forEach((freq, idx) => {
            const t = now + 0.012 + idx * 0.055;
            const osc = c.createOscillator();
            const g = c.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq * mod.pitchMult, t);

            g.gain.setValueAtTime(0.16 * masterVolume * mod.gainMult, t);
            g.gain.exponentialRampToValueAtTime(0.001, t + 0.11);

            osc.connect(g);
            g.connect(dest);
            osc.start(t);
            osc.stop(t + 0.12);
        });
    }

    // 10. timerPause(): Chronometer disengagement — soft damped brake tone (B5 -> E5)
    function timerPause() {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Pause timer');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0.2);
        const now = c.currentTime;

        [987.77, 659.25].forEach((freq, idx) => {
            const t = now + idx * 0.055;
            const osc = c.createOscillator();
            const g = c.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq * mod.pitchMult, t);

            g.gain.setValueAtTime(0.15 * masterVolume * mod.gainMult, t);
            g.gain.exponentialRampToValueAtTime(0.001, t + 0.095);

            osc.connect(g);
            g.connect(dest);
            osc.start(t);
            osc.stop(t + 0.1);
        });
    }

    // 11. chronoTick(secondCount, forceAudition): Alternating 2800Hz "tick" / 2200Hz "tock" escapement
    function chronoTick(secondCount = 0, forceAudition = false) {
        if (!clockTickEnabled && !forceAudition) return;
        const c = getContext();
        if (!c) return;
        tickCounter = (secondCount !== undefined ? secondCount : tickCounter + 1);
        const isEven = (tickCounter % 2 === 0);
        const freq = isEven ? 2800 : 2200;
        if (forceAudition) {
            pulseEqualizer('Timer tick');
        }
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, isEven ? -0.15 : 0.15);
        const now = c.currentTime;

        playNoiseBurst(c, dest, 0.004, 'bandpass', freq * mod.pitchMult, 8.0, 0.065 * masterVolume * mod.gainMult);

        const osc = c.createOscillator();
        const g = c.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq * mod.pitchMult, now);
        osc.frequency.exponentialRampToValueAtTime(480, now + 0.009);
        g.gain.setValueAtTime(0.045 * masterVolume * mod.gainMult, now);
        g.gain.exponentialRampToValueAtTime(0.0005, now + 0.01);
        osc.connect(g);
        g.connect(dest);
        osc.start(now);
        osc.stop(now + 0.012);
    }

    // 12. taskComplete(): Rich 4-note arpeggiated crystalline bell (C5 -> E5 -> G5 -> C6)
    function taskComplete() {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Task done');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0);
        const now = c.currentTime;

        const notes = [523.25, 659.25, 783.99, 1046.50];
        notes.forEach((freq, idx) => {
            const t = now + idx * 0.055;
            const osc = c.createOscillator();
            const overtone = c.createOscillator();
            const g = c.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq * mod.pitchMult, t);
            overtone.type = 'triangle';
            overtone.frequency.setValueAtTime(freq * 2 * mod.pitchMult, t);

            g.gain.setValueAtTime(0.001, t);
            g.gain.linearRampToValueAtTime(0.15 * masterVolume * mod.gainMult, t + 0.01);
            g.gain.exponentialRampToValueAtTime(0.0008, t + 0.28);

            osc.connect(g);
            overtone.connect(g);
            g.connect(dest);
            osc.start(t);
            overtone.start(t);
            osc.stop(t + 0.3);
            overtone.stop(t + 0.3);
        });
    }

    // 13. createSuccess(): Warm uplifting harmonic confirmation triad (F4 -> A4 -> C5 -> F5)
    function createSuccess() {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Saved');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0);
        const now = c.currentTime;

        [349.23, 440.00, 523.25, 698.46].forEach((freq, idx) => {
            const t = now + idx * 0.045;
            const osc = c.createOscillator();
            const g = c.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(freq * mod.pitchMult, t);

            g.gain.setValueAtTime(0.14 * masterVolume * mod.gainMult, t);
            g.gain.exponentialRampToValueAtTime(0.001, t + 0.22);

            osc.connect(g);
            g.connect(dest);
            osc.start(t);
            osc.stop(t + 0.23);
        });
    }

    // 14. filterReset(): Filtered pink noise sweep (1200Hz -> 250Hz over 90ms)
    function filterReset() {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Clear filters');
        if (!pinkNoiseBuffer) buildNoiseBuffers(c);
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0);
        const now = c.currentTime;

        const src = c.createBufferSource();
        src.buffer = pinkNoiseBuffer;
        const flt = c.createBiquadFilter();
        flt.type = 'bandpass';
        flt.Q.setValueAtTime(2.2, now);
        flt.frequency.setValueAtTime(1250 * mod.filterMult, now);
        flt.frequency.exponentialRampToValueAtTime(240, now + 0.095);

        const g = c.createGain();
        g.gain.setValueAtTime(0.32 * masterVolume * mod.gainMult, now);
        g.gain.exponentialRampToValueAtTime(0.001, now + 0.095);

        src.connect(flt);
        flt.connect(g);
        g.connect(dest);
        src.start(now);
        src.stop(now + 0.1);
    }

    // 15. deleteTrash(): Damped low-frequency paper/matte thud (160Hz -> 55Hz sine + lowpass crunch)
    function deleteTrash() {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Delete');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0.15);
        const now = c.currentTime;

        playNoiseBurst(c, dest, 0.045, 'lowpass', 520, 1.5, 0.14 * masterVolume * mod.gainMult);

        const osc = c.createOscillator();
        const g = c.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(160 * mod.pitchMult, now);
        osc.frequency.exponentialRampToValueAtTime(52, now + 0.11);

        g.gain.setValueAtTime(0.24 * masterVolume * mod.gainMult, now);
        g.gain.exponentialRampToValueAtTime(0.001, now + 0.115);

        osc.connect(g);
        g.connect(dest);
        osc.start(now);
        osc.stop(now + 0.12);
    }

    // 16. sodAlert(): Subtle dual-pulse security/compliance chime for SoD rules or account locks
    function sodAlert() {
        const c = getContext();
        if (!c) return;
        pulseEqualizer('Heads up');
        const mod = PRESET_MOD[preset];
        const dest = createStereoOut(c, 0.25);
        const now = c.currentTime;

        [740.0, 587.33].forEach((freq, idx) => {
            const t = now + idx * 0.075;
            const osc = c.createOscillator();
            const g = c.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq * mod.pitchMult, t);

            g.gain.setValueAtTime(0.14 * masterVolume * mod.gainMult, t);
            g.gain.exponentialRampToValueAtTime(0.001, t + 0.09);

            osc.connect(g);
            g.connect(dest);
            osc.start(t);
            osc.stop(t + 0.095);
        });
    }

    /** Restore the saved sound settings (a per-browser preference; ignored if storage is unavailable). */
    function loadSettings() {
        try {
            const saved = JSON.parse(localStorage.getItem(SETTINGS_KEY) || 'null');
            if (!saved) return;
            muted = saved.muted === true;
            if (VOLUME_LEVELS.includes(saved.volume)) masterVolume = saved.volume;
            if (PRESET_MOD[saved.preset]) preset = saved.preset;
            clockTickEnabled = saved.tick === true;
        } catch (e) { /* keep defaults */ }
    }

    function saveSettings() {
        try {
            localStorage.setItem(SETTINGS_KEY, JSON.stringify({ muted, volume: masterVolume, preset, tick: clockTickEnabled }));
        } catch (e) { /* storage unavailable: settings last for this visit */ }
    }

    /** Reflect the current state on every sound control (header button + popover). */
    function syncUI() {
        const btn = document.getElementById('audio-menu-btn');
        if (btn) btn.dataset.muted = muted ? 'true' : 'false';
        const status = document.getElementById('sfx-status-text');
        if (status) status.textContent = muted ? 'Muted' : 'Sound on';

        const muteSwitch = document.getElementById('popover-mute-switch');
        if (muteSwitch) muteSwitch.setAttribute('aria-checked', muted ? 'false' : 'true');
        const sub = document.getElementById('popover-preset-subtitle');
        if (sub) sub.textContent = muted ? 'Off' : `${PRESET_MOD[preset].label} · ${Math.round(masterVolume * 100)}%`;
        const body = document.querySelector('.axis-sound-popover__body');
        if (body) body.classList.toggle('is-disabled', muted);

        const sel = document.getElementById('sfx-preset-select');
        if (sel) sel.value = preset;
        document.querySelectorAll('[data-preset-btn]').forEach(b => {
            const active = b.dataset.presetBtn === preset;
            b.classList.toggle('is-active', active);
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        document.getElementById('lab-muted-note')?.classList.toggle('hidden', !muted);
        document.getElementById('lab-playing-row')?.classList.toggle('hidden', muted);

        const disp = document.getElementById('sfx-vol-display');
        if (disp) disp.textContent = Math.round(masterVolume * 100) + '%';
        document.querySelectorAll('.vol-seg-btn').forEach(b => {
            const active = Math.abs(parseFloat(b.getAttribute('data-vol-btn')) - masterVolume) < 0.05;
            b.classList.toggle('is-active', active);
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        const tick = document.getElementById('sfx-tick-btn');
        if (tick) tick.setAttribute('aria-checked', clockTickEnabled ? 'true' : 'false');
    }

    function setPreset(newPreset) {
        if (!PRESET_MOD[newPreset]) return;
        preset = newPreset;
        saveSettings();
        syncUI();
        buttonTap(0);
        showToast(`Sound style: ${PRESET_MOD[newPreset].label}`);
    }

    function toggleMute() {
        muted = !muted;
        saveSettings();
        syncUI();
        if (!muted) createSuccess();
        showToast(muted ? 'Sound off' : 'Sound on');
    }

    function toggleClockTick() {
        clockTickEnabled = !clockTickEnabled;
        saveSettings();
        syncUI();
        chronoTick(0, true);
        showToast(clockTickEnabled ? 'Timer ticking on' : 'Timer ticking off');
    }

    function setVolume(val) {
        const wanted = parseFloat(val) || 0.6;
        masterVolume = VOLUME_LEVELS.reduce((best, level) => Math.abs(level - wanted) < Math.abs(best - wanted) ? level : best, 0.6);
        saveSettings();
        syncUI();
        buttonTap(0);
    }

    loadSettings();
    document.addEventListener('DOMContentLoaded', syncUI);

    return {
        // 16 Explicit Studio Profiles
        hoverTick,
        keyTick,
        buttonTap,
        navSwitch,
        dropdownSelect,
        toggleSwitch,
        sliderScrub,
        modalOpen,
        modalClose,
        timerStart,
        timerPause,
        chronoTick,
        taskComplete,
        createSuccess,
        filterReset,
        deleteTrash,
        sodAlert,
        // Backwards-compatible aliases
        playClick: buttonTap,
        playNav: navSwitch,
        playHover: hoverTick,
        playModalOpen: modalOpen,
        playModalClose: modalClose,
        playTimerStart: timerStart,
        playTimerPause: timerPause,
        playClockTick: chronoTick,
        playSuccess: createSuccess,
        playDelete: deleteTrash,
        // Controls
        setPreset,
        toggleMute,
        toggleClockTick,
        setVolume,
        syncUI,
        isMuted: () => muted
    };
})();

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

    // Global Interactive Button Ripple, Icon Lifecycle & Tactile Audio Feedback
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

        const interactive = e.target.closest('button, tr[onclick]');
        if (!interactive) return;
        if (interactive.hasAttribute('data-no-click-sfx')) return;

        const onclickAttr = interactive.getAttribute('onclick') || '';
        if (
            onclickAttr.includes('switchPage') ||
            onclickAttr.includes('openProjectTaskList') ||
            onclickAttr.includes('openModal') ||
            onclickAttr.includes('closeModal') ||
            onclickAttr.includes('toggleTaskStopwatch') ||
            onclickAttr.includes('resetTaskStopwatch') ||
            onclickAttr.includes('resetProjectFilters') ||
            onclickAttr.includes('toggleSidebar') ||
            onclickAttr.includes('toggleMemberLoginStatus') ||
            onclickAttr.includes('delete')
        ) {
            return;
        }
        const rect = interactive.getBoundingClientRect();
        const pan = ((rect.left + rect.width / 2) / window.innerWidth) * 1.4 - 0.7;
        SoundFX.buttonTap(pan);
    });

    // Global delegated mechanical ratchet detent sound for select dropdowns & date pickers
    document.addEventListener('change', (e) => {
        if (e.target.matches('select, input[type="date"]')) {
            if (e.target.hasAttribute('data-no-click-sfx')) return;
            const onchangeAttr = e.target.getAttribute('onchange') || '';
            if (onchangeAttr.includes('updateTaskStatus') || onchangeAttr.includes('updateProjectStatus')) {
                return;
            }
            SoundFX.dropdownSelect();
        }
    });

    // Spatial stereo hover brush on sidebar items, project rows, role rows, and primary buttons
    document.addEventListener('mouseover', (e) => {
        const hoverTarget = e.target.closest('.sidebar-link, #projects-tbody tr, .btn-primary-glass');
        if (hoverTarget) {
            const rect = hoverTarget.getBoundingClientRect();
            const pan = ((rect.left + rect.width / 2) / window.innerWidth) * 1.4 - 0.7;
            SoundFX.hoverTick(pan);
        }
    });
});
