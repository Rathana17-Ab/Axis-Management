@extends('layouts.auth')

@section('title', $mode === 'register' ? 'Create account — Axis Management' : 'Sign in — Axis Management')

@section('content')
    <main class="flex-1 flex items-center justify-center px-4 py-10">
        <section class="axis-modal w-full max-w-md overflow-hidden" aria-labelledby="login-title">
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
                <nav class="grid grid-cols-2 gap-1 rounded-xl bg-slate-800/50 p-1" aria-label="Account access">
                    <a href="{{ route('login') }}" @class([
                        'rounded-lg px-3 py-2 text-center text-xs font-semibold',
                        'bg-sky-500/15 text-white' => $mode === 'login',
                        'text-slate-400 hover:text-white' => $mode !== 'login',
                    ]) @if ($mode === 'login') aria-current="page" @endif>Sign in</a>
                    <a href="{{ route('register') }}" @class([
                        'rounded-lg px-3 py-2 text-center text-xs font-semibold',
                        'bg-sky-500/15 text-white' => $mode === 'register',
                        'text-slate-400 hover:text-white' => $mode !== 'register',
                    ]) @if ($mode === 'register') aria-current="page" @endif>Create account</a>
                </nav>

                @if ($mode === 'register')
                    <h1 id="login-title" class="text-base font-bold text-white mt-4">Create account</h1>
                    <p class="text-xs text-slate-400 mt-1">Choose your sign-in details to start using Axis.</p>
                @else
                    <h1 id="login-title" class="text-base font-bold text-white mt-4">Sign in</h1>
                    <p class="text-xs text-slate-400 mt-1">Use your Axis account to continue.</p>
                @endif

                @if ($errors->any())
                    <div class="mt-4 text-xs text-rose-200 bg-rose-500/10 border border-rose-400/30 rounded-xl px-3 py-2" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if ($mode === 'register')
                    <form method="POST" action="{{ route('register.store') }}" class="mt-5 space-y-4">
                        @csrf

                        <div>
                            <label for="register-name" class="block text-xs font-semibold text-slate-400 mb-1.5">Name</label>
                            <input id="register-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" autofocus
                                   aria-invalid="@error('name') true @else false @enderror"
                                   class="axis-input w-full h-10 px-3 text-sm">
                            @error('name')
                                <p class="axis-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="register-email" class="block text-xs font-semibold text-slate-400 mb-1.5">Email</label>
                            <input id="register-email" name="email" type="email" value="{{ old('email') }}" autocomplete="username"
                                   aria-invalid="@error('email') true @else false @enderror"
                                   class="axis-input w-full h-10 px-3 text-sm">
                            @error('email')
                                <p class="axis-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="register-password" class="block text-xs font-semibold text-slate-400 mb-1.5">Password</label>
                            <input id="register-password" name="password" type="password" autocomplete="new-password"
                                   aria-invalid="@error('password') true @else false @enderror"
                                   class="axis-input w-full h-10 px-3 text-sm">
                            @error('password')
                                <p class="axis-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="register-password-confirmation" class="block text-xs font-semibold text-slate-400 mb-1.5">Confirm password</label>
                            <input id="register-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                                   class="axis-input w-full h-10 px-3 text-sm">
                        </div>

                        <button type="submit" class="btn-primary-glass h-10 w-full text-xs font-semibold">Create account</button>
                    </form>

                    <p class="mt-4 text-xs text-slate-400">
                        Already have an account?
                        <a href="{{ route('login') }}" class="font-semibold text-sky-300 hover:text-sky-200">Sign in</a>
                    </p>
                @else
                    <form method="POST" action="{{ route('login.store') }}" class="mt-5 space-y-4">
                        @csrf

                        <div>
                            <label for="login-email" class="block text-xs font-semibold text-slate-400 mb-1.5">Email</label>
                            <input id="login-email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus
                                   aria-invalid="@error('email') true @else false @enderror"
                                   class="axis-input w-full h-10 px-3 text-sm">
                            @error('email')
                                <p class="axis-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="login-password" class="block text-xs font-semibold text-slate-400 mb-1.5">Password</label>
                            <input id="login-password" name="password" type="password" autocomplete="current-password"
                                   aria-invalid="@error('password') true @else false @enderror"
                                   class="axis-input w-full h-10 px-3 text-sm">
                            @error('password')
                                <p class="axis-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-center gap-2 text-xs text-slate-300">
                            <input type="checkbox" name="remember" value="1" class="w-3.5 h-3.5 rounded accent-sky-500">
                            Remember me
                        </label>

                        <button type="submit" class="btn-primary-glass h-10 w-full text-xs font-semibold">Sign in</button>
                    </form>

                    <p class="mt-4 text-xs text-slate-400">
                        Need an account?
                        <a href="{{ route('register') }}" class="font-semibold text-sky-300 hover:text-sky-200">Create one</a>
                    </p>
                @endif
            </div>
        </section>
    </main>
@endsection
