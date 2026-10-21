<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            // Хариуцагч хэрэглэгч (хэрэглэгч уствал компьютер устахгүй, зөвхөн хоосорно)
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('users')
                ->nullOnDelete();
        });

        $this->backfillFromLegacyText();
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
        });
    }

    /**
     * Хуучин `assigned_to` текстийг (username / имэйл / нэр) хэрэглэгчтэй холбоно.
     * Зөвхөн ЯГ НЭГ хэрэглэгчтэй таарсан тохиолдолд л холбоно (давхцсан нэрийг холбохгүй).
     */
    private function backfillFromLegacyText(): void
    {
        $byLogin = [];
        $byName = [];

        foreach (DB::table('users')->get(['id', 'name', 'username', 'email']) as $user) {
            foreach ([$user->username, $user->email] as $login) {
                if (filled($login)) {
                    $byLogin[mb_strtolower(trim($login))][] = $user->id;
                }
            }
            if (filled($user->name)) {
                $byName[mb_strtolower(trim($user->name))][] = $user->id;
            }
        }

        $resolve = function (string $text) use ($byLogin, $byName): ?int {
            $key = mb_strtolower(trim($text));

            foreach ([$byLogin, $byName] as $map) {
                $ids = array_values(array_unique($map[$key] ?? []));
                if (count($ids) === 1) {
                    return $ids[0];
                }
            }

            return null;
        };

        DB::table('computers')
            ->whereNotNull('assigned_to')
            ->where('assigned_to', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($resolve) {
                foreach ($rows as $row) {
                    $userId = $resolve($row->assigned_to);

                    if ($userId !== null) {
                        DB::table('computers')->where('id', $row->id)->update(['assigned_user_id' => $userId]);
                    }
                }
            });
    }
};