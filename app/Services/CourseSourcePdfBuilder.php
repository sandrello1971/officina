<?php

namespace App\Services;

use TCPDF;

/**
 * P25.1 — Rigenera un PDF dal sorgente strutturato (blocchi tipizzati).
 *
 * Il PDF è un OUTPUT del sorgente, non il contrario. Obiettivo: FEDELTÀ DI
 * CONTENUTO E STRUTTURA, non estetica — il risultato è volutamente diverso dal
 * .docx originale. La gerarchia PART/H1/H2 deve essere visivamente distinguibile.
 *
 * Font: DejaVuSans (unicode, bundled in TCPDF) per rendere em-dash, apostrofi
 * tipografici e accenti senza glyph mancanti.
 */
class CourseSourcePdfBuilder
{
    // Palette brand (coerente col default dei corsi, non vincolante per la fedeltà).
    private const TEAL = [85, 177, 174];   // #55B1AE — PART band / H2
    private const INK = [26, 31, 31];      // testo
    private const BOX_FILL = [244, 246, 246];
    private const BOX_BORDER = [200, 210, 210];

    /** Diagnostica dell'ultima build (per i log/round-trip). */
    public int $lastRenderedBlocks = 0;
    public int $lastPageCount = 0;

    /**
     * Costruisce i bytes del PDF dai blocchi.
     *
     * @param  list<array>  $blocks  blocchi tipizzati (output di CourseSourceExtractor)
     * @param  array{title?: string}  $meta
     */
    public function build(array $blocks, array $meta = []): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Officina');
        $pdf->SetTitle($meta['title'] ?? 'Corso — sorgente strutturato');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 18, 20);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();

        $rendered = 0;
        foreach ($blocks as $block) {
            $this->renderBlock($pdf, $block, $rendered);
            $rendered++;
        }

        $this->lastRenderedBlocks = $rendered;
        $this->lastPageCount = $pdf->getNumPages();

        return $pdf->Output('', 'S');
    }

    private function renderBlock(TCPDF $pdf, array $block, int $index): void
    {
        $type = $block['type'] ?? 'P';
        $text = trim((string) ($block['text'] ?? ''));

        switch ($type) {
            case 'PART':
                // Nuova pagina (tranne se è il primissimo blocco) + banda colorata piena.
                if ($index > 0) {
                    $pdf->AddPage();
                }
                $pdf->Ln(2);
                $pdf->SetFont('dejavusans', 'B', 19);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFillColorArray(self::TEAL);
                $pdf->MultiCell(0, 12, mb_strtoupper($text), 0, 'L', true, 1, '', '', true, 0, false, true, 12, 'M');
                $pdf->SetTextColorArray(self::INK);
                $pdf->Ln(4);
                break;

            case 'H1':
                // Capitolo: grande, grassetto, con riga inferiore (border 'B').
                $pdf->Ln(3);
                $pdf->SetFont('dejavusans', 'B', 15);
                $pdf->SetTextColorArray(self::INK);
                $pdf->MultiCell(0, 8, $text, 'B', 'L');
                $pdf->Ln(2);
                break;

            case 'H2':
                // Sezione: medio, grassetto, colore teal — distinto da H1.
                $pdf->Ln(1.5);
                $pdf->SetFont('dejavusans', 'B', 12);
                $pdf->SetTextColorArray(self::TEAL);
                $pdf->MultiCell(0, 6.5, $text, 0, 'L');
                $pdf->SetTextColorArray(self::INK);
                $pdf->Ln(1);
                break;

            case 'BOX':
                $pdf->Ln(1);
                $pdf->SetFont('dejavusans', '', 10);
                $pdf->SetFillColorArray(self::BOX_FILL);
                $pdf->SetDrawColorArray(self::BOX_BORDER);
                $pdf->MultiCell(0, 5, $text, 1, 'L', true);
                $pdf->Ln(2);
                break;

            case 'EX':
            case 'ESE':
                $label = $type === 'EX' ? 'ESEMPIO' : 'ESERCIZIO';
                $pdf->Ln(1);
                $pdf->SetFont('dejavusans', 'B', 9);
                $pdf->SetTextColorArray(self::TEAL);
                $pdf->MultiCell(0, 5, $label, 0, 'L');
                $pdf->SetTextColorArray(self::INK);
                $pdf->SetFont('dejavusans', '', 10);
                $pdf->SetFillColorArray(self::BOX_FILL);
                $pdf->SetDrawColorArray(self::BOX_BORDER);
                $pdf->MultiCell(0, 5, $text, 1, 'L', true);
                $pdf->Ln(2);
                break;

            case 'NUM':
            case 'BUL':
                $pdf->SetFont('dejavusans', '', 10.5);
                $pdf->SetTextColorArray(self::INK);
                $items = $block['items'] ?? [];
                foreach ($items as $i => $item) {
                    $marker = $type === 'NUM' ? (($i + 1) . '.') : '•';
                    $pdf->MultiCell(0, 5, $marker . '  ' . trim((string) $item), 0, 'L');
                }
                $pdf->Ln(1.5);
                break;

            case 'P':
            default:
                $pdf->SetFont('dejavusans', '', 10.5);
                $pdf->SetTextColorArray(self::INK);
                $pdf->MultiCell(0, 5.2, $text, 0, 'J');
                $pdf->Ln(1.5);
                break;
        }
    }
}
