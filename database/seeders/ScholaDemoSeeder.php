<?php

namespace Database\Seeders;

use App\Models\ArtifactPublication;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ClassStudent;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentArtifactView;
use App\Models\StudentGeneratedArtifact;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Models\UnansweredQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dataset demo/sviluppo del modulo Schola: rende il cruscotto docente (pkg 8)
 * significativo a colpo d'occhio e fa da base per l'agente proattivo.
 *
 * SOLO ambienti non-prod (guard esplicito). Idempotente: ripulisce e ricrea le
 * entità demo (marker dominio @schola.demo). Nessuna chiamata API: i contenuti
 * degli artefatti sono fissi nel seeder.
 */
class ScholaDemoSeeder extends Seeder
{
    private const DEMO_DOMAIN = '@schola.demo';

    public function run(): void
    {
        $dbName = config('database.connections.' . config('database.default') . '.database');
        if (app()->environment('production') || $dbName === 'atheneum_db') {
            $this->command?->warn("ScholaDemoSeeder: ambiente prod / DB '{$dbName}' — SALTATO.");
            return;
        }

        $this->cleanup();

        $prof = Student::create([
            'name' => 'Prof. Demo', 'email' => 'prof.demo' . self::DEMO_DOMAIN,
            'password' => bcrypt('password'), 'role' => 'professor',
            'is_active' => true, 'must_change_password' => false,
        ]);

        $fisica = Subject::firstOrCreate(['name' => 'Fisica']);
        $storia = Subject::firstOrCreate(['name' => 'Storia']);

        $classF = $this->makeClass($prof, '3ªB', $fisica);
        $classS = $this->makeClass($prof, '4ªA', $storia);

        $students = $this->makeStudents();
        // 5 in Fisica, 5 in Storia, con overlap; 1 pending, 1 removed per realismo.
        $this->enrollMany($classF, array_slice($students, 0, 5), array_slice($students, 5, 1));
        $this->enrollMany($classS, array_slice($students, 3, 5), array_slice($students, 5, 1), array_slice($students, 6, 1));

        $this->seedSubject($classF, $prof, $fisica, $this->fisicaContent(), array_slice($students, 0, 5));
        $this->seedSubject($classS, $prof, $storia, $this->storiaContent(), array_slice($students, 3, 5));

        $this->command?->info('ScholaDemoSeeder: 1 professor, 2 classi, ' . count($students) . ' studenti, materiali/artefatti/pubblicazioni/attività su 3 settimane.');
    }

    // ===== cleanup idempotente =====

    private function cleanup(): void
    {
        $demoIds = Student::where('email', 'like', '%' . self::DEMO_DOMAIN)->pluck('id');
        if ($demoIds->isEmpty()) {
            return;
        }
        // Le FK cascadeOnDelete portano via classi→iscrizioni/pubblicazioni/viste,
        // documenti→artefatti, ecc. quando si eliminano professor e studenti demo.
        Student::whereIn('id', $demoIds)->forceDelete();
    }

    // ===== entità =====

    private function makeClass(Student $prof, string $name, Subject $subject): SchoolClass
    {
        return SchoolClass::create([
            'teacher_id' => $prof->id, 'name' => $name, 'subject_id' => $subject->id,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => true, 'requires_approval' => true, 'is_archived' => false,
        ]);
    }

    /** @return list<Student> */
    private function makeStudents(): array
    {
        $names = ['Giulia Rossi', 'Marco Bianchi', 'Sofia Esposito', 'Luca Romano',
            'Aurora Ferrari', 'Matteo Conti', 'Chiara Greco', 'Davide Marino'];
        $out = [];
        foreach ($names as $i => $name) {
            $slug = str(\Illuminate\Support\Str::ascii($name))->lower()->replace(' ', '.');
            $out[] = Student::create([
                'name' => $name, 'email' => $slug . self::DEMO_DOMAIN,
                'password' => bcrypt('password'), 'role' => 'student',
                'is_active' => true, 'must_change_password' => false,
                'birth_date' => Carbon::create(2009, 1, 1)->addDays($i * 47), // ~16-17 anni
            ]);
        }
        return $out;
    }

