<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('gd_groups', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('biz_id');
                $table->index('parent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gd_groups', function (Blueprint $table) {
            if (Schema::hasColumn('gd_groups', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
        });
    }
};
