<?php

/*
 * Moduli attivabili per ente (colonna tenants.licensed_modules).
 * Il core (corsi, moduli, quiz, certificati, discenti, formatori) è sempre attivo.
 *
 * `routes`: prefissi di nome rotta che appartengono al modulo. Con il modulo
 * spento l'ente riceve 404 (EnforceTenantModules), come se non esistessero.
 */
return [
    'modules' => [
        'ai_chat' => 'Assistente AI (Minerva) sui corsi',
        'ai_news' => 'Rassegna AI settimanale',
        'freshness' => 'Aggiornamento contenuti (freshness)',
        'gap_scout' => 'Gap scout e completezza corsi',
        'course_generation' => 'Generazione corsi da knowledge base',
        'scuola' => 'Scuola (classi, docenti, lezioni, segreteria)',
    ],

    'routes' => [
        'ai_chat' => ['student.chat.', 'student.minerva'],
        'ai_news' => ['admin.news.', 'student.news.'],
        'freshness' => ['admin.freshness.'],
        'gap_scout' => ['admin.coverage.', 'admin.completeness.'],
        'course_generation' => ['admin.course-generation.'],
        'scuola' => ['scuola.', 'docente.', 'student.classes.', 'student.classi.', 'admin.scuole.'],
    ],

    // Default per un nuovo ente.
    'default' => ['ai_chat', 'ai_news', 'freshness', 'gap_scout', 'course_generation'],

    // Moduli riservati al tenant primario (Effetto Glitch).
    'primary_only' => ['scuola'],
];
