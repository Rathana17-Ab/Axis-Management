                <!-- ===================================================================== -->
                <!-- PAGE: PROFILE (`profile`) — details for the signed-in team member     -->
                <!-- ===================================================================== -->
                <div id="page-profile" @class(['app-page', 'active-page' => $initialPage === 'profile', 'space-y-5'])>
                    <div class="flex flex-col gap-1 pt-1">
                        <h1 class="text-xl font-bold text-white tracking-tight">Profile</h1>
                        <p class="text-xs text-slate-300/90">Your account details and sign-in access.</p>
                    </div>

                    <section class="ent-card" aria-labelledby="profile-heading">
                        @if (session('status'))
                            <div class="border-b border-sky-400/15 px-5 py-3 text-xs text-emerald-300" role="status">
                                {{ session('status') }}
                            </div>
                        @endif

                        <div class="flex flex-col gap-6 px-5 py-5 md:flex-row md:items-start">
                            <div class="w-full md:w-28 shrink-0">
                                @if ($currentUser?->avatarUrl)
                                    <img src="{{ $currentUser->avatarUrl }}"
                                         alt="{{ $currentUser->name }}'s profile photo"
                                         class="aspect-square w-28 rounded-2xl border border-sky-400/20 bg-sky-500/10 object-cover md:w-full">
                                @else
                                    <span class="axis-sidebar__avatar !h-28 !w-28 !rounded-2xl !text-2xl" aria-hidden="true">
                                        {{ $currentUser?->initials }}
                                    </span>
                                @endif

                                <form method="POST" action="{{ route('admin.profile.photo.store') }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                                    @csrf
                                    <label for="profile-photo" class="axis-field__label">Profile photo</label>
                                    <input id="profile-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"
                                           class="axis-input !h-auto px-3 py-2 text-xs">
                                    <p class="axis-field__hint">JPG, PNG or WebP. Max 2 MB.</p>
                                    @error('photo')
                                        <p class="axis-field__error">{{ $message }}</p>
                                    @enderror
                                    <button type="submit" id="profile-photo-upload" class="btn-glass h-9 w-full text-xs font-semibold">Upload photo</button>
                                </form>

                                @if ($currentUser?->avatar_path)
                                    <form method="POST" action="{{ route('admin.profile.photo.destroy') }}" class="mt-2">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-glass h-9 w-full text-xs font-semibold text-rose-300">Remove photo</button>
                                    </form>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h2 id="profile-heading" class="text-base font-bold text-white truncate">{{ $currentUser?->name }}</h2>
                                        <p class="text-xs text-slate-400 mt-1">{{ $currentUser?->job_title ?: 'Team member' }}</p>
                                    </div>
                                    <span @class([
                                        'inline-flex items-center h-6 px-2 rounded-full text-xs font-medium border',
                                        'text-emerald-300 bg-emerald-500/10 border-emerald-400/25' => $currentUser?->status?->value === 'active',
                                        'text-amber-300 bg-amber-500/10 border-amber-400/25' => $currentUser?->status?->value !== 'active',
                                    ])>{{ $currentUser?->status ? ucfirst($currentUser->status->value) : '—' }}</span>
                                </div>

                                <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4 border-t border-sky-400/15 pt-5 sm:grid-cols-2">
                                    <div class="min-w-0">
                                        <dt class="axis-detail__label">Login username</dt>
                                        <dd class="axis-detail__value break-all">{{ $currentUser?->email ?: '—' }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="axis-detail__label">Department</dt>
                                        <dd class="axis-detail__value">{{ ($currentUser?->departmentRecord?->name ?? $currentUser?->department) ?: '—' }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="axis-detail__label">Position</dt>
                                        <dd class="axis-detail__value">{{ $currentUser?->positionRecord?->name ?? ($currentUser?->position?->value === 'leader' ? 'Leader' : 'Developer') }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="axis-detail__label">Sign-in access</dt>
                                        <dd class="axis-detail__value">{{ $currentUser?->can_login ? 'Enabled' : 'Locked' }}</dd>
                                    </div>
                                    <div class="min-w-0 sm:col-span-2">
                                        <dt class="axis-detail__label">Description</dt>
                                        <dd class="axis-detail__value">{{ $currentUser?->description ?: '—' }}</dd>
                                    </div>
                                </dl>

                                <form method="POST" action="{{ route('admin.profile.password.update') }}" class="mt-5 border-t border-sky-400/15 pt-5">
                                    @csrf
                                    @method('PATCH')

                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-sm font-bold text-white">Change password</h3>
                                            <p class="text-xs text-slate-400 mt-1">Confirm your current password before choosing a new one.</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div class="min-w-0">
                                            <label for="current-password" class="axis-field__label">Current password</label>
                                            <input id="current-password" name="current_password" type="password" autocomplete="current-password"
                                                   aria-invalid="@error('current_password') true @else false @enderror"
                                                   class="axis-input h-10 px-3 text-sm">
                                            @error('current_password')
                                                <p class="axis-field__error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="min-w-0">
                                            <label for="new-password" class="axis-field__label">New password</label>
                                            <input id="new-password" name="password" type="password" autocomplete="new-password"
                                                   aria-invalid="@error('password') true @else false @enderror"
                                                   class="axis-input h-10 px-3 text-sm">
                                            @error('password')
                                                <p class="axis-field__error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="min-w-0">
                                            <label for="new-password-confirmation" class="axis-field__label">Confirm new password</label>
                                            <input id="new-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                                                   class="axis-input h-10 px-3 text-sm">
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                                        <p class="axis-field__hint">Use at least 8 characters.</p>
                                        <button type="submit" id="profile-password-update" class="btn-primary-glass h-10 px-4 text-xs font-semibold">Update password</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-sky-400/15 px-5 py-4">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" id="profile-sign-out" class="btn-glass h-10 px-4 text-xs font-semibold flex items-center gap-2" aria-label="Sign out">
                                    <i class="fa-solid fa-right-from-bracket text-[11px] text-rose-300" aria-hidden="true"></i>
                                    <span>Sign out</span>
                                </button>
                            </form>
                        </div>
                    </section>
                </div>
