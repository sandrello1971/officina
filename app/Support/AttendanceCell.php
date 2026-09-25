<?php

namespace App\Support;

use App\Models\AttendanceRecord;

/**
 * Etichetta breve di una cella del registro (griglia, PDF, CSV):
 * P · A · AG · R 09:20 (ritardo) · U 12:30 (uscita anticipata) · R/U · vuoto.
 */
class AttendanceCell
{
    /** @param array{record:?AttendanceRecord, late:int, early:int} $cell */
    public static function label(array $cell): string
    {
        $record = $cell['record'];

        return match ($record?->status) {
            AttendanceRecord::STATUS_ABSENT => 'A',
            AttendanceRecord::STATUS_JUSTIFIED => 'AG',
            AttendanceRecord::STATUS_PRESENT => match (true) {
                $cell['late'] > 0 && $cell['early'] > 0 => 'R/U',
                $cell['late'] > 0 => 'R ' . substr((string) $record->arrived_at, 0, 5),
                $cell['early'] > 0 => 'U ' . substr((string) $record->left_at, 0, 5),
                default => 'P',
            },
            default => '',
        };
    }

    /** Descrizione estesa per tooltip e note. */
    public static function describe(array $cell): string
    {
        $record = $cell['record'];
        if (! $record) {
            return 'Appello non registrato';
        }

        $parts = [AttendanceRecord::STATUSES[$record->status] ?? $record->status];
        if ($cell['late'] > 0) {
            $parts[] = 'entrata ' . substr((string) $record->arrived_at, 0, 5) . " ({$cell['late']} min di ritardo)";
        }
        if ($cell['early'] > 0) {
            $parts[] = 'uscita ' . substr((string) $record->left_at, 0, 5) . " ({$cell['early']} min prima)";
        }
        if ($record->isPresent()) {
            $parts[] = self::hours((float) $record->hours_credited);
        }
        if ($record->note) {
            $parts[] = 'nota: ' . $record->note;
        }

        return implode(' · ', $parts);
    }

    public static function hours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',') . 'h';
    }
}
