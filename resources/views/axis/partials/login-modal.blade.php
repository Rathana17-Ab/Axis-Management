    {{-- Switch user: quick switch to a teammate, or sign in with email + password (handleManualSignIn). --}}
    <div id="login-modal" class="axis-sound-lab-overlay fixed inset-0 z-[250] hidden items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="login-modal-title" onclick="if (event.target === this) closeModal('login-modal')">
        <div class="axis-sound-lab w-full max-w-md">
            <div class="flex items-start justify-between gap-3 pb-4 border-b border-white/[0.08]">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="axis-sound-lab__icon" aria-hidden="true"><i class="fa-solid fa-user-group text-sm"></i></span>
                    <div class="min-w-0">
                        <h2 id="login-modal-title" class="text-sm font-bold text-white">Switch user</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Pick a teammate, or sign in with an email.</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('login-modal')" class="axis-topbar-icon-btn shrink-0" aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            @if ($quickSwitchMembers->isNotEmpty())
                <div class="pt-4">
                    <div id="quick-switch-label" class="text-xs font-semibold text-slate-400 mb-2">Quick switch</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" role="group" aria-labelledby="quick-switch-label">
                        @foreach ($quickSwitchMembers as $member)
                            <button type="button" onclick="quickSwitchSession(@js($member->email))" data-switch-email="{{ $member->email }}" class="axis-switch-card">
                                <span class="axis-switch-card__avatar">{{ $member->initials }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-xs font-semibold text-white truncate">{{ $member->name }}</span>
                                    <span class="block text-xs text-slate-400 truncate">{{ $member->job_title }}</span>
                                </span>
                                <span class="axis-switch-card__you">You</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <form onsubmit="handleManualSignIn(event)" class="pt-4 mt-4 border-t border-white/[0.08] space-y-3" novalidate>
                <div>
                    <label for="login-email-input" class="block text-xs font-semibold text-slate-400 mb-1.5">Email</label>
                    <input type="email" id="login-email-input" value="{{ $currentUser?->email }}" autocomplete="username" class="axis-input w-full h-10 px-3 text-sm" required data-autofocus>
                </div>
                <div>
                    <label for="login-pass-input" class="block text-xs font-semibold text-slate-400 mb-1.5">Password</label>
                    <input type="password" id="login-pass-input" placeholder="••••••••" autocomplete="current-password" class="axis-input w-full h-10 px-3 text-sm" required>
                </div>
                <div id="login-error-msg" class="hidden text-xs text-rose-200 bg-rose-500/10 border border-rose-400/30 rounded-xl px-3 py-2" role="alert"></div>
                <div class="pt-1 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('login-modal')" class="btn-glass h-10 px-4 text-xs font-semibold">Cancel</button>
                    <button type="submit" id="login-submit-btn" class="btn-primary-glass h-10 px-5 text-xs font-semibold">Sign in</button>
                </div>
            </form>
        </div>
    </div>
