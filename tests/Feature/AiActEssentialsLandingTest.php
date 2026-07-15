<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Landing pubblica del corso AI ACT Essentials — stile GLITCH e AUTOSUFFICIENTE:
 * niente link verso il vecchio /contatti (layouts.app); contatti on-page.
 */
class AiActEssentialsLandingTest extends TestCase
{
    public function test_landing_is_glitch_and_self_contained(): void
    {
        $this->withoutVite();

        $res = $this->get('/ai-act-essentials');

        $res->assertOk();
        $res->assertSee('AI ACT Essentials');
        $res->assertSee('Articolo 4');
        // Stile brand Effetto Glitch (NON il vecchio layouts.app):
        $res->assertSee('bg-glitch-black', false);
        $res->assertSee('EFFETTO GLITCH', false);
        $res->assertDontSee('nav-link', false);
        // Autosufficiente: contatti on-page, nessun rimando alla vecchia pagina /contatti.
        $res->assertSee('id="contatti"', false);
        $res->assertSee('mailto:rumore@effettoglitch.it', false);
        $res->assertSee('#contatti', false);
        $res->assertDontSee('href="/contatti"', false);
    }
}
