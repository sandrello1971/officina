<?php

namespace App\Console\Commands;

use App\Services\CertificatePdfBuilder as B;
use Illuminate\Console\Command;

/**
 * Genera il template vettoriale del certificato (brand Effetto Glitch):
 * cornice, logo, filigrana ed etichette fisse. I campi dinamici (nome,
 * corso, data, codice, QR…) li scrive CertificatePdfBuilder sopra questo
 * template, alle coordinate definite nelle sue costanti.
 *
 * Il PDF risultante è committato in resources/pdf/templates/: il comando
 * serve a rigenerarlo quando cambiano layout o brand, non gira in deploy.
 * I certificati già firmati non cambiano (il PDF firmato è un file a sé);
 * quelli non firmati si riallineano con certificates:rebuild-pdfs.
 */
class BuildCertificateTemplate extends Command
{
    protected $signature = 'certificates:build-template
                            {--output= : Path di destinazione (default: il template usato dal builder)}';

    protected $description = 'Rigenera il template PDF del certificato (brand Effetto Glitch).';

    private const W = 297.0;
    private const H = 210.0;

    private const LOGO = 'resources/pdf/templates/assets/effettoglitch-logo.png';
    private const MARK = 'resources/pdf/templates/assets/effettoglitch-mark.png';

    public function handle(): int
    {
        $output = $this->option('output') ?: base_path(B::TEMPLATE_PATH);

        $pdf = B::newDocument();
        $pdf->SetTitle('Template certificato Officina — Effetto Glitch');
        [$pw, $ph, $orientation] = B::pageFormat(self::W, self::H);
        $pdf->AddPage($orientation, [$pw, $ph]);

        $this->watermark($pdf);
        $this->frame($pdf);

        // Logo completo (G + wordmark + payoff), centrato in alto.
        $logoW = 78.0;
        $pdf->Image(base_path(self::LOGO), (self::W - $logoW) / 2.0, 16.0, $logoW, 0, 'PNG');

        $this->labels($pdf);
        $this->divider($pdf, 121.0);
        $this->officinaBadge($pdf);

        $pdf->Output($output, 'F');
        $this->info("Template scritto: {$output}");

        return self::SUCCESS;
    }

    /** Monogramma G molto tenue al centro, dietro al testo. */
    private function watermark($pdf): void
    {
        $markW = 128.0; // 453×415 px → h ≈ 117mm
        $pdf->SetAlpha(0.045);
        $pdf->Image(base_path(self::MARK), (self::W - $markW) / 2.0, 50.0, $markW, 0, 'PNG');
        $pdf->SetAlpha(1);
    }

    /**
     * Doppia cornice (viola esterna, indaco interna) "rotta" in quattro punti:
     * un tratto della linea è coperto di bianco e ridisegnato sfalsato, come
     * i segmenti del logo. Il copyright (B::COPYRIGHT_Y) sta dentro la
     * cornice interna, il cui lato basso è a 199.5mm.
     */
    private function frame($pdf): void
    {
        $pdf->SetDrawColor(...B::VIOLET);
        $pdf->SetLineWidth(0.7);
        $pdf->Rect(8.0, 8.0, self::W - 16.0, self::H - 16.0, 'D');

        $pdf->SetDrawColor(...B::INDIGO);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect(10.5, 10.5, self::W - 21.0, self::H - 21.0, 'D');

        $white = [255, 255, 255];
        $bar = function (array $color, float $x, float $y, float $w, float $h) use ($pdf): void {
            $pdf->SetFillColor(...$color);
            $pdf->Rect($x, $y, $w, $h, 'F');
        };

        // Lato alto, a sinistra
        $bar($white, 40.0, 7.4, 24.0, 1.2);
        $bar(B::VIOLET, 40.0, 5.9, 24.0, 0.7);
        $bar(B::INDIGO, 46.0, 9.1, 12.0, 0.5);
        $bar(B::VIOLET, 14.5, 3.9, 2.2, 2.2);

        // Lato basso, a destra
        $bar($white, 233.0, 201.4, 24.0, 1.2);
        $bar(B::VIOLET, 233.0, 203.4, 24.0, 0.7);
        $bar(B::INDIGO, 239.0, 200.3, 12.0, 0.5);
        $bar(B::VIOLET, 280.3, 203.9, 2.2, 2.2);

        // Lato sinistro, in basso
        $bar($white, 7.4, 150.0, 1.2, 16.0);
        $bar(B::VIOLET, 5.9, 150.0, 0.7, 16.0);

        // Lato destro, in alto
        $bar($white, 288.4, 44.0, 1.2, 16.0);
        $bar(B::VIOLET, 290.4, 44.0, 0.7, 16.0);
    }

    private function labels($pdf): void
    {
        $label = fn (string $text, array $cfg) => B::writeField($pdf, $text, $cfg + [
            'h' => 5.0,
            'font' => 'jetbrainsmonomedium',
            'size' => 8.5,
            'color' => B::MUTED,
            'spacing' => 1.4,
        ], self::W);

        $label('CERTIFICA CHE', ['y' => 48.0]);

        B::writeField($pdf, 'ha completato con successo il corso', [
            'y' => 72.0, 'h' => 6.0, 'font' => 'spacegrotesk', 'size' => 11.5, 'color' => B::MUTED,
        ], self::W);

        $details = ['date' => 'DATA DI EMISSIONE', 'code' => 'CODICE CERTIFICATO', 'owner' => 'RILASCIATO DA'];
        foreach ($details as $col => $text) {
            $label($text, ['y' => B::DETAILS_LABEL_Y, 'h' => 4.0, 'size' => 6.8, 'spacing' => 1.1, 'col' => $col]);
        }

        // Separatori verticali tra le tre colonne
        $pdf->SetDrawColor(...B::HAZE);
        $pdf->SetLineWidth(0.25);
        $cols = array_values(B::COLUMNS);
        for ($i = 0; $i < count($cols) - 1; $i++) {
            $x = ($cols[$i]['cx'] + $cols[$i + 1]['cx']) / 2.0;
            $pdf->Line($x, 129.5, $x, 142.0);
        }
    }

    /** Linea divisoria sottile con tre segmenti sfalsati al centro. */
    private function divider($pdf, float $y): void
    {
        $pdf->SetDrawColor(...B::HAZE);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(108.5, $y, 188.5, $y);

        $pdf->SetFillColor(...B::VIOLET);
        $pdf->Rect(141.5, $y - 0.45, 9.0, 0.9, 'F');
        $pdf->SetFillColor(...B::INDIGO);
        $pdf->Rect(152.0, $y + 0.5, 4.0, 0.5, 'F');
        $pdf->Rect(133.0, $y - 1.1, 5.0, 0.5, 'F');
    }

    /** Firma di prodotto in basso a destra, a bilanciare il blocco QR. */
    private function officinaBadge($pdf): void
    {
        $x = 227.0;
        $w = 45.0;
        $markW = 10.0;
        $pdf->Image(base_path(self::MARK), $x + ($w - $markW) / 2.0, 157.0, $markW, 0, 'PNG');

        B::writeField($pdf, 'OFFICINA', [
            'x' => $x, 'w' => $w, 'y' => 169.5, 'h' => 5.0,
            'font' => 'jetbrainsmonomedium', 'size' => 8.5, 'color' => B::VIOLET, 'spacing' => 2.2,
        ], self::W);
        B::writeField($pdf, 'officina.effettoglitch.it', [
            'x' => $x, 'w' => $w, 'y' => 175.0, 'h' => 4.0,
            'font' => 'jetbrainsmono', 'size' => 6.5, 'color' => B::MUTED,
        ], self::W);
    }
}
