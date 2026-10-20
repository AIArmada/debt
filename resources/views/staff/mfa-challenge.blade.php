<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff verification</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <main class="mx-auto flex min-h-screen max-w-lg items-center px-6 py-12">
        <section class="w-full rounded-xl bg-white p-8 shadow">
            <h1 class="text-xl font-semibold">Verify your staff sign-in</h1>
            <p class="mt-2 text-sm text-gray-600">Enter a current code from your authenticator app, or use one recovery code.</p>
            <form method="POST" action="{{ route('staff.mfa.verify') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="code" class="block text-sm font-medium">Authenticator code</label>
                    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" class="mt-1 block w-full rounded-md border-gray-300" />
                    @error('code') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="recovery_code" class="block text-sm font-medium">Recovery code</label>
                    <input id="recovery_code" name="recovery_code" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300" />
                    @error('recovery_code') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="w-full rounded-md bg-amber-600 px-4 py-2 font-medium text-white">Continue to staff admin</button>
            </form>
        </section>
    </main>
</body>
</html>
