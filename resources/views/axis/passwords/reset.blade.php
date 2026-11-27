@extends('layouts.auth')

@section('title', 'Choose a new password — Axis Management')

@section('content')
    <main class="flex-1 flex items-center justify-center px-4 py-10">
        <section class="axis-modal w-full max-w-md overflow-hidden" aria-labelledby="password-reset-title">
            <div class="border-b border-sky-400/15 px-5 py-5">
                <div class="flex items-center gap-3">
                    <span class="axis-sidebar__logo !w-10 !h-10" aria-hidden="true">
                        <img src="{{ asset('images/brand/axis-symbol-white.png') }}?v={{ filemtime(public_path('images/brand/axis-symbol-white.png')) }}" alt="" width="40" height="40" class="block w-10 h-10 object-contain">
                    </span>
                    <div class="min-w-0">
                        <div class="text-[16px] font-semibold text-white leading-tight whitespace-nowrap">Axis Management</div>
                        <div class="text-xs text-slate-400 mt-0.5">Project hub</div>
                    </div>
                </div>
            </div>

            <div class="px-5 py-5">
                @if (session('status'))
                    <div class="mb-4 text-xs text-emerald-300 border border-emerald-400/25 bg-emerald-500/10 rounded-xl px-3 py-2" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 text-xs text-rose-200 bg-rose-500/10 border border-rose-400/30 rounded-xl px-3 py-2" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <h1 id="password-reset-title" class="text-base font-bold text-white">Choose a new password</h1>
                <p class="text-xs text-slate-400 mt-1">Enter the code sent to your email, then choose a new password.</p>

                <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="password-reset-email" class="block text-xs font-semibold text-slate-400 mb-1.5">Email</label>
                        <input id="password-reset-email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" readonly
                               aria-invalid="@error('email') true @else false @enderror"
                               class="axis-input w-full h-10 px-3 text-sm">
                        @error('email')
                            <p class="axis-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password-reset-code" class="block text-xs font-semibold text-slate-400 mb-1.5">Verification code</label>
                        <input id="password-reset-code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus
                               aria-invalid="@error('code') true @else false @enderror"
                               class="axis-input w-full h-10 px-3 text-sm tracking-[0.3em]">
                        @error('code')
                            <p class="axis-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password-reset-password" class="block text-xs font-semibold text-slate-400 mb-1.5">New password</label>
                        <input id="password-reset-password" name="password" type="password" autocomplete="new-password"
                               aria-invalid="@error('password') true @else false @enderror"
                               class="axis-input w-full h-10 px-3 text-sm">
                        @error('password')
                            <p class="axis-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password-reset-confirmation" class="block text-xs font-semibold text-slate-400 mb-1.5">Confirm new password</label>
                        <input id="password-reset-confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                               class="axis-input w-full h-10 px-3 text-sm">
                    </div>

                    <button type="submit" class="btn-primary-glass h-10 w-full text-xs font-semibold">Reset password</button>
                </form>

                <div class="mt-4 flex items-center justify-between gap-3 text-xs text-slate-400">
                    <span>Didn’t receive the code?</span>
                    <form method="POST" action="{{ route('password.resend') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        <button type="submit" class="font-semibold text-sky-300 hover:text-sky-200">Resend code</button>
                    </form>
                </div>
                <p class="mt-1 text-[11px] text-slate-500">Codes expire after {{ $expireMinutes }} minutes. Wait {{ $resendSeconds }} seconds between requests.</p>

                <p class="mt-4 text-xs text-slate-400">
                    <a href="{{ route('login') }}" class="font-semibold text-sky-300 hover:text-sky-200">Back to sign in</a>
                </p>
            </div>
        </section>
    </main>
@endsection
