<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Board;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Collaboration;
use App\Models\Content;
use App\Models\FileUsage;
use App\Models\Influencer;
use App\Models\Material;
use App\Models\User;
use App\Services\FileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function jpeg(int $w = 1200, int $h = 800): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 140, 90));
        ob_start(); imagejpeg($im, null, 90); imagedestroy($im);

        return (string) ob_get_clean();
    }

    private function usageFor($usable, string $name, string $binary, string $mime): FileUsage
    {
        $tmp = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($tmp, $binary);
        $file = app(FileStore::class)->storeLocalFile($tmp, $mime);

        return FileUsage::create(['file_id' => $file->id, 'usable_type' => $usable->getMorphClass(), 'usable_id' => $usable->id, 'name' => $name]);
    }

    public function test_image_goes_to_new_board_watermarked_and_client_can_comment(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create(['name' => 'Zielony Dom']);
        $anna = User::factory()->create(['client_id' => $client->id]);
        $content = Content::create(['client_id' => $client->id, 'title' => 'Post z donicą', 'type' => 'post', 'format' => 'image']);
        $original = $this->jpeg();
        $usage = $this->usageFor($content, 'donica.jpg', $original, 'image/jpeg');

        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);
        $this->getJson(route('to-board.targets', $usage))->assertOk()->assertJsonPath('client', 'Zielony Dom')->assertJsonPath('kind', 'image');
        $r = $this->postJson(route('to-board.store', $usage), ['note' => 'Prosimy zaznaczyć, co zmienić.', 'ask' => true])->assertOk();

        $board = Board::with('ticket', 'files.usage.file')->first();
        $this->assertSame(route('boards.show', $board), $r->json('url'));
        $this->assertSame($client->id, $board->ticket->client_id);
        $this->assertSame(TicketStatus::WaitingClient, $board->ticket->status);
        $this->assertCount(1, $board->files);
        // Na tablicy jest kopia (inny plik niż oryginał), JPEG, pomniejszona do max 1600 px.
        $copy = file_get_contents(app(FileStore::class)->absolutePath($board->files[0]->usage->file));
        $this->assertNotSame(sha1($original), sha1($copy));
        $this->assertSame('image/jpeg', getimagesizefromstring($copy)['mime']);
        $types = collect($board->liveElements())->pluck('type')->sort()->values()->all();
        $this->assertSame(['image', 'text'], $types);
        $img = collect($board->liveElements())->firstWhere('type', 'image');
        $this->assertSame($board->files[0]->excalidraw_id, $img['fileId']);
        $this->assertSame('admin', $img['customData']['side']);
        $this->assertSame(1, $board->versions()->count());
        $this->assertStringContainsString('Prosimy zaznaczyć', $board->ticket->messages()->latest('id')->value('body'));

        // Klient widzi tablicę i obraz, może dodać strzałkę i komentarz.
        $this->actingAs($anna);
        $this->get(route('boards.show', $board))->assertOk();
        $this->get(route('boards.files.show', [$board, $img['fileId']]))->assertOk();
        $this->postJson(route('boards.push', $board), ['elements' => [['id' => 'arrow1', 'type' => 'arrow', 'version' => 1, 'x' => 10, 'y' => 10]], 'since' => 0])->assertOk();
        $this->assertSame('client', $board->fresh()->elements['arrow1']['customData']['side']);
        // …ale nie może sam wysyłać plików na tablicę przez „Na tablicę”.
        $this->postJson(route('to-board.store', $usage), [])->assertForbidden();
    }

    public function test_video_frames_to_existing_board_and_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create(['name' => 'FitLife']);
        $campaign = Campaign::create(['client_id' => $client->id, 'name' => 'Zima', 'status' => 'active']);
        $collab = Collaboration::create(['influencer_id' => Influencer::create(['name' => 'Marta Lis'])->id, 'campaign_id' => $campaign->id]);
        $material = Material::create(['collaboration_id' => $collab->id, 'kind' => 'video', 'description' => 'Rolka z treningu', 'is_received' => true]);
        $usage = $this->usageFor($material, 'rolka.mp4', 'not-really-a-video', 'video/mp4');
        $usage->file->forceFill(['mime' => 'video/mp4'])->save(); // magazyn rozpoznaje typ z treści — tu udajemy film
        $material->update(['file_usage_id' => $usage->id]);

        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);
        $ticket = app(\App\Services\TicketService::class)->create($admin, ['title' => 'Montaż rolki', 'client_id' => $client->id], null);
        $board = app(\App\Services\BoardService::class)->create($ticket, $admin, 'Storyboard');
        $this->getJson(route('to-board.targets', $usage))->assertOk()->assertJsonPath('kind', 'video')->assertJsonPath('boards.0.id', $board->id);

        $this->postJson(route('to-board.store', $usage), ['board_id' => $board->id])->assertStatus(422); // brak klatek
        $frame = 'data:image/jpeg;base64,'.base64_encode($this->jpeg(1920, 1080));
        $this->postJson(route('to-board.store', $usage), ['board_id' => $board->id, 'ask' => false,
            'frames' => [['data' => $frame, 'time' => 12.4], ['data' => $frame, 'time' => 75]]])->assertOk();
        $board->refresh();
        $this->assertCount(2, $board->files);
        $this->assertSame(['Rolka z treningu'], collect($board->liveElements())->pluck('text')->filter()->values()->all());
        $this->assertSame(TicketStatus::WithAgency, $ticket->fresh()->status);

        // Tablica innego klienta jest odrzucana.
        $other = app(\App\Services\BoardService::class)->create(app(\App\Services\TicketService::class)->create($admin, ['title' => 'X', 'client_id' => Client::factory()->create()->id], null), $admin, 'Obca');
        $this->postJson(route('to-board.store', $usage), ['board_id' => $other->id, 'frames' => [['data' => $frame, 'time' => 1]]])->assertStatus(422);
    }
}
