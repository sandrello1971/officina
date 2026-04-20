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
        $organizzazioni = $request->get('organizzazioni');
        $dataFrom = $request->get('data_from');
        $dataTo = $request->get('data_to');
        $sentiment = $request->get('sentiment');

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

        if ($organizzazioni) {
            $query->where('organizzazioni', 'ilike', "%{$organizzazioni}%");
        }
        if ($dataFrom) {
            $query->where('data_documento', '>=', $dataFrom);
        }
        if ($dataTo) {
            $query->where('data_documento', '<=', $dataTo);
        }
        if ($sentiment) {
            $query->where('sentiment', $sentiment);
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
            'user', 'documents', 'tipi', 'allTags', 'stats',
            'search', 'tipo', 'tag',
            'organizzazioni', 'dataFrom', 'dataTo', 'sentiment'
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

    public function downloadOriginal(string $stem)
    {
        $vaultPath = config('kb.vault_path');
        $archivePath = $vaultPath . '/_archive/';

        $files = glob($archivePath . $stem . '.*');

        if (empty($files)) {
            abort(404, 'File non trovato nell\'archivio.');
        }

        $filePath = $files[0];
        $filename = basename($filePath);

        return response()->download($filePath, $filename);
    }

    public function destroy(KbDocument $document)
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);

        $vaultPath = config('kb.vault_path');
        $stem = $document->file_stem;

        $mdPath = $vaultPath . '/_metadata/' . $stem . '.md';
        if (file_exists($mdPath)) {
            unlink($mdPath);
        }

        if ($document->file_path && file_exists($document->file_path)) {
            unlink($document->file_path);
        }

        // Cancella anche eventuali residui in _inbox con lo stesso stem (evita re-processing del rescan)
        foreach (glob($vaultPath . '/_inbox/' . $stem . '.*') as $orphan) {
            @unlink($orphan);
        }

        $document->delete();

        $vault = escapeshellarg($vaultPath);

        // Rimuovi esplicitamente dal git index
        exec("cd {$vault} && git rm --ignore-unmatch _metadata/{$document->file_stem}.md 2>&1");
        exec("cd {$vault} && git rm --ignore-unmatch _archive/{$document->file_stem}.* 2>&1");

        // Commit + sync con remote + push
        $msg = escapeshellarg("kb: eliminato {$document->file_stem}");
        exec("cd {$vault} && git add -A && git commit -m {$msg} 2>&1", $commitOut, $commitRc);
        exec("cd {$vault} && git pull --rebase origin main 2>&1", $pullOut, $pullRc);
        exec("cd {$vault} && git push origin main 2>&1", $pushOut, $pushRc);

        if ($pushRc !== 0) {
            \Illuminate\Support\Facades\Log::warning("KB destroy: push fallito rc={$pushRc}: " . implode(' ', $pushOut));
        }

        return back()->with('success', 'Documento eliminato.');
    }

    public function processingStatus()
    {
        $vaultPath = config('kb.vault_path');
        $inboxPath = $vaultPath . '/_inbox';
        $metadataPath = $vaultPath . '/_metadata';

        $inboxFiles = [];
        if (is_dir($inboxPath)) {
            foreach (glob($inboxPath . '/*') as $path) {
                if (!is_file($path)) continue;
                $filename = basename($path);
                if (in_array(strtolower($filename), ['.ds_store', '.localized', 'thumbs.db'])) continue;

                $age = time() - filemtime($path);
                $stem = pathinfo($filename, PATHINFO_FILENAME);
                $metaExists = is_file($metadataPath . '/' . $stem . '.md');

                $inboxFiles[] = [
                    'filename' => $filename,
                    'size' => filesize($path),
                    'age_seconds' => $age,
                    'meta_exists' => $metaExists,
                    'orphan' => $metaExists || $age > 600, // orfano = già catalogato o > 10 min
                ];
            }
        }

        $recent = KbDocument::where('last_synced_at', '>=', now()->subMinutes(2))
            ->orderByDesc('last_synced_at')
            ->get(['id', 'file_stem', 'title', 'last_synced_at'])
            ->map(fn($d) => [
                'id' => $d->id,
                'file_stem' => $d->file_stem,
                'title' => $d->title ?? $d->file_stem,
                'synced_at' => $d->last_synced_at?->format('H:i:s'),
            ]);

        return response()->json([
            'inbox' => $inboxFiles,
            'recent' => $recent,
            'total_docs' => KbDocument::count(),
        ]);
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
            return response()->json([
                'error' => "Inbox directory mancante: {$inboxPath}. Contattare l'amministratore."
            ], 500);
        }

        $count = 0;
        foreach ($request->file('files') as $file) {
            $filename = $file->getClientOriginalName();
            $destPath = $inboxPath . '/' . $filename;
            $file->move($inboxPath, $filename);

            // Allinea owner/group e perms così il watcher (noscite) può processare
            @chgrp($destPath, 'noscite');
            @chmod($destPath, 0664);

            $count++;
        }

        return response()->json(['count' => $count, 'message' => "Caricati {$count} file in inbox"]);
    }
}
