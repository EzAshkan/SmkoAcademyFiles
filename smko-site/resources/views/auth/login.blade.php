@extends('layouts.app', ['title' => 'Log in'])

@section('content')
<div class="flex min-h-[70vh] flex-col items-center justify-center">
    <a href="/" class="mb-6"><x-logo class="h-12 w-auto text-gray-700" /></a>

    <form method="POST" action="{{ route('login') }}" class="w-full max-w-md space-y-4 rounded-lg bg-white px-6 py-6 shadow-md">
        @csrf
        <div>
            <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">Phone</label>
            <input id="phone" name="phone" type="tel" inputmode="numeric" value="{{ old('phone') }}" required autofocus autocomplete="username"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
        </div>
        @error('phone')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600"> Remember me
        </label>
        <div class="flex justify-end">
            <button class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">Log in</button>
        </div>
    </form>
</div>
@endsection
