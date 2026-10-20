<?php

use App\Concerns\ProfileValidationRules;
use App\Actions\Promises\Data\UpdateUserLocaleData;
use App\Actions\Promises\UpdateUserLocale;
use App\Domain\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $locale = 'en';
    public bool $reminderNotificationsEnabled = true;
    public bool $shareAcceptedNotificationsEnabled = true;
    public bool $shareDisputedNotificationsEnabled = true;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->locale = Auth::user()->locale;
        $preference = NotificationPreference::query()->firstOrCreate(
            ['user_id' => Auth::id(), 'type' => NotificationType::ReminderDue],
            ['enabled' => true],
        );
        $this->reminderNotificationsEnabled = $preference->enabled;
        $this->shareAcceptedNotificationsEnabled = NotificationPreference::query()->firstOrCreate(
            ['user_id' => Auth::id(), 'type' => NotificationType::ShareAccepted],
            ['enabled' => true],
        )->enabled;
        $this->shareDisputedNotificationsEnabled = NotificationPreference::query()->firstOrCreate(
            ['user_id' => Auth::id(), 'type' => NotificationType::ShareDisputed],
            ['enabled' => true],
        )->enabled;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function updateLocale(UpdateUserLocale $updateUserLocale): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $user = $updateUserLocale->handle($user, UpdateUserLocaleData::fromInput(['locale' => $this->locale]));
        $this->locale = $user->locale;

        Flux::toast(variant: 'success', text: __('app.language_saved'));
    }

    public function updatedReminderNotificationsEnabled(bool $enabled): void
    {
        NotificationPreference::query()->updateOrCreate(
            ['user_id' => Auth::id(), 'type' => NotificationType::ReminderDue],
            ['enabled' => $enabled],
        );

        Flux::toast(variant: 'success', text: $enabled ? __('Reminder notifications enabled.') : __('Reminder notifications disabled.'));
    }

    public function updatedShareAcceptedNotificationsEnabled(bool $enabled): void
    {
        $this->updateNotificationPreference(NotificationType::ShareAccepted, $enabled, 'Share acceptance notifications');
    }

    public function updatedShareDisputedNotificationsEnabled(bool $enabled): void
    {
        $this->updateNotificationPreference(NotificationType::ShareDisputed, $enabled, 'Share dispute notifications');
    }

    private function updateNotificationPreference(NotificationType $type, bool $enabled, string $label): void
    {
        NotificationPreference::query()->updateOrCreate(
            ['user_id' => Auth::id(), 'type' => $type],
            ['enabled' => $enabled],
        );

        Flux::toast(variant: 'success', text: $enabled ? $label.' enabled.' : $label.' disabled.');
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('home', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification" wire:loading.attr="disabled">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        <form wire:submit="updateLocale" class="my-6 w-full space-y-4 border-t border-zinc-200/80 pt-6 dark:border-zinc-700/80">
            <flux:heading size="sm">{{ __('app.language') }}</flux:heading>
            <flux:select wire:model="locale" :label="__('app.language')" required>
                <flux:select.option value="en">English</flux:select.option>
                <flux:select.option value="ms">Bahasa Melayu</flux:select.option>
            </flux:select>
            <flux:button variant="primary" type="submit" wire:loading.attr="disabled">{{ __('app.save_language') }}</flux:button>
        </form>

        <div class="border-t border-zinc-200/80 pt-6 dark:border-zinc-700/80">
            <flux:heading size="sm">Notifications</flux:heading>
            <flux:text class="mt-1 text-sm">Choose whether due reminder notifications appear in your inbox and bell.</flux:text>
            <label class="mt-4 flex items-start gap-3 text-sm">
                <input wire:model.live="reminderNotificationsEnabled" type="checkbox" class="mt-1 rounded border-zinc-300 text-emerald-600" />
                <span><span class="font-medium">Due reminders</span><span class="block text-zinc-500">Notify me when a reminder reaches its due date.</span></span>
            </label>
            <label class="mt-4 flex items-start gap-3 text-sm">
                <input wire:model.live="shareAcceptedNotificationsEnabled" type="checkbox" class="mt-1 rounded border-zinc-300 text-emerald-600" />
                <span><span class="font-medium">Share accepted</span><span class="block text-zinc-500">Notify me when someone accepts shared terms.</span></span>
            </label>
            <label class="mt-4 flex items-start gap-3 text-sm">
                <input wire:model.live="shareDisputedNotificationsEnabled" type="checkbox" class="mt-1 rounded border-zinc-300 text-emerald-600" />
                <span><span class="font-medium">Share disputed</span><span class="block text-zinc-500">Notify me when someone disputes a shared promise.</span></span>
            </label>
        </div>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
