<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_dashboard_but_not_team(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/gestion')->assertOk()->assertDontSee('Équipe');
        $this->actingAs($employee)->get('/gestion/equipe')->assertForbidden();
        $this->actingAs($employee)->post('/gestion/equipe', [])->assertForbidden();
    }

    public function test_manager_sees_team_menu_and_list(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['name' => 'Awa Diallo']);

        $this->actingAs($manager)->get('/gestion')->assertOk()->assertSee('Équipe');
        $this->actingAs($manager)->get('/gestion/equipe')
            ->assertOk()
            ->assertSee('Awa Diallo')
            ->assertSee('Employé');
    }

    public function test_manager_creates_an_employee(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post('/gestion/equipe', [
            'name' => 'Kofi Mensah',
            'email' => 'kofi@example.com',
            'role' => 'employe',
            'password' => 'un-mot-de-passe',
            'password_confirmation' => 'un-mot-de-passe',
        ])->assertRedirect('/gestion/equipe');

        $user = User::firstWhere('email', 'kofi@example.com');
        $this->assertSame(UserRole::Employee, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotSame('un-mot-de-passe', $user->password);
    }

    public function test_short_password_is_refused(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post('/gestion/equipe', [
            'name' => 'Kofi', 'email' => 'kofi@example.com', 'role' => 'employe',
            'password' => 'court', 'password_confirmation' => 'court',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'kofi@example.com']);
    }

    public function test_manager_cannot_remove_own_manager_access(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->put("/gestion/equipe/{$manager->id}", [
            'name' => $manager->name, 'email' => $manager->email, 'role' => 'employe', 'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->actingAs($manager)->put("/gestion/equipe/{$manager->id}", [
            'name' => $manager->name, 'email' => $manager->email, 'role' => 'gerant', 'is_active' => '0',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($manager->fresh()->isManager());
        $this->assertTrue($manager->fresh()->is_active);
    }

    public function test_deactivated_account_is_logged_out_on_next_request(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create();

        $this->actingAs($manager)->put("/gestion/equipe/{$employee->id}", [
            'name' => $employee->name, 'email' => $employee->email, 'role' => 'employe', 'is_active' => '0',
        ])->assertRedirect('/gestion/equipe');

        $this->actingAs($employee->fresh())->get('/gestion')->assertRedirect('/gestion/connexion');
        $this->assertGuest();
    }

    public function test_password_is_kept_when_left_blank(): void
    {
        // Seul le super administrateur peut nommer un gérant
        $manager = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $hash = $employee->password;

        $this->actingAs($manager)->put("/gestion/equipe/{$employee->id}", [
            'name' => 'Nouveau nom', 'email' => $employee->email, 'role' => 'gerant', 'is_active' => '1', 'password' => '',
        ])->assertRedirect('/gestion/equipe');

        $employee->refresh();
        $this->assertSame($hash, $employee->password);
        $this->assertTrue($employee->isManager());
    }
}
