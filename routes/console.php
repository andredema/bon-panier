<?php

use Illuminate\Support\Facades\Schedule;

/*
| Planificateur : PlanetHoster (N0C) lance « php artisan schedule:run » par cron,
| chaque minute. Il n'y a pas de processus permanent : la file d'attente
| (courriels, avis de précommande) est vidée à chaque passage.
*/

Schedule::command('queue:work', [
    '--stop-when-empty',
    '--max-time' => config('queue.cron_max_time'),
    '--tries' => 3,
])
    ->everyMinute()
    ->withoutOverlapping(10)
    ->name('vider-file-attente');

Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])->daily();

// Sauvegarde quotidienne de la base dans BACKUP_PATH (hors du web), 30 jours gardés
Schedule::command('backup:database')
    ->dailyAt(config('bonpanier.backup.at'))
    ->withoutOverlapping(60)
    ->name('sauvegarde-base');
