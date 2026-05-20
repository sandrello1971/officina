<?php

namespace App\Console\Commands;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Console\Command;

class RegisterPdfFonts extends Command
{
    protected $signature = 'pdf:register-fonts';
    protected $description = 'Registra font custom (Cormorant Garamond, Inter) in dompdf per il template certificato.';

    /**
     * I file in storage/fonts/ sono variable fonts (axes wght / opsz,wght).
     * dompdf li tratta come TTF singoli: l'asse interno non viene
     * interpretato, quindi mappiamo manualmente la stessa fonte sia su
     * normal sia su bold (l'effetto bold sarà visivamente identico al
     * regular — accettabile per la prima iterazione, futuro switch a
     * font statici weight-specifici se serve).
     */
    public function handle(): int
    {
        $cgDir = storage_path('fonts/cormorant-garamond');
        $interDir = storage_path('fonts/inter');

        $families = [
            'cormorant garamond' => [
                'normal'      => $cgDir . '/CormorantGaramond-Variable.ttf',
                'bold'        => $cgDir . '/CormorantGaramond-Variable.ttf',
                'italic'      => $cgDir . '/CormorantGaramond-Italic-Variable.ttf',
                'bold_italic' => $cgDir . '/CormorantGaramond-Italic-Variable.ttf',
            ],
            'inter' => [
                'normal' => $interDir . '/Inter-Variable.ttf',
                'bold'   => $interDir . '/Inter-Variable.ttf',
            ],
        ];

        // Critico: senza queste Options la nuova istanza dompdf usa
        // i font_dir/font_cache di default (vendor/dompdf/dompdf/lib/fonts),
        // e saveFontFamilies() persiste lì invece che in storage/fonts.
        // Il config laravel-dompdf è onorato solo dalla facade Pdf:: —
        // questa istanza diretta va configurata manualmente.
        $options = new Options();
        $options->setFontDir(storage_path('fonts'));
        $options->setFontCache(storage_path('fonts'));

        $dompdf = new Dompdf($options);
        $fontMetrics = $dompdf->getFontMetrics();

        $registered = 0;
        $missing = 0;

        foreach ($families as $family => $variants) {
            $this->info("Registrazione famiglia: {$family}");

            $entries = []; // entries da passare a setFontFamily()

            foreach ($variants as $variant => $path) {
                if (!is_file($path)) {
                    $this->error("  MISSING {$variant}: {$path}");
                    $missing++;
                    continue;
                }
                // registerFont (1) installa il font nel font_dir con i nomi
                // canonici dompdf (es. <family>-<variant>.ttf), e (2)
                // produce le metriche di base. Ne ho bisogno per la prima
                // installazione; per i run successivi rileva il file
                // esistente e salta.
                $fontMetrics->registerFont(
                    ['family' => $family, 'style' => str_contains($variant, 'italic') ? 'italic' : 'normal', 'weight' => str_contains($variant, 'bold') ? 'bold' : 'normal'],
                    $path
                );

                $entries[$variant] = $path;
                $this->info("  OK {$variant}: " . basename($path));
                $registered++;
            }

            if (!empty($entries)) {
                // setFontFamily aggiunge la famiglia a _fontFamilies →
                // saveFontFamilies() ora la serializza in installed-fonts.json
                $fontMetrics->setFontFamily($family, $entries);
            }
        }

        // Persiste la mappatura family → file in storage/fonts/installed-fonts.json
        // così le successive istanze dompdf (chiamate dal PDF builder)
        // riconoscono le famiglie senza dover ri-chiamare registerFont.
        $fontMetrics->saveFontFamilies();

        $this->newLine();
        $this->info("Registrati: {$registered}; mancanti: {$missing}");
        $this->info('Mappatura persistita in: ' . storage_path('fonts/installed-fonts.json'));

        return $missing > 0 ? self::FAILURE : self::SUCCESS;
    }
}
