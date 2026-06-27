<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_sent_messages', function (Blueprint $table) {
            $table->string('delivery_status', 30)->default('pending')->after('status');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('gd_sent_messages', function (Blueprint $table) {
            $table->dropColumn(['delivery_status', 'delivered_at', 'read_at']);
        });
    }
};
