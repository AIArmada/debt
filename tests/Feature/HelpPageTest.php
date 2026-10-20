<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_page_requires_authentication_and_shows_malaysian_support_contacts(): void
    {
        $this->get(route('help.debt-support'))->assertRedirect(route('login'));

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)
            ->get(route('help.debt-support'))
            ->assertOk()
            ->assertSee('Agensi Kaunseling dan Pengurusan Kredit')
            ->assertSee('Talian Kasih')
            ->assertSee('15999')
            ->assertSee('Befrienders KL')
            ->assertSee('find local help in your area')
            ->assertSee('not financial or legal advice');

        $malayUser = User::factory()->create(['email_verified_at' => now(), 'locale' => 'ms']);
        $this->actingAs($malayUser)
            ->get(route('help.debt-support'))
            ->assertOk()
            ->assertSee('Bantuan dan sokongan hutang')
            ->assertSee('cari bantuan setempat di kawasan anda');
    }
}
