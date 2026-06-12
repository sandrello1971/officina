<?php

namespace App\Services\Freshness;

use App\Models\Course;
use App\Models\CourseChangelog;
use App\Models\CourseSource;
use App\Models\FormatoreSnapshot;
use App\Models\InstructorManualSection;
use App\Models\UpdateProposal;
use App\Services\CourseSourcePdfBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * P25.3c — Applicazione delle proposte APPROVED al lato formatore, con versioning e
 * rollback. Variante A: tocca SOLO course_sources + instructor_manual_sections (+ PDF).
 * `modules.content` (studente) NON viene toccato (sotto-fase B).
 *
 * HITL: consuma SOLO proposte status='approved'. Nessuna pending raggiunge il contenuto.
 * Verbatim o niente: ogni proposta si applica solo se il `before` è trovato ESATTAMENTE
 * una volta sia nel blocco del sorgente sia nel contenuto formatore live; altrimenti
 * fallisce in modo pulito (apply_error) e NON viene applicata.
 *
 * Additività: non interferisce con le vie di modifica manuale di instructor_manual_sections.
 */
class ProposalApplicator
{
    public function __construct(private CourseSourcePdfBuilder $pdfBuilder) {}

    /**
     * Applica le proposte approved del corso. Crea una nuova versione di course_sources,
     * aggiorna il formatore live (con backup), scrive il changelog, rigenera il PDF.
     *
     * @return array{applied:int, failed:array<int,array{id:string,error:string}>, version_from:?string, version_to:?string}
     */
    public function apply(Course $course, bool $minorConfirmed = false): array
    {
        return DB::transaction(function () use ($course, $minorConfirmed) {
            // GATE SCHOLA/MINORI (P25.3e): barriera in PIÙ, non sostituisce l'HITL. Su un
            // corso audience=minor l'applicazione richiede una conferma esplicita aggiuntiva
            // (gate 2, umano) oltre alle proposte già approvate (gate 1, umano). Senza
            // conferma → bloccata in modo pulito, NESSUNA modifica.
            $audience = optional($course->freshnessConfig)->audience ?? 'adult';
            if ($audience === 'minor' && !$minorConfirmed) {
                return [
                    'applied' => 0, 'failed' => [], 'blocked' => 'minor_confirmation_required',
                    'version_from' => optional($this->latestSource($course))->version, 'version_to' => null,
                ];
            }

            $current = $this->latestSource($course);
            if (!$current) {
                throw new RuntimeException("Nessun course_sources per il corso {$course->id}: niente da applicare.");
            }

            $approved = UpdateProposal::where('course_id', $course->id)
                ->where('status', 'approved')
                ->orderBy('created_at')
                ->get();

            if ($approved->isEmpty()) {
                return ['applied' => 0, 'failed' => [], 'version_from' => $current->version, 'version_to' => null, 'blocked' => null];
            }

            $blocks = $current->blocks ?? [];
            // indice block_id → posizione
            $blockIndex = [];
            foreach ($blocks as $i => $b) {
                if (isset($b['id'])) {
                    $blockIndex[$b['id']] = $i;
                }
            }

            $sections = InstructorManualSection::where('course_id', $course->id)->get();
            $liveContent = [];   // section_id → html di lavoro (progressivamente modificato)
            $preSnapshot = [];   // section_id → html PRE-batch (per rollback), al primo tocco
            foreach ($sections as $s) {
                $liveContent[$s->id] = $s->content_html;
            }

            $appliedProposals = [];
            $failed = [];

            foreach ($approved as $p) {
                // 1) Sorgente strutturato: blocco per block_id.
                if (!array_key_exists($p->block_id, $blockIndex)) {
                    $this->fail($p, "sorgente: block_id {$p->block_id} non presente nella versione corrente");
                    $failed[] = ['id' => $p->id, 'error' => $p->apply_error];
                    continue;
                }
                $idx = $blockIndex[$p->block_id];
                $srcRes = VerbatimReplacer::replaceUnique($blocks[$idx]['text'] ?? '', $p->before, $p->after);
                if (!$srcRes['ok']) {
                    $this->fail($p, 'sorgente: ' . $srcRes['reason']);
                    $failed[] = ['id' => $p->id, 'error' => $p->apply_error];
                    continue;
                }

                // 2) Formatore live: il before deve essere unico SU TUTTE le sezioni.
                $hitSection = null;
                $totalHits = 0;
                foreach ($liveContent as $sid => $html) {
                    $c = VerbatimReplacer::countOccurrences($html, $p->before);
                    if ($c > 0) {
                        $totalHits += $c;
                        $hitSection = $sid;
                    }
                }
                if ($totalHits !== 1) {
                    $this->fail($p, "formatore: before non trovato o non unico ({$totalHits} occorrenze)");
                    $failed[] = ['id' => $p->id, 'error' => $p->apply_error];
                    continue;
                }
                $liveRes = VerbatimReplacer::replaceUnique($liveContent[$hitSection], $p->before, $p->after);
                if (!$liveRes['ok']) {
                    $this->fail($p, 'formatore: ' . $liveRes['reason']);
                    $failed[] = ['id' => $p->id, 'error' => $p->apply_error];
                    continue;
                }

                // 3) Entrambi ok → applica alle copie di lavoro.
                $blocks[$idx]['text'] = $srcRes['result'];
                if (!array_key_exists($hitSection, $preSnapshot)) {
                    $preSnapshot[$hitSection] = $sections->firstWhere('id', $hitSection)->content_html; // PRE-batch
                }
                $liveContent[$hitSection] = $liveRes['result'];
                $appliedProposals[] = ['proposal' => $p, 'section_id' => $hitSection];
            }

            if (empty($appliedProposals)) {
                // Tutte fallite: apply_error già persistito, nessun bump di versione.
                return ['applied' => 0, 'failed' => $failed, 'version_from' => $current->version, 'version_to' => null, 'blocked' => null];
            }

            // 4) Nuova versione del sorgente (la precedente resta intatta).
            $newVersion = $this->nextVersion($current->version);
            $newSource = CourseSource::create([
                'course_id' => $course->id,
                'version' => $newVersion,
                'blocks' => array_values($blocks),
            ]);

            // 5) Backup live (pre-batch) + scrittura del formatore live aggiornato.
            foreach ($preSnapshot as $sid => $preHtml) {
                FormatoreSnapshot::create([
                    'course_id' => $course->id,
                    'course_source_id' => $newSource->id,
                    'version' => $newVersion,
                    'instructor_manual_section_id' => $sid,
                    'content_html' => $preHtml,
                ]);
                InstructorManualSection::where('id', $sid)->update(['content_html' => $liveContent[$sid]]);
            }

            // 6) Proposte → applied + changelog (audit per proposta).
            foreach ($appliedProposals as $ap) {
                $p = $ap['proposal'];
                $p->update(['status' => 'applied', 'applied_at' => now(), 'apply_error' => null]);
                CourseChangelog::create([
                    'course_id' => $course->id,
                    'proposal_id' => $p->id,
                    'version_from' => $current->version,
                    'version_to' => $newVersion,
                    'kind' => 'apply',
                    'summary' => mb_substr($p->before, 0, 120) . ' → ' . mb_substr($p->after, 0, 120),
                    'approved_by' => $p->reviewed_by,
                    'approved_at' => $p->reviewed_at,
                ]);
            }

            $this->regeneratePdf($course, $newSource, $newVersion);

            return ['applied' => count($appliedProposals), 'failed' => $failed, 'version_from' => $current->version, 'version_to' => $newVersion, 'blocked' => null];
        });
    }

