<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_user_contacts', function (Blueprint $table) {
            $table->string('lead_stage', 40)->default('lead')->after('status');
            $table->string('lead_status', 40)->default('new')->after('lead_stage');
            $table->string('source', 120)->nullable()->after('lead_status');
            $table->boolean('whatsapp_opt_in')->default(false)->after('source');
            $table->timestamp('last_contacted_at')->nullable()->after('whatsapp_opt_in');
            $table->timestamp('next_follow_up_at')->nullable()->after('last_contacted_at');
            $table->timestamp('won_at')->nullable()->after('next_follow_up_at');
            $table->timestamp('lost_at')->nullable()->after('won_at');
            $table->string('lost_reason', 255)->nullable()->after('lost_at');
            $table->text('crm_notes')->nullable()->after('lost_reason');
        });
    }

    public function down(): void
    {
        Schema::table('gd_user_contacts', function (Blueprint $table) {
            $table->dropColumn([
                'lead_stage',
                'lead_status',
                'source',
                'whatsapp_opt_in',
                'last_contacted_at',
                'next_follow_up_at',
                'won_at',
                'lost_at',
                'lost_reason',
                'crm_notes',
            ]);
        });
    }
};
