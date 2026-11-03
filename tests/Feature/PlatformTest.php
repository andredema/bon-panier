<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Réglages du socle exigés par l'hébergement mutualisé et par la Loi 25. */
class PlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_is_in_french_with_ios_viewport(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('lang="fr-CA"', false)
            ->assertSee('viewport-fit=cover', false)
            ->assertSee('840, avenue du Marché')
            ->assertDontSee('fonts.googleapis.com', false);
    }

    public function test_app_defaults_to_quebec_locale_and_timezone(): void
    {
        $this->assertSame('fr', config('app.locale'));
        $this->assertSame('America/Toronto', config('app.timezone'));
    }

    public function test_scheduler_drains_the_database_queue_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'queue:work'));

        $this->assertNotNull($event, 'La file d’attente doit être vidée par le planificateur.');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertStringContainsString('--stop-when-empty', $event->command);
        $this->assertStringContainsString('--max-time=50', $event->command);
    }

    public function test_create_user_command(): void
    {
        $this->artisan('users:create', ['--name' => 'Gérante', '--email' => 'gerante@example.com', '--role' => 'gerant'])
            ->expectsQuestion('Mot de passe (10 caractères minimum)', 'un-mot-de-passe-solide')
            ->assertSuccessful();

        $this->assertTrue(User::firstWhere('email', 'gerante@example.com')->isManager());
    }

    public function test_demo_seeder_refuses_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->app->make(DatabaseSeeder::class)->run();
    }
}
