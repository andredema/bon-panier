<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Cahier des charges 9 : sécurité et pages d'erreur en français, sans détail technique. */
class ProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_error_pages_are_in_french(): void
    {
        $this->get('/page-qui-n-existe-pas')->assertNotFound()
            ->assertSee('lang="fr-CA"', false)->assertSee('Page introuvable')->assertSee('Retour à la page de commande');
    }

    public function test_server_error_shows_no_technical_detail_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test-erreur', fn () => throw new \RuntimeException('secret SQL détail'));

        $this->get('/_test-erreur')->assertStatus(500)
            ->assertSee('Un problème est survenu')->assertDontSee('secret SQL détail');
    }

    public function test_privacy_policy_covers_the_loi_25_essentials(): void
    {
        $this->get(route('shop.privacy'))->assertOk()
            ->assertSee('Responsable de la protection')
            ->assertSee('Montréal')
            ->assertSee('Commission d’accès à l’information')
            ->assertSee('Aucun outil de mesure d’audience');
    }
}
