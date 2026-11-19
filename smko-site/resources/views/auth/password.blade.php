@extends('layouts.app', ['title' => 'Change password'])

@section('content')
<div class="mx-auto mt-6 max-w-md">
    <h1 class="mb-2 text-xl font-semibold">{{ $forced ? 'Choose your password' : 'Change password' }}</h1>
    @if ($forced)
        <p class="mb-4 text-sm text-slate-600">Welcome to the new SMKO Files. Please choose your own password before you continue. You'll use it from now on.</p>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @unless ($forced)
            <div>
                <label for="current_password" class="mb-1 block text-sm font-medium">Current password</label>
                <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                       class="block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endunless
        <div>
            <label for="password" class="mb-1 block text-sm font-medium">New password</label>
            <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
            <p class="mt-1 text-xs text-slate-500">At least 6 characters.</p>
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Repeat new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
        </div>
        <button class="w-full rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">Save password</button>
    </form>
</div>
@endsection