    /**
     * Rollback dell'ultima applicazione: ripristina il sorgente (nuova versione = copia
     * della versione precedente) E il contenuto formatore live (dai backup).
     *
     * @return array{rolled_back:bool, version_from:?string, version_to:?string, restored_to:?string}
     */
    public function rollback(Course $course): array
    {
        return DB::transaction(function () use ($course) {
            $latest = $this->latestSource($course);
            if (!$latest) {
                throw new RuntimeException('Nessun sorgente da cui fare rollback.');
            }

            // Changelog dell'applicazione che ha PRODOTTO la versione corrente.
            $entry = CourseChangelog::where('course_id', $course->id)
                ->where('version_to', $latest->version)
                ->where('kind', 'apply')
                ->orderByDesc('created_at')
                ->first();
            if (!$entry) {
                throw new RuntimeException("Nessuna applicazione da annullare per la versione {$latest->version}.");
            }

            $preVersion = $entry->version_from;
            $preSource = $course->sources()->where('version', $preVersion)->first();
            if (!$preSource) {
                throw new RuntimeException("Versione precedente {$preVersion} non più disponibile per il rollback.");
            }

            // Sorgente: nuova versione = copia di quella precedente (append-only).
            $newVersion = $this->nextVersion($latest->version);
            $newSource = CourseSource::create([
                'course_id' => $course->id,
                'version' => $newVersion,
                'blocks' => $preSource->blocks,
            ]);

            // Formatore live: ripristina dalle snapshot pre-applicazione.
            $snapshots = FormatoreSnapshot::where('course_id', $course->id)
                ->where('version', $latest->version)
                ->get();
            foreach ($snapshots as $snap) {
                InstructorManualSection::where('id', $snap->instructor_manual_section_id)
                    ->update(['content_html' => $snap->content_html]);
            }

            CourseChangelog::create([
                'course_id' => $course->id,
                'proposal_id' => null,
                'version_from' => $latest->version,
                'version_to' => $newVersion,
                'kind' => 'rollback',
                'summary' => "Rollback dalla versione {$latest->version} (ripristino dei contenuti di {$preVersion})",
                'approved_by' => null,
                'approved_at' => now(),
            ]);

            $this->regeneratePdf($course, $newSource, $newVersion);

            return ['rolled_back' => true, 'version_from' => $latest->version, 'version_to' => $newVersion, 'restored_to' => $preVersion];
        });
    }

