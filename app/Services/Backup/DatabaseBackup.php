<?php

namespace App\Services\Backup;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Backup database ke storage/app/private/backups/*.sql.gz (tidak bisa diakses publik).
 * Dipakai jadwal harian (db:backup) dan tombol "Backup Sekarang" di Settings > Backup Database.
 */
class DatabaseBackup
{
    public const KEEP = 14;

    public const PATTERN = '/^bizbos-\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/';

    public function dir(): string
    {
        return storage_path('app/private/backups');
    }

    /** File backup terbaru dulu: name, size, time. */
    public function list(): Collection
    {
        return collect(File::isDirectory($this->dir()) ? File::files($this->dir()) : [])
            ->filter(fn ($file) => preg_match(self::PATTERN, $file->getFilename()))
            ->map(fn ($file) => ['name' => $file->getFilename(), 'size' => $file->getSize(), 'time' => $file->getMTime()])
            ->sortByDesc('name')->values();
    }

    public function find(string $name): ?string
    {
        $path = $this->dir().DIRECTORY_SEPARATOR.$name;

        return preg_match(self::PATTERN, $name) && File::isFile($path) ? $path : null;
    }

    /** Buat backup baru, hapus yang lebih lama dari KEEP terbaru. Satu proses dalam satu waktu. */
    public function create(): string
    {
        $lock = Cache::lock('database-backup', 1800);

        if (! $lock->get()) {
            throw new RuntimeException('Backup lain sedang berjalan. Tunggu sebentar ya.');
        }

        try {
            File::ensureDirectoryExists($this->dir());
            $name = 'bizbos-'.now()->format('Y-m-d_His').'.sql.gz';
            // Ditulis ke .part dulu, baru diganti nama setelah selesai: file setengah jadi tidak pernah muncul di daftar.
            $part = $this->dir().DIRECTORY_SEPARATOR.$name.'.part';

            try {
                $this->dump($part);
                File::move($part, $this->dir().DIRECTORY_SEPARATOR.$name);
            } finally {
                File::delete($part);
            }

            $this->list()->slice(self::KEEP)->each(fn ($old) => File::delete($this->dir().DIRECTORY_SEPARATOR.$old['name']));

            return $name;
        } finally {
            $lock->release();
        }
    }

    private function dump(string $target): void
    {
        $db = config('database.connections.'.(config('services.backup.connection') ?? config('database.default')));

        if ($db['driver'] === 'sqlite') {
            $this->compress($db['database'], $target);

            return;
        }

        if (! in_array($db['driver'], ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Backup untuk database {$db['driver']} belum didukung.");
        }

        // mysqldump menulis ke file sementara, lalu dikompres sepotong-sepotong: hemat memori untuk database besar.
        $sql = $target.'.sql';

        try {
            $result = Process::timeout(1800)
                ->env(['MYSQL_PWD' => (string) $db['password']]) // password tidak terlihat di daftar proses server
                ->run(array_values(array_filter([
                    config('services.backup.mysqldump', 'mysqldump'),
                    '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
                    $db['unix_socket'] ? '--socket='.$db['unix_socket'] : null,
                    '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces',
                    '--default-character-set=utf8mb4', '--result-file='.$sql, $db['database'],
                ])));

            if ($result->failed() || ! File::isFile($sql)) {
                throw new RuntimeException('Backup gagal: '.trim($result->errorOutput() ?: 'mysqldump tidak bisa dijalankan.'));
            }

            $this->compress($sql, $target);
        } finally {
            File::delete($sql);
        }
    }

    private function compress(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $gz = gzopen($target, 'wb6');

        try {
            while (! feof($in)) {
                gzwrite($gz, fread($in, 1024 * 1024));
            }
        } finally {
            fclose($in);
            gzclose($gz);
        }
    }
}
