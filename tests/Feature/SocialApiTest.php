<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Collaboration;
use App\Models\Content;
use App\Models\FileUsage;
use App\Models\Influencer;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\PublicationFailed;
use App\Services\FileStore;
use App\Services\Social\AutoPublisher;
use App\Services\Social\Integrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SocialApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin)->withSession(['two_factor_passed' => true]);
        Notification::fake();
    }

    private function enable(string $provider): void
    {
        Integrations::save($provider, true, $provider.'-app-id', $provider.'-secret');
    }

    private function account(string $platform, array $extra = []): SocialAccount
    {
        $acc = SocialAccount::factory()->create(['platform' => $platform, 'name' => 'Marka '.$platform]);
        $acc->forceFill($extra + ['external_id' => '1789', 'access_token' => 'PAGE-TOKEN', 'connected_at' => now()])->save();

        return $acc;
    }

    private function contentWith(SocialAccount $acc, string $mime, array $pub = []): Publication
    {
        $content = Content::create(['client_id' => $acc->client_id, 'title' => 'Nowa kolekcja', 'description' => 'Opis posta', 'hashtags' => '#marka',
            'type' => 'post', 'format' => str_starts_with($mime, 'video') ? 'video' : 'image', 'stage' => 'ready', 'created_by' => $this->admin->id]);
        $tmp = tempnam(sys_get_temp_dir(), 'c');
        file_put_contents($tmp, random_bytes(3000));
        $f = app(FileStore::class)->storeLocalFile($tmp, $mime);
        $f->forceFill(['mime' => $mime])->save();
        FileUsage::create(['file_id' => $f->id, 'name' => str_starts_with($mime, 'video') ? 'film.mp4' : 'grafika.jpg', 'uploaded_by' => $this->admin->id, 'usable_type' => 'content', 'usable_id' => $content->id]);

        return Publication::create($pub + ['content_id' => $content->id, 'social_account_id' => $acc->id, 'platform' => $acc->platform->value,
            'scheduled_at' => now()->subMinute(), 'status' => 'planned', 'auto_publish' => true]);
    }

    private function pngBytes(): string
    {
        $im = imagecreatetruecolor(200, 120);
        imagefill($im, 0, 0, imagecolorallocate($im, 19, 163, 180));
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }

    public function test_account_avatar_upload_is_cropped_and_shown_in_preview(): void
    {
        $acc = SocialAccount::factory()->create(['platform' => 'instagram']);
        $tmp = tempnam(sys_get_temp_dir(), 'av').'.png';
        file_put_contents($tmp, $this->pngBytes());
        $this->post(route('social.accounts.avatar', $acc), ['avatar' => new \Illuminate\Http\UploadedFile($tmp, 'logo.png', 'image/png', null, true)])->assertRedirect();
        $acc->refresh();
        $path = app(FileStore::class)->absolutePath($acc->avatar->file);
        $this->assertSame([320, 320], array_slice(getimagesize($path), 0, 2)); // kwadrat
        $this->get(route('social.accounts'))->assertOk()->assertSee($acc->avatarUrl(), false);
        $this->get(route('social.contents.create', ['client' => $acc->client_id]))->assertOk()->assertSee(str_replace('/', '\\/', $acc->avatarUrl()), false);
        $this->post(route('social.accounts.avatar', $acc), ['avatar' => \Illuminate\Http\UploadedFile::fake()->create('x.txt', 3, 'text/plain')])->assertSessionHasErrors('avatar');
    }

    public function test_everything_stays_manual_when_integration_is_disabled(): void
    {
        $acc = $this->account('facebook');
        $pub = $this->contentWith($acc, 'image/jpeg');
        Http::fake();
        $this->assertFalse($acc->fresh()->isConnected());
        app(AutoPublisher::class)->process($pub->fresh());
        Http::assertNothingSent();
        $this->assertSame('held', $pub->fresh()->api_status);
        $this->assertSame('planned', $pub->fresh()->status);
        // Formularz nie zapisze automatycznej publikacji dla niepołączonego konta.
        $this->put(route('social.contents.update', $pub->content), ['client_id' => $acc->client_id, 'title' => 'Nowa kolekcja', 'type' => 'post', 'format' => 'image', 'stage' => 'ready',
            'pubs' => [['id' => $pub->id, 'social_account_id' => $acc->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'auto_publish' => 1]]]);
        $this->assertFalse($pub->fresh()->auto_publish);
    }

    public function test_facebook_photo_is_published_with_signed_media_url_and_permalink(): void
    {
        $this->enable('meta');
        $pub = $this->contentWith($this->account('facebook'), 'image/jpeg');
        Http::fake([
            'graph.facebook.com/v23.0/1789/photos' => Http::response(['id' => 'p1', 'post_id' => '1789_555']),
            'graph.facebook.com/v23.0/1789_555*' => Http::response(['permalink_url' => 'https://www.facebook.com/marka/posts/555']),
        ]);
        app(AutoPublisher::class)->runDue();
        $pub->refresh();
        $this->assertSame(['published', 'published', '1789_555', 'https://www.facebook.com/marka/posts/555'], [$pub->status, $pub->api_status, $pub->external_id, $pub->url]);
        Http::assertSent(function (HttpRequest $r) {
            if (! str_ends_with($r->url(), '/photos')) {
                return false;
            }
            // Meta pobiera grafikę z podpisanego adresu — sprawdzamy, że działa bez logowania.
            $this->assertSame("Opis posta\n\n#marka", $r['caption']);
            auth()->logout();
            $this->get($r['url'])->assertOk();
            $this->get(preg_replace('/signature=\w+/', 'signature=zle', $r['url']))->assertForbidden();

            return $r['access_token'] === 'PAGE-TOKEN';
        });
    }

    public function test_instagram_reel_waits_for_processing_then_publishes(): void
    {
        $this->enable('meta');
        $pub = $this->contentWith($this->account('instagram'), 'video/mp4');
        Http::fakeSequence('graph.facebook.com/v23.0/1789/media')->push(['id' => 'C1']);
        Http::fake([
            'graph.facebook.com/v23.0/1789/media' => Http::response(['id' => 'C1']),
            'graph.facebook.com/v23.0/C1*' => Http::sequence()->push(['status_code' => 'IN_PROGRESS'])->push(['status_code' => 'FINISHED']),
            'graph.facebook.com/v23.0/1789/media_publish' => Http::response(['id' => 'M9']),
            'graph.facebook.com/v23.0/M9*' => Http::response(['permalink' => 'https://www.instagram.com/reel/abc/']),
        ]);
        $ap = app(AutoPublisher::class);
        $ap->process($pub->fresh());
        $this->assertSame(['processing', 'C1', 'planned'], [$pub->fresh()->api_status, $pub->fresh()->api_container, $pub->fresh()->status]);
        $ap->process($pub->fresh());
        $this->assertSame(['published', 'M9', 'https://www.instagram.com/reel/abc/'], [$pub->fresh()->status, $pub->fresh()->external_id, $pub->fresh()->url]);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/1789/media') && $r['media_type'] === 'REELS' && str_contains($r['video_url'], '/m/'));
    }

    public function test_tiktok_video_is_uploaded_in_chunks_and_status_checked_with_token_refresh(): void
    {
        $this->enable('tiktok');
        $acc = $this->account('tiktok', ['refresh_token' => 'R1', 'token_expires_at' => now()->subMinute(), 'api_meta' => ['username' => 'marka']]);
        $pub = $this->contentWith($acc, 'video/mp4');
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'NEW', 'refresh_token' => 'R2', 'expires_in' => 86400, 'open_id' => '1789']),
            'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response(['data' => ['privacy_level_options' => ['SELF_ONLY', 'PUBLIC_TO_EVERYONE']], 'error' => ['code' => 'ok']]),
            'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response(['data' => ['publish_id' => 'v_pub_1', 'upload_url' => 'https://open-upload.tiktokapis.com/upload/?id=1'], 'error' => ['code' => 'ok']]),
            'open-upload.tiktokapis.com/*' => Http::response('', 201),
            'open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response(['data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => ['7300']], 'error' => ['code' => 'ok']]),
        ]);
        $ap = app(AutoPublisher::class);
        $ap->process($pub->fresh());
        $this->assertSame('processing', $pub->fresh()->api_status);
        $this->assertSame('NEW', $acc->fresh()->access_token);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'video/init') && $r['post_info']['privacy_level'] === 'SELF_ONLY' && $r['source_info']['video_size'] === 3000);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'open-upload') && $r->header('Content-Range')[0] === 'bytes 0-2999/3000');
        $ap->process($pub->fresh()->forceFill(['api_next_attempt_at' => null]));
        $this->assertSame(['published', 'https://www.tiktok.com/@marka/video/7300'], [$pub->fresh()->status, $pub->fresh()->url]);
    }

    public function test_license_and_client_approval_hold_publication_and_errors_retry_then_notify(): void
    {
        $this->enable('meta');
        $acc = $this->account('facebook');
        // Licencja materiału skończyła się — nie publikujemy.
        $pub = $this->contentWith($acc, 'image/jpeg');
        $collab = Collaboration::create(['influencer_id' => Influencer::create(['name' => 'Ola'])->id, 'campaign_id' => Campaign::create(['client_id' => $acc->client_id, 'name' => 'K', 'status' => 'active'])->id, 'rate' => 1]);
        $pub->content->materials()->attach($collab->materials()->create(['description' => 'Rolka', 'license_until' => today()->subDay()])->id);
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'Service temporarily unavailable', 'code' => 2]], 500)
            ->push(['error' => ['message' => 'Error validating access token', 'code' => 190]], 400)]);
        app(AutoPublisher::class)->process($pub->fresh());
        $this->assertSame('held', $pub->fresh()->api_status);
        $this->assertStringContainsString('Rolka', $pub->fresh()->api_error);
        Notification::assertSentTo($this->admin, PublicationFailed::class);
        Http::assertNothingSent();

        // Post pokazany klientowi czeka na akceptację.
        $p2 = $this->contentWith($acc, 'image/jpeg', ['visible_to_client' => true]);
        app(AutoPublisher::class)->process($p2->fresh());
        $this->assertSame(__('api.hold_client'), $p2->fresh()->api_error);

        // Błąd platformy: ponowienie z odstępem, po 3 próbach koniec i powiadomienie; nieważny token odłącza konto.
        $p3 = $this->contentWith($acc, 'image/jpeg');
        $ap = app(AutoPublisher::class);
        $ap->process($p3->fresh());
        $this->assertSame([1, 'failed'], [$p3->fresh()->api_attempts, $p3->fresh()->api_status]);
        $this->assertTrue($p3->fresh()->api_next_attempt_at->isFuture());
        $this->assertCount(0, $ap->due()->where('id', $p3->id)); // czeka na kolejną próbę
        $ap->process($p3->fresh());
        $this->assertSame(AutoPublisher::MAX_ATTEMPTS, $p3->fresh()->api_attempts);
        $this->assertNull($acc->fresh()->connected_at);
        $this->assertNotNull($acc->fresh()->api_error);
    }

    public function test_meta_connect_flow_attaches_chosen_page_and_instagram(): void
    {
        $this->enable('meta');
        $acc = SocialAccount::factory()->create(['platform' => 'instagram']);
        $redirect = $this->get(route('social.connect', $acc))->assertRedirect();
        $this->assertStringContainsString('facebook.com/v23.0/dialog/oauth', $redirect->headers->get('Location'));
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertStringContainsString('instagram_content_publish', $q['scope']);

        Http::fake([
            'graph.facebook.com/v23.0/oauth/access_token*' => Http::sequence()->push(['access_token' => 'SHORT'])->push(['access_token' => 'LONG']),
            'graph.facebook.com/v23.0/me/accounts*' => Http::response(['data' => [
                ['id' => '11', 'name' => 'Strona bez IG', 'access_token' => 'T11'],
                ['id' => '22', 'name' => 'Zielony Dom', 'access_token' => 'PAGE-TOKEN-22', 'instagram_business_account' => ['id' => '9922', 'username' => 'zielonydom']],
            ]]),
            'graph.facebook.com/v23.0/9922*' => Http::response(['profile_picture_url' => 'https://cdn.example.com/avatar.jpg']),
            'cdn.example.com/*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png']),
        ]);
        $this->get(route('social.connect.callback', ['provider' => 'meta', 'state' => 'zly', 'code' => 'x']))->assertForbidden();
        // Stan sesji zużyty przy błędnej próbie — zaczynamy od nowa.
        $this->get(route('social.connect', $acc));
        $this->get(route('social.connect.callback', ['provider' => 'meta', 'state' => session('social_connect')['state'], 'code' => 'CODE']))->assertRedirect(route('social.connect.choose'));
        $this->get(route('social.connect.choose'))->assertOk()->assertSee('Zielony Dom')->assertSee('@zielonydom')->assertDontSee('PAGE-TOKEN-22');
        $this->post(route('social.connect.attach'), ['page' => '22'])->assertRedirect(route('social.accounts'));
        $acc->refresh();
        $this->assertSame(['9922', 'PAGE-TOKEN-22', 'zielonydom'], [$acc->external_id, $acc->access_token, $acc->api_meta['username']]);
        $this->assertTrue($acc->isConnected());
        $this->assertNotNull($acc->avatar_usage_id); // zdjęcie profilu pobrane z Instagrama
        $this->get($acc->avatarUrl())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringNotContainsString('PAGE-TOKEN-22', json_encode($acc->toArray()));
        $this->get(route('social.accounts'))->assertOk()->assertSee(__('api.connected_as', ['name' => 'zielonydom']));
    }

    public function test_stats_are_fetched_from_api_instead_of_manual_entry(): void
    {
        $this->enable('meta');
        $pub = $this->contentWith($this->account('instagram'), 'image/jpeg');
        $pub->forceFill(['status' => 'published', 'published_at' => now()->subDays(2), 'external_id' => 'M9'])->save();
        Http::fake(['graph.facebook.com/v23.0/M9/insights*' => Http::response(['data' => [
            ['name' => 'reach', 'values' => [['value' => 1200]]], ['name' => 'likes', 'values' => [['value' => 80]]],
            ['name' => 'comments', 'values' => [['value' => 7]]], ['name' => 'shares', 'values' => [['value' => 3]]], ['name' => 'saved', 'values' => [['value' => 5]]],
        ]])]);
        $this->assertSame(1, app(AutoPublisher::class)->fetchStats());
        $m = $pub->measurements()->first();
        $this->assertSame([1200, 85, 7, 3, 'api'], [$m->reach, $m->reactions, $m->comments, $m->shares, $m->source]);
        $this->assertSame(1, $pub->fresh()->checks_done);

        Integrations::save('meta', true, null, null);
        \App\Models\Setting::put('int_auto_stats', '0');
        $this->assertSame(0, app(AutoPublisher::class)->fetchStats());
    }

    public function test_admin_integrations_page_saves_keys_encrypted_and_toggles(): void
    {
        $this->get(route('admin.integrations'))->assertOk()->assertSee(route('social.connect.callback', 'meta'));
        $this->put(route('admin.integrations.update'), ['provider' => 'meta', 'enabled' => 1, 'key' => '123', 'secret' => 'tajne'])->assertRedirect();
        $this->assertTrue(Integrations::enabled('meta'));
        $this->assertNotSame('tajne', \App\Models\Setting::get('int_meta_secret'));
        $this->put(route('admin.integrations.update'), ['provider' => 'meta', 'key' => '123'])->assertRedirect(); // bez sekretu = zostaje, wyłączona
        $this->assertFalse(Integrations::enabled('meta'));
        $this->assertSame('tajne', Integrations::secret('meta'));
        $client = User::factory()->create(['client_id' => \App\Models\Client::factory()->create()->id]);
        $this->actingAs($client)->get(route('admin.integrations'))->assertForbidden();
    }
}
