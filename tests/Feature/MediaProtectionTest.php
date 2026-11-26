<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Collaboration;
use App\Models\Content;
use App\Models\FileUsage;
use App\Models\Influencer;
use App\Models\Material;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\FileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MediaProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $mime, User $by): FileUsage
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mp');
        if ($mime === 'image/png') {
            $im = imagecreatetruecolor(400, 300);
            imagefill($im, 0, 0, imagecolorallocate($im, 19, 163, 180));
            imagepng($im, $tmp);
        } else {
            file_put_contents($tmp, random_bytes(2048));
        }
        $f = app(FileStore::class)->storeLocalFile($tmp, $mime);
        $f->forceFill(['mime' => $mime])->save();

        return FileUsage::create(['file_id' => $f->id, 'name' => $mime === 'image/png' ? 'kadr.png' : 'rolka.mp4', 'uploaded_by' => $by->id]);
    }

    private function world(): array
    {
        $admin = User::factory()->admin()->create();
        $acc = SocialAccount::factory()->create();
        $campaign = Campaign::create(['client_id' => $acc->client_id, 'name' => 'Wiosna', 'status' => 'active']);
        $collab = Collaboration::create(['influencer_id' => Influencer::create(['name' => 'Ola'])->id, 'campaign_id' => $campaign->id, 'rate' => 1000]);
        $client = User::factory()->create(['client_id' => $acc->client_id]);
        $mk = function (string $mime, array $extra = []) use ($collab, $admin) {
            $u = $this->file($mime, $admin);
            $m = $collab->materials()->create(['description' => 'M', 'is_received' => true, 'file_usage_id' => $u->id, 'license_until' => today()->addMonth()] + $extra);
            $u->update(['usable_type' => 'material', 'usable_id' => $m->id]);

            return [$m, $u];
        };

        return [$admin, $client, $acc, $mk];
    }

    public function test_client_gets_watermarked_image_and_cannot_download_original(): void
    {
        [$admin, $client, , $mk] = $this->world();
        [$m, $u] = $mk('image/png');
        $url = route('files.show', [$u, $u->name]);

        $this->actingAs($client);
        $res = $this->get($url)->assertOk();
        $this->assertSame('image/jpeg', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->assertNotSame(md5_file(app(FileStore::class)->absolutePath($u->file)), md5((string) file_get_contents($res->baseResponse->getFile()->getPathname())));
        $this->get($url.'?download=1')->assertForbidden();
        $this->assertSame(1, DB::table('file_access_logs')->where('action', 'blocked')->where('user_id', $client->id)->count());

        // Administrator — oryginał; po zgodzie agencji klient też pobiera oryginał (i to się zapisuje).
        $this->actingAs($admin)->withSession(['two_factor_passed' => true])->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $m->update(['client_download' => true]);
        $this->actingAs($client)->get($url.'?download=1')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame(1, DB::table('file_access_logs')->where('action', 'download')->count());
    }

    public function test_client_video_plays_only_inside_player_and_expired_license_locks_preview(): void
    {
        [, $client, , $mk] = $this->world();
        [$m, $u] = $mk('video/mp4');
        $url = route('files.show', [$u, $u->name]);
        $this->actingAs($client);

        $this->get($url, ['Sec-Fetch-Dest' => 'video'])->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get($url, ['Sec-Fetch-Dest' => 'document'])->assertForbidden(); // otwarcie linku wprost
        $this->get($url)->assertForbidden();                                    // np. menedżer pobierania / curl
        $this->get(route('materials.index'))->assertOk()->assertSee(__('files.view_only'))->assertDontSee('download=1')->assertSee('mat-thumb-guard', false);

        $m->update(['license_until' => today()->subDay()]);
        $this->get($url, ['Sec-Fetch-Dest' => 'video'])->assertForbidden();
        $this->get(route('materials.index'))->assertOk()->assertSee(__('files.license_locked'));
    }

    public function test_post_drafts_are_protected_until_published(): void
    {
        [$admin, $client, $acc] = $this->world();
        $content = Content::create(['client_id' => $acc->client_id, 'title' => 'Projekt', 'type' => 'post', 'format' => 'image', 'stage' => 'ready', 'created_by' => $admin->id]);
        $u = $this->file('image/png', $admin);
        $u->update(['usable_type' => 'content', 'usable_id' => $content->id]);
        $pub = Publication::create(['content_id' => $content->id, 'social_account_id' => $acc->id, 'platform' => $acc->platform->value,
            'scheduled_at' => now()->addDay(), 'status' => 'planned', 'visible_to_client' => true]);
        $url = route('files.show', [$u, $u->name]);

        $this->actingAs($client)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get($url.'?download=1')->assertForbidden();
        $pub->update(['status' => 'published', 'published_at' => now()]);
        $this->get($url.'?download=1')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_video_poster_is_stored_as_jpeg_and_watermarked_for_client(): void
    {
        [$admin, $client, , $mk] = $this->world();
        [, $u] = $mk('video/mp4');
        $png = \Illuminate\Http\UploadedFile::fake()->image('kadr.png', 1920, 1080);

        // Klient nie może ustawić kadru cudzego pliku; zespół może.
        $this->actingAs($client)->post(route('files.poster.store', $u), ['poster' => $png])->assertForbidden();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true])->post(route('files.poster.store', $u), ['poster' => $png])->assertOk();
        $poster = $u->file->fresh()->poster;
        $this->assertSame('image/jpeg', $poster->mime);
        $this->assertSame(960, getimagesize(app(FileStore::class)->absolutePath($poster))[0]); // przeskalowany i zapisany na nowo
        $this->get(route('files.poster', $u))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('licenses.index'))->assertSee('mat-thumb-poster', false)->assertDontSee('data-make-poster', false);

        $res = $this->actingAs($client)->get(route('files.poster', $u))->assertOk();
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control')); // kopia ze znakiem wodnym
        $this->assertNotSame(app(FileStore::class)->absolutePath($poster), $res->baseResponse->getFile()->getPathname());
    }

    public function test_upload_by_client_can_set_own_video_poster_once(): void
    {
        [, $client] = $this->world();
        $tmp = tempnam(sys_get_temp_dir(), 'v');
        file_put_contents($tmp, random_bytes(1024));
        $f = app(FileStore::class)->storeLocalFile($tmp, 'video/mp4');
        $f->forceFill(['mime' => 'video/quicktime'])->save();
        $u = FileUsage::create(['file_id' => $f->id, 'name' => 'IMG_0001.MOV', 'uploaded_by' => $client->id]);
        $this->actingAs($client)->post(route('files.poster.store', $u), ['poster' => \Illuminate\Http\UploadedFile::fake()->image('p.jpg', 400, 700)])->assertOk();
        $first = $f->fresh()->poster_id;
        $this->assertNotNull($first);
        $this->post(route('files.poster.store', $u), ['poster' => \Illuminate\Http\UploadedFile::fake()->image('q.jpg', 300, 300)])->assertOk();
        $this->assertSame($first, $f->fresh()->poster_id);
    }
}
