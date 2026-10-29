<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Алба хэлтэс тусдаа хүснэгтэд: салбар бүрт нэр нь давтагдахгүй. Төхөөрөмж, хэрэглэгч department_id-аар холбогдоно
     * (computers.department, users.department текст нь харуулах, шүүх, хайхад хэвээр — Department нэр солигдоход синк).
     * Одоо байгаа текстийг шилжүүлнэ: салбар дотор том/жижиг үсгээр л ялгаатай нэрүүд нэг хэлтэс болно.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
            $t->unique(['branch_id', 'name']);
        });

        foreach (['computers', 'users'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('department_id')->nullable()->after('department')->constrained()->nullOnDelete();
            });
        }

        // [салбар|жижиг үсгээр нэр => department id]
        $ids = [];
        foreach (['computers', 'users'] as $table) {
            DB::table($table)->whereNotNull('department')->orderBy('id')->get(['id', 'branch_id', 'department'])
                ->each(function ($row) use ($table, &$ids) {
                    $name = trim(preg_replace('/\s+/u', ' ', (string) $row->department));
                    if ($name === '') {
                        DB::table($table)->where('id', $row->id)->update(['department' => null]);

                        return;
                    }
                    $key = ($row->branch_id ?? '').'|'.mb_strtolower($name);
                    $ids[$key] ??= DB::table('departments')->insertGetId([
                        'branch_id' => $row->branch_id, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $canonical = DB::table('departments')->where('id', $ids[$key])->value('name');
                    DB::table($table)->where('id', $row->id)->update(['department_id' => $ids[$key], 'department' => $canonical]);
                });
        }
    }

    public function down(): void
    {
        foreach (['computers', 'users'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('department_id'));
        }
        Schema::dropIfExists('departments');
    }
};
