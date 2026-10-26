<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Супер админы шилжүүлэг хэрэглэгч (ажилтан, админ), алба хэлтэс, агуулах руу очно:
     *   destination — user | department | storage
     *   to_department — алба хэлтэс рүү шилжүүлсэн бол хэлтсийн нэр
     *   from_label — эзэмшигчгүй төхөөрөмжийн өмнөх байдал («Агуулах», «Алба: IT»), from_user_id хоосон үед
     */
    public function up(): void
    {
        Schema::table('computer_transfers', function (Blueprint $t) {
            $t->string('destination', 20)->default('user')->after('to_user_id');
            $t->string('to_department')->nullable()->after('destination');
            $t->string('from_label')->nullable()->after('from_user_id');
            $t->foreignId('from_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('computer_transfers', function (Blueprint $t) {
            $t->dropColumn(['destination', 'to_department', 'from_label']);
        });
    }
};
