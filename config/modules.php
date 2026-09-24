<?php

/*
 * Moduli attivabili per ente (colonna tenants.licensed_modules).
 * Il core (corsi, moduli, quiz, certificati, discenti, formatori) è sempre attivo.
 */
return [
    'modules' => [
        'ai_chat' => 'Assistente AI e RAG sui corsi',
        'ai_news' => 'Rassegna AI settimanale',
        'freshness' => 'Aggiornamento contenuti (freshness)',
        'gap_scout' => 'Gap scout e completezza corsi',
        'course_generation' => 'Generazione corsi da knowledge base',
        'video' => 'Video AI dei moduli',
        'scuola' => 'Scuola (classi, docenti, materiali condivisi)',
    ],

    // Default per un nuovo ente.
    'default' => ['ai_chat', 'ai_news', 'freshness', 'gap_scout', 'course_generation', 'video'],

    // Moduli riservati al tenant primario (Effetto Glitch).
    'primary_only' => ['scuola'],
];
