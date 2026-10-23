<?php

use App\Models\User;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    File::deleteDirectory(app(DatabaseBackup::class)->dir());
    // Tes memakai SQLite in-memory; di sini pura-pura MySQL dan mysqldump dipalsukan.
    config(['database.connections.mysql.database' => 'bizbos_test', 'database.connections.mysql.password' => 'rahasia']);
    $this->superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
});

afterEach(fn () => File::deleteDirectory(app(DatabaseBackup::class)->dir()));

function asMysql(): void
{
    config(['services.backup.connection' => 'mysql']);
}

/** mysqldump palsu: menulis isi dump ke --result-file seperti aslinya. */
function fakeDump(string $sql = "CREATE TABLE users (id int);\nINSERT INTO users VALUES (1);\n"): void
{
    Process::fake(function ($process) use ($sql) {
        $file = collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--result-file='));
        File::put(substr($file, strlen('--result-file=')), $sql);

        return Process::result();
    });
}

it('superadmin bisa backup sekarang lalu download file .sql.gz', function () {
    fakeDump();

    $this->actingAs($this->superadmin)->get(route('superadmin.backups.index'))->assertOk()->assertSee('Belum ada backup');

    asMysql();
    $this->post(route('superadmin.backups.store'))->assertRedirect()->assertSessionHas('success');

    Process::assertRan(fn ($process) => in_array('bizbos_test', $process->command, true)
        && in_array('--single-transaction', $process->command, true)
        && ! str_contains(implode(' ', $process->command), 'rahasia')
        && $process->environment['MYSQL_PWD'] === 'rahasia');

    $file = app(DatabaseBackup::class)->list()->first()['name'];
    expect($file)->toMatch(DatabaseBackup::PATTERN)
        ->and(gzdecode(File::get(app(DatabaseBackup::class)->dir().'/'.$file)))->toContain('INSERT INTO users');

    $this->get(route('superadmin.backups.index'))->assertSee($file)->assertSee('Terbaru');
    $this->get(route('superadmin.backups.download', $file))->assertOk()->assertDownload($file);
});

it('hanya menyimpan 14 backup terbaru', function () {
    fakeDump();
    asMysql();
    File::ensureDirectoryExists(app(DatabaseBackup::class)->dir());
    foreach (range(1, 15) as $day) {
        File::put(app(DatabaseBackup::class)->dir().sprintf('/bizbos-2026-01-%02d_010000.sql.gz', $day), 'x');
    }

    $this->artisan('db:backup')->assertSuccessful();

    $names = app(DatabaseBackup::class)->list()->pluck('name');
    expect($names)->toHaveCount(14)->not->toContain('bizbos-2026-01-02_010000.sql.gz')->toContain('bizbos-2026-01-15_010000.sql.gz');
});

it('mysqldump gagal: pesan jelas dan tidak ada file setengah jadi', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Access denied for user', exitCode: 2)]);
    asMysql();

    expect(fn () => app(DatabaseBackup::class)->create())->toThrow(RuntimeException::class, 'Access denied');
    expect(File::allFiles(app(DatabaseBackup::class)->dir()))->toBeEmpty();
});

it('bukan superadmin tidak bisa membuka, dan nama file aneh ditolak', function () {
    $this->actingAs(User::factory()->create())->get(route('superadmin.backups.index'))->assertForbidden();
    $this->actingAs($this->superadmin)->get('/superadmin/backups/..%2F.env')->assertNotFound();
    $this->get(route('superadmin.backups.download', 'laravel.log'))->assertNotFound();
});
