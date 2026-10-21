<?php

namespace App\Support;

use RuntimeException;

/**
 * Өгөгдлийн сангийн нөөцлөлт: mysqldump-аар бүтэн хуулбар (хүснэгт, view, өгөгдөл) авч .sql.gz болгон хадгална.
 * Сэргээх үед mysql клиентээр ачаална. Нууц үгийг тушаалын мөрөнд биш MYSQL_PWD орчны хувьсагчаар дамжуулна.
 */
class DatabaseBackup
{
    /** Нөөц үүсгээд файлын замыг буцаана */
    public static function create(): string
    {
        $db = config('database.connections.'.config('database.default'));
        $dir = config('backup.path');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true)) {
            throw new RuntimeException("Нөөцийн хавтас үүсгэж чадсангүй: $dir");
        }

        $file = $dir.DIRECTORY_SEPARATOR.$db['database'].'-'.now()->format('Y-m-d_His').'.sql.gz';
        $sql = self::run([
            self::bin('mysqldump'),
            '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
            '--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4',
            $db['database'],
        ], $db['password']);

        // mysqldump амжилттай дууссан бол файлын төгсгөлд «Dump completed» гэж бичдэг
        if (! str_contains(substr($sql, -200), 'Dump completed')) {
            throw new RuntimeException('Нөөц бүрэн бус байна (mysqldump дуусаагүй).');
        }

        file_put_contents($file, gzencode($sql, 9));

        return $file;
    }

    /** Нэмэлт газар (өөр диск, сүлжээний хавтас) хуулна. Хуулсан замыг, тохируулаагүй бол null буцаана */
    public static function copy(string $file): ?string
    {
        $to = config('backup.copy_to');
        if (blank($to)) {
            return null;
        }
        if (! is_dir($to) && ! @mkdir($to, 0775, true)) {
            throw new RuntimeException("Нэмэлт хуулбарын хавтас олдсонгүй: $to");
        }
        $target = rtrim($to, '\\/').DIRECTORY_SEPARATOR.basename($file);
        if (! copy($file, $target)) {
            throw new RuntimeException("Нэмэлт газар хуулж чадсангүй: $target");
        }

        return $target;
    }

    /** keep_days-аас хуучин нөөцийг устгаж, устгасан тоог буцаана (нэмэлт хуулбарын хавтсанд ч) */
    public static function prune(): int
    {
        $limit = now()->subDays(max(1, config('backup.keep_days')))->getTimestamp();
        $deleted = 0;
        foreach (array_filter([config('backup.path'), config('backup.copy_to')]) as $dir) {
            foreach (glob(rtrim($dir, '\\/').DIRECTORY_SEPARATOR.'*.sql.gz') ?: [] as $old) {
                if (filemtime($old) < $limit && @unlink($old)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /** Нөөцийн жагсаалт: шинэ нь эхэнд */
    public static function list(): array
    {
        $files = glob(rtrim(config('backup.path'), '\\/').DIRECTORY_SEPARATOR.'*.sql.gz') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $files;
    }

    /** Нөөцийг $database өгөгдлийн сан руу сэргээнэ (байхгүй бол үүсгэнэ) */
    public static function restore(string $file, string $database): void
    {
        $sql = gzdecode((string) file_get_contents($file));
        if ($sql === false || ! str_contains($sql, 'Dump completed')) {
            throw new RuntimeException('Нөөц файл гэмтсэн эсвэл бүрэн бус байна.');
        }

        $db = config('database.connections.'.config('database.default'));
        $base = [self::bin('mysql'), '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'], '--default-character-set=utf8mb4'];
        $name = str_replace('`', '', $database);
        self::run([...$base, '-e', "CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"], $db['password']);
        self::run([...$base, $name], $db['password'], $sql);
    }

    private static function bin(string $name): string
    {
        $path = rtrim(config('backup.mysql_bin'), '\\/').DIRECTORY_SEPARATOR.$name.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

        return is_file($path) ? $path : $name; // тохируулсан хавтсанд байхгүй бол PATH-аас
    }

    /** Гадаад програм ажиллуулж stdout-ыг буцаана. Алдаатай дуусвал stderr-ийг алдаа болгоно */
    private static function run(array $command, ?string $password, ?string $stdin = null): string
    {
        $env = getenv();
        if (filled($password)) {
            $env['MYSQL_PWD'] = $password;
        }

        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (! is_resource($process)) {
            throw new RuntimeException('Програм ажиллуулж чадсангүй: '.$command[0]);
        }
        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            throw new RuntimeException(trim($err) ?: 'Програм алдаатай дууслаа: '.basename($command[0]));
        }

        return (string) $out;
    }
}
