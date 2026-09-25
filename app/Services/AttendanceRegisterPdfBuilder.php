<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEdition;
use App\Support\AttendanceCell;
use App\Support\Pdf\CopyrightTcpdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Costruisce il PDF del registro di frequenza di un corso: intestazione con i
 * dati del corso, tabella discenti con ore sincrono / asincrono / totale, e le
 * sessioni sincrone svolte. Font unicode 'dejavusans' (bundled) per accenti.
 */
class AttendanceRegisterPdfBuilder
{
    /**
     * @param  Collection<int, array>  $rows   output di AttendanceService::courseRegister()
     * @param  Collection<int, \App\Models\CourseSession>  $sessions
     */
    public function buildCourseRegister(Course $course, Collection $rows, Collection $sessions): string
    {
        $owner = atheneum_setting('platform_owner', 'Stefano Andrello');

        $pdf = new CopyrightTcpdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Officina');
        $pdf->SetAuthor($owner);
        $pdf->SetTitle('Registro di frequenza — ' . $course->name);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        // Intestazione.
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->SetTextColor(26, 31, 31);
        $pdf->Cell(0, 9, 'Registro di frequenza', 0, 1, 'L');

        $pdf->SetFont('dejavusans', '', 11);
        $pdf->SetTextColor(85, 177, 174);
        $pdf->Cell(0, 7, $course->name, 0, 1, 'L');

        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(90, 90, 90);
        $totHours = $course->duration_hours ? $course->duration_hours . 'h previste' : '';
        $meta = trim(implode('  ·  ', array_filter([
            'Modalità: ' . $course->modalityLabel(),
            $totHours,
            'Iscritti: ' . $rows->count(),
            'Emesso il ' . Carbon::now()->locale('it')->isoFormat('D MMMM YYYY'),
        ])));
        $pdf->Cell(0, 6, $meta, 0, 1, 'L');
        $pdf->Ln(3);

        // Tabella discenti.
        $this->tableHeader($pdf, [
            ['Discente', 70], ['Sincrono', 28], ['FAD', 28], ['Totale', 28], ['Ultima attività', 26],
        ]);

        $pdf->SetFont('dejavusans', '', 9);
        $fill = false;
        foreach ($rows as $r) {
            $pdf->SetFillColor(247, 249, 249);
            $pdf->SetTextColor(40, 40, 40);
            $last = $r['last_activity'] ? Carbon::parse($r['last_activity'])->format('d/m/Y') : '—';
            $this->row($pdf, [
                [$r['student']->name, 70, 'L'],
                [$this->h($r['sync_hours']), 28, 'C'],
                [$this->h($r['async_hours']), 28, 'C'],
                [$this->h($r['total_hours']), 28, 'C'],
                [$last, 26, 'C'],
            ], $fill);
            $fill = ! $fill;
        }

        if ($rows->isEmpty()) {
            $pdf->SetFont('dejavusans', 'I', 9);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell(0, 8, 'Nessun discente iscritto.', 0, 1, 'L');
        }

        // Sessioni sincrone.
        if ($sessions->isNotEmpty()) {
            $pdf->Ln(6);
            $pdf->SetFont('dejavusans', 'B', 11);
            $pdf->SetTextColor(26, 31, 31);
            $pdf->Cell(0, 7, 'Sessioni sincrone', 0, 1, 'L');

            $this->tableHeader($pdf, [
                ['Sessione', 80], ['Data', 34], ['Durata', 24], ['Modalità', 42],
            ]);
            $pdf->SetFont('dejavusans', '', 9);
            $fill = false;
            foreach ($sessions as $s) {
                $modality = $s->modality === 'in_person' ? 'In aula' : 'Live online';
                $this->row($pdf, [
                    [$s->title, 80, 'L'],
                    [$s->scheduled_at?->format('d/m/Y H:i') ?? '—', 34, 'C'],
                    [($s->duration_minutes ?? 0) . ' min', 24, 'C'],
                    [$modality, 42, 'C'],
                ], $fill);
                $fill = ! $fill;
            }
        }

        return $pdf->Output('registro.pdf', 'S');
    }

