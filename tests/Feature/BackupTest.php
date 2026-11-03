<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Cahier des charges 9 : export quotidien de la base par cron, hors du web. */
class BackupTest extends TestCase
{
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backups');
        File::deleteDirectory($this->dir);
        config(['bonpanier.backup.path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_backup_writes_a_compressed_copy_and_prunes_old_ones(): void
    {
        Customer::factory()->create(['name' => 'Client Sauvegarde']);
        File::ensureDirectoryExists($this->dir);
        touch($old = $this->dir.'/bon-panier-20260101-031500.sqlite.gz', now()->subDays(31)->getTimestamp());
        touch($recent = $this->dir.'/bon-panier-20261101-031500.sqlite.gz', now()->subDays(2)->getTimestamp());

        $this->artisan('backup:database')->assertSuccessful();

        $files = glob($this->dir.'/bon-panier-*.gz');
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
        $this->assertCount(2, $files);

        $new = collect($files)->first(fn ($f) => $f !== $recent);
        $db = gzdecode(file_get_contents($new));
        $this->assertStringStartsWith('SQLite format 3', $db);
        $this->assertStringContainsString('Client Sauvegarde', $db);
    }

    public function test_backup_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'backup:database'));

        $this->assertNotNull($event);
        $this->assertSame('15 3 * * *', $event->expression);
    }
}
