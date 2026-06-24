<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_user_contacts', function (Blueprint $table) {
            $table->string('lead_temperature', 20)->default('cold')->after('lead_status');
            $table->timestamp('first_response_at')->nullable()->after('last_contacted_at');
            $table->timestamp('last_inbound_at')->nullable()->after('first_response_at');
            $table->unsignedInteger('response_time_minutes')->nullable()->after('last_inbound_at');
        });
    }

    public function down(): void
    {
        Schema::table('gd_user_contacts', function (Blueprint $table) {
            $table->dropColumn([
                'lead_temperature',
                'first_response_at',
                'last_inbound_at',
                'response_time_minutes',
            ]);
        });
    }
};
