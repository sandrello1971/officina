<?php

namespace App\Services\Schola;

use App\Services\Schola\Contracts\TextToSpeech;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * TTS via ElevenLabs (text-to-speech API). Ritorna i byte MP3. La chiave vive in
 * config('services.elevenlabs.key'). Una chiamata = audio di UNA slide (il caching
 * per testo è responsabilità del chiamante, VideoRenderService).
 */
class ElevenLabsTextToSpeech implements TextToSpeech
{
    private const BASE = 'https://api.elevenlabs.io/v1/text-to-speech';

    public function synthesize(string $text, string $voiceId): string
    {
        $key = config('services.elevenlabs.key');
        if (empty($key)) {
            throw new RuntimeException('ELEVENLABS_API_KEY non configurata.');
        }

        $response = Http::withHeaders([
            'xi-api-key' => $key,
            'accept' => 'audio/mpeg',
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::BASE . '/' . $voiceId, [
            'text' => $text,
            'model_id' => config('services.elevenlabs.model', 'eleven_multilingual_v2'),
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore ElevenLabs: ' . $response->status());
        }

        $audio = $response->body();
        if ($audio === '') {
            throw new RuntimeException('ElevenLabs ha restituito audio vuoto.');
        }

        return $audio;
    }
}
