<?php

namespace App\Console\Commands;

use App\Services\CertificatePdfBuilder;
use Illuminate\Console\Command;

class RegisterTcpdfFonts extends Command
{
    protected $signature = 'pdf:register-tcpdf-fonts
                            {--force : Rigenera anche le definizioni già presenti}';
    protected $description = 'Registra i font del brand (Space Grotesk, JetBrains Mono) in TCPDF per il certificato.';

    /**
     * Genera in storage/fonts/tcpdf le definizioni TCPDF dei .ttf in
     * resources/fonts (vedi CertificatePdfBuilder::FONTS).
     *
     * Non è più un passo obbligatorio: il builder genera da solo i font
     * mancanti al primo certificato. Serve per pre-generarli al deploy (così
     * la prima richiesta non paga la conversione) e per verificarli.
     */
    public function handle(): int
    {
        $dir = CertificatePdfBuilder::fontDir();

        if ($this->option('force')) {
            foreach (array_keys(CertificatePdfBuilder::FONTS) as $name) {
                foreach (glob("{$dir}/{$name}.*") ?: [] as $file) {
                    @unlink($file);
                }
            }
        }

        try {
            $defs = CertificatePdfBuilder::ensureFonts();
        } catch (\Throwable $e) {
            $this->error('  ✗ ' . $e->getMessage());
            return self::FAILURE;
        }

        foreach ($defs as $name => $def) {
            $this->info("  ✓ {$name} → {$def}");
        }

        return self::SUCCESS;
    }
}
