<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_in_french_to_an_active_account(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'equipe@example.com']);

        $this->get('/gestion/connexion')->assertSee('Mot de passe oublié ?');
        $this->get(route('password.request'))->assertOk()->assertSee('Recevoir le lien');
        $this->post(route('password.email'), ['email' => 'equipe@example.com'])
            ->assertRedirect()->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $mail = $n->toMail($user);

            return $mail->subject === __('app.reset.mail_subject') && str_contains($mail->actionUrl, '/gestion/mot-de-passe/');
        });
    }

    public function test_unknown_or_inactive_accounts_get_the_same_answer_and_no_mail(): void
    {
        Notification::fake();
        $inactive = User::factory()->create(['email' => 'parti@example.com', 'is_active' => false]);

        $this->post(route('password.email'), ['email' => 'inconnu@example.com'])->assertSessionHas('status')->assertSessionHasNoErrors();
        $this->post(route('password.email'), ['email' => 'parti@example.com'])->assertSessionHas('status')->assertSessionHasNoErrors();

        Notification::assertNotSentTo($inactive, ResetPassword::class);
    }

    public function test_password_changes_with_a_valid_token_only_once(): void
    {
        $user = User::factory()->create(['email' => 'equipe@example.com']);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

        $data = ['token' => $token, 'email' => $user->email, 'password' => 'nouveau-secret-42', 'password_confirmation' => 'nouveau-secret-42'];
        $this->post(route('password.update'), $data)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('nouveau-secret-42', $user->fresh()->password));

        $this->post(route('password.update'), [...$data, 'password' => 'autre-secret-99', 'password_confirmation' => 'autre-secret-99'])
            ->assertSessionHasErrors('email');
    }

    public function test_short_password_is_refused(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'court', 'password_confirmation' => 'court'])
            ->assertSessionHasErrors('password');
    }
}