    private function enrollMany(SchoolClass $class, array $active, array $pending = [], array $removed = []): void
    {
        foreach ($active as $s) {
            ClassStudent::firstOrCreate(['school_class_id' => $class->id, 'student_id' => $s->id],
                ['status' => 'active', 'approved_at' => now()->subWeeks(3)]);
        }
        foreach ($pending as $s) {
            ClassStudent::firstOrCreate(['school_class_id' => $class->id, 'student_id' => $s->id], ['status' => 'pending']);
        }
        foreach ($removed as $s) {
            ClassStudent::firstOrCreate(['school_class_id' => $class->id, 'student_id' => $s->id], ['status' => 'removed']);
        }
    }

    /**
     * Crea per la classe: materiali (vari source_type) + artefatti (contenuti
     * fissi) + pubblicazioni + viste/tentativi/domande/auto-generazioni
     * distribuiti su 3 settimane.
     */
    private function seedSubject(SchoolClass $class, Student $prof, Subject $subject, array $c, array $students): void
    {
        // --- Materiale 1: audio/lezione con segments ---
        $docAudio = TeachingDocument::create([
            'teacher_id' => $prof->id, 'title' => $c['audio_title'], 'source_type' => 'audio',
            'status' => 'ready', 'extracted_text' => $c['audio_text'], 'subject_id' => $subject->id,
            'source_files' => ['teaching-documents/demo/' . $class->id . '/lezione.mp3'],
            'extraction_meta' => ['method' => 'whisper', 'language' => 'it', 'segments' => $c['segments']],
        ]);
        $this->transcript($docAudio, $prof, $subject);
        $summaryA = $this->artifact($docAudio, $prof, $subject, 'summary', 'Riassunto — ' . $c['audio_title'], $c['summary']);
        $mind = $this->artifact($docAudio, $prof, $subject, 'mindmap', 'Mappa mentale — ' . $c['audio_title'], $c['mindmap']);

        // --- Materiale 2: testo/dispensa ---
        $docText = TeachingDocument::create([
            'teacher_id' => $prof->id, 'title' => $c['text_title'], 'source_type' => 'text',
            'status' => 'ready', 'extracted_text' => $c['text_text'], 'subject_id' => $subject->id,
            'source_files' => ['teaching-documents/demo/' . $class->id . '/testo.md'],
        ]);
        $concept = $this->artifact($docText, $prof, $subject, 'conceptmap', 'Mappa concettuale — ' . $c['text_title'], $c['conceptmap']);
        $quizArt = $this->quizArtifact($docText, $prof, $subject, $c);

        // --- Pubblicazioni (rag ready) ---
        $pubSummary = $this->publish($summaryA, $class);
        $pubMind = $this->publish($mind, $class);
        $pubConcept = $this->publish($concept, $class);
        $pubQuiz = $this->publish($quizArt, $class);

        // --- Viste distribuite su 3 settimane (alcuni studenti, non tutti) ---
        $pubs = [$pubSummary, $pubMind, $pubConcept, $pubQuiz];
        foreach ($students as $i => $s) {
            // L'ultimo studente NON apre nulla (inattivo) per far emergere il segnale.
            if ($i === count($students) - 1) {
                continue;
            }
            foreach ($pubs as $j => $pub) {
                if (($i + $j) % 2 === 0) { // copertura parziale realistica
                    $when = now()->subWeeks(3)->addDays($i * 2 + $j);
                    StudentArtifactView::create([
                        'artifact_publication_id' => $pub->id, 'student_id' => $s->id,
                        'first_viewed_at' => $when, 'last_viewed_at' => $when->copy()->addDays(2),
                        'view_count' => 1 + ($i % 3),
                    ]);
                }
            }
        }

        // --- Tentativi sul quiz pubblicato (punteggi vari, alcune risposte sbagliate) ---
        $quiz = $quizArt->quiz()->with('questions')->first();
        foreach (array_slice($students, 0, 4) as $i => $s) {
            $score = [40, 60, 80, 100][$i];
            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id, 'student_id' => $s->id,
                'started_at' => now()->subWeeks(2)->addDays($i),
                'completed_at' => now()->subWeeks(2)->addDays($i)->addMinutes(8),
                'score' => $score, 'passed' => $score >= 70, 'abandoned' => false,
                'time_spent_seconds' => 480,
            ]);
            foreach ($quiz->questions as $qi => $q) {
                // La prima domanda la sbagliano i punteggi bassi → "domanda più sbagliata".
                $correct = !($qi === 0 && $score < 70);
                QuizAnswer::create([
                    'attempt_id' => $attempt->id, 'question_id' => $q->id,
                    'answer' => $correct ? $q->correct_answer : 'risposta errata',
                    'is_correct' => $correct, 'points_earned' => $correct ? 1 : 0,
                ]);
            }
        }

        // --- Auto-generazioni studente ---
        StudentGeneratedArtifact::create([
            'student_id' => $students[0]->id, 'artifact_publication_id' => $pubSummary->id,
            'type' => 'mindmap', 'status' => 'ready', 'content' => $c['mindmap'],
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);

        // --- Chat di classe (qualche messaggio) ---
        $conv = ChatConversation::create([
            'student_id' => $students[1]->id, 'school_class_id' => $class->id,
            'is_active' => true, 'title' => 'Classe ' . $class->name, 'course_id' => null,
        ]);
        foreach ($c['chat'] as $k => $msg) {
            ChatMessage::create([
                'conversation_id' => $conv->id, 'role' => $k % 2 === 0 ? 'user' : 'assistant',
                'content' => $msg, 'created_at' => now()->subDays(4)->addMinutes($k),
                'updated_at' => now()->subDays(4)->addMinutes($k),
            ]);
        }

        // --- Domande scoperte (alcune simili → cluster) ---
        foreach ($c['unanswered'] as $i => $text) {
            UnansweredQuestion::create([
                'school_class_id' => $class->id, 'student_id' => $students[$i % count($students)]->id,
                'question' => $text, 'best_similarity' => 0.2 + ($i * 0.03), 'status' => 'open',
                'created_at' => now()->subDays(5 - $i), 'updated_at' => now()->subDays(5 - $i),
            ]);
        }
    }

    private function transcript(TeachingDocument $doc, Student $prof, Subject $subject): TeachingArtifact
    {
        return TeachingArtifact::create([
            'teaching_document_id' => $doc->id, 'teacher_id' => $prof->id, 'subject_id' => $subject->id,
            'type' => 'transcript', 'title' => 'Trascrizione — ' . $doc->title,
            'content' => $doc->extracted_text, 'status' => 'ready',
            'generation_meta' => ['source' => 'extraction', 'method' => 'whisper'],
        ]);
    }

    private function artifact(TeachingDocument $doc, Student $prof, Subject $subject, string $type, string $title, string $content): TeachingArtifact
    {
        return TeachingArtifact::create([
            'teaching_document_id' => $doc->id, 'teacher_id' => $prof->id, 'subject_id' => $subject->id,
            'type' => $type, 'title' => $title, 'content' => $content, 'status' => 'ready',
            'generation_meta' => ['model' => 'demo', 'prompt_version' => 'demo'],
        ]);
    }

    private function quizArtifact(TeachingDocument $doc, Student $prof, Subject $subject, array $c): TeachingArtifact
    {
        $quiz = Quiz::create(['module_id' => null, 'course_id' => null,
            'title' => 'Quiz — ' . $doc->title, 'description' => 'Quiz demo', 'passing_score' => 70,
            'is_active' => true, 'randomize_questions' => true, 'show_results_immediately' => true]);
        foreach ($c['quiz'] as $i => $q) {
            QuizQuestion::create([
                'quiz_id' => $quiz->id, 'question' => $q['q'], 'type' => 'multiple_choice',
                'options' => $q['options'], 'correct_answer' => $q['correct'],
                'explanation' => $q['explanation'] ?? null, 'points' => 1, 'sort_order' => $i + 1,
            ]);
        }
        return TeachingArtifact::create([
            'teaching_document_id' => $doc->id, 'teacher_id' => $prof->id, 'subject_id' => $subject->id,
            'type' => 'quiz', 'title' => 'Quiz — ' . $doc->title, 'content' => null,
            'quiz_id' => $quiz->id, 'status' => 'ready', 'generation_meta' => ['model' => 'demo'],
        ]);
    }

    private function publish(TeachingArtifact $art, SchoolClass $class): ArtifactPublication
    {
        return ArtifactPublication::create([
            'teaching_artifact_id' => $art->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'downloadable' => false,
            'published_at' => now()->subWeeks(3), 'rag_status' => 'ready',
        ]);
    }

    // ===== contenuti fissi (no API) =====

    private function fisicaContent(): array
    {
        return [
            'audio_title' => 'Il moto rettilineo uniforme',
            'audio_text' => "Oggi parliamo del moto rettilineo uniforme. Un corpo si muove di moto rettilineo uniforme quando percorre spazi uguali in tempi uguali, cioè con velocità costante. La legge oraria è s = s0 + v·t. La velocità è il rapporto tra lo spazio percorso e il tempo impiegato.",
            'segments' => [
                ['start_seconds' => 0, 'end_seconds' => 18, 'text' => 'Oggi parliamo del moto rettilineo uniforme.'],
                ['start_seconds' => 18, 'end_seconds' => 45, 'text' => 'Un corpo si muove di MRU quando percorre spazi uguali in tempi uguali, a velocità costante.'],
                ['start_seconds' => 45, 'end_seconds' => 72, 'text' => 'La legge oraria è s = s0 + v per t.'],
            ],
            'summary' => "## Moto rettilineo uniforme\n\nUn corpo è in **MRU** quando ha **velocità costante**: percorre spazi uguali in tempi uguali.\n\n- **Legge oraria**: `s = s0 + v·t`\n- **Velocità**: `v = Δs / Δt`\n\nIl grafico spazio-tempo è una **retta**.",
            'mindmap' => "# Moto rettilineo uniforme\n## Definizione\n- Velocità costante\n- Spazi uguali in tempi uguali\n## Formule\n- s = s0 + v·t\n- v = Δs/Δt\n## Grafici\n- s-t: retta\n- v-t: orizzontale",
            'text_title' => 'Energia cinetica e potenziale',
            'text_text' => "L'energia cinetica è l'energia posseduta da un corpo in movimento e dipende dalla massa e dal quadrato della velocità. L'energia potenziale gravitazionale dipende dalla massa, dall'altezza e dall'accelerazione di gravità. L'energia meccanica totale si conserva in assenza di attrito.",
            'conceptmap' => json_encode([
                'nodes' => [
                    ['id' => 'n1', 'label' => 'Energia meccanica', 'description' => 'Somma di cinetica e potenziale'],
                    ['id' => 'n2', 'label' => 'Energia cinetica', 'description' => '½ m v²'],
                    ['id' => 'n3', 'label' => 'Energia potenziale', 'description' => 'm g h'],
                ],
                'edges' => [
                    ['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'label' => 'include', 'arrows' => 'to'],
                    ['id' => 'e2', 'from' => 'n1', 'to' => 'n3', 'label' => 'include', 'arrows' => 'to'],
                ],
                'physics' => ['enabled' => true],
            ], JSON_UNESCAPED_UNICODE),
            'quiz' => [
                ['q' => 'Nel MRU la velocità è…', 'options' => ['costante', 'crescente', 'nulla', 'casuale'], 'correct' => 'costante', 'explanation' => 'Per definizione la velocità è costante.'],
                ['q' => 'La legge oraria del MRU è…', 'options' => ['s = s0 + v·t', 's = ½at²', 'v = a·t', 'F = m·a'], 'correct' => 's = s0 + v·t'],
                ['q' => "L'energia cinetica dipende dal…", 'options' => ['quadrato della velocità', 'colore', 'tempo', 'volume'], 'correct' => 'quadrato della velocità'],
            ],
            'chat' => ['Prof, la velocità nel MRU cambia mai?', 'No: nel moto rettilineo uniforme la velocità è costante per definizione.', 'E il grafico v-t com\'è?', 'È una linea orizzontale, perché v non varia nel tempo.'],
            'unanswered' => [
                'Come si calcola la velocità media nel moto vario?',
                'Qual è la differenza tra velocità media e istantanea?',
                'Cosa succede all\'energia con l\'attrito?',
            ],
        ];
    }

    private function storiaContent(): array
    {
        return [
            'audio_title' => 'La Rivoluzione francese',
            'audio_text' => "La Rivoluzione francese inizia nel 1789 con la presa della Bastiglia. Le cause sono la crisi economica, le disuguaglianze tra i tre stati e le idee illuministe. La Dichiarazione dei diritti dell'uomo e del cittadino sancisce libertà e uguaglianza.",
            'segments' => [
                ['start_seconds' => 0, 'end_seconds' => 20, 'text' => 'La Rivoluzione francese inizia nel 1789 con la presa della Bastiglia.'],
                ['start_seconds' => 20, 'end_seconds' => 50, 'text' => 'Le cause: crisi economica, disuguaglianze tra i tre stati, idee illuministe.'],
                ['start_seconds' => 50, 'end_seconds' => 80, 'text' => 'La Dichiarazione dei diritti dell\'uomo sancisce libertà e uguaglianza.'],
            ],
            'summary' => "## La Rivoluzione francese (1789)\n\nInizia con la **presa della Bastiglia** (14 luglio 1789).\n\n- **Cause**: crisi economica, disuguaglianze tra i *tre stati*, Illuminismo\n- **Dichiarazione dei diritti dell'uomo e del cittadino**: libertà, uguaglianza\n\nSegna la fine dell'**Ancien Régime**.",
            'mindmap' => "# Rivoluzione francese\n## Cause\n- Crisi economica\n- Disuguaglianze\n- Illuminismo\n## Eventi\n- Presa della Bastiglia 1789\n- Dichiarazione dei diritti\n## Conseguenze\n- Fine Ancien Régime",
            'text_title' => 'I tre stati e l\'Ancien Régime',
            'text_text' => "Nell'Ancien Régime la società era divisa in tre stati: clero, nobiltà e terzo stato. I primi due godevano di privilegi fiscali, mentre il terzo stato, la maggioranza, sosteneva il peso delle tasse. Questa disuguaglianza fu una delle cause della rivoluzione.",
            'conceptmap' => json_encode([
                'nodes' => [
                    ['id' => 'n1', 'label' => 'Ancien Régime', 'description' => 'Società divisa in tre stati'],
                    ['id' => 'n2', 'label' => 'Clero e Nobiltà', 'description' => 'Privilegi fiscali'],
                    ['id' => 'n3', 'label' => 'Terzo stato', 'description' => 'Paga le tasse'],
                ],
                'edges' => [
                    ['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'label' => 'privilegia', 'arrows' => 'to'],
                    ['id' => 'e2', 'from' => 'n1', 'to' => 'n3', 'label' => 'opprime', 'arrows' => 'to'],
                ],
                'physics' => ['enabled' => true],
            ], JSON_UNESCAPED_UNICODE),
            'quiz' => [
                ['q' => 'La Rivoluzione francese inizia nel…', 'options' => ['1789', '1492', '1861', '1914'], 'correct' => '1789', 'explanation' => 'Presa della Bastiglia, 14 luglio 1789.'],
                ['q' => 'Quanti erano gli stati dell\'Ancien Régime?', 'options' => ['tre', 'due', 'cinque', 'uno'], 'correct' => 'tre'],
                ['q' => 'Chi pagava soprattutto le tasse?', 'options' => ['il terzo stato', 'il clero', 'la nobiltà', 'il re'], 'correct' => 'il terzo stato'],
            ],
            'chat' => ['Perché è scoppiata la Rivoluzione francese?', 'Per la crisi economica, le disuguaglianze tra i tre stati e le idee illuministe.', 'Cos\'è la Bastiglia?', 'Una prigione-fortezza a Parigi: la sua presa il 14 luglio 1789 è simbolo dell\'inizio della rivoluzione.'],
            'unanswered' => [
                'Chi era Robespierre?',
                'Cosa fu il Terrore?',
                'Quando finì la Rivoluzione francese?',
            ],
        ];
    }
}
