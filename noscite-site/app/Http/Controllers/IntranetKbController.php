<?php

namespace App\Http\Controllers;

use App\Models\KbDocument;
use App\Services\KbSyncService;
use Illuminate\Http\Request;

class IntranetKbController extends Controller
{
    public function index(Request $request)
    {
        $user = session('intranet_user');
        $query = KbDocument::query();

        $search = $request->get('q');
        $tipo = $request->get('tipo');
        $tag = $request->get('tag');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('sommario', 'ilike', "%{$search}%")
                  ->orWhereJsonContains('tags', $search)
                  ->orWhereJsonContains('argomenti', $search);
            });
        }

        if ($tipo) {
            $query->where('tipo_documento', $tipo);
        }

        if ($tag) {
            $query->whereJsonContains('tags', $tag);
        }

        $documents = $query->orderByDesc('data_catalogazione')
            ->orderByDesc('created_at')
            ->paginate(20);

        $tipi = KbDocument::selectRaw('tipo_documento, count(*) as count')
            ->whereNotNull('tipo_documento')
            ->groupBy('tipo_documento')
            ->orderByDesc('count')
            ->get();

        $allTags = KbDocument::all()
            ->flatMap(fn($d) => $d->tags ?? [])
            ->countBy()
            ->sortDesc()
            ->take(20);

        $stats = app(KbSyncService::class)->getVaultStats();

        return view('intranet.kb.index', compact(
            'user', 'documents', 'tipi', 'allTags', 'stats', 'search', 'tipo', 'tag'
        ));
    }

    public function show(KbDocument $document)
    {
        $user = session('intranet_user');
        return view('intranet.kb.show', compact('user', 'document'));
    }

    public function sync()
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);

        $vaultPath = config('kb.vault_path');
        exec("cd " . escapeshellarg($vaultPath) . " && git pull origin main 2>&1", $output);

        $stats = app(KbSyncService::class)->sync();

        return back()->with('success',
            "Sync completato: {$stats['created']} nuovi, {$stats['updated']} aggiornati. Git: " . implode(' ', $output)
        );
    }

    public function download(KbDocument $document)
    {
        if (!$document->file_path || !file_exists($document->file_path)) {
            abort(404, 'File non trovato');
        }
        return response()->download($document->file_path, basename($document->file_path));
    }

    public function upload(Request $request)
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $request->validate([
            'files.*' => 'required|file|max:102400|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,md,csv',
        ]);

        $inboxPath = config('kb.vault_path') . '/_inbox';
        if (!is_dir($inboxPath)) {
            @mkdir($inboxPath, 0755, true);
        }

        $count = 0;
        foreach ($request->file('files') as $file) {
            $filename = $file->getClientOriginalName();
            $file->move($inboxPath, $filename);
            $count++;
        }

        return response()->json(['count' => $count, 'message' => "Caricati {$count} file in inbox"]);
    }
}
