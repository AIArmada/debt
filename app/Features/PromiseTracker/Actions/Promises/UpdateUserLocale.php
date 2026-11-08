<?php

namespace App\Features\PromiseTracker\Actions\Promises;

use App\Features\PromiseTracker\Actions\Promises\Data\UpdateUserLocaleData;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Support\Facades\Session;

class UpdateUserLocale
{
    public function handle(User $user, UpdateUserLocaleData $data): User
    {
        $user->forceFill(['locale' => $data->locale])->save();
        Session::put('locale', $data->locale);

        return $user->refresh();
    }
}
