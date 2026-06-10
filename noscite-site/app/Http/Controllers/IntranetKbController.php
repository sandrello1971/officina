<?php

namespace App\Http\Controllers;

use App\Models\KbDocument;
use App\Services\KbSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class IntranetKbController extends Controller
{
    /**
     * Esegue un comando git SENZA shell (array di argomenti), con working dir
     * esplicita. Niente interpolazione di variabili in stringhe di comando →
     * nessun rischio di command injection. Ritorna il Process eseguito.
     */
    private function runGit(array $args, string $cwd): Process
    {
        $process = new Process(array_merge(['git'], $args), $cwd);
        $process->run();

        return $process;
    }

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
        $pull = $this->runGit(['pull', 'origin', 'main'], $vaultPath);
        $gitOutput = trim($pull->getOutput() . $pull->getErrorOutput());

        $stats = app(KbSyncService::class)->sync();

        return back()->with('success',
            "Sync completato: {$stats['created']} nuovi, {$stats['updated']} aggiornati. Git: " . $gitOutput
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

        // Validazione difensiva dello stem: solo caratteri sicuri per nomi file.
        // Se non matcha, salta SOLO le operazioni git (record DB e file locali
        // sono già stati cancellati sopra, come da comportamento attuale).
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $stem)) {
            Log::warning("KB destroy: file_stem non valido, operazioni git saltate: {$stem}");

            return back()->with('success', 'Documento eliminato.');
        }

        // Rimozione dal git index SENZA shell (Process con array di argomenti).
        $this->runGit(['rm', '--ignore-unmatch', "_metadata/{$stem}.md"], $vaultPath);

        // Glob risolto lato PHP su path assoluto: niente espansione wildcard via
        // shell. I file trovati sono passati come argomenti espliciti a `git rm`.
        $archiveFiles = glob($vaultPath . '/_archive/' . $stem . '.*') ?: [];
        if ($archiveFiles) {
            $archiveRelPaths = array_map(fn ($f) => '_archive/' . basename($f), $archiveFiles);
            $this->runGit(array_merge(['rm', '--ignore-unmatch'], $archiveRelPaths), $vaultPath);
        }

        // Commit + sync con remote + push
        $this->runGit(['add', '-A'], $vaultPath);
        $this->runGit(['commit', '-m', "kb: eliminato {$stem}"], $vaultPath);
        $this->runGit(['pull', '--rebase', 'origin', 'main'], $vaultPath);
        $push = $this->runGit(['push', 'origin', 'main'], $vaultPath);

        if (!$push->isSuccessful()) {
            Log::warning("KB destroy: push fallito rc={$push->getExitCode()}: "
                . trim($push->getOutput() . $push->getErrorOutput()));
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
