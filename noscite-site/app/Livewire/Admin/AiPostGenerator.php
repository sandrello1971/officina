<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Component;

class AiPostGenerator extends Component
{
    public string $prompt = '';
    public string $tone = 'professionale';
    public string $category = 'Visione';
    public string $length = 'medio';
    public bool $generating = false;
    public bool $generated = false;
    public string $error = '';

    protected $rules = [
        'prompt' => 'required|min:10|max:1000',
        'tone' => 'required',
    ];

    public function generate()
    {
        $this->validate();
        $this->generating = true;
        $this->error = '';

        $toneMap = [
            'professionale' => 'professionale, autorevole ma accessibile, mai tecnocratico',
            'divulgativo' => 'divulgativo, semplice, pensato per imprenditori PMI non tecnici',
            'critico' => 'critico e analitico, stimola la riflessione, fa domande scomode',
            'pratico' => 'pratico e operativo, ricco di esempi concreti e step applicabili',
            'visionario' => 'visionario e ispirazionale, orientato al futuro, con profondita filosofica',
        ];

        $lengthMap = [
            'breve' => 'circa 400-600 parole',
            'medio' => 'circa 800-1200 parole',
            'lungo' => 'circa 1500-2000 parole',
        ];

        $systemPrompt = <<<'SYSTEM'
Sei il copywriter editoriale di Noscite, una startup italiana di consulenza AI per PMI.
Il brand Noscite si basa sull'"Umanesimo Digitale": la tecnologia serve le persone, non il contrario.
Il motto e "In digitali nova virtus".

Regole di scrittura Noscite:
- Sempre in italiano
- Chiaro, diretto, mai tecnocratico
- No anglicismi inutili (no: disrupt, leverage, synergy)
- Autorevole ma accessibile
- Concreto: esempi reali, dati quando possibile
- Mai iperbolico o promesse esagerate
- Target principale: imprenditori e manager di PMI italiane (5-50 dipendenti)

Rispondi SOLO con un JSON valido, senza markdown, senza backtick, senza testo extra.
Il JSON deve avere esattamente questi campi:
{
  "title": "Titolo dell'articolo",
  "slug": "slug-url-friendly",
  "excerpt": "Estratto di max 150 caratteri",
  "content": "Contenuto HTML completo dell'articolo con tag <h2>, <p>, <ul>, <li>, <strong>, <em>, <blockquote>",
  "meta_title": "Meta title SEO max 60 caratteri",
  "meta_description": "Meta description SEO max 160 caratteri",
  "category": "Visione o Pratica"
}
SYSTEM;

        $userPrompt = "Scrivi un articolo per il blog Commentarium di Noscite.\n\n";
        $userPrompt .= "Argomento/Prompt: {$this->prompt}\n";
        $userPrompt .= "Tono: {$toneMap[$this->tone]}\n";
        $userPrompt .= "Lunghezza: {$lengthMap[$this->length]}\n";
        $userPrompt .= "Categoria suggerita: {$this->category}\n\n";
        $userPrompt .= 'Ricorda: rispondi SOLO con JSON valido, nessun testo prima o dopo.';

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 4096,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

            if ($response->failed()) {
                $this->error = 'Errore API: ' . $response->status();
                $this->generating = false;
                return;
            }

            $body = $response->json();
            $text = $body['content'][0]['text'] ?? '';

            $text = preg_replace('/```json\s*/i', '', $text);
            $text = preg_replace('/```\s*/i', '', $text);
            $text = trim($text);

            $data = json_decode($text, true);

            if (!$data || !isset($data['title'])) {
                $this->error = 'Risposta AI non valida. Riprova.';
                $this->generating = false;
                return;
            }

            $this->dispatch('ai-content-generated',
                title: $data['title'],
                slug: $data['slug'] ?? Str::slug($data['title']),
                content: $data['content'],
                excerpt: $data['excerpt'] ?? '',
                meta_title: $data['meta_title'] ?? '',
                meta_description: $data['meta_description'] ?? '',
                category: $data['category'] ?? $this->category,
            );

            $this->generated = true;

        } catch (\Exception $e) {
            $this->error = 'Errore: ' . $e->getMessage();
        }

        $this->generating = false;
    }

    public function render()
    {
        return view('livewire.admin.ai-post-generator');
    }
}
