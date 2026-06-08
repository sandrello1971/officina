<?php

/*
|--------------------------------------------------------------------------
| Global helpers — atheneum settings
|--------------------------------------------------------------------------
|
| Wrapper sottili sopra App\Models\Setting che permettono accesso
| ergonomico dal codice (Blade, controller) senza importare il model.
| La fonte unica resta Setting::resolve/put (cache + difensività gestiti
| lì); questi helper sono solo zucchero sintattico.
|
*/

use App\Models\Setting;

if (!function_exists('atheneum_setting')) {
    /**
     * Legge un settings con default. Difensivo per design (vedi Setting::resolve).
     */
    function atheneum_setting(string $key, $default = null)
    {
        return Setting::resolve($key, $default);
    }
}

if (!function_exists('atheneum_setting_put')) {
    /**
     * Salva un setting e invalida la cache della chiave.
     */
    function atheneum_setting_put(string $key, $value): void
    {
        Setting::put($key, $value);
    }
}

if (!function_exists('schola_markdown')) {
    /**
     * Render Markdown SICURO per i contenuti Schola (artefatti, biblioteca,
     * auto-generati). Hardening XSS (pacchetto 10):
     *  - html_input=strip → l'HTML grezzo eventualmente presente viene rimosso;
     *  - allow_unsafe_links=false → niente href `javascript:`/`data:` (vettore
     *    XSS via link, possibile per content AI prompt-injected o editato a mano).
     * I contenuti passano dal modello o dall'editing docente: non fidarsi.
     */
    function schola_markdown(?string $text): string
    {
        return \Illuminate\Support\Str::markdown((string) $text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