    /** Giornate per blocco di griglia: oltre, la griglia continua in un blocco successivo. */
    private const DAYS_PER_BLOCK = 10;

    /**
     * Registro dell'edizione (A4 orizzontale): griglia discenti × giornate con
     * lo stato di ogni appello, ore svolte/previste e % di frequenza, legenda e
     * note degli appelli.
     *
     * @param  array  $register  output di AttendanceService::editionRegister()
     */
    public function buildEditionRegister(CourseEdition $edition, array $register): string
    {
        $course = $edition->course;
        $days = $register['days'];
        $rows = $register['rows'];

        $pdf = new CopyrightTcpdf('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Officina');
        $pdf->SetAuthor(atheneum_setting('platform_owner', atheneum_setting('instance_name', 'Officina')));
        $pdf->SetTitle('Registro presenze — ' . $course->name . ' — ' . $edition->name);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->SetMargins(12, 12, 12);
        $pdf->AddPage();

        $pdf->SetFont('dejavusans', 'B', 15);
        $pdf->SetTextColor(26, 31, 31);
        $pdf->Cell(0, 8, 'Registro presenze', 0, 1, 'L');
        $pdf->SetFont('dejavusans', '', 11);
        $pdf->SetTextColor(85, 177, 174);
        $pdf->Cell(0, 6, $course->name . ' — ' . $edition->name, 0, 1, 'L');

        $pdf->SetFont('dejavusans', '', 8.5);
        $pdf->SetTextColor(90, 90, 90);
        $first = $days->first()?->scheduled_at;
        $last = $days->last()?->scheduled_at;
        $meta = implode('  ·  ', array_filter([
            atheneum_setting('instance_name', 'Officina'),
            $edition->modalityLabel(),
            $edition->location,
            $edition->instructor ? 'Formatore: ' . $edition->instructor->name : null,
            $first ? 'Dal ' . $first->format('d/m/Y') . ' al ' . $last->format('d/m/Y') : null,
            $days->count() . ' giornate · ' . AttendanceCell::hours($register['planned_hours']) . ' previste',
            'Discenti: ' . $rows->count(),
            'Emesso il ' . Carbon::now()->format('d/m/Y'),
        ]));
        $pdf->MultiCell(0, 5, $meta, 0, 'L');
        $pdf->Ln(2);

        if ($rows->isEmpty() || $days->isEmpty()) {
            $pdf->SetFont('dejavusans', 'I', 9);
            $pdf->Cell(0, 8, $days->isEmpty() ? 'Nessuna giornata pianificata.' : 'Nessun discente associato.', 0, 1, 'L');

            return $pdf->Output('registro.pdf', 'S');
        }

        $nameW = 58.0;
        $totW = [22.0, 16.0];
        foreach ($days->chunk(self::DAYS_PER_BLOCK) as $blockIndex => $block) {
            $isLast = $blockIndex === (int) ceil($days->count() / self::DAYS_PER_BLOCK) - 1;
            $avail = 273.0 - $nameW - ($isLast ? array_sum($totW) : 0);
            $dayW = min(20.0, $avail / max(1, $block->count()));

            // Intestazione: numero giornata e data.
            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->SetFillColor(85, 177, 174);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($nameW, 10, 'Discente', 0, 0, 'L', true);
            foreach ($block as $day) {
                $x = $pdf->GetX();
                $y = $pdf->GetY();
                $pdf->MultiCell($dayW, 10, "G{$day->day_number}\n" . $day->scheduled_at?->format('d/m'), 0, 'C', true, 0, $x, $y, true, 0, false, true, 10, 'M');
            }
            if ($isLast) {
                $pdf->Cell($totW[0], 10, 'Ore', 0, 0, 'C', true);
                $pdf->Cell($totW[1], 10, '%', 0, 0, 'C', true);
            }
            $pdf->Ln();

            $pdf->SetFont('dejavusans', '', 8);
            $fill = false;
            foreach ($rows as $row) {
                $pdf->SetFillColor(247, 249, 249);
                $pdf->SetTextColor(40, 40, 40);
                $pdf->Cell($nameW, 6.5, $row['student']->name, 0, 0, 'L', $fill);
                foreach ($block as $day) {
                    $label = AttendanceCell::label($row['cells'][$day->id]);
                    $this->statusColor($pdf, $label);
                    $pdf->Cell($dayW, 6.5, $label, 0, 0, 'C', $fill);
                    $pdf->SetTextColor(40, 40, 40);
                }
                if ($isLast) {
                    $pdf->Cell($totW[0], 6.5, AttendanceCell::hours($row['hours']) . ' / ' . AttendanceCell::hours($row['planned']), 0, 0, 'C', $fill);
                    $pdf->Cell($totW[1], 6.5, number_format($row['percent'], 0) . '%', 0, 0, 'C', $fill);
                }
                $pdf->Ln();
                $fill = ! $fill;
            }

            // Presenti per giornata.
            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->SetTextColor(26, 31, 31);
            $pdf->Cell($nameW, 6.5, 'Presenti', 'T', 0, 'L');
            foreach ($block as $day) {
                $pdf->Cell($dayW, 6.5, $register['present_per_day'][$day->id] . '/' . $rows->count(), 'T', 0, 'C');
            }
            $pdf->Ln(9);
        }

        $pdf->SetFont('dejavusans', '', 7.5);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->MultiCell(0, 4.5, 'Legenda: P presente · A assente · AG assente giustificato · R hh:mm entrata in ritardo · U hh:mm uscita anticipata · R/U entrambi · cella vuota: appello non registrato.', 0, 'L');

        $notes = [];
        foreach ($rows as $row) {
            foreach ($days as $day) {
                $record = $row['cells'][$day->id]['record'];
                if ($record?->note) {
                    $notes[] = $row['student']->name . " — G{$day->day_number}: " . $record->note;
                }
            }
        }
        if ($notes) {
            $pdf->Ln(2);
            $pdf->SetFont('dejavusans', 'B', 8.5);
            $pdf->SetTextColor(26, 31, 31);
            $pdf->Cell(0, 5, 'Note', 0, 1, 'L');
            $pdf->SetFont('dejavusans', '', 8);
            $pdf->SetTextColor(60, 60, 60);
            foreach ($notes as $note) {
                $pdf->MultiCell(0, 4.5, $note, 0, 'L');
            }
        }

        return $pdf->Output('registro.pdf', 'S');
    }

    private function statusColor($pdf, string $label): void
    {
        match (true) {
            $label === 'A' => $pdf->SetTextColor(180, 35, 24),
            $label === 'AG' => $pdf->SetTextColor(180, 110, 20),
            str_starts_with($label, 'R') || str_starts_with($label, 'U') => $pdf->SetTextColor(160, 90, 0),
            default => $pdf->SetTextColor(40, 40, 40),
        };
    }

    /** @param array<int, array{0:string,1:int}> $cols */
    private function tableHeader($pdf, array $cols): void
    {
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetFillColor(85, 177, 174);
        $pdf->SetTextColor(255, 255, 255);
        foreach ($cols as $i => [$label, $w]) {
            $align = $i === 0 ? 'L' : 'C';
            $pdf->Cell($w, 8, $label, 0, $i === count($cols) - 1 ? 1 : 0, $align, true);
        }
    }

    /** @param array<int, array{0:string,1:int,2:string}> $cells */
    private function row($pdf, array $cells, bool $fill): void
    {
        foreach ($cells as $i => [$text, $w, $align]) {
            $pdf->Cell($w, 7, $text, 0, $i === count($cells) - 1 ? 1 : 0, $align, $fill);
        }
    }

    private function h(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',') . 'h';
    }
}
