<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordPressSsoTest extends TestCase
{
    use RefreshDatabase;

    private function link(array $over = [], string $secret = 'tajny-sekret'): string
    {
        $p = array_merge(['site' => 'sklep.pl', 'email' => 'anna@example.com', 'ts' => time(), 'nonce' => bin2hex(random_bytes(8)), 'redirect' => '/tickets/create?type=bug&page=https%3A%2F%2Fsklep.pl%2Fkoszyk'], $over);
        $p['sig'] = $over['sig'] ?? hash_hmac('sha256', implode('|', [$p['site'], $p['email'], $p['ts'], $p['nonce'], $p['redirect']]), $secret);

        return '/sso/wordpress?'.http_build_query($p);
    }

    public function test_signed_link_logs_in_client_once(): void
    {
        $site = Site::create(['client_id' => User::factory()->create(['email' => 'anna@example.com'])->client_id, 'domain' => 'sklep.pl']);
        $site->forceFill(['sso_secret' => 'tajny-sekret'])->save();

        $url = $this->link();
        $this->get($url)->assertRedirect('/tickets/create?type=bug&page=https%3A%2F%2Fsklep.pl%2Fkoszyk');
        $this->assertAuthenticated();
        auth()->logout();

        $this->get($url)->assertForbidden(); // jednorazowy
        $this->get($this->link(['ts' => time() - 600]))->assertForbidden(); // wygasły
        $this->get($this->link([], 'zly-sekret'))->assertForbidden();
        $this->get($this->link(['email' => 'obcy@example.com']))->assertForbidden(); // brak konta → strona z informacją
        $this->get($this->link(['redirect' => 'https://zly.example']))->assertRedirect(route('dashboard'));

        // Konto administratora nie loguje się przez SSO.
        auth()->logout();
        User::factory()->admin()->create(['email' => 'boss@example.com']);
        $this->get($this->link(['email' => 'boss@example.com']))->assertForbidden();
        $this->assertGuest();
    }

    public function test_site_secret_generation_and_prefilled_report(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);
        $site = Site::create(['client_id' => User::factory()->create()->client_id, 'domain' => 'sklep.pl']);
        $this->post(route('sites.sso-secret', $site))->assertSessionHas('sso_secret');
        $this->assertNotNull($site->fresh()->sso_secret);

        $client = User::factory()->create(['client_id' => $site->client_id]);
        $this->actingAs($client)->get('/tickets/create?type=bug&page=https://sklep.pl/koszyk')->assertOk()->assertSee('https://sklep.pl/koszyk');
    }
}
