<?php

namespace App\Services\Schola\Contracts;

/**
 * Astrazione TTS (parametrica): default ElevenLabs, sostituibile in test/dev.
 * synthesize() ritorna i BYTE dell'audio MP3 del testo dato.
 */
interface TextToSpeech
{
    public function synthesize(string $text, string $voiceId): string;
}
