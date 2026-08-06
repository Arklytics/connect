<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('gd_orders', 'api_webhook_url')) {
                $table->string('api_webhook_url', 500)->nullable()->after('api_key_created_at');
            }

            if (!Schema::hasColumn('gd_orders', 'api_webhook_secret')) {
                $table->string('api_webhook_secret', 120)->nullable()->after('api_webhook_url');
            }

            if (!Schema::hasColumn('gd_orders', 'api_webhook_enabled')) {
                $table->boolean('api_webhook_enabled')->default(false)->after('api_webhook_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gd_orders', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['api_webhook_url', 'api_webhook_secret', 'api_webhook_enabled'] as $column) {
                if (Schema::hasColumn('gd_orders', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
