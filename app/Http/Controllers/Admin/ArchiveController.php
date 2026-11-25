<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ExportPackage;
use App\Models\Setting;
use App\Models\Ticket;
use App\Services\ArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ArchiveController extends Controller
{
    public function __construct(private ArchiveService $archive) {}

    public function index()
    {
        $total = $this->archive->totalBytes();
        $threshold = (int) Setting::get('offline_threshold_mb', 0) * 1024 * 1024;

        return view('admin.archive', [
            'usage' => $this->archive->usageByClient(),
            'total' => $total,
            'threshold' => $threshold,
            'ready' => $this->archive->readyToArchive(),
            'archived' => Ticket::with('client')->where('archive_state', 'archived')->orderBy('closed_at')->limit(50)->get(),
            'offline' => Ticket::with('client')->where('archive_state', 'offline')->latest('updated_at')->limit(50)->get(),
            'packages' => ExportPackage::with('client')->latest()->limit(30)->get(),
            'retention' => (int) Setting::get('default_retention_months', 0),
        ]);
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['offline_threshold_mb' => ['nullable', 'integer', 'min:0'], 'default_retention_months' => ['nullable', 'integer', 'min:0', 'max:600'], 'hourly_rate' => ['nullable', 'numeric', 'min:0']]);
        foreach ($data as $k => $v) {
            Setting::put($k, $v ?? 0);
        }

        return back()->with('status', __('ui.saved'));
    }

    public function archiveTicket(Request $request, Ticket $ticket)
    {
        $this->archive->archive($ticket, $request->user());

        return back()->with('status', __('arch.archived'));
    }

    public function archiveAllReady(Request $request)
    {
        $n = 0;
        foreach ($this->archive->readyToArchive() as $t) {
            $this->archive->archive($t, $request->user());
            $n++;
        }

        return back()->with('status', trans_choice('arch.archived_n', $n, ['n' => $n]));
    }

    public function restore(Request $request, Ticket $ticket)
    {
        try {
            $this->archive->restore($ticket, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['archive' => $e->getMessage()]);
        }

        return back()->with('status', __('arch.restored'));
    }

    public function exportTicket(Request $request, Ticket $ticket)
    {
        $pkg = $this->archive->export('ticket', collect([$ticket]), null, $request->user());

        return $this->download($pkg);
    }

    /** Paczka klienta: archiwum, przekazanie materiałów, eksport na potrzeby RODO. */
    public function exportClient(Request $request, Client $client)
    {
        $pkg = $this->archive->export('client', $client->tickets()->get(), $client, $request->user());

        return $this->download($pkg);
    }

    public function moveOffline(Request $request)
    {
        $ids = $request->validate(['tickets' => ['required', 'array', 'min:1'], 'tickets.*' => ['integer']])['tickets'];
        $tickets = Ticket::whereIn('id', $ids)->whereIn('archive_state', ['archived'])->get();
        abort_if($tickets->isEmpty(), 422);
        $pkg = $this->archive->moveOffline($tickets, $request->user());

        return back()->with('status', __('arch.moved_offline', ['package' => $pkg->name]));
    }

    public function restorePackage(Request $request)
    {
        $request->validate(['package' => ['required', 'file', 'mimes:zip']]);
        try {
            $r = $this->archive->restoreFromPackage($request->file('package')->getRealPath(), $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['package' => $e->getMessage()]);
        }

        return back()->with('status', __('arch.package_restored', $r));
    }

    public function download(ExportPackage $package)
    {
        abort_unless(Storage::disk('local')->exists($package->path()), 404);

        return Storage::disk('local')->download($package->path(), $package->name);
    }

    public function destroyPackage(ExportPackage $package)
    {
        abort_if($package->kind === 'offline' && Ticket::where('offline_package', $package->name)->exists(), 422, __('arch.package_in_use'));
        Storage::disk('local')->delete($package->path());
        $package->delete();

        return back()->with('status', __('ui.deleted'));
    }

    /** Usunięcie na wniosek klienta. */
    public function destroyTicket(Request $request, Ticket $ticket)
    {
        $request->validate(['confirm' => ['required', 'in:'.$ticket->id]]);
        $this->archive->deleteTicket($ticket, $request->user(), 'client_request');

        return redirect()->route('admin.archive')->with('status', __('arch.deleted'));
    }
}
