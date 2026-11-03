<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_in_french(): void
    {
        $this->get('/gestion/connexion')
            ->assertOk()
            ->assertSee('lang="fr-CA"', false)
            ->assertSee('Connexion de l’équipe')
            ->assertSee('Se connecter');
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/gestion')->assertRedirect('/gestion/connexion');
        $this->get('/gestion/equipe')->assertRedirect('/gestion/connexion');
    }

    public function test_team_member_can_log_in_and_out(): void
    {
        $user = User::factory()->create();

        $this->post('/gestion/connexion', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/gestion');
        $this->assertAuthenticatedAs($user);

        $this->get('/gestion')->assertOk()->assertSee('Bonjour '.$user->name);

        $this->post('/gestion/deconnexion')->assertRedirect('/gestion/connexion');
        $this->assertGuest();
    }

    public function test_wrong_password_shows_french_error(): void
    {
        $user = User::factory()->create();

        $this->from('/gestion/connexion')
            ->post('/gestion/connexion', ['email' => $user->email, 'password' => 'mauvais'])
            ->assertRedirect('/gestion/connexion')
            ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte.']);
        $this->assertGuest();
    }

    public function test_validation_messages_are_in_french(): void
    {
        $this->post('/gestion/connexion', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'Le champ courriel est obligatoire.']);
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/gestion/connexion', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->post('/gestion/connexion', ['email' => $user->email, 'password' => 'mauvais']);
        }

        $this->post('/gestion/connexion', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
    }
}