    /** Registra il fallimento pulito di una proposta (resta 'approved', non applicata). */
    private function fail(UpdateProposal $proposal, string $reason): void
    {
        $proposal->update(['apply_error' => $reason]);
        Log::warning('[ProposalApplicator] proposta non applicata (verbatim)', [
            'proposal_id' => $proposal->id, 'reason' => $reason,
        ]);
    }

    /**
     * Versione più recente del sorgente, in modo DETERMINISTICO: created_at è al secondo
     * (versioni create nello stesso secondo darebbero ordine ambiguo); gli id sono
     * orderedUuid (time-sortable), quindi rompono il pareggio nell'ordine corretto.
     */
    private function latestSource(Course $course): ?CourseSource
    {
        return CourseSource::where('course_id', $course->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /** Incremento della versione come STRINGA (mai float): "2.0" → "2.1". */
    private function nextVersion(string $v): string
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $v, $m)) {
            return $m[1] . '.' . ((int) $m[2] + 1);
        }
        if (preg_match('/^\d+$/', $v)) {
            return $v . '.1';
        }
        throw new RuntimeException("Versione non incrementabile in modo deterministico: {$v}");
    }

    /** Rigenera il PDF dal nuovo sorgente. Best-effort: un errore PDF non annulla l'applicazione. */
    private function regeneratePdf(Course $course, CourseSource $source, string $version): void
    {
        try {
            $bytes = $this->pdfBuilder->build($source->blocks, ['title' => "{$course->name} — sorgente v{$version}"]);
            Storage::disk('local')->put("course-sources/{$course->id}/v{$version}.pdf", $bytes);
        } catch (\Throwable $e) {
            Log::warning('[ProposalApplicator] rigenerazione PDF fallita (non bloccante)', [
                'course_id' => $course->id, 'version' => $version, 'error' => $e->getMessage(),
            ]);
        }
    }
}
