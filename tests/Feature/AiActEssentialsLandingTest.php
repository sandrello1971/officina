<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Landing pubblica del corso AI ACT Essentials.
 */
class AiActEssentialsLandingTest extends TestCase
{
    public function test_landing_renders_and_proposes_the_course(): void
    {
        $res = $this->get('/ai-act-essentials');

        $res->assertOk();
        $res->assertSee('AI ACT Essentials');
        $res->assertSee('Articolo 4'); // l'obbligo di legge
        $res->assertSee('Attiva il corso per la tua azienda'); // CTA
        $res->assertSee('/contatti', false); // porta al contatto
    }
}
