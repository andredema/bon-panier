<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;

/**
 * Sauvegarde quotidienne de la base, compressée, dans un dossier hors du web
 * (BACKUP_PATH). Écrit en PHP pur : ne dépend pas de mysqldump, absent ou
 * bridé sur certains hébergements mutualisés. Restauration (MySQL) :
 * gunzip < bon-panier-AAAAMMJJ-HHMM.sql.gz | mysql -u UTILISATEUR -p BASE
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep= : Nombre de jours à garder (défaut : BACKUP_KEEP_DAYS)}';

    protected $description = 'Sauvegarde la base de données dans BACKUP_PATH et supprime les sauvegardes trop anciennes';

    private const CHUNK = 500;

    public function handle(): int
    {
        $dir = rtrim((string) config('bonpanier.backup.path'), '/\\');
        File::ensureDirectoryExists($dir, 0700);

        $driver = DB::connection()->getDriverName();
        $stamp = now()->format('Ymd-His');

        $file = match ($driver) {
            'mysql', 'mariadb' => $this->dumpMysql("{$dir}/bon-panier-{$stamp}.sql.gz"),
            'sqlite' => $this->copySqlite("{$dir}/bon-panier-{$stamp}.sqlite.gz"),
            default => throw new RuntimeException("Pilote non pris en charge : {$driver}"),
        };

        $removed = $this->prune($dir, (int) ($this->option('keep') ?? config('bonpanier.backup.keep_days')));

        $this->info('Sauvegarde : '.$file.' ('.number_format(filesize($file) / 1024, 0, ',', ' ').' Ko)');
        if ($removed) {
            $this->line("Anciennes sauvegardes supprimées : {$removed}");
        }

        return self::SUCCESS;
    }

    private function dumpMysql(string $path): string
    {
        $pdo = DB::connection()->getPdo();
        $tmp = $path.'.part';
        $gz = gzopen($tmp, 'wb6');

        gzwrite($gz, '-- Le Bon Panier : sauvegarde du '.now()->toDateTimeString()."\n");
        gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

        foreach (array_column($tables, 0) as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

            $stmt = $pdo->query("SELECT * FROM `{$table}`", PDO::FETCH_NUM);
            $batch = [];
            while (($row = $stmt->fetch()) !== false) {
                $batch[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)).')';
                if (count($batch) === self::CHUNK) {
                    gzwrite($gz, "INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, "INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
            }
            gzwrite($gz, "\n");
        }

        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        rename($tmp, $path);
        chmod($path, 0600);

        return $path;
    }

    /** Copie cohérente par VACUUM INTO (fonctionne aussi pendant une écriture), puis compression. */
    private function copySqlite(string $path): string
    {
        $tmp = substr($path, 0, -3).'.part';
        @unlink($tmp);
        DB::statement('VACUUM INTO ?', [$tmp]);

        file_put_contents($path, gzencode(file_get_contents($tmp), 6));
        unlink($tmp);
        chmod($path, 0600);

        return $path;
    }

    private function prune(string $dir, int $keepDays): int
    {
        if ($keepDays < 1) {
            return 0;
        }

        $limit = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;
        foreach (glob($dir.'/bon-panier-*.gz') ?: [] as $f) {
            if (filemtime($f) < $limit) {
                unlink($f);
                $removed++;
            }
        }

        return $removed;
    }
}
