<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Applica la dicitura di copyright di piattaforma a un .pptx CARICATO a mano.
 *
 * Le presentazioni generate ricevono il footer durante il render
 * (build_pptx.py); quelle caricate dall'admin arriverebbero allo studente — e
 * nei video, che nascono dai PNG delle slide — senza alcuna tutela. Qui si
 * colma la differenza, così ogni slide che circola porta la stessa dicitura.
 *
 * Fail-closed per scelta: se la stampigliatura non riesce si solleva
 * un'eccezione e l'upload viene rifiutato. Meglio un errore visibile che un
 * contenuto pubblicato senza dicitura.
 */
class PptxCopyrightStamper
{
    /**
     * Stampiglia in place il file assoluto indicato. No-op se la dicitura di
     * piattaforma è vuota (copyright disattivato per configurazione).
     *
     * @throws RuntimeException se lo script python fallisce
     */
    public function stamp(string $absolutePath): void
    {
        $notice = copyright_notice();
        if ($notice === '') {
            return;
        }

        $python = config('services.pptx.python', '/home/noscite/venv/bin/python');
        $script = base_path('resources/python/stamp_pptx_copyright.py');

        $process = new Process([$python, $script, $absolutePath, $notice]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Stampigliatura copyright .pptx fallita', [
                'path' => $absolutePath,
                'exit' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);

            throw new RuntimeException(
                'Impossibile applicare la dicitura di copyright alla presentazione.'
            );
        }
    }
}
