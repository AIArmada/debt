<?php

namespace Tests\Feature;

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\CreateRecordShare;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\CreateRecordShareData;
use App\Domain\Enums\Direction;
use App\Domain\Presenters\HijriDatePresenter;
use App\Livewire\Parties\Index as PeopleIndex;
use App\Livewire\Profiles\Settings as ProfileSettings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use IntlCalendar;
use IntlDateFormatter;
use Livewire\Livewire;
use Tests\TestCase;

class I18nTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_locale_renders_malay_and_the_profile_switcher_persists_it(): void
    {
        $user = User::factory()->create(['locale' => 'ms', 'email_verified_at' => now()]);
        $profile = $user->financialProfiles()->firstOrFail();
        app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
            'partyName' => 'Bahasa counterparty',
            'direction' => Direction::Payable->value,
            'amount' => '12.00',
        ], (string) $profile->base_currency));

        $this->actingAs($user)
            ->get(route('promises.index', $profile))
            ->assertOk()
            ->assertSee('Individu')
            ->assertSee('Janji baharu');

        Livewire::actingAs($user)
            ->test(PeopleIndex::class, ['profile' => $profile])
            ->set('name', 'Individu baharu')
            ->call('save')
            ->assertSee('Individu telah disimpan.');

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('locale', 'en')
            ->call('updateLocale')
            ->assertHasNoErrors();

        self::assertSame('en', $user->fresh()->locale);
        $this->actingAs($user->fresh())
            ->get(route('promises.index', $profile))
            ->assertOk()
            ->assertSee('People')
            ->assertSee('New promise');
    }

    public function test_share_locale_override_and_guest_legal_locale_are_applied(): void
    {
        $owner = User::factory()->create(['locale' => 'ms']);
        $profile = $owner->financialProfiles()->firstOrFail();
        $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
            'partyName' => 'Shared counterparty',
            'direction' => Direction::Payable->value,
            'amount' => '8.00',
        ], (string) $profile->base_currency));
        $share = app(CreateRecordShare::class)->handle($owner, $record, CreateRecordShareData::fromInput([]));

        $this->get(route('shares.show', ['token' => $share['token']]))
            ->assertOk()
            ->assertSee('Janji dikongsi')
            ->assertSee('Baki');
        $this->get(route('shares.show', ['token' => $share['token'], 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Shared promise')
            ->assertSee('Balance');

        $this->from(route('legal.privacy'))->get(route('locale.switch', ['locale' => 'ms']))->assertRedirect();
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Dasar Privasi');
    }

    public function test_hijri_dates_match_intl_and_render_when_enabled(): void
    {
        $date = Carbon::parse('2026-10-17', 'UTC')->startOfDay();
        $presenter = app(HijriDatePresenter::class);
        $intlAvailable = class_exists(IntlCalendar::class) && class_exists(IntlDateFormatter::class);
        if ($intlAvailable) {
            $calendar = IntlCalendar::createInstance(null, 'en@calendar=islamic');
            if ($calendar instanceof IntlCalendar) {
                $formatter = new IntlDateFormatter(
                    'en@calendar=islamic',
                    IntlDateFormatter::NONE,
                    IntlDateFormatter::NONE,
                    'UTC',
                    $calendar,
                    'd MMMM y',
                );
                $expectedHijriDate = $formatter->format($date);
                self::assertIsString($expectedHijriDate);
                self::assertSame($expectedHijriDate, $presenter->format($date));
            } else {
                self::assertNull($presenter->format($date));
            }
        } else {
            self::assertNull($presenter->format($date));
        }
        self::assertNull($presenter->format($date, false));

        $user = User::factory()->create(['email_verified_at' => now()]);
        $profile = $user->financialProfiles()->firstOrFail();
        Livewire::actingAs($user)
            ->test(ProfileSettings::class, ['profile' => $profile])
            ->set('showHijri', true)
            ->call('save')
            ->assertHasNoErrors();
        $profile->refresh();
        $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
            'partyName' => 'Hijri date counterparty',
            'direction' => Direction::Payable->value,
            'amount' => '5.00',
            'dueOn' => $date->toDateString(),
        ], (string) $profile->base_currency));
        $response = $this->actingAs($user)->get(route('promises.show', [$profile, $record]));
        $response->assertOk();
        $hijriDate = $presenter->format($date);
        if (is_string($hijriDate)) {
            $response->assertSee($hijriDate.' Hijri');
        }
    }
}
