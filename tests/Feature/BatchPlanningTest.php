<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Content;
use App\Models\FileUsage;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\FileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchPlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    private SocialAccount $ig;

    private SocialAccount $fb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin)->withSession(['two_factor_passed' => true]);
        $this->client = Client::factory()->create();
        $this->ig = SocialAccount::factory()->create(['client_id' => $this->client->id, 'platform' => 'instagram']);
        $this->fb = SocialAccount::factory()->create(['client_id' => $this->client->id, 'platform' => 'facebook']);
    }

    private function upload(string $name): int
    {
        $tmp = tempnam(sys_get_temp_dir(), 'b');
        file_put_contents($tmp, $name.random_bytes(8));
        $f = app(FileStore::class)->storeLocalFile($tmp, 'image/png');

        return FileUsage::create(['file_id' => $f->id, 'name' => $name, 'uploaded_by' => $this->admin->id])->id;
    }

    public function test_batch_creates_posts_with_publications_on_selected_accounts_and_remembers_defaults(): void
    {
        $d = fn ($n) => today()->addDays($n)->toDateString();
        $res = $this->post(route('social.contents.batch.store'), [
            'client_id' => $this->client->id,
            'accounts' => [$this->ig->id => ['on' => 1, 'time' => '18:00'], $this->fb->id => ['on' => 1, 'time' => '12:30']],
            'type' => 'post', 'format' => 'image', 'hashtags' => '#marka #donice', 'visible_to_client' => 1, 'remember' => 1,
            'hashtag_sets' => "Produkt: #donice #ceramika\nSezon: #jesień",
            'rows' => [
                ['date' => $d(1), 'description' => "Nowe donice już są!\nDrugi akapit", 'uploads' => [$this->upload('donica-1.png')]],
                ['date' => $d(3), 'time' => '09:15', 'title' => 'Konkurs', 'description' => 'Wygraj zestaw', 'hashtags' => '#konkurs'],
                ['date' => $d(5), 'uploads' => [$this->upload('kulisy-sesji.png'), $this->upload('kulisy-2.png')]],
                ['date' => '', 'title' => '', 'description' => ''], // pusty wiersz pomijamy
            ],
        ]);
        $res->assertRedirect()->assertSessionHas('status', trans_choice('social.batch_created', 3, ['n' => 3]));
        $contents = Content::orderBy('id')->get();
        $this->assertSame(['Nowe donice już są!', 'Konkurs', 'kulisy-sesji'], $contents->pluck('title')->all());
        $this->assertSame(['#marka #donice', '#konkurs', '#marka #donice'], $contents->pluck('hashtags')->all());
        $this->assertSame([1, 0, 2], $contents->map(fn ($c) => $c->files()->count())->all());
        $this->assertSame(6, Publication::count());
        $p = Publication::where('content_id', $contents[0]->id)->where('social_account_id', $this->ig->id)->first();
        $this->assertSame($d(1).' 18:00', $p->scheduled_at->format('Y-m-d H:i'));
        $this->assertTrue($p->visible_to_client);
        $this->assertSame(['09:15'], Publication::where('content_id', $contents[1]->id)->get()->map(fn ($x) => $x->scheduled_at->format('H:i'))->unique()->values()->all());
        $this->assertSame('ready', $contents[0]->stage);

        $def = $this->client->fresh()->socialDefaults();
        $this->assertSame([['id' => $this->ig->id, 'time' => '18:00'], ['id' => $this->fb->id, 'time' => '12:30']], $def['accounts']);
        $this->assertSame([['name' => 'Produkt', 'tags' => '#donice #ceramika'], ['name' => 'Sezon', 'tags' => '#jesień']], $def['hashtag_sets']);

        // Ekran podpowiada zapamiętane ustawienia, a zwykły formularz — konta, godziny i zestawy hashtagów.
        $this->get(route('social.contents.batch', ['client' => $this->client->id]))->assertOk()->assertSee('value="18:00"', false)->assertSee('#marka #donice');
        $this->get(route('social.contents.create', ['client' => $this->client->id, 'date' => $d(9)]))->assertOk()
            ->assertSee('value="'.$d(9).'T18:00"', false)->assertSee('value="'.$d(9).'T12:30"', false)
            ->assertSee('value="#marka #donice"', false)->assertSee('data-tags="#donice #ceramika"', false);
    }

    public function test_batch_requires_dates_when_accounts_selected_and_keeps_input(): void
    {
        $this->from(route('social.contents.batch', ['client' => $this->client->id]))->followingRedirects()->post(route('social.contents.batch.store'), [
            'client_id' => $this->client->id, 'accounts' => [$this->ig->id => ['on' => 1, 'time' => '18:00']], 'type' => 'post', 'format' => 'image',
            'rows' => [['date' => '', 'description' => 'Tekst bez daty']],
        ])->assertOk()->assertSee('Tekst bez daty')->assertSee(__('social.batch_need_date'));
        $this->assertSame(0, Content::count());

        // Bez kont — posty trafiają do treści „w przygotowaniu”, bez dat.
        $this->post(route('social.contents.batch.store'), ['client_id' => $this->client->id, 'type' => 'post', 'format' => 'image',
            'rows' => [['description' => 'Pomysł na post']]])->assertRedirect();
        $this->assertSame('preparing', Content::first()->stage);
        $this->assertSame(0, Publication::count());
    }

    public function test_duplicate_copies_text_files_and_materials_without_publications(): void
    {
        $c = Content::create(['client_id' => $this->client->id, 'title' => 'Oryginał', 'description' => 'Opis', 'hashtags' => '#a', 'type' => 'reel', 'format' => 'video', 'stage' => 'ready', 'created_by' => $this->admin->id]);
        FileUsage::find($this->upload('film.png'))->update(['usable_type' => 'content', 'usable_id' => $c->id]);
        Publication::create(['content_id' => $c->id, 'social_account_id' => $this->ig->id, 'platform' => 'instagram', 'scheduled_at' => now()->addDay(), 'status' => 'planned']);
        $this->post(route('social.contents.duplicate', $c))->assertRedirect();
        $copy = Content::latest('id')->first();
        $this->assertSame(['Oryginał '.__('social.copy_suffix'), 'Opis', '#a', 'reel', 'preparing'], [$copy->title, $copy->description, $copy->hashtags, $copy->type, $copy->stage]);
        $this->assertSame(1, $copy->files()->count());
        $this->assertSame(0, $copy->publications()->count());
        $this->assertSame(1, $c->files()->count());
    }
}
