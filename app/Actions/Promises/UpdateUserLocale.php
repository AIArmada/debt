<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\UpdateUserLocaleData;
use App\Models\User;
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
