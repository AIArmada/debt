<?php

namespace App\Features\PromiseTracker\EntryPoints\Http\Controllers;

use App\Features\PromiseTracker\Actions\Data\UpdateUserLocaleData;
use App\Features\PromiseTracker\Actions\Profiles\UpdateUserLocale;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class LocaleController
{
    public function __invoke(Request $request, string $locale, UpdateUserLocale $updateUserLocale): RedirectResponse
    {
        if (! in_array($locale, ['en', 'ms'], true)) {
            throw ValidationException::withMessages(['locale' => __('app.locale_invalid')]);
        }

        $user = $request->user();
        if ($user instanceof User) {
            $updateUserLocale->handle($user, UpdateUserLocaleData::fromInput(['locale' => $locale]));
        } else {
            Session::put('locale', $locale);
        }

        return redirect()->back();
    }
}
