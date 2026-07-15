<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Landing pubblica del corso AI ACT Essentials — stile GLITCH (come la homepage "/").
 */
class AiActEssentialsLandingTest extends TestCase
{
    public function test_landing_renders_in_glitch_style_and_proposes_the_course(): void
    {
        $this->withoutVite(); // in test non c'è il manifest Vite; qui basta il markup

        $res = $this->get('/ai-act-essentials');

        $res->assertOk();
        $res->assertSee('AI ACT Essentials');
        $res->assertSee('Articolo 4'); // l'obbligo di legge
        $res->assertSee('Attiva il corso per la tua azienda'); // CTA
        $res->assertSee('/contatti', false); // porta al contatto
        // Stile brand Effetto Glitch (NON il vecchio layouts.app):
        $res->assertSee('bg-glitch-black', false);
        $res->assertSee('EFFETTO GLITCH', false);
        $res->assertDontSee('nav-link', false); // classi del vecchio layout marketing
    }
}
