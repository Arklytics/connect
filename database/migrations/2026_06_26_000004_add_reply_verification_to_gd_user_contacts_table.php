<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_user_contacts', function (Blueprint $table) {
            $table->timestamp('reply_verified_at')->nullable()->after('last_inbound_at');
            $table->string('reply_path', 30)->nullable()->after('reply_verified_at');
            $table->string('reply_verified_via', 50)->nullable()->after('reply_path');
            $table->text('last_reply_text')->nullable()->after('reply_verified_via');
        });
    }

    public function down(): void
    {
        Schema::table('gd_user_contacts', function (Blueprint $table) {
            $table->dropColumn([
                'reply_verified_at',
                'reply_path',
                'reply_verified_via',
                'last_reply_text',
            ]);
        });
    }
};
