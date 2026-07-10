<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_sent_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('gd_sent_messages', 'delivery_status')) {
                $table->string('delivery_status', 30)->default('pending')->after('status');
            }
            if (!Schema::hasColumn('gd_sent_messages', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }
            if (!Schema::hasColumn('gd_sent_messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('delivered_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gd_sent_messages', function (Blueprint $table) {
            foreach (['delivery_status', 'delivered_at', 'read_at'] as $column) {
                if (Schema::hasColumn('gd_sent_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
