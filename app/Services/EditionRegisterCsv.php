<?php

namespace App\Services;

use App\Models\CourseEdition;
use App\Support\AttendanceCell;

/**
 * Registro dell'edizione in CSV per Excel: UTF-8 con BOM e separatore ";"
 * (le impostazioni italiane di Excel lo aprono con un doppio clic).
 */
class EditionRegisterCsv
{
    public function build(CourseEdition $edition, array $register): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");

        $header = ['Discente', 'Email'];
        foreach ($register['days'] as $day) {
            $header[] = "G{$day->day_number} " . $day->scheduled_at?->format('d/m/Y');
        }
        array_push($header, 'Ore svolte', 'Ore previste', '% frequenza', 'Presenze', 'Assenze', 'Assenze giustificate', 'Ritardi', 'Uscite anticipate', 'Note');
        fputcsv($out, $header, ';');

        foreach ($register['rows'] as $row) {
            $line = [$row['student']->name, $row['student']->email];
            $notes = [];
            foreach ($register['days'] as $day) {
                $cell = $row['cells'][$day->id];
                $line[] = AttendanceCell::label($cell);
                if ($cell['record']?->note) {
                    $notes[] = "G{$day->day_number}: {$cell['record']->note}";
                }
            }
            $t = $row['totals'];
            array_push($line,
                $this->num($row['hours']), $this->num($row['planned']), $this->num($row['percent']),
                $t['present'], $t['absent'], $t['justified'], $t['late'], $t['early'], implode(' | ', $notes)
            );
            fputcsv($out, $line, ';');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Decimali con la virgola, come li legge Excel in italiano. */
    private function num(float $n): string
    {
        return str_replace('.', ',', (string) round($n, 2));
    }
}
